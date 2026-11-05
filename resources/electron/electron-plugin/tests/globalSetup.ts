import { createRequire } from 'node:module';

// Since Electron 42 the binary is no longer downloaded on install; the first
// require('electron') fetches it. Do that once here, before vitest starts its
// workers, otherwise parallel test files race to download it and all but one
// fail with "Electron failed to install correctly".
export default function () {
    createRequire(import.meta.url)('electron');
}
