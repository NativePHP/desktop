import axios from 'axios';
import { describe, expect, it, vi } from 'vitest';
import state from '../src/server/state';
import { notifyLaravel, pingPhpServer, reloadWindows } from '../src/server/utils';

vi.mock('axios');
vi.mock('electron-store');

describe('Utils test', () => {
    it('notifies laravel', async () => {
        state.phpPort = 8000;
        state.randomSecret = 'i-am-secret';

        await notifyLaravel('endpoint', { payload: 'payload' });

        expect(axios.post).toHaveBeenCalledWith(
            `http://127.0.0.1:8000/_native/api/endpoint`,
            { payload: 'payload' },
            {
                headers: {
                    'X-NativePHP-Secret': 'i-am-secret',
                },
            },
        );
    });

    it('pings the PHP server with the secret and a timeout', async () => {
        state.phpPort = 8000;
        state.randomSecret = 'i-am-secret';
        vi.mocked(axios.get).mockResolvedValueOnce({ status: 200 });

        expect(await pingPhpServer(10_000)).toBe(true);

        expect(axios.get).toHaveBeenCalledWith(
            'http://127.0.0.1:8000/_native/api/health',
            expect.objectContaining({
                headers: {
                    'X-NativePHP-Secret': 'i-am-secret',
                },
                timeout: 10_000,
            }),
        );
    });

    it('counts any HTTP answer as alive', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ status: 404 });

        expect(await pingPhpServer(10_000)).toBe(true);

        const { validateStatus } = vi.mocked(axios.get).mock.lastCall[1];
        expect(validateStatus(404)).toBe(true);
        expect(validateStatus(500)).toBe(true);
    });

    it('counts a refused connection or a timeout as a miss', async () => {
        vi.mocked(axios.get).mockRejectedValueOnce(new Error('connect ECONNREFUSED 127.0.0.1:8000'));
        expect(await pingPhpServer(10_000)).toBe(false);

        vi.mocked(axios.get).mockRejectedValueOnce(new Error('timeout of 10000ms exceeded'));
        expect(await pingPhpServer(10_000)).toBe(false);
    });

    it('reloads every open window and the menu bar window', () => {
        const window = () => ({ isDestroyed: vi.fn(() => false), webContents: { reload: vi.fn() } });
        const main = window();
        const settings = window();
        const closed = { ...window(), isDestroyed: vi.fn(() => true) };
        const menuBar = window();

        state.windows = { main, settings, closed } as never;
        state.activeMenuBar = { window: menuBar } as never;

        reloadWindows();

        expect(main.webContents.reload).toHaveBeenCalledOnce();
        expect(settings.webContents.reload).toHaveBeenCalledOnce();
        expect(menuBar.webContents.reload).toHaveBeenCalledOnce();
        expect(closed.webContents.reload).not.toHaveBeenCalled();

        state.windows = {};
        state.activeMenuBar = null;
    });
});
