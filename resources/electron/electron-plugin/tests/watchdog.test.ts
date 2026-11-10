import { ChildProcess } from 'child_process';
import { EventEmitter } from 'events';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { PhpServerWatchdog, watchdogOptionsFromConfig } from '../src/server/watchdog';

class FakeServer extends EventEmitter {
    pid = 4242;
    exitCode: number | null = null;
    signalCode: NodeJS.Signals | null = null;

    // A SIGSTOPped process sits on SIGTERM until it's continued, but SIGKILL still lands.
    constructor(public ignoresSigterm = false) {
        super();
    }

    receive(signal: NodeJS.Signals) {
        if (signal === 'SIGTERM' && this.ignoresSigterm) {
            return;
        }

        this.exit(null, signal);
    }

    exit(code: number | null = null, signal: NodeJS.Signals | null = 'SIGKILL') {
        this.exitCode = code;
        this.signalCode = signal;
        this.emit('exit', code, signal);
    }
}

function setup(options = {}) {
    const servers: FakeServer[] = [];
    let hung = false;

    const hooks = {
        start: vi.fn(async () => {
            const server = new FakeServer();
            servers.push(server);

            return server as unknown as ChildProcess;
        }),
        // Answers straight away, or after the full timeout when hung.
        ping: vi.fn(
            (timeout: number) =>
                new Promise<boolean>((resolve) => {
                    if (hung) {
                        setTimeout(() => resolve(false), timeout);
                    } else {
                        resolve(true);
                    }
                }),
        ),
        reloadWindows: vi.fn(),
        onRestart: vi.fn(),
        kill: vi.fn((server: FakeServer, signal: NodeJS.Signals) => server.receive(signal)),
    };

    const watchdog = new PhpServerWatchdog(hooks as never, options);

    return {
        watchdog,
        hooks,
        servers,
        hang: (value = true) => {
            hung = value;
        },
    };
}

const server = (ignoresSigterm = false) => new FakeServer(ignoresSigterm) as unknown as ChildProcess & FakeServer;
const advance = (ms: number) => vi.advanceTimersByTimeAsync(ms);

