import { spawnSync } from 'child_process';
import { mkdirSync, renameSync, writeFileSync } from 'fs';
import { homedir } from 'os';
import { basename, join } from 'path';
function desktopValue(value) {
    return value.replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
}
function desktopExecArgument(value) {
    return `"${value.replace(/([\\`"$])/g, '\\$1').replace(/%/g, '%%')}"`;
}
function registerMimeHandler(desktopFile, mimeType) {
    const result = spawnSync('xdg-mime', ['default', desktopFile, mimeType], { encoding: 'utf8' });
    return result.status === 0;
}
export function configureLinuxDevelopmentDesktop(icon, options = {}) {
    var _a, _b, _c, _d;
    const environment = (_a = options.environment) !== null && _a !== void 0 ? _a : process.env;
    const platform = (_b = options.platform) !== null && _b !== void 0 ? _b : process.platform;
    if (platform !== 'linux' || environment.NODE_ENV !== 'development') {
        return null;
    }
    const desktopName = environment.NATIVEPHP_DESKTOP_NAME;
    if (!desktopName) {
        return null;
    }
    const dataHome = environment.XDG_DATA_HOME || join(homedir(), '.local', 'share');
    const applicationsDirectory = join(dataHome, 'applications');
    const desktopFile = join(applicationsDirectory, `${desktopName}.desktop`);
    const temporaryDesktopFile = `${desktopFile}.tmp-${process.pid}`;
    const displayName = environment.NATIVEPHP_APP_NAME || desktopName;
    const scheme = (_c = options.scheme) === null || _c === void 0 ? void 0 : _c.trim().toLowerCase();
    const hasProtocolHandler = Boolean(scheme && /^[a-z][a-z0-9+.-]*$/.test(scheme) && options.executable && options.entryScript);
    const mimeType = hasProtocolHandler ? `x-scheme-handler/${scheme}` : null;
    const entry = [
        '[Desktop Entry]',
        'Type=Application',
        `Name=${desktopValue(displayName)} (Development)`,
        `Icon=${desktopValue(icon)}`,
        `StartupWMClass=${desktopValue(desktopName)}`,
        'Terminal=false',
        'NoDisplay=true',
        'X-NativePHP-Development=true',
    ];
    if (hasProtocolHandler) {
        entry.splice(5, 0, `TryExec=${desktopValue(options.executable)}`, `Exec=${desktopExecArgument(options.executable)} ${desktopExecArgument(options.entryScript)} %u`, `MimeType=${mimeType};`);
    }
    try {
        mkdirSync(applicationsDirectory, { recursive: true });
        writeFileSync(temporaryDesktopFile, [...entry, ''].join('\n'), { mode: 0o644 });
        renameSync(temporaryDesktopFile, desktopFile);
        if (mimeType) {
            const registrar = (_d = options.registerMimeHandler) !== null && _d !== void 0 ? _d : registerMimeHandler;
            if (!registrar(basename(desktopFile), mimeType)) {
                console.warn(`Unable to register ${mimeType} with ${basename(desktopFile)}.`);
            }
        }
    }
    catch (error) {
        console.warn('Unable to configure the Linux development desktop identity:', error);
        return null;
    }
    return desktopFile;
}
