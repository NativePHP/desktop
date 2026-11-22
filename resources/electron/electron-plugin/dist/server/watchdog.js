var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
import killSync from 'kill-sync';
export const watchdogDefaults = {
    interval: 30000,
    timeout: 10000,
    misses: 3,
    killGrace: 5000,
    restartDelay: 1000,
    maxRestartDelay: 30000,
    maxRestarts: 5,
};
export class PhpServerWatchdog {
    constructor(hooks, options = {}) {
        this.hooks = hooks;
        this.server = null;
        this.timer = null;
        this.misses = 0;
        this.restarts = 0;
        this.restarting = false;
        this.reloadPending = false;
        this.paused = false;
        this.stopped = false;
        this.epoch = 0;
        this.options = Object.assign(Object.assign({}, watchdogDefaults), options);
    }
    watch(server) {
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
    adopt(server) {
        this.server = server;
        server.once('exit', (code, signal) => {
            if (this.stopped || server !== this.server) {
                return;
            }
            console.error(`PHP server exited unexpectedly (${signal !== null && signal !== void 0 ? signal : `code ${code}`}), restarting it`);
            this.restart(server);
        });
    }
    scheduleCheck(delay = this.options.interval) {
        this.clearTimer();
        if (this.stopped || this.paused) {
            return;
        }
        this.timer = setTimeout(() => this.check(), delay);
    }
    clearTimer() {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
    }
    check() {
        return __awaiter(this, void 0, void 0, function* () {
            this.timer = null;
            if (this.restarting || !this.server) {
                return;
            }
            const epoch = this.epoch;
            const startedAt = Date.now();
            const answered = yield this.hooks.ping(this.options.timeout);
            if (epoch !== this.epoch) {
                return;
            }
            if (answered) {
                this.misses = 0;
                if (this.reloadPending) {
                    this.reloadPending = false;
                    console.log('PHP server is answering again, reloading windows');
                    this.hooks.reloadWindows();
                }
                else {
                    this.restarts = 0;
                }
            }
            else {
                this.misses++;
                console.warn(`PHP server did not answer a health check within ${this.options.timeout}ms (${this.misses} of ${this.options.misses})`);
                if (this.misses >= this.options.misses) {
                    console.error(`PHP server missed ${this.misses} health checks in a row, restarting it`);
                    this.restart(this.server);
                    return;
                }
            }
            this.scheduleCheck(Math.max(0, this.options.interval - (Date.now() - startedAt)));
        });
    }
    restart(previous) {
        return __awaiter(this, void 0, void 0, function* () {
            var _a, _b;
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
                    yield this.terminate(previous);
                }
                while (!this.stopped) {
                    if (this.restarts >= this.options.maxRestarts) {
                        console.error(`PHP server was restarted ${this.restarts} times without staying up. Giving up; quit and reopen the app to try again.`);
                        return;
                    }
                    const delay = this.backoff();
                    this.restarts++;
                    if (delay > 0) {
                        yield sleep(delay);
                    }
                    if (this.stopped) {
                        return;
                    }
                    console.log(`Restarting PHP server (attempt ${this.restarts} of ${this.options.maxRestarts})`);
                    try {
                        const next = yield this.hooks.start();
                        if (this.stopped) {
                            this.kill(next, 'SIGTERM');
                            return;
                        }
                        this.adopt(next);
                        (_b = (_a = this.hooks).onRestart) === null || _b === void 0 ? void 0 : _b.call(_a, previous, next);
                        this.reloadPending = true;
                        break;
                    }
                    catch (error) {
                        console.error('PHP server failed to start:', error instanceof Error ? error.message : error);
                    }
                }
            }
            finally {
                this.restarting = false;
            }
            this.scheduleCheck(0);
        });
    }
    terminate(server) {
        return __awaiter(this, void 0, void 0, function* () {
            this.kill(server, 'SIGTERM');
            if (yield exitsWithin(server, this.options.killGrace)) {
                return;
            }
            console.warn(`PHP server did not exit within ${this.options.killGrace}ms of SIGTERM, sending SIGKILL`);
            this.kill(server, 'SIGKILL');
            yield exitsWithin(server, this.options.killGrace);
        });
    }
    kill(server, signal) {
        if (this.hooks.kill) {
            this.hooks.kill(server, signal);
            return;
        }
        try {
            killSync(server.pid, signal, true);
        }
        catch (error) {
            console.error(error);
        }
    }
    backoff() {
        if (this.restarts === 0) {
            return 0;
        }
        return Math.min(this.options.restartDelay * Math.pow(2, (this.restarts - 1)), this.options.maxRestartDelay);
    }
}
export function watchdogOptionsFromConfig(config = {}) {
    const options = {};
    const interval = positiveNumber(config.interval);
    const timeout = positiveNumber(config.timeout);
    const misses = positiveNumber(config.misses);
    if (interval)
        options.interval = interval * 1000;
    if (timeout)
        options.timeout = timeout * 1000;
    if (misses)
        options.misses = Math.ceil(misses);
    return options;
}
function positiveNumber(value) {
    const number = Number(value);
    return value !== null && value !== '' && Number.isFinite(number) && number > 0 ? number : null;
}
function isRunning(server) {
    return server.exitCode === null && server.signalCode === null;
}
function exitsWithin(server, ms) {
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
function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}
