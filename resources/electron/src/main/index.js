import NativePHP from '#plugin';
import { app, BrowserWindow } from 'electron';
import path from 'path';
import { createSplash } from './splash.js';
// Inherit User's PATH in Process & ChildProcess
import fixPath from 'fix-path';
fixPath();

const buildPath = path.resolve(import.meta.dirname, import.meta.env.MAIN_VITE_NATIVEPHP_BUILD_PATH);
const defaultIcon = path.join(buildPath, 'icon.png');
const certificate = path.join(buildPath, 'cacert.pem');

const executable = process.platform === 'win32' ? 'php.exe' : 'php';
const phpBinary = path.join(buildPath, 'php', executable);
const appPath = path.join(buildPath, 'app');

let splashWindow;

app.whenReady().then(() => {
    try {
        splashWindow = createSplash(appPath, import.meta.dirname);
    } catch (error) {
        console.error('Error creating splash screen:', error);
    }

    NativePHP.bootstrap(app, defaultIcon, phpBinary, certificate, appPath);
});

app.on('browser-window-created', (event, window) => {
    if (splashWindow && window !== splashWindow) {
        window.webContents.on('did-navigate', (evt, url) => {
            if (url.startsWith('http://127.0.0.1') || url.startsWith('http://localhost')) {
                if (splashWindow) {
                    splashWindow.close();
                    splashWindow = null;
                }
            }
        });
    }
});
