import { clipboard, ClipboardItem, nativeImage } from 'electron';
import * as express from 'express';
const router = express.Router();

// Electron 44 rebuilt the clipboard module around the W3C Clipboard API: reads
// and writes are async, HTML and images go through read()/write() as MIME-typed
// ClipboardItems, and the Linux selection clipboard moved to clipboard.selection
// instead of a `type` argument.
function target(type: unknown): Electron.Clipboard {
    if (type === 'selection' && clipboard.selection) {
        return clipboard.selection;
    }

    return clipboard;
}

async function readType(board: Electron.Clipboard, matches: (mimeType: string) => boolean): Promise<Blob | null> {
    for (const item of await board.read()) {
        const mimeType = item.types.find(matches);

        if (mimeType) {
            return (await item.getType(mimeType)) as Blob;
        }
    }

    return null;
}

router.get('/text', async (req, res) => {
    res.json({
        text: await target(req.query.type).readText(),
    });
});

router.post('/text', async (req, res) => {
    const { text } = req.body;

    await target(req.query.type).writeText(text);

    res.json({
        text,
    });
});

router.get('/html', async (req, res) => {
    const blob = await readType(target(req.query.type), (mimeType) => mimeType === 'text/html');

    res.json({
        html: blob ? await blob.text() : '',
    });
});

router.post('/html', async (req, res) => {
    const { html } = req.body;

    await target(req.query.type).write([new ClipboardItem({ 'text/html': html })]);

    res.json({
        html,
    });
});

router.get('/image', async (req, res) => {
    const board = target(req.query.type);

    const blob =
        (await readType(board, (mimeType) => mimeType === 'image/png')) ??
        (await readType(board, (mimeType) => mimeType.startsWith('image/')));

    const image = blob ? nativeImage.createFromBuffer(Buffer.from(await blob.arrayBuffer())) : null;

    res.json({
        image: !image || image.isEmpty() ? null : image.toDataURL(),
    });
});

router.post('/image', async (req, res) => {
    const { image } = req.body;

    let png: Buffer;

    try {
        const decoded = nativeImage.createFromDataURL(image);

        if (decoded.isEmpty()) {
            throw new Error('The image could not be decoded');
        }

        png = decoded.toPNG();
    } catch (e) {
        res.status(400).json({
            error: e.message,
        });
        return;
    }

    await target(req.query.type).write([
        new ClipboardItem({ 'image/png': new Blob([new Uint8Array(png)], { type: 'image/png' }) }),
    ]);

    res.sendStatus(200);
});

router.delete('/', async (req, res) => {
    await target(req.query.type).clear();

    res.sendStatus(200);
});

export default router;
