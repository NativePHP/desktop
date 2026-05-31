import axios from 'axios';
import { Notification } from 'electron';
import express from 'express';
import fs from 'fs';
import type { Server } from 'http';
import type { AddressInfo } from 'net';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import notificationRoutes from '../src/server/api/notification';
import state from '../src/server/state';
import { broadcastToWindows, notifyLaravel } from '../src/server/utils';

const { playMock } = vi.hoisted(() => ({ playMock: vi.fn() }));

// The route constructs a real electron Notification, so mock the class with one
// that records its event handlers and lets the test fire them, mimicking the
// OS raising a click/close on the notification.
vi.mock('electron', () => ({
    Notification: vi.fn().mockImplementation(() => {
        const handlers: Record<string, (...args: any[]) => void> = {};
        return {
            show: vi.fn(),
            on: vi.fn((event: string, callback: (...args: any[]) => void) => {
                handlers[event] = callback;
            }),
            emit: (event: string, ...args: any[]) => handlers[event]?.(...args),
        };
    }),
}));

vi.mock('play-sound', () => ({ default: vi.fn(() => ({ play: playMock })) }));

vi.mock('../src/server/utils.js', () => ({
    notifyLaravel: vi.fn(),
    broadcastToWindows: vi.fn(),
}));

let server: Server;

const latestNotification = () => vi.mocked(Notification).mock.results.at(-1)!.value;

const mockFsAccess = (error: Error | null = null) =>
    vi.spyOn(fs, 'access').mockImplementation(((_path: any, _mode: any, callback: any) => callback(error)) as any);

describe('notification', () => {
    beforeEach(async () => {
        vi.clearAllMocks();
        state.notifications = {};

        const app = express();
        app.use(express.json());
        app.use('/api/notification', notificationRoutes);

        await new Promise<void>((resolve) => {
            server = app.listen(0, '127.0.0.1', () => resolve());
        });

        const { port } = server.address() as AddressInfo;
        axios.defaults.baseURL = `http://127.0.0.1:${port}`;
    });

    afterEach(async () => {
        await new Promise<void>((resolve) => server.close(() => resolve()));
    });

    it('constructs the notification with the provided options', async () => {
        await axios.post('/api/notification', {
            title: 'Build finished',
            body: 'Tests are green',
            subtitle: 'My App',
        });

        expect(Notification).toHaveBeenCalledWith(
            expect.objectContaining({
                title: 'Build finished',
                body: 'Tests are green',
                subtitle: 'My App',
            }),
        );
        expect(latestNotification().show).toHaveBeenCalledOnce();
    });

    it('retains the notification so its event handlers survive garbage collection', async () => {
        const response = await axios.post('/api/notification', {
            title: 'Build finished',
            body: 'Tests are green',
            reference: 'process-42',
        });

        expect(response.status).toBe(200);
        expect(response.data.reference).toBe('process-42');
        // Held in state, mirroring windows/processes, so V8 cannot collect the
        // wrapper before the user interacts with the OS notification.
        expect(state.notifications['process-42']).toBe(latestNotification());
    });

    it('retains notifications created without an explicit reference', async () => {
        const response = await axios.post('/api/notification', {
            title: 'Heads up',
        });

        expect(state.notifications[response.data.reference]).toBe(latestNotification());
    });

    it('releases the notification and forwards the click to Laravel', async () => {
        await axios.post('/api/notification', {
            title: 'Build finished',
            reference: 'process-42',
            event: '\\App\\Events\\Terminals\\ProcessNotificationClicked',
        });

        latestNotification().emit('click', {});

        expect(notifyLaravel).toHaveBeenCalledWith('events', {
            event: '\\App\\Events\\Terminals\\ProcessNotificationClicked',
            payload: { reference: 'process-42', event: JSON.stringify({}) },
        });
        expect(state.notifications['process-42']).toBeUndefined();
    });

    it('falls back to the default clicked event when none is provided', async () => {
        await axios.post('/api/notification', {
            title: 'Build finished',
            reference: 'process-42',
        });

        latestNotification().emit('click', {});

        expect(notifyLaravel).toHaveBeenCalledWith('events', {
            event: '\\Native\\Desktop\\Events\\Notifications\\NotificationClicked',
            payload: { reference: 'process-42', event: JSON.stringify({}) },
        });
    });

    it('forwards notification actions to Laravel', async () => {
        await axios.post('/api/notification', {
            title: 'Build finished',
            reference: 'process-42',
        });

        latestNotification().emit('action', {}, 1);

        expect(notifyLaravel).toHaveBeenCalledWith('events', {
            event: '\\Native\\Desktop\\Events\\Notifications\\NotificationActionClicked',
            payload: { reference: 'process-42', index: 1, event: JSON.stringify({}) },
        });
    });

    it('forwards notification replies to Laravel', async () => {
        await axios.post('/api/notification', {
            title: 'Build finished',
            reference: 'process-42',
        });

        latestNotification().emit('reply', {}, 'on it');

        expect(notifyLaravel).toHaveBeenCalledWith('events', {
            event: '\\Native\\Desktop\\Events\\Notifications\\NotificationReply',
            payload: { reference: 'process-42', reply: 'on it', event: JSON.stringify({}) },
        });
    });

    it('releases the notification and forwards the close to Laravel', async () => {
        await axios.post('/api/notification', {
            title: 'Build finished',
            reference: 'process-42',
        });

        latestNotification().emit('close', {});

        expect(notifyLaravel).toHaveBeenCalledWith('events', {
            event: '\\Native\\Desktop\\Events\\Notifications\\NotificationClosed',
            payload: { reference: 'process-42', event: JSON.stringify({}) },
        });
        expect(state.notifications['process-42']).toBeUndefined();
    });

    it('plays a local sound file itself and mutes electron for it', async () => {
        mockFsAccess();

        await axios.post('/api/notification', {
            title: 'Build finished',
            sound: '/sounds/done.mp3',
        });

        // Electron must not also play it, so the sound is stripped and silenced.
        expect(Notification).toHaveBeenCalledWith(expect.objectContaining({ sound: undefined, silent: true }));
        expect(playMock).toHaveBeenCalledWith('/sounds/done.mp3', expect.any(Function));
    });

    it('logs an error and does not play when the local sound file is missing', async () => {
        mockFsAccess(new Error('missing'));

        await axios.post('/api/notification', {
            title: 'Build finished',
            sound: '/sounds/missing.mp3',
        });

        expect(broadcastToWindows).toHaveBeenCalledWith('log', {
            level: 'error',
            message: 'Sound file not found: /sounds/missing.mp3',
            context: { sound: '/sounds/missing.mp3' },
        });
        expect(playMock).not.toHaveBeenCalled();
    });
});
