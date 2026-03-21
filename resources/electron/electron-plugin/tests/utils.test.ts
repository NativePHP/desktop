import axios from 'axios';
import { describe, expect, it, vi } from 'vitest';
import state from '../src/server/state';
import { notifyLaravel } from '../src/server/utils';

vi.mock('axios');
vi.mock('electron-store');

describe('Utils test', () => {
    it('notifies laravel', async () => {
        state.phpPort = 8000;
        state.randomSecret = 'i-am-secret';

        await notifyLaravel('endpoint', { payload: 'payload' });

        expect(axios.post).toHaveBeenCalledWith(
            `http://127.0.0.1:8000/_native/api/endpoint`,
            { payload: 'payload' },
            {
                headers: {
                    'X-NativePHP-Secret': 'i-am-secret',
                },
            },
        );
    });
});
