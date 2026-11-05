var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
import { clipboard, ClipboardItem, nativeImage } from 'electron';
import * as express from 'express';
const router = express.Router();
function target(type) {
    if (type === 'selection' && clipboard.selection) {
        return clipboard.selection;
    }
    return clipboard;
}
function readType(board, matches) {
    return __awaiter(this, void 0, void 0, function* () {
        for (const item of yield board.read()) {
            const mimeType = item.types.find(matches);
            if (mimeType) {
                return (yield item.getType(mimeType));
            }
        }
        return null;
    });
}
router.get('/text', (req, res) => __awaiter(void 0, void 0, void 0, function* () {
    res.json({
        text: yield target(req.query.type).readText(),
    });
}));
router.post('/text', (req, res) => __awaiter(void 0, void 0, void 0, function* () {
    const { text } = req.body;
    yield target(req.query.type).writeText(text);
    res.json({
        text,
    });
}));
router.get('/html', (req, res) => __awaiter(void 0, void 0, void 0, function* () {
    const blob = yield readType(target(req.query.type), (mimeType) => mimeType === 'text/html');
    res.json({
        html: blob ? yield blob.text() : '',
    });
}));
router.post('/html', (req, res) => __awaiter(void 0, void 0, void 0, function* () {
    const { html } = req.body;
    yield target(req.query.type).write([new ClipboardItem({ 'text/html': html })]);
    res.json({
        html,
    });
}));
router.get('/image', (req, res) => __awaiter(void 0, void 0, void 0, function* () {
    var _a;
    const board = target(req.query.type);
    const blob = (_a = (yield readType(board, (mimeType) => mimeType === 'image/png'))) !== null && _a !== void 0 ? _a : (yield readType(board, (mimeType) => mimeType.startsWith('image/')));
    const image = blob ? nativeImage.createFromBuffer(Buffer.from(yield blob.arrayBuffer())) : null;
    res.json({
        image: !image || image.isEmpty() ? null : image.toDataURL(),
    });
}));
router.post('/image', (req, res) => __awaiter(void 0, void 0, void 0, function* () {
    const { image } = req.body;
    let png;
    try {
        const decoded = nativeImage.createFromDataURL(image);
        if (decoded.isEmpty()) {
            throw new Error('The image could not be decoded');
        }
        png = decoded.toPNG();
    }
    catch (e) {
        res.status(400).json({
            error: e.message,
        });
        return;
    }
    yield target(req.query.type).write([
        new ClipboardItem({ 'image/png': new Blob([new Uint8Array(png)], { type: 'image/png' }) }),
    ]);
    res.sendStatus(200);
}));
router.delete('/', (req, res) => __awaiter(void 0, void 0, void 0, function* () {
    yield target(req.query.type).clear();
    res.sendStatus(200);
}));
export default router;
