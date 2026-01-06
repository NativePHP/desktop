import { BrowserWindow, Tray, UtilityProcess } from 'electron';
import Store from 'electron-store';
import type { Menubar } from '../libs/menubar/index.js';
import { notifyLaravel } from './utils.js';

const settingsStore = new Store();
settingsStore.onDidAnyChange((newValue, oldValue) => {
    // Only notify of the changed key/value pair
    const changedKeys = Object.keys(newValue).filter((key) => newValue[key] !== oldValue[key]);

    changedKeys.forEach((key) => {
        notifyLaravel('events', {
            event: 'Native\\Desktop\\Events\\Settings\\SettingChanged',
            payload: {
                key,
                value: newValue[key] || null,
            },
        });
    });
});

interface State {
    electronApiPort: number | null;
    activeMenuBar: Menubar | null;
    tray: Tray | null;
    php: string | null;
    phpPort: number | null;
    phpIni: Record<string, string> | null;
    caCert: string | null;
    appPath: string | null;
    icon: string | null;
    processes: Record<string, { pid: number | null; proc: UtilityProcess | null; settings: Record<string, unknown> }>;
    windows: Record<string, BrowserWindow>;
    randomSecret: string;
    store: Store;
    findWindow: (id: string) => BrowserWindow | null;
    dockBounce: number;
}

function generateRandomString(length: number) {
    let result = '';
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    const charactersLength = characters.length;

    for (let i = 0; i < length; i += 1) {
        result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }

    return result;
}

export default {
    electronApiPort: null,
    activeMenuBar: null,
    tray: null,
    php: null,
    phpPort: null,
    phpIni: null,
    caCert: null,
    appPath: null,
    icon: null,
    store: settingsStore,
    randomSecret: generateRandomString(32),
    processes: {},
    windows: {},
    findWindow(id: string) {
        return this.windows[id] || null;
    },
} as State;
