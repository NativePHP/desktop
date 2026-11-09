import axios from 'axios';
import express from 'express';
import type { Server } from 'http';
import type { AddressInfo } from 'net';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import systemRoutes from '../src/server/api/system';

// The synchronous safeStorage methods are deprecated in Electron 45 and removed
// in 46, so they throw here to keep the routes on the async API.
vi.mock('electron', () => {
    const removed = () => {
        throw new Error('Synchronous safeStorage methods are removed in Electron 46');
    };

    return {
        BrowserWindow: {},
        nativeTheme: {},
        systemPreferences: {},
        safeStorage: {
            isEncryptionAvailable: removed,
            encryptString: removed,
            decryptString: removed,
            isAsyncEncryptionAvailable: vi.fn(async () => true),
            encryptStringAsync: vi.fn(async (text: string) => Buffer.from(`enc:${text}`)),
            decryptStringAsync: vi.fn(async (buffer: Buffer) => {
                const text = buffer.toString();

                if (!text.startsWith('enc:')) {
                    throw new Error('Error while decrypting the ciphertext provided');
                }

                return { shouldReEncrypt: false, result: text.slice(4) };
            }),
        },
    };
});

let server: Server;

describe('safeStorage', () => {
    beforeEach(async () => {
        const app = express();
        app.use(express.json());
        app.use('/api/system', systemRoutes);

        await new Promise<void>((resolve) => {
            server = app.listen(0, '127.0.0.1', () => resolve());
        });

        const { port } = server.address() as AddressInfo;
        axios.defaults.baseURL = `http://127.0.0.1:${port}`;
    });

    afterEach(async () => {
        await new Promise<void>((resolve) => server.close(() => resolve()));
    });

    it('reports whether encryption is available', async () => {
        const response = await axios.get('/api/system/can-encrypt');
        expect(response.data).toEqual({ result: true });
    });

    it('round-trips a string through encrypt and decrypt', async () => {
        const encrypted = await axios.post('/api/system/encrypt', { string: 'secret' });
        expect(encrypted.data.result).toBe(Buffer.from('enc:secret').toString('base64'));

        const decrypted = await axios.post('/api/system/decrypt', { string: encrypted.data.result });
        expect(decrypted.data).toEqual({ result: 'secret' });
    });

    it('returns a 400 when decryption fails', async () => {
        await expect(
            axios.post('/api/system/decrypt', { string: Buffer.from('garbage').toString('base64') }),
        ).rejects.toMatchObject({ response: { status: 400 } });
    });
});
