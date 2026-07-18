import { createHash } from 'crypto';
import type { App } from 'electron';
import { mkdirSync, renameSync, writeFileSync } from 'fs';
import { homedir } from 'os';
import { join } from 'path';

type Environment = Record<string, string | undefined>;

function desktopIdentifier(appId: string): string {
    const normalized = appId
        .replace(/[^A-Za-z0-9._-]/g, '-')
        .split('.')
        .filter(Boolean)
        .map((segment) => (/^[0-9]/.test(segment) ? `_${segment}` : segment))
        .join('.');

    return normalized || 'com.nativephp.app';
}

function desktopValue(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
}

export function configureLinuxDevelopmentDesktop(
    app: Pick<App, 'commandLine'>,
    icon: string,
    environment: Environment = process.env,
    platform: NodeJS.Platform = process.platform,
): string | null {
    if (platform !== 'linux' || environment.NODE_ENV !== 'development') {
        return null;
    }

    const appId = environment.NATIVEPHP_APP_ID;
    const appPath = environment.APP_PATH;

    if (!appId || !appPath) {
        return null;
    }

    const projectHash = createHash('sha256').update(appPath).digest('hex').slice(0, 12);
    const desktopName = `${desktopIdentifier(appId)}.nativephp-dev-${projectHash}`;
    const dataHome = environment.XDG_DATA_HOME || join(homedir(), '.local', 'share');
    const applicationsDirectory = join(dataHome, 'applications');
    const desktopFile = join(applicationsDirectory, `${desktopName}.desktop`);
    const temporaryDesktopFile = `${desktopFile}.tmp-${process.pid}`;
    const displayName = environment.NATIVEPHP_APP_NAME || appId;

    try {
        // Keep this hidden identity entry between runs so desktop shells can
        // cache it reliably. A later run refreshes it atomically. It has no
        // Exec command because it is metadata for an active dev process, not
        // an application launcher.
        mkdirSync(applicationsDirectory, { recursive: true });
        writeFileSync(
            temporaryDesktopFile,
            [
                '[Desktop Entry]',
                'Type=Application',
                `Name=${desktopValue(displayName)} (Development)`,
                `Icon=${desktopValue(icon)}`,
                `StartupWMClass=${desktopName}`,
                'NoDisplay=true',
                'X-NativePHP-Development=true',
                '',
            ].join('\n'),
            { mode: 0o644 },
        );
        renameSync(temporaryDesktopFile, desktopFile);
        app.commandLine.appendSwitch('class', desktopName);
    } catch (error) {
        console.warn('Unable to configure the Linux development desktop identity:', error);

        return null;
    }

    return desktopFile;
}