describe('PHP server watchdog', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.spyOn(console, 'log').mockImplementation(() => {});
        vi.spyOn(console, 'warn').mockImplementation(() => {});
        vi.spyOn(console, 'error').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    describe('when the server exits', () => {
        it('starts it again and reloads the windows once it answers', async () => {
            const { watchdog, hooks, servers } = setup();
            const first = server();

            watchdog.watch(first);
            first.exit(null, 'SIGKILL');
            await advance(0);

            expect(hooks.start).toHaveBeenCalledTimes(1);
            expect(hooks.onRestart).toHaveBeenCalledWith(first, servers[0]);
            expect(hooks.ping).toHaveBeenCalledTimes(1);
            expect(hooks.reloadWindows).toHaveBeenCalledTimes(1);
        });

        it('watches the restarted server too', async () => {
            const { watchdog, hooks, servers } = setup();
            const first = server();

            watchdog.watch(first);
            first.exit(1);
            await advance(0);

            servers[0].exit(1);
            await advance(1_000);

            expect(hooks.start).toHaveBeenCalledTimes(2);
            expect(hooks.onRestart).toHaveBeenLastCalledWith(servers[0], servers[1]);
        });

        it('backs off between attempts and gives up when it never comes up', async () => {
            const { watchdog, hooks } = setup();
            hooks.start.mockRejectedValue(new Error('Address already in use'));
            const first = server();

            watchdog.watch(first);
            first.exit(255);

            await advance(0);
            expect(hooks.start).toHaveBeenCalledTimes(1);

            await advance(999);
            expect(hooks.start).toHaveBeenCalledTimes(1);

            await advance(1);
            expect(hooks.start).toHaveBeenCalledTimes(2);

            await advance(2_000);
            expect(hooks.start).toHaveBeenCalledTimes(3);

            await advance(4_000);
            expect(hooks.start).toHaveBeenCalledTimes(4);

            await advance(8_000);
            expect(hooks.start).toHaveBeenCalledTimes(5);

            await advance(10 * 60_000);
            expect(hooks.start).toHaveBeenCalledTimes(5);
            expect(hooks.ping).not.toHaveBeenCalled();
            expect(hooks.reloadWindows).not.toHaveBeenCalled();
        });

        it('gives up on a server that dies straight after every start', async () => {
            const { watchdog, hooks, servers } = setup();
            hooks.start.mockImplementation(async () => {
                const next = new FakeServer();
                servers.push(next);
                setTimeout(() => next.exit(255), 100);

                return next as unknown as ChildProcess;
            });
            const first = server();

            watchdog.watch(first);
            first.exit(255);
            await advance(10 * 60_000);

            expect(hooks.start).toHaveBeenCalledTimes(5);
        });

        it('keeps restarting a server that stays up in between', async () => {
            const { watchdog, hooks, servers } = setup();
            const first = server();

            watchdog.watch(first);

            for (let i = 0; i < 10; i++) {
                (servers[servers.length - 1] ?? first).exit(null, 'SIGKILL');
                await advance(0);
                await advance(30_000);
            }

            expect(hooks.start).toHaveBeenCalledTimes(10);
            expect(hooks.reloadWindows).toHaveBeenCalledTimes(10);
        });

        it('leaves it alone once the app is quitting', async () => {
            const { watchdog, hooks } = setup();
            const first = server();

            watchdog.watch(first);
            watchdog.stop();
            first.exit(0);
            await advance(10 * 60_000);

            expect(hooks.start).not.toHaveBeenCalled();
            expect(hooks.ping).not.toHaveBeenCalled();
            expect(console.error).not.toHaveBeenCalled();
        });

        it('kills a server that finishes starting after the app began quitting', async () => {
            const { watchdog, hooks } = setup();
            const late = server();
            let finishStarting: (server: ChildProcess) => void = () => {};
            hooks.start.mockImplementation(() => new Promise((resolve) => (finishStarting = resolve)));
            const first = server();

            watchdog.watch(first);
            first.exit(null, 'SIGKILL');
            await advance(0);

            watchdog.stop();
            finishStarting(late);
            await advance(0);

            expect(hooks.kill).toHaveBeenCalledWith(late, 'SIGTERM');
            expect(hooks.onRestart).not.toHaveBeenCalled();
            expect(hooks.reloadWindows).not.toHaveBeenCalled();
        });
    });

    describe('health checks', () => {
        it('checks the server every interval', async () => {
            const { watchdog, hooks } = setup();

            watchdog.watch(server());

            await advance(29_999);
            expect(hooks.ping).not.toHaveBeenCalled();

            await advance(1);
            expect(hooks.ping).toHaveBeenCalledWith(10_000);

            await advance(60_000);
            expect(hooks.ping).toHaveBeenCalledTimes(3);
            expect(hooks.kill).not.toHaveBeenCalled();
            expect(hooks.reloadWindows).not.toHaveBeenCalled();
        });

        it('kills a hung server after three misses, then restarts it and reloads the windows', async () => {
            const { watchdog, hooks, servers, hang } = setup();
            const hung = server(true);
            hang();

            watchdog.watch(hung);

            // Checks start every 30s and each waits 10s for an answer.
            await advance(40_000);
            await advance(30_000);
            expect(hooks.kill).not.toHaveBeenCalled();

            await advance(30_000);
            expect(hooks.kill).toHaveBeenCalledWith(hung, 'SIGTERM');
            expect(hooks.kill).not.toHaveBeenCalledWith(hung, 'SIGKILL');
            expect(hooks.start).not.toHaveBeenCalled();

            hang(false);
            await advance(6_000);

            expect(hooks.kill).toHaveBeenCalledWith(hung, 'SIGKILL');
            expect(hooks.start).toHaveBeenCalledTimes(1);
            expect(hooks.onRestart).toHaveBeenCalledWith(hung, servers[0]);
            expect(hooks.reloadWindows).toHaveBeenCalledTimes(1);

            // The hung server's own exit doesn't set off a second restart.
            await advance(10 * 60_000);
            expect(hooks.start).toHaveBeenCalledTimes(1);
            expect(hooks.reloadWindows).toHaveBeenCalledTimes(1);
        });

        it('does not send SIGKILL to a server that exits on SIGTERM', async () => {
            const { watchdog, hooks, hang } = setup();
            const slow = server();
            hang();

            watchdog.watch(slow);
            await advance(95_000);
            hang(false);
            await advance(6_000);

            expect(hooks.kill).toHaveBeenCalledWith(slow, 'SIGTERM');
            expect(hooks.start).toHaveBeenCalledTimes(1);
            expect(hooks.reloadWindows).toHaveBeenCalledTimes(1);

            await advance(10_000);
            expect(hooks.kill).not.toHaveBeenCalledWith(slow, 'SIGKILL');
        });

        it('only counts misses in a row', async () => {
            const { watchdog, hooks, hang } = setup();
            hooks.ping
                .mockResolvedValueOnce(false)
                .mockResolvedValueOnce(false)
                .mockResolvedValueOnce(true)
                .mockResolvedValueOnce(false)
                .mockResolvedValueOnce(false);
            hang();

            watchdog.watch(server());
            await advance(5 * 30_000);
            expect(hooks.kill).not.toHaveBeenCalled();

            await advance(40_000);
            expect(hooks.kill).toHaveBeenCalled();
        });

        it('honours the configured knobs', async () => {
            const { watchdog, hooks, hang } = setup({ interval: 5_000, timeout: 1_000, misses: 1 });
            hang();

            watchdog.watch(server());
            await advance(6_000);

            expect(hooks.ping).toHaveBeenCalledWith(1_000);
            expect(hooks.kill).toHaveBeenCalled();
        });
    });

    describe('sleep', () => {
        it('stops checking while the machine sleeps', async () => {
            const { watchdog, hooks } = setup();

            watchdog.watch(server());
            watchdog.pause();
            await advance(10 * 60_000);

            expect(hooks.ping).not.toHaveBeenCalled();

            watchdog.resume();
            await advance(30_000);

            expect(hooks.ping).toHaveBeenCalledTimes(1);
        });

        it('forgets earlier misses, and ignores a check cut short by sleep', async () => {
            const { watchdog, hooks, hang } = setup();
            hang();

            watchdog.watch(server());
            await advance(70_000);
            expect(hooks.ping).toHaveBeenCalledTimes(2);

            // The third check is in flight when the lid closes.
            await advance(25_000);
            expect(hooks.ping).toHaveBeenCalledTimes(3);
            watchdog.pause();
            await advance(10_000);

            watchdog.resume();
            await advance(40_000);
            await advance(30_000);
            expect(hooks.kill).not.toHaveBeenCalled();

            await advance(30_000);
            expect(hooks.kill).toHaveBeenCalled();
        });
    });
});

describe('watchdog config', () => {
    it('reads seconds from config/nativephp.php', () => {
        expect(watchdogOptionsFromConfig({ enabled: true, interval: 45, timeout: '5', misses: 2 })).toEqual({
            interval: 45_000,
            timeout: 5_000,
            misses: 2,
        });
    });

    it('falls back to the defaults for missing or nonsense values', () => {
        expect(watchdogOptionsFromConfig()).toEqual({});
        expect(watchdogOptionsFromConfig({ interval: 0, timeout: -1, misses: 'lots' })).toEqual({});
        expect(watchdogOptionsFromConfig({ interval: null, timeout: '' })).toEqual({});
    });
});
