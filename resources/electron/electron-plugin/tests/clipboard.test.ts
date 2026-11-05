import axios from 'axios';
import express from 'express';
import type { Server } from 'http';
import type { AddressInfo } from 'net';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import clipboardRoutes from '../src/server/api/clipboard';

// An in-memory clipboard shaped like Electron 44+'s W3C-style API. Only the
// async methods exist, so a route that falls back to the removed helpers
// (readHTML, writeImage, ...) fails here instead of in a shipped app.
const { boards } = vi.hoisted(() => {
    class FakeClipboardItem {
        constructor(public readonly items: Record<string, string | Blob>) {}

        get types() {
            return Object.keys(this.items);
        }

        async getType(type: string) {
            const value = this.items[type];

            if (value === undefined) {
                throw new Error(`No ${type} on the clipboard`);
            }

            return value instanceof Blob ? value : new Blob([value], { type });
        }
    }

    const makeBoard = () => {
        let items: FakeClipboardItem[] = [];

        return {
            readText: async () => {
                const item = items.find((i) => i.types.includes('text/plain'));
                return item ? (await item.getType('text/plain')).text() : '';
            },
            writeText: async (text: string) => {
                items = [new FakeClipboardItem({ 'text/plain': text })];
            },
            read: async () => items,
            write: async (data: FakeClipboardItem[]) => {
                items = data;
            },
            has: async (type: string) => items.some((i) => i.types.includes(type)),
            clear: () => {
                items = [];
            },
        };
    };

    return { boards: { FakeClipboardItem, makeBoard, system: makeBoard(), selection: makeBoard() } };
});

vi.mock('electron', () => {
    const fakeImage = (bytes: Buffer) => ({
        isEmpty: () => bytes.length === 0,
        toPNG: () => bytes,
        toDataURL: () => `data:image/png;base64,${bytes.toString('base64')}`,
    });

    return {
        clipboard: new Proxy(
            {},
            {
                get: (_target, prop) =>
                    prop === 'selection' ? boards.selection : (boards.system as Record<string | symbol, unknown>)[prop],
            },
        ),
        ClipboardItem: boards.FakeClipboardItem,
        nativeImage: {
            createFromDataURL: (url: string) => {
                const match = /^data:image\/png;base64,(.+)$/.exec(url ?? '');
                return fakeImage(match ? Buffer.from(match[1], 'base64') : Buffer.alloc(0));
            },
            createFromBuffer: (buffer: Buffer) => fakeImage(buffer),
        },
    };
});

let server: Server;

const PNG = `data:image/png;base64,${Buffer.from('not really a png').toString('base64')}`;

describe('clipboard', () => {
    beforeEach(async () => {
        boards.system = boards.makeBoard();
        boards.selection = boards.makeBoard();

        const app = express();
        app.use(express.json());
        app.use('/api/clipboard', clipboardRoutes);

        await new Promise<void>((resolve) => {
            server = app.listen(0, '127.0.0.1', () => resolve());
        });

        const { port } = server.address() as AddressInfo;
        axios.defaults.baseURL = `http://127.0.0.1:${port}`;
    });

    afterEach(async () => {
        await new Promise<void>((resolve) => server.close(() => resolve()));
    });

    it('writes and reads text', async () => {
        const write = await axios.post('/api/clipboard/text', { text: 'hello' });
        expect(write.data).toEqual({ text: 'hello' });

        const read = await axios.get('/api/clipboard/text');
        expect(read.data).toEqual({ text: 'hello' });
    });

    it('writes and reads html', async () => {
        await axios.post('/api/clipboard/html', { html: '<b>hello</b>' });

        const read = await axios.get('/api/clipboard/html');
        expect(read.data).toEqual({ html: '<b>hello</b>' });
    });

    it('returns an empty string when there is no html', async () => {
        await axios.post('/api/clipboard/text', { text: 'plain only' });

        const read = await axios.get('/api/clipboard/html');
        expect(read.data).toEqual({ html: '' });
    });

    it('writes and reads an image as a png data url', async () => {
        const write = await axios.post('/api/clipboard/image', { image: PNG });
        expect(write.status).toBe(200);
        await expect(boards.system.has('image/png')).resolves.toBe(true);

        const read = await axios.get('/api/clipboard/image');
        expect(read.data).toEqual({ image: PNG });
    });

    it('returns null when there is no image', async () => {
        const read = await axios.get('/api/clipboard/image');
        expect(read.data).toEqual({ image: null });
    });

    it('rejects an image that cannot be decoded', async () => {
        await expect(axios.post('/api/clipboard/image', { image: 'nonsense' })).rejects.toMatchObject({
            response: { status: 400 },
        });
    });

    it('clears the clipboard', async () => {
        await axios.post('/api/clipboard/text', { text: 'hello' });
        await axios.delete('/api/clipboard');

        const read = await axios.get('/api/clipboard/text');
        expect(read.data).toEqual({ text: '' });
    });

    it('targets the linux selection clipboard with type=selection', async () => {
        await axios.post('/api/clipboard/text?type=selection', { text: 'selected' });

        expect((await axios.get('/api/clipboard/text?type=selection')).data).toEqual({ text: 'selected' });
        expect((await axios.get('/api/clipboard/text')).data).toEqual({ text: '' });
    });
});
