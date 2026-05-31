import axios from 'axios';
import { Notification } from 'electron';
import express from 'express';
import type { Server } from 'http';
import type { AddressInfo } from 'net';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import notificationRoutes from '../src/server/api/notification';
import state from '../src/server/state';
import { notifyLaravel } from '../src/server/utils';

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

vi.mock('../src/server/utils.js', () => ({
    notifyLaravel: vi.fn(),
    broadcastToWindows: vi.fn(),
}));

let server: Server;

const latestNotification = () => vi.mocked(Notification).mock.results.at(-1)!.value;

describe('notification', () => {
    beforeEach(async () => {
        vi.clearAllMocks();
        for (const reference of Object.keys(state.notifications)) {
            delete state.notifications[reference];
        }

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
});
