import { afterEach, describe, expect, it, vi } from 'vitest';

// "MUNĖ" from https://github.com/NativePHP/desktop/issues/98, written out as escapes so the
// two normalisation forms stay distinguishable however this file is stored.
const composed = 'MUN\u0116'; // Ė as a single precomposed code point
const decomposed = 'MUNE\u0307'; // E followed by a combining dot above

async function loadBuilderConfig(appName: string) {
    vi.resetModules();
    vi.stubEnv('APP_PATH', '/fake/app/path');
    vi.stubEnv('NATIVEPHP_BUILDING', '');
    vi.stubEnv('NATIVEPHP_APP_NAME', appName);

    return (await import('../../electron-builder.mjs')).default;
}

describe('electron-builder config', () => {
    afterEach(() => {
        vi.unstubAllEnvs();
    });

    it('decomposes the macOS bundle name so Electron can find its helper apps', async () => {
        const config = await loadBuilderConfig(composed);

        expect(config.mac.extendInfo.CFBundleName).toBe(decomposed);
        expect(config.mac.extendInfo.CFBundleDisplayName).toBe(decomposed);
    });

    it('leaves the product name itself composed', async () => {
        const config = await loadBuilderConfig(composed);

        expect(config.productName).toBe(composed);
    });

    it('leaves ASCII app names untouched', async () => {
        const config = await loadBuilderConfig('My App');

        expect(config.mac.extendInfo.CFBundleName).toBe('My App');
        expect(config.mac.extendInfo.CFBundleDisplayName).toBe('My App');
    });

    it('does not blank out the bundle name when no app name is set', async () => {
        const config = await loadBuilderConfig('');

        expect(config.mac.extendInfo).not.toHaveProperty('CFBundleName');
        expect(config.mac.extendInfo).not.toHaveProperty('CFBundleDisplayName');
    });

    it('keeps the usage descriptions alongside the bundle name', async () => {
        const config = await loadBuilderConfig(composed);

        expect(config.mac.extendInfo.NSCameraUsageDescription).toBe(
            "Application requests access to the device's camera.",
        );
    });
});
