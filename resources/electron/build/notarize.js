import { notarize } from '@electron/notarize';

export default async (context) => {
    // Only notarize when process is running on a Mac
    if (process.platform !== 'darwin') return;

    // And the current build target is macOS
    if (context.packager.platform.name !== 'mac') return;

    // BuildCommand always passes these, as empty strings when they aren't configured
    if (
        !process.env.NATIVEPHP_APPLE_ID ||
        !process.env.NATIVEPHP_APPLE_ID_PASS ||
        !process.env.NATIVEPHP_APPLE_TEAM_ID
    ) {
        console.warn(
            'skipping notarizing, NATIVEPHP_APPLE_ID, NATIVEPHP_APPLE_ID_PASS and NATIVEPHP_APPLE_TEAM_ID env variables must be set.',
        );
        return;
    }

    console.log('aftersign hook triggered, start to notarize app.');

    const appId = process.env.NATIVEPHP_APP_ID;

    const { appOutDir } = context;

    const appName = context.packager.appInfo.productFilename;

    try {
        await notarize({
            appBundleId: appId,
            appPath: `${appOutDir}/${appName}.app`,
            appleId: process.env.NATIVEPHP_APPLE_ID,
            appleIdPassword: process.env.NATIVEPHP_APPLE_ID_PASS,
            teamId: process.env.NATIVEPHP_APPLE_TEAM_ID,
            tool: 'notarytool',
        });
    } catch (error) {
        console.error(error);
    }

    console.log(`done notarizing ${appId}.`);
};
