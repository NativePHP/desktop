import axios from 'axios';
import { session } from 'electron';
import state from './state.js';
import { signEventBody } from './nativeEventSignature.js';

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

    const headers: Record<string, string> = {
        'X-NativePHP-Secret': state.randomSecret,
    };

    let data: any = payload;

    if (endpoint === 'events') {
        // Serialize once and sign the exact bytes PHP reads as the request body,
        // so the HMAC matches request()->getContent() byte for byte. The app key
        // signs it; a page cannot, so PHP can reject a forged event.
        const body = JSON.stringify(payload);
        headers['Content-Type'] = 'application/json';
        data = body;

        const signature = signEventBody(body);
        if (signature) {
            Object.assign(headers, signature);
        }
    }

    try {
        await axios.post(`http://127.0.0.1:${state.phpPort}/_native/api/${endpoint}`, data, {
            headers,
        });
    } catch (e) {
        if (parseInt(process.env.SHELL_VERBOSITY) > 0) {
            console.error(`notifyLaravel('${endpoint}') failed:`, e instanceof Error ? e.message : e);
        }
    }
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
