import { createHmac, randomBytes } from 'crypto';
import { existsSync, readFileSync } from 'fs';
import { join } from 'path';
const PURPOSE = 'nativephp.native-event';
export const NONCE_HEADER = 'X-Native-Event-Nonce';
export const TIMESTAMP_HEADER = 'X-Native-Event-Timestamp';
export const SIGNATURE_HEADER = 'X-Native-Event-Signature';
let loaded = false;
let signingKey = null;
export function configureSigning(appPath) {
    if (!loaded) {
        signingKey = loadRawAppKey(appPath);
        loaded = true;
    }
    return signingKey !== null;
}
export function isSigningEnabled() {
    return signingKey !== null;
}
export function signEventBody(body) {
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
function loadRawAppKey(appPath) {
    let appKey = process.env.APP_KEY;
    try {
        const envPath = join(appPath, '.env');
        if (existsSync(envPath)) {
            const match = readFileSync(envPath, 'utf8').match(/^\s*APP_KEY\s*=\s*(.*)\s*$/m);
            if (match) {
                appKey = match[1].trim().replace(/^["']|["']$/g, '');
            }
        }
    }
    catch (_a) {
    }
    if (!appKey) {
        return null;
    }
    const key = appKey.startsWith('base64:')
        ? Buffer.from(appKey.slice('base64:'.length), 'base64')
        : Buffer.from(appKey, 'utf8');
    return key.length > 0 ? key : null;
}
