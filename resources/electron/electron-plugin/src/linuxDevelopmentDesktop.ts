import { mkdirSync, renameSync, writeFileSync } from 'fs';
import { homedir } from 'os';
import { join } from 'path';

type Environment = Record<string, string | undefined>;

function desktopValue(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
}

export function configureLinuxDevelopmentDesktop(
    icon: string,
    environment: Environment = process.env,
    platform: NodeJS.Platform = process.platform,
): string | null {
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
    } catch (error) {
        console.warn('Unable to configure the Linux development desktop identity:', error);

        return null;
    }

    return desktopFile;
}
