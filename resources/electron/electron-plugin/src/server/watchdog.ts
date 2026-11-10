import { ChildProcess } from 'child_process';
import killSync from 'kill-sync';

export interface WatchdogOptions {
    // How often the PHP server is checked, and how long each check waits for an answer.
    interval: number;
    timeout: number;
    // How many checks in a row the server can miss before it's killed and restarted.
    misses: number;
    // How long a hung server gets to exit after SIGTERM before it's sent SIGKILL.
    killGrace: number;
    // Restarts back off from restartDelay, doubling up to maxRestartDelay. After maxRestarts
    // in a row without a healthy check in between, the watchdog gives up.
    restartDelay: number;
    maxRestartDelay: number;
    maxRestarts: number;
}

export const watchdogDefaults: WatchdogOptions = {
    interval: 30_000,
    timeout: 10_000,
    misses: 3,
    killGrace: 5_000,
    restartDelay: 1_000,
    maxRestartDelay: 30_000,
    maxRestarts: 5,
};

export interface WatchdogHooks {
    // Starts a fresh PHP server on the same port, resolving once it's listening.
    start: () => Promise<ChildProcess>;
    // Resolves true if the server answered within the timeout.
    ping: (timeout: number) => Promise<boolean>;
    reloadWindows: () => void;
    // Lets the owner swap its reference, so quitting the app kills the new server.
    onRestart?: (previous: ChildProcess, next: ChildProcess) => void;
    kill?: (server: ChildProcess, signal: NodeJS.Signals) => void;
}

/**
 * Keeps the PHP server that serves the app's windows alive. If it exits, it's
 * started again on the same port. If it stops answering health checks, it's
 * killed and restarted. Either way the windows reload once it answers again.
 */
export class PhpServerWatchdog {
    private options: WatchdogOptions;
    private server: ChildProcess | null = null;
    private timer: NodeJS.Timeout | null = null;
    private misses = 0;
    private restarts = 0;
    private restarting = false;
    private reloadPending = false;
    private paused = false;
    private stopped = false;
    // Bumped on every pause, resume, restart and stop, so a health check that
    // was in flight at the time knows to throw its result away.
    private epoch = 0;

    constructor(
        private hooks: WatchdogHooks,
        options: Partial<WatchdogOptions> = {},
    ) {
        this.options = { ...watchdogDefaults, ...options };
    }

    watch(server: ChildProcess) {
        this.adopt(server);
        this.scheduleCheck();
    }

    pause() {
        this.paused = true;
        this.epoch++;
        this.clearTimer();
    }

    resume() {
        this.paused = false;
        this.misses = 0;
        this.epoch++;
        this.scheduleCheck();
    }

    stop() {
        this.stopped = true;
        this.epoch++;
        this.clearTimer();
    }

    private adopt(server: ChildProcess) {
        this.server = server;

        server.once('exit', (code, signal) => {
            if (this.stopped || server !== this.server) {
                return;
            }

            console.error(`PHP server exited unexpectedly (${signal ?? `code ${code}`}), restarting it`);

            this.restart(server);
        });
    }

    private scheduleCheck(delay = this.options.interval) {
        this.clearTimer();

        if (this.stopped || this.paused) {
            return;
        }

        this.timer = setTimeout(() => this.check(), delay);
    }

    private clearTimer() {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
    }

    private async check() {
        this.timer = null;

        if (this.restarting || !this.server) {
            return;
        }

        const epoch = this.epoch;
        const startedAt = Date.now();
        const answered = await this.hooks.ping(this.options.timeout);

        if (epoch !== this.epoch) {
            return;
        }

        if (answered) {
            this.misses = 0;

            if (this.reloadPending) {
                this.reloadPending = false;
                console.log('PHP server is answering again, reloading windows');
                this.hooks.reloadWindows();
            } else {
                // A restarted server has now stayed up for a full interval.
                this.restarts = 0;
            }
        } else {
            this.misses++;

            console.warn(
                `PHP server did not answer a health check within ${this.options.timeout}ms (${this.misses} of ${this.options.misses})`,
            );

            if (this.misses >= this.options.misses) {
                console.error(`PHP server missed ${this.misses} health checks in a row, restarting it`);

                this.restart(this.server);

                return;
            }
        }

        this.scheduleCheck(Math.max(0, this.options.interval - (Date.now() - startedAt)));
    }

