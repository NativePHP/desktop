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
        const appendSwitch = vi.fn();
        temporaryDirectories.push(dataHome);

        const desktopFile = configureLinuxDevelopmentDesktop(
            { commandLine: { appendSwitch } as never },
            '/project/build/icon.png',
            {
                NODE_ENV: 'development',
                NATIVEPHP_APP_ID: 'com.example.product',
                NATIVEPHP_APP_NAME: 'Example',
                APP_PATH: '/projects/example',
                XDG_DATA_HOME: dataHome,
            },
            'linux',
        );

        expect(desktopFile).not.toBeNull();
        expect(existsSync(desktopFile!)).toBe(true);
        expect(appendSwitch).toHaveBeenCalledWith(
            'class',
            expect.stringMatching(/^com\.example\.product\.nativephp-dev-[a-f0-9]{12}$/),
        );
        expect(readFileSync(desktopFile!, 'utf8')).toContain('Name=Example (Development)');
        expect(readFileSync(desktopFile!, 'utf8')).toContain('Icon=/project/build/icon.png');
        expect(readFileSync(desktopFile!, 'utf8')).toContain('NoDisplay=true');
        expect(readFileSync(desktopFile!, 'utf8')).toContain('X-NativePHP-Development=true');
    });

    it('uses a different identity for another checkout', () => {
        const environment = {
            NODE_ENV: 'development',
            NATIVEPHP_APP_ID: 'com.example.product',
            XDG_DATA_HOME: mkdtempSync(join(tmpdir(), 'nativephp-desktop-')),
        };
        const identities: string[] = [];
        temporaryDirectories.push(environment.XDG_DATA_HOME);

        const app = {
            commandLine: { appendSwitch: (_switch: string, value: string) => identities.push(value) } as never,
        };

        configureLinuxDevelopmentDesktop(app, '/icon.png', { ...environment, APP_PATH: '/first' }, 'linux');
        configureLinuxDevelopmentDesktop(app, '/icon.png', { ...environment, APP_PATH: '/second' }, 'linux');

        expect(identities[0]).not.toBe(identities[1]);
    });

    it('normalizes application IDs and escapes display values', () => {
        const dataHome = mkdtempSync(join(tmpdir(), 'nativephp-desktop-'));
        const identities: string[] = [];
        temporaryDirectories.push(dataHome);

        const desktopFile = configureLinuxDevelopmentDesktop(
            {
                commandLine: { appendSwitch: (_switch: string, value: string) => identities.push(value) } as never,
            },
            '/project/icon.png',
            {
                NODE_ENV: 'development',
                NATIVEPHP_APP_ID: '7.example/My App',
                NATIVEPHP_APP_NAME: 'Example\nInjected=true',
                APP_PATH: '/project',
                XDG_DATA_HOME: dataHome,
            },
            'linux',
        );

        expect(identities[0]).toMatch(/^_7\.example-My-App\.nativephp-dev-/);
        expect(readFileSync(desktopFile!, 'utf8')).toContain('Name=Example\\nInjected=true (Development)');
    });

    it.each(['darwin', 'win32'] as const)('does nothing on %s', (platform) => {
        const appendSwitch = vi.fn();

        expect(
            configureLinuxDevelopmentDesktop(
                { commandLine: { appendSwitch } as never },
                '/icon.png',
                { NODE_ENV: 'development' },
                platform,
            ),
        ).toBeNull();
        expect(appendSwitch).not.toHaveBeenCalled();
    });

    it('does nothing in a production build', () => {
        const appendSwitch = vi.fn();

        expect(
            configureLinuxDevelopmentDesktop(
                { commandLine: { appendSwitch } as never },
                '/icon.png',
                { NODE_ENV: 'production' },
                'linux',
            ),
        ).toBeNull();
        expect(appendSwitch).not.toHaveBeenCalled();
    });

    it('does not prevent development startup when the data directory is unavailable', () => {
        const appendSwitch = vi.fn();
        const warning = vi.spyOn(console, 'warn').mockImplementation(() => undefined);

        expect(
            configureLinuxDevelopmentDesktop(
                { commandLine: { appendSwitch } as never },
                '/icon.png',
                {
                    NODE_ENV: 'development',
                    NATIVEPHP_APP_ID: 'com.example.product',
                    APP_PATH: '/project',
                    XDG_DATA_HOME: '/dev/null',
                },
                'linux',
            ),
        ).toBeNull();
        expect(appendSwitch).not.toHaveBeenCalled();
        expect(warning).toHaveBeenCalledOnce();
    });
});
