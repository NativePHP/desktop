import { existsSync, mkdtempSync, readFileSync, rmSync } from 'fs';
import { tmpdir } from 'os';
import { join } from 'path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { configureLinuxDevelopmentDesktop } from '../src/linuxDevelopmentDesktop.js';

const temporaryDirectories: string[] = [];

afterEach(() => {
    temporaryDirectories.splice(0).forEach((directory) => rmSync(directory, { recursive: true }));
});

describe('Linux development desktop identity', () => {
    it('creates a hidden project-scoped desktop entry', () => {
        const dataHome = mkdtempSync(join(tmpdir(), 'nativephp-desktop-'));
        temporaryDirectories.push(dataHome);

        const desktopFile = configureLinuxDevelopmentDesktop('/project/build/icon.png', {
            environment: {
                NODE_ENV: 'development',
                NATIVEPHP_APP_NAME: 'Example',
                NATIVEPHP_DESKTOP_NAME: 'com.example.product.nativephp-dev-0123456789ab',
                XDG_DATA_HOME: dataHome,
            },
            platform: 'linux',
        });

        expect(desktopFile).not.toBeNull();
        expect(existsSync(desktopFile!)).toBe(true);
        expect(desktopFile!.endsWith('com.example.product.nativephp-dev-0123456789ab.desktop')).toBe(true);
        expect(readFileSync(desktopFile!, 'utf8')).toContain('Name=Example (Development)');
        expect(readFileSync(desktopFile!, 'utf8')).toContain('Icon=/project/build/icon.png');
        expect(readFileSync(desktopFile!, 'utf8')).toContain('NoDisplay=true');
        expect(readFileSync(desktopFile!, 'utf8')).toContain('X-NativePHP-Development=true');
    });

    it('escapes display values', () => {
        const dataHome = mkdtempSync(join(tmpdir(), 'nativephp-desktop-'));
        temporaryDirectories.push(dataHome);

        const desktopFile = configureLinuxDevelopmentDesktop('/project/icon.png', {
            environment: {
                NODE_ENV: 'development',
                NATIVEPHP_APP_NAME: 'Example\nInjected=true',
                NATIVEPHP_DESKTOP_NAME: '_7.example-My-App.nativephp-dev-0123456789ab',
                XDG_DATA_HOME: dataHome,
            },
            platform: 'linux',
        });

        expect(readFileSync(desktopFile!, 'utf8')).toContain('Name=Example\\nInjected=true (Development)');
    });

    it.each(['darwin', 'win32'] as const)('does nothing on %s', (platform) => {
        expect(
            configureLinuxDevelopmentDesktop('/icon.png', { environment: { NODE_ENV: 'development' }, platform }),
        ).toBeNull();
    });

    it('does nothing in a production build', () => {
        expect(
            configureLinuxDevelopmentDesktop('/icon.png', {
                environment: { NODE_ENV: 'production' },
                platform: 'linux',
            }),
        ).toBeNull();
    });

    it('does not prevent development startup when the data directory is unavailable', () => {
        const warning = vi.spyOn(console, 'warn').mockImplementation(() => undefined);

        expect(
            configureLinuxDevelopmentDesktop('/icon.png', {
                environment: {
                    NODE_ENV: 'development',
                    NATIVEPHP_DESKTOP_NAME: 'com.example.product.nativephp-dev-0123456789ab',
                    XDG_DATA_HOME: '/dev/null',
                },
                platform: 'linux',
            }),
        ).toBeNull();
        expect(warning).toHaveBeenCalledOnce();
    });

    it('registers a launchable protocol handler through xdg-mime', () => {
        const dataHome = mkdtempSync(join(tmpdir(), 'nativephp-desktop-'));
        const registerMimeHandler = vi.fn().mockReturnValue(true);
        temporaryDirectories.push(dataHome);

        const desktopFile = configureLinuxDevelopmentDesktop('/project/icon.png', {
            environment: {
                NODE_ENV: 'development',
                NATIVEPHP_APP_NAME: 'Example',
                NATIVEPHP_DESKTOP_NAME: 'com.example.product.nativephp-dev-0123456789ab',
                XDG_DATA_HOME: dataHome,
            },
            platform: 'linux',
            executable: '/project with spaces/electron',
            entryScript: '/project with spaces/electron app',
            scheme: 'devkeepr',
            registerMimeHandler,
        });
        const contents = readFileSync(desktopFile!, 'utf8');

        expect(contents).toContain('Exec="/project with spaces/electron" "/project with spaces/electron app" %u');
        expect(contents).toContain('MimeType=x-scheme-handler/devkeepr;');
        expect(contents).toContain('Terminal=false');
        expect(registerMimeHandler).toHaveBeenCalledWith(
            'com.example.product.nativephp-dev-0123456789ab.desktop',
            'x-scheme-handler/devkeepr',
        );
    });
});
