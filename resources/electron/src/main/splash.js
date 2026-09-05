import path from 'path';
import fs from 'fs';
import { BrowserWindow, app } from 'electron';

function getLaravelBaseDir(appPath, importMetaDirname) {
    if (app.isPackaged) {
        return appPath;
    }

    let currentPath = importMetaDirname;
    for (let i = 0; i < 10; i++) {
        if (fs.existsSync(path.join(currentPath, '.env'))) {
            return currentPath;
        }
        const parentPath = path.join(currentPath, '..');
        if (parentPath === currentPath) break;
        currentPath = parentPath;
    }
    return process.cwd();
}

export function getEnvConfig(baseDir, key, defaultValue = null) {
    if (!baseDir) return defaultValue;
    const envPath = path.join(baseDir, '.env');

    try {
        if (fs.existsSync(envPath)) {
            const content = fs.readFileSync(envPath, 'utf8');
            const lines = content.split('\n');
            for (const line of lines) {
                const trimmed = line.trim();
                if (!trimmed || trimmed.startsWith('#')) continue;
                const [lineKey, ...valueParts] = trimmed.split('=');
                if (lineKey.trim() === key) {
                    return valueParts.join('=').trim().replace(/^["']|["']$/g, '');
                }
            }
        }
    } catch (e) { /* ignore */ }
    return defaultValue;
}

export function createSplash(appPath, importMetaDirname) {
    const baseDir = getLaravelBaseDir(appPath, importMetaDirname);

    const enabled = getEnvConfig(baseDir, 'NATIVEPHP_SPLASH_ENABLED', 'false') === 'true';
    if (!enabled) return null;

    const width = parseInt(getEnvConfig(baseDir, 'NATIVEPHP_SPLASH_WIDTH', '400'));
    const height = parseInt(getEnvConfig(baseDir, 'NATIVEPHP_SPLASH_HEIGHT', '300'));
    const splashRelativePath = getEnvConfig(baseDir, 'NATIVEPHP_SPLASH_HTML', 'public/splash.html');

    const splash = new BrowserWindow({
        width,
        height,
        frame: false,
        transparent: true,
        alwaysOnTop: true,
        webPreferences: { nodeIntegration: false }
    });

    const finalHtmlPath = path.join(baseDir, splashRelativePath);

    if (fs.existsSync(finalHtmlPath)) {
        splash.loadURL('file:///' + finalHtmlPath.replace(/\\/g, '/'));
    } else {
        console.error(`[NativePHP Splash] HTML Not Found. Path: ${finalHtmlPath}`);
    }

    return splash;
}
