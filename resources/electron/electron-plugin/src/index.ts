import { electronApp, optimizer } from '@electron-toolkit/utils';
import { initialize } from '@electron/remote/main/index.js';
import { ChildProcessWithoutNullStreams } from 'child_process';
import CrossProcessExports, { app, powerMonitor, session } from 'electron';
import killSync from 'kill-sync';
import { resolve } from 'path';
import ps from 'ps-node';
import { DeepLinkQueue } from './deepLinks.js';
import { configureLinuxDevelopmentDesktop } from './linuxDevelopmentDesktop.js';
import { stopAllProcesses } from './server/api/childProcess.js';
import {
    killScheduler,
    retrieveNativePHPConfig,
    retrievePhpIniSettings,
    runScheduler,
    startAPI,
    startPhpApp,
} from './server/index.js';
import state from './server/state.js';
import { notifyLaravel } from './server/utils.js';

// Workaround for CommonJS module
import electronUpdater from 'electron-updater';
const { autoUpdater } = electronUpdater;

class NativePHP {
    processes: ChildProcessWithoutNullStreams[] = [];
    schedulerInterval = undefined;
    quitting = false;
    singleInstanceConfigured = false;
    deepLinks = new DeepLinkQueue((url) =>
        notifyLaravel('events', {
            event: '\\Native\\Desktop\\Events\\App\\OpenedFromURL',
            payload: { url },
        }),
    );

    public bootstrap(
        app: CrossProcessExports.App,
        icon: string,
        phpBinary: string,
        cert: string,
        appPath: string,
        deepLinkProtocol?: string,
    ) {
        configureLinuxDevelopmentDesktop(icon, {
            executable: process.execPath,
            entryScript: process.argv[1] ? resolve(process.argv[1]) : undefined,
            scheme: deepLinkProtocol,
        });
        initialize();

        state.icon = icon;
        state.php = phpBinary;
        state.caCert = cert;
        state.appPath = appPath;

        this.deepLinks.configure(deepLinkProtocol, process.argv);

        if (!this.configureSingleInstance(app, deepLinkProtocol)) {
            return;
        }

        this.addEventListeners(app);
        this.bootstrapApp(app);
    }

    private addEventListeners(app: Electron.CrossProcessExports.App) {
        app.on('open-url', (event, url) => {
            event.preventDefault();
            this.deepLinks.captureUrl(url);
        });

        app.on('open-file', (event, path) => {
            notifyLaravel('events', {
                event: '\\Native\\Desktop\\Events\\App\\OpenFile',
                payload: [path],
            });
        });

        app.on('window-all-closed', () => {
            if (process.platform !== 'darwin') {
                app.quit();
            }
        });

        app.on('before-quit', async (event) => {
            // We call app.quit() again at the end, which fires this handler a
            // second time. Let that pass straight through so the quit happens.
            if (this.quitting) {
                return;
            }
            this.quitting = true;
            event.preventDefault();

            // Stop the framework's own processes first (the PHP server and the
            // like). While they're still up, an incoming request could boot the
            // app and spawn fresh child processes that we'd never clean up here.
            this.killChildProcesses();

            // Now the app's child processes. The ones started with `handlesOwnShutdown`
            // get a plain SIGTERM rather than a tree-kill, so they can bring down
            // their own children themselves before they exit.
            stopAllProcesses();

            // Give them a moment to act on that SIGTERM (flush, persist, whatever
            // they need) before we pull the plug. Each one drops out of state as
            // it exits; the deadline stops a stuck process from blocking the quit.
            const deadline = Date.now() + 12_000;
            while (Object.keys(state.processes).length > 0 && Date.now() < deadline) {
                await new Promise((resolve) => setTimeout(resolve, 200));
            }

            app.quit();
        });

        // Default open or close DevTools by F12 in development
        // and ignore CommandOrControl + R in production.
        // see https://github.com/alex8088/electron-toolkit/tree/master/packages/utils
        app.on('browser-window-created', (_, window) => {
            optimizer.watchWindowShortcuts(window);
        });

        app.on('activate', (event, hasVisibleWindows) => {
            // On macOS it's common to re-create a window in the app when the
            // dock icon is clicked and there are no other windows open.
            if (!hasVisibleWindows) {
                void this.notifyBootedAndFlushDeepLinks();
            }

            event.preventDefault();
        });
    }

