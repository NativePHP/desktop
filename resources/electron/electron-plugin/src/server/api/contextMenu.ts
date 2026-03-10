import contextMenu from 'electron-context-menu';
import express from 'express';
import { compileMenu } from './helper/index.js';

const router = express.Router();

let contextMenuDisposable = null;

router.delete('/', (req, res) => {
    res.sendStatus(200);

    if (contextMenuDisposable) {
        contextMenuDisposable();
        contextMenuDisposable = null;
    }
});

router.post('/', (req, res) => {
    res.sendStatus(200);

    if (contextMenuDisposable) {
        contextMenuDisposable();
        contextMenuDisposable = null;
    }

    contextMenuDisposable = contextMenu({
        showLookUpSelection: false,
        showSearchWithGoogle: false,
        showInspectElement: false,
        prepend: () => {
            return req.body.entries.map(compileMenu);
        },
    });
});

export default router;
