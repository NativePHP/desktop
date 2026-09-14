const mediaBox = /\/MediaBox\s*\[\s*(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s*\]/;
export function parsePdfPageSizePoints(buffer) {
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
function pointsToMicrons(points) {
    return Math.round((points / 72) * 25400);
}
function toPrintPageSize({ widthPt, heightPt }) {
    return {
        pageSize: {
            width: pointsToMicrons(widthPt),
            height: pointsToMicrons(heightPt),
        },
        landscape: widthPt > heightPt,
    };
}
export function buildNativePrintOptions(deviceName, page) {
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
