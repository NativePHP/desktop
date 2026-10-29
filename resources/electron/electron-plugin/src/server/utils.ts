import axios from 'axios';
import { session } from 'electron';
import state from './state.js';

export async function appendCookie() {
    const cookie = {
        url: `http://localhost:${state.phpPort}`,
        name: '_php_native',
        value: state.randomSecret,
    };

    await session.defaultSession.cookies.set(cookie);
}

export async function notifyLaravel(endpoint: string, payload = {}) {
    if (endpoint === 'events') {
        broadcastToWindows('native-event', payload);
    }

    try {
        await axios.post(`http://127.0.0.1:${state.phpPort}/_native/api/${endpoint}`, payload, {
            headers: {
                'X-NativePHP-Secret': state.randomSecret,
            },
        });
    } catch (e) {
        if (parseInt(process.env.SHELL_VERBOSITY) > 0) {
            console.error(`notifyLaravel('${endpoint}') failed:`, e instanceof Error ? e.message : e);
        }
    }
}

/**
 * Checks the PHP server is still answering. Any response counts, whatever its
 * status: only a refused connection or a timeout means it's down or hung.
 */
export async function pingPhpServer(timeout: number) {
    try {
        await axios.get(`http://127.0.0.1:${state.phpPort}/_native/api/health`, {
            headers: {
                'X-NativePHP-Secret': state.randomSecret,
            },
            timeout,
            validateStatus: () => true,
        });

        return true;
    } catch {
        return false;
    }
}

export function reloadWindows() {
    const windows = Object.values(state.windows);

    if (state.activeMenuBar?.window) {
        windows.push(state.activeMenuBar.window);
    }

    windows
        .filter((window) => !window.isDestroyed())
        .forEach((window) => {
            window.webContents.reload();
        });
}

export function broadcastToWindows(event, payload) {
    Object.values(state.windows).forEach((window) => {
        window.webContents.send(event, payload);
    });

    if (state.activeMenuBar?.window) {
        state.activeMenuBar.window.webContents.send(event, payload);
    }
}

/**
 * Remove null and undefined values from an object
 */
export function trimOptions<T extends Record<string, unknown>>(options: T): T {
    Object.keys(options).forEach((key) => options[key] == null && delete options[key]);

    return options;
}

export function appendWindowIdToUrl(url, id) {
    return url + (url.indexOf('?') === -1 ? '?' : '&') + '_windowId=' + id;
}

export function goToUrl(url, windowId) {
    state.windows[windowId]?.loadURL(appendWindowIdToUrl(url, windowId));
}
