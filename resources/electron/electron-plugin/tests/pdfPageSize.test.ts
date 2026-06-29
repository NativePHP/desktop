import { describe, it, expect } from 'vitest';
import { parsePdfPageSizePoints, buildNativePrintOptions } from '../src/server/pdfPageSize';

// Minimal PDF text containing a 4x6 inch MediaBox (288 x 432 points).
const pdf4x6 = Buffer.from(
    '%PDF-1.4\n1 0 obj<</Type/Page/MediaBox [0 0 288 432]>>endobj\n%%EOF',
    'latin1',
);

// 6x4 inch sample (432 x 288 points), width > height → landscape.
const pdf6x4 = Buffer.from(
    '%PDF-1.4\n1 0 obj<</Type/Page/MediaBox[0 0 432 288]>>endobj\n%%EOF',
    'latin1',
);

describe('pdfPageSize', () => {
    it('parses the first MediaBox into points', () => {
        expect(parsePdfPageSizePoints(pdf4x6)).toEqual({ widthPt: 288, heightPt: 432 });
    });

    it('handles MediaBox with no spaces after the key and decimals', () => {
        const pdf = Buffer.from('/MediaBox[0 0 283.5 425.25]', 'latin1');
        expect(parsePdfPageSizePoints(pdf)).toEqual({ widthPt: 283.5, heightPt: 425.25 });
    });

    it('returns null when no MediaBox is present', () => {
        expect(parsePdfPageSizePoints(Buffer.from('not a pdf', 'latin1'))).toBeNull();
    });

    it('returns null for a degenerate (zero-area) MediaBox', () => {
        expect(parsePdfPageSizePoints(Buffer.from('/MediaBox [0 0 0 432]', 'latin1'))).toBeNull();
    });

    it('derives size from a non-zero origin MediaBox', () => {
        // Origin offset must not change the page dimensions: 298-10 x 442-10.
        expect(parsePdfPageSizePoints(Buffer.from('/MediaBox [10 10 298 442]', 'latin1')))
            .toEqual({ widthPt: 288, heightPt: 432 });
    });

    it('handles negative coordinates via absolute size', () => {
        expect(parsePdfPageSizePoints(Buffer.from('/MediaBox [-288 -432 0 0]', 'latin1')))
            .toEqual({ widthPt: 288, heightPt: 432 });
    });

    it('uses the first MediaBox when several are present', () => {
        const pdf = Buffer.from('/MediaBox [0 0 288 432] ... /MediaBox [0 0 595 842]', 'latin1');
        expect(parsePdfPageSizePoints(pdf)).toEqual({ widthPt: 288, heightPt: 432 });
    });

    it('builds portrait print options with microns page size and zeroed margins', () => {
        expect(buildNativePrintOptions('Test Printer', { widthPt: 288, heightPt: 432 })).toEqual({
            silent: true,
            deviceName: 'Test Printer',
            color: false,
            landscape: false,
            pageSize: { width: 101600, height: 152400 }, // 4in x 6in in microns
            margins: { marginType: 'custom', top: 0, bottom: 0, left: 0, right: 0 },
        });
    });

    it('builds landscape print options for a wider-than-tall page (6x4)', () => {
        const points = parsePdfPageSizePoints(pdf6x4);
        expect(points).not.toBeNull();
        expect(buildNativePrintOptions('Test Printer', points!)).toEqual({
            silent: true,
            deviceName: 'Test Printer',
            color: false,
            landscape: true,
            pageSize: { width: 152400, height: 101600 }, // 6in x 4in in microns
            margins: { marginType: 'custom', top: 0, bottom: 0, left: 0, right: 0 },
        });
    });

    it('parses a MediaBox whose array spans multiple lines', () => {
        const pdf = Buffer.from('/MediaBox [0 0\n288 432]', 'latin1');
        expect(parsePdfPageSizePoints(pdf)).toEqual({ widthPt: 288, heightPt: 432 });
    });
});
