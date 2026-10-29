import { ChildProcess } from 'child_process';
import startAPIServer, { APIProcess } from './api.js';
import { retrieveNativePHPConfig, retrievePhpIniSettings, serveApp, startPhpServer, startScheduler } from './php.js';
import state from './state.js';
import { appendCookie } from './utils.js';

let schedulerProcess: ChildProcess | null = null;

export async function startPhpApp() {
    const result = await serveApp(state.randomSecret, state.electronApiPort, state.phpIni);

    state.phpPort = result.port;

    await appendCookie();

    return result.process;
}

export async function restartPhpServer() {
    const result = await startPhpServer(state.phpPort);

    return result.process;
}

export function runScheduler() {
    killScheduler();
    schedulerProcess = startScheduler(state.randomSecret, state.electronApiPort, state.phpIni);
}

export function killScheduler() {
    if (schedulerProcess && !schedulerProcess.killed) {
        schedulerProcess.kill();
        schedulerProcess = null;
    }
}

export function startAPI(): Promise<APIProcess> {
    return startAPIServer(state.randomSecret);
}

export { retrieveNativePHPConfig, retrievePhpIniSettings };
