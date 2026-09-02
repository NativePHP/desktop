import { BrowserWindow, nativeTheme, safeStorage, systemPreferences } from 'electron';
import express from 'express';
import { readFileSync } from 'node:fs';
import { parsePdfPageSizePoints, buildNativePrintOptions } from '../pdfPageSize.js';

const router = express.Router();

router.get('/can-prompt-touch-id', (req, res) => {
    res.json({
        result: systemPreferences.canPromptTouchID(),
    });
});

router.post('/prompt-touch-id', async (req, res) => {
    try {
        await systemPreferences.promptTouchID(req.body.reason);

        res.sendStatus(200);
    } catch (e) {
        res.status(400).json({
            error: e.message,
        });
    }
});

router.get('/can-encrypt', async (req, res) => {
    res.json({
        result: await safeStorage.isEncryptionAvailable(),
    });
});

router.post('/encrypt', async (req, res) => {
    try {
        res.json({
            result: await safeStorage.encryptString(req.body.string).toString('base64'),
        });
    } catch (e) {
        res.status(400).json({
            error: e.message,
        });
    }
});

router.post('/decrypt', async (req, res) => {
    try {
        res.json({
            result: await safeStorage.decryptString(Buffer.from(req.body.string, 'base64')),
        });
    } catch (e) {
        res.status(400).json({
            error: e.message,
        });
    }
});

router.get('/printers', async (req, res) => {
    const printers = await BrowserWindow.getAllWindows()[0].webContents.getPrintersAsync();

    res.json({
        printers,
    });
});

router.post('/print', async (req, res) => {
    const { printer, html, settings } = req.body;

    let printWindow = new BrowserWindow({
        show: false,
    });

    const defaultSettings = {
        silent: true,
        deviceName: printer,
    };

    const mergedSettings = {
        ...defaultSettings,
        ...(settings && typeof settings === 'object' ? settings : {}),
    };

    printWindow.webContents.on('did-finish-load', () => {
        printWindow.webContents.print(mergedSettings, (success, errorType) => {
            if (success) {
                console.log('Print job completed successfully.');
                res.sendStatus(200);
            } else {
                console.error('Print job failed:', errorType);
                res.sendStatus(500);
            }
            if (printWindow) {
                printWindow.close(); // Close the window and the process
                printWindow = null; // Free memory
            }
        });
    });

    await printWindow.loadURL(`data:text/html;charset=UTF-8,${html}`);
});

router.post('/print-file', async (req, res) => {
    const { path: filePath, printer, settings } = req.body;

    let pagePoints = null;
    try {
        pagePoints = parsePdfPageSizePoints(readFileSync(filePath));
    } catch (e) {
        console.error('Native print: failed to read PDF:', e.message);
        res.status(500).json({ error: e.message });
        return;
    }

    if (!pagePoints) {
        const message = 'could not determine PDF page size (no MediaBox)';
        console.error('Native print:', message, filePath);
        res.status(500).json({ error: message });
        return;
    }

    // Wait this long after load for PDFium to paint before printing
    const renderDelay = 1500;

    const options = {
        ...buildNativePrintOptions(printer, pagePoints),
        ...(settings && typeof settings === 'object' ? settings : {}),
    };

    let printWindow = new BrowserWindow({
        show: false,
        backgroundColor: '#ffffff',
        webPreferences: {
            plugins: true,
        },
    });

    let responded = false;
    const respond = (statusCode, error?) => {
        if (responded) {
            return;
        }
        responded = true;
        if (error) {
            res.status(statusCode).json({ error });
        } else {
            res.sendStatus(statusCode);
        }
        if (printWindow) {
            printWindow.close();
            printWindow = null;
        }
    };

    printWindow.webContents.once('did-finish-load', () => {
        setTimeout(() => {
            if (!printWindow) {
                return;
            }
            printWindow.webContents.print(options, (success, errorType) => {
                if (success) {
                    respond(200);
                } else {
                    console.error('Native print job failed:', errorType);
                    respond(500, errorType);
                }
            });
        }, renderDelay);
    });

    printWindow.webContents.on('did-fail-load', (_event, _errorCode, errorDescription, _validatedURL, isMainFrame) => {
        if (!isMainFrame) {
            return;
        }
        console.error('Native print: failed to load PDF:', errorDescription);
        respond(500, errorDescription);
    });

    printWindow.loadFile(filePath).catch((e) => {
        respond(500, e.message);
    });
});

router.post('/print-to-pdf', async (req, res) => {
    const { html, settings } = req.body;

    const printWindow = new BrowserWindow({
        show: false,
    });

    printWindow.webContents.on('did-finish-load', () => {
        printWindow.webContents
            .printToPDF(settings ?? {})
            .then((data) => {
                printWindow.close();
                res.json({
                    result: data.toString('base64'),
                });
            })
            .catch((e) => {
                printWindow.close();

                res.status(400).json({
                    error: e.message,
                });
            });
    });

    await printWindow.loadURL(`data:text/html;base64;charset=UTF-8,${html}`);
});

router.get('/theme', (req, res) => {
    res.json({
        result: nativeTheme.themeSource,
    });
});

router.post('/theme', (req, res) => {
    const { theme } = req.body;

    nativeTheme.themeSource = theme;

    res.json({
        result: theme,
    });
});

export default router;