    private async bootstrapApp(app: Electron.CrossProcessExports.App) {
        await app.whenReady();

        const config = await this.loadConfig();

        this.setDockIcon();
        this.setAppUserModelId(config);
        if (!this.setDeepLinkHandler(app, config)) {
            return;
        }
        this.startAutoUpdater(config);

        await this.startElectronApi();

        state.phpIni = await this.loadPhpIni();

        await this.startPhpApp();
        this.startScheduler();

        powerMonitor.on('suspend', () => {
            this.stopScheduler();
        });

        powerMonitor.on('resume', () => {
            this.stopScheduler();
            this.startScheduler();
        });

        const filter = {
            urls: [`http://127.0.0.1:${state.phpPort}/*`],
        };

        session.defaultSession.webRequest.onBeforeSendHeaders(filter, (details, callback) => {
            details.requestHeaders['X-NativePHP-Secret'] = state.randomSecret;

            callback({ requestHeaders: details.requestHeaders });
        });

        if (process.env.NATIVEPHP_NO_FOCUS) {
            state.noFocusOnRestart = true;
        }

        await this.notifyBootedAndFlushDeepLinks();
    }

    private async loadConfig() {
        let config = {};

        try {
            const result = await retrieveNativePHPConfig();

            config = JSON.parse(result.stdout);
        } catch (error) {
            console.error(error);
        }

        return config;
    }

    private setDockIcon() {
        // Only run this on macOS
        if (process.platform === 'darwin' && process.env.NODE_ENV === 'development') {
            app.dock.setIcon(state.icon);
        }
    }

    private setAppUserModelId(config) {
        electronApp.setAppUserModelId(config?.app_id);
    }

    private setDeepLinkHandler(app: Electron.CrossProcessExports.App, config): boolean {
        const deepLinkProtocol = config?.deeplink_scheme;

        if (deepLinkProtocol) {
            this.deepLinks.configure(deepLinkProtocol, process.argv);

            if (!this.configureSingleInstance(app, deepLinkProtocol)) {
                return false;
            }

            if (process.defaultApp) {
                if (process.argv.length >= 2) {
                    app.setAsDefaultProtocolClient(deepLinkProtocol, process.execPath, [resolve(process.argv[1])]);
                }
            } else {
                app.setAsDefaultProtocolClient(deepLinkProtocol);
            }
        }

        return true;
    }

    private configureSingleInstance(app: Electron.CrossProcessExports.App, deepLinkProtocol?: string): boolean {
        if (!deepLinkProtocol || process.platform === 'darwin' || this.singleInstanceConfigured) {
            return true;
        }

        if (!app.requestSingleInstanceLock()) {
            app.quit();

            return false;
        }

        this.singleInstanceConfigured = true;
        app.on('second-instance', (_event, commandLine) => {
            Object.values(state.windows).forEach((window) => {
                if (window.isMinimized()) {
                    window.restore();
                }

                window.show();
                window.focus();
            });

            this.deepLinks.captureArguments(commandLine);
        });

        return true;
    }

    private async notifyBootedAndFlushDeepLinks(): Promise<void> {
        if (await notifyLaravel('booted')) {
            await this.deepLinks.markReady();
        }
    }

    private startAutoUpdater(config) {
        if (config?.updater?.enabled === true) {
            // If a public URL is configured for the current provider, use it for updates
            const defaultProvider = config?.updater?.default;
            const publicUrl = config?.updater?.providers?.[defaultProvider]?.public_url;

            if (publicUrl) {
                autoUpdater.setFeedURL({
                    provider: 'generic',
                    url: publicUrl,
                });
            }

            autoUpdater.checkForUpdatesAndNotify();
        }
    }

    private async startElectronApi() {
        // Start an Express server so that the Electron app can be controlled from PHP via API
        const electronApi = await startAPI();

        state.electronApiPort = electronApi.port;

        console.log('Electron API server started on port', electronApi.port);
    }

    private async loadPhpIni() {
        let config = {};

        try {
            const result = await retrievePhpIniSettings();

            config = JSON.parse(result.stdout);
        } catch (error) {
            console.error(error);
        }

        return config;
    }

    private async startPhpApp() {
        this.processes.push(await startPhpApp());
    }

    private stopScheduler() {
        if (this.schedulerInterval) {
            clearInterval(this.schedulerInterval);
            this.schedulerInterval = null;
        }
        killScheduler();
    }

    private startScheduler() {
        const now = new Date();
        const delay = (60 - now.getSeconds()) * 1000 + (1000 - now.getMilliseconds());

        setTimeout(() => {
            console.log('Running scheduler...');

            runScheduler();

            this.schedulerInterval = setInterval(() => {
                console.log('Running scheduler...');

                runScheduler();
            }, 60 * 1000);
        }, delay);
    }

    private killChildProcesses() {
        this.stopScheduler();

        this.processes
            .filter((p) => p !== undefined)
            .forEach((process) => {
                if (!process || !process.pid) return;
                if (process.killed && process.exitCode !== null) return;

                try {
                    // @ts-ignore
                    killSync(process.pid, 'SIGTERM', true); // Kill tree
                    ps.kill(process.pid); // Sometimes does not kill the subprocess of php server
                } catch (err) {
                    console.error(err);
                }
            });
    }
}

export default new NativePHP();
