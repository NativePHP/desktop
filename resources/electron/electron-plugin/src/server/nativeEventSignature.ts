import { createHmac, randomBytes } from 'crypto';
import { existsSync, readFileSync } from 'fs';
import { join } from 'path';

/**
 * Signs the native events the main process POSTs to /_native/api/events so PHP
 * can tell them apart from events forged by script in the web view.
 *
 * The renderer shares the app's origin, so a same-origin fetch from a malicious
 * page carries the _php_native cookie and clears the shared-secret gate. It
 * cannot, though, read the app key: that lives in the app's .env, server-side,
 * and never reaches the page. So the main process signs each event with an
 * app-key HMAC over a per-event nonce, a timestamp and the exact body, and PHP
 * (Native\...\Support\NativeEventSignature) verifies it with the same key.
 *
 * Keep the message layout here byte-identical to the PHP verifier.
 */

const PURPOSE = 'nativephp.native-event';

export const NONCE_HEADER = 'X-Native-Event-Nonce';
export const TIMESTAMP_HEADER = 'X-Native-Event-Timestamp';
export const SIGNATURE_HEADER = 'X-Native-Event-Signature';

let loaded = false;
let signingKey: Buffer | null = null;

/**
 * Load the raw app key once and remember whether signing is possible. Called at
 * server start with the Laravel app path; returns true when a key was found, so
 * the caller can set NATIVEPHP_EVENTS_SIGNED and have PHP enforce. When no key
 * is available (an exotic build, or APP_KEY missing) signing stays off and the
 * app keeps working unsigned, exactly as before this change.
 */
export function configureSigning(appPath: string): boolean {
    if (!loaded) {
        signingKey = loadRawAppKey(appPath);
        loaded = true;
    }

    return signingKey !== null;
}

export function isSigningEnabled(): boolean {
    return signingKey !== null;
}

interface SignedHeaders {
    [NONCE_HEADER]: string;
    [TIMESTAMP_HEADER]: string;
    [SIGNATURE_HEADER]: string;
}

/**
 * Sign the exact body bytes that will be POSTed. Returns the headers to attach,
 * or null when signing is unavailable (the request then goes unsigned and PHP,
 * with enforcement off, accepts it as before).
 */
export function signEventBody(body: string): SignedHeaders | null {
    if (signingKey === null) {
        return null;
    }

    const nonce = randomBytes(16).toString('hex');
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const message = `${PURPOSE}\n${nonce}\n${timestamp}\n${body}`;
    const signature = createHmac('sha256', signingKey).update(message, 'utf8').digest('hex');

    return {
        [NONCE_HEADER]: nonce,
        [TIMESTAMP_HEADER]: timestamp,
        [SIGNATURE_HEADER]: signature,
    };
}

/**
 * The raw key the PHP encrypter holds: base64-decoded when APP_KEY is
 * `base64:...`, otherwise the literal bytes. Mirrors Laravel's own handling so
 * the two sides derive the same key.
 */
function loadRawAppKey(appPath: string): Buffer | null {
    let appKey: string | undefined = process.env.APP_KEY;

    try {
        const envPath = join(appPath, '.env');

        if (existsSync(envPath)) {
            const match = readFileSync(envPath, 'utf8').match(/^\s*APP_KEY\s*=\s*(.*)\s*$/m);

            if (match) {
                appKey = match[1].trim().replace(/^["']|["']$/g, '');
            }
        }
    } catch {
        // Fall back to process.env.APP_KEY (or none) below.
    }

    if (!appKey) {
        return null;
    }

    const key = appKey.startsWith('base64:')
        ? Buffer.from(appKey.slice('base64:'.length), 'base64')
        : Buffer.from(appKey, 'utf8');

    return key.length > 0 ? key : null;
}