    private async restart(previous: ChildProcess) {
        if (this.restarting || this.stopped) {
            return;
        }

        this.restarting = true;
        this.epoch++;
        this.clearTimer();
        this.server = null;
        this.misses = 0;

        try {
            if (isRunning(previous)) {
                await this.terminate(previous);
            }

            while (!this.stopped) {
                if (this.restarts >= this.options.maxRestarts) {
                    console.error(
                        `PHP server was restarted ${this.restarts} times without staying up. Giving up; quit and reopen the app to try again.`,
                    );

                    return;
                }

                const delay = this.backoff();
                this.restarts++;

                if (delay > 0) {
                    await sleep(delay);
                }

                if (this.stopped) {
                    return;
                }

                console.log(`Restarting PHP server (attempt ${this.restarts} of ${this.options.maxRestarts})`);

                try {
                    const next = await this.hooks.start();

                    // The app started quitting while the server came up. It isn't
                    // in the list of processes the quit handler kills, so kill it here.
                    if (this.stopped) {
                        this.kill(next, 'SIGTERM');

                        return;
                    }

                    this.adopt(next);
                    this.hooks.onRestart?.(previous, next);
                    this.reloadPending = true;

                    break;
                } catch (error) {
                    console.error('PHP server failed to start:', error instanceof Error ? error.message : error);
                }
            }
        } finally {
            this.restarting = false;
        }

        // Check straight away, so the windows reload as soon as it answers.
        this.scheduleCheck(0);
    }

    private async terminate(server: ChildProcess) {
        this.kill(server, 'SIGTERM');

        if (await exitsWithin(server, this.options.killGrace)) {
            return;
        }

        console.warn(`PHP server did not exit within ${this.options.killGrace}ms of SIGTERM, sending SIGKILL`);

        this.kill(server, 'SIGKILL');

        await exitsWithin(server, this.options.killGrace);
    }

    private kill(server: ChildProcess, signal: NodeJS.Signals) {
        if (this.hooks.kill) {
            this.hooks.kill(server, signal);

            return;
        }

        try {
            // Kill the tree, so whatever the stuck request was waiting on goes too.
            killSync(server.pid, signal, true);
        } catch (error) {
            console.error(error);
        }
    }

    private backoff() {
        if (this.restarts === 0) {
            return 0;
        }

        return Math.min(this.options.restartDelay * 2 ** (this.restarts - 1), this.options.maxRestartDelay);
    }
}

/**
 * Reads the `watchdog` block of config/nativephp.php. Times there are in
 * seconds; anything missing or invalid falls back to the defaults.
 */
export function watchdogOptionsFromConfig(config: Record<string, unknown> = {}): Partial<WatchdogOptions> {
    const options: Partial<WatchdogOptions> = {};

    const interval = positiveNumber(config.interval);
    const timeout = positiveNumber(config.timeout);
    const misses = positiveNumber(config.misses);

    if (interval) options.interval = interval * 1000;
    if (timeout) options.timeout = timeout * 1000;
    if (misses) options.misses = Math.ceil(misses);

    return options;
}

function positiveNumber(value: unknown): number | null {
    const number = Number(value);

    return value !== null && value !== '' && Number.isFinite(number) && number > 0 ? number : null;
}

function isRunning(server: ChildProcess) {
    return server.exitCode === null && server.signalCode === null;
}

function exitsWithin(server: ChildProcess, ms: number): Promise<boolean> {
    if (!isRunning(server)) {
        return Promise.resolve(true);
    }

    return new Promise((resolve) => {
        const timer = setTimeout(() => {
            server.off('exit', onExit);
            resolve(false);
        }, ms);

        const onExit = () => {
            clearTimeout(timer);
            resolve(true);
        };

        server.once('exit', onExit);
    });
}

function sleep(ms: number) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}
