import { mkdirSync, renameSync, writeFileSync } from 'fs';
import { homedir } from 'os';
import { join } from 'path';
function desktopValue(value) {
    return value.replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
}
export function configureLinuxDevelopmentDesktop(icon, environment = process.env, platform = process.platform) {
    if (platform !== 'linux' || environment.NODE_ENV !== 'development') {
        return null;
    }
    const appId = environment.NATIVEPHP_APP_ID;
    const desktopName = environment.NATIVEPHP_DESKTOP_NAME;
    if (!appId || !desktopName) {
        return null;
    }
    const dataHome = environment.XDG_DATA_HOME || join(homedir(), '.local', 'share');
    const applicationsDirectory = join(dataHome, 'applications');
    const desktopFile = join(applicationsDirectory, `${desktopName}.desktop`);
    const temporaryDesktopFile = `${desktopFile}.tmp-${process.pid}`;
    const displayName = environment.NATIVEPHP_APP_NAME || appId;
    try {
        mkdirSync(applicationsDirectory, { recursive: true });
        writeFileSync(temporaryDesktopFile, [
            '[Desktop Entry]',
            'Type=Application',
            `Name=${desktopValue(displayName)} (Development)`,
            `Icon=${desktopValue(icon)}`,
            `StartupWMClass=${desktopName}`,
            'NoDisplay=true',
            'X-NativePHP-Development=true',
            '',
        ].join('\n'), { mode: 0o644 });
        renameSync(temporaryDesktopFile, desktopFile);
    }
    catch (error) {
        console.warn('Unable to configure the Linux development desktop identity:', error);
        return null;
    }
    return desktopFile;
}
