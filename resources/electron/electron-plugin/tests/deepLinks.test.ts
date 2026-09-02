import { describe, expect, it, vi } from 'vitest';
import { DeepLinkQueue, findDeepLink } from '../src/deepLinks.js';

describe('deep links', () => {
    it('finds the configured protocol regardless of argument order or case', () => {
        expect(findDeepLink(['--flag', 'DEVKEEPR://projects?project=/tmp/app', '--another'], 'devkeepr')).toBe(
            'DEVKEEPR://projects?project=/tmp/app',
        );
        expect(findDeepLink(['https://example.com'], 'devkeepr')).toBeNull();
    });

    it('queues cold and warm links until Laravel is ready', async () => {
        const deliver = vi.fn().mockResolvedValue(true);
        const queue = new DeepLinkQueue(deliver);

        queue.captureUrl('devkeepr://oauth/callback?code=early');
        queue.captureArguments(['electron', '--flag', 'devkeepr://projects?project=/tmp/app']);
        queue.configure('devkeepr', ['electron', 'devkeepr://services/connected?driver=github']);

        expect(deliver).not.toHaveBeenCalled();

        await queue.markReady();

        expect(deliver).toHaveBeenNthCalledWith(1, 'devkeepr://oauth/callback?code=early');
        expect(deliver).toHaveBeenNthCalledWith(2, 'devkeepr://projects?project=/tmp/app');
        expect(deliver).toHaveBeenNthCalledWith(3, 'devkeepr://services/connected?driver=github');
    });

    it('ignores other protocols and only delivers duplicate links once', async () => {
        const deliver = vi.fn().mockResolvedValue(true);
        const queue = new DeepLinkQueue(deliver);

        queue.configure('devkeepr');
        queue.captureUrl('https://example.com');
        queue.captureUrl('devkeepr://projects?project=/tmp/app');
        queue.captureUrl('devkeepr://projects?project=/tmp/app');

        await queue.markReady();

        expect(deliver).toHaveBeenCalledOnce();
    });

    it('retains a link when delivery fails and retries it later', async () => {
        const deliver = vi.fn().mockResolvedValueOnce(false).mockResolvedValueOnce(true);
        const queue = new DeepLinkQueue(deliver);

        queue.configure('devkeepr', ['devkeepr://projects?project=/tmp/app']);
        await queue.markReady();
        await queue.markReady();

        expect(deliver).toHaveBeenCalledTimes(2);
    });
});
