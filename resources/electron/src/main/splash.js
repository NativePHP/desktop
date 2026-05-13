import path from 'path';
import fs from 'fs';
import { BrowserWindow } from 'electron';

function getProjectRoot(startPath) {
    let currentPath = startPath;
    for (let i = 0; i < 10; i++) {
        if (fs.existsSync(path.join(currentPath, '.env'))) {
            return currentPath;
        }
        const parentPath = path.join(currentPath, '..');
        if (parentPath === currentPath) break;
        currentPath = parentPath;
    }
    return null;
}

export function getEnvConfig(projectRoot, key, defaultValue = null) {
    if (!projectRoot) return defaultValue;
    const envPath = path.join(projectRoot, '.env');

    try {
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
    } catch (e) { }
    return defaultValue;
}

export function createSplash(importMetaDirname) {
    const projectRoot = getProjectRoot(importMetaDirname);

    if (!projectRoot) return null;

    const enabled = getEnvConfig(projectRoot, 'NATIVEPHP_SPLASH_ENABLED', 'false') === 'true';
    if (!enabled) return null;

    const width = parseInt(getEnvConfig(projectRoot, 'NATIVEPHP_SPLASH_WIDTH', '400'));
    const height = parseInt(getEnvConfig(projectRoot, 'NATIVEPHP_SPLASH_HEIGHT', '300'));
    const splashRelativePath = getEnvConfig(projectRoot, 'NATIVEPHP_SPLASH_HTML', 'public/splash.html');

    const finalHtmlPath = path.join(projectRoot, splashRelativePath);

    if (!fs.existsSync(finalHtmlPath)) {
        return null;
    }

    const splash = new BrowserWindow({
        width,
        height,
        frame: false,
        transparent: true,
        alwaysOnTop: true,
        webPreferences: { nodeIntegration: false }
    });

    splash.loadFile(finalHtmlPath);
    return splash;
}
