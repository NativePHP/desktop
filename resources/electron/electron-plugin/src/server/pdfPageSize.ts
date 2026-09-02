interface PagePoints {
    widthPt: number;
    heightPt: number;
}

interface PrintPageSize {
    pageSize: { width: number; height: number };
    landscape: boolean;
}

interface NativePrintOptions {
    silent: true;
    deviceName: string;
    color: false;
    landscape: boolean;
    pageSize: { width: number; height: number };
    margins: { marginType: 'custom'; top: 0; bottom: 0; left: 0; right: 0 };
}

const mediaBox = /\/MediaBox\s*\[\s*(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s*\]/;

export function parsePdfPageSizePoints(buffer: Buffer): PagePoints | null {
    // latin1 keeps byte offsets stable for the ASCII PDF structure tokens.
    const match = buffer.toString('latin1').match(mediaBox);
    if (!match) {
        return null;
    }

    const x0 = parseFloat(match[1]);
    const y0 = parseFloat(match[2]);
    const x1 = parseFloat(match[3]);
    const y1 = parseFloat(match[4]);

    const widthPt = Math.abs(x1 - x0);
    const heightPt = Math.abs(y1 - y0);

    if (!(widthPt > 0) || !(heightPt > 0)) {
        return null;
    }

    return { widthPt, heightPt };
}

// PDF points (1/72") to microns (Electron pageSize unit, 1/25400").
function pointsToMicrons(points: number): number {
    return Math.round((points / 72) * 25400);
}

function toPrintPageSize({ widthPt, heightPt }: PagePoints): PrintPageSize {
    return {
        pageSize: {
            width: pointsToMicrons(widthPt),
            height: pointsToMicrons(heightPt),
        },
        landscape: widthPt > heightPt,
    };
}

export function buildNativePrintOptions(deviceName: string, page: PagePoints): NativePrintOptions {
    const { pageSize, landscape } = toPrintPageSize(page);

    return {
        silent: true,
        deviceName,
        color: false,
        landscape,
        pageSize,
        margins: { marginType: 'custom', top: 0, bottom: 0, left: 0, right: 0 },
    };
}
