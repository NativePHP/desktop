<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Desktop\DataObjects\Printer;
use Native\Desktop\Facades\System;

beforeEach(function () {
    $this->pdfPath = sys_get_temp_dir().'/nativephp-print-test.pdf';
    file_put_contents($this->pdfPath, '%PDF-1.4 test');
});

afterEach(function () {
    @unlink($this->pdfPath);
});

it('sends a print-file request with path, printer and settings', function () {
    Http::fake();

    $printer = new Printer('Test Printer', 'Test Printer', 'Label printer', []);

    $result = System::printFile($this->pdfPath, $printer, ['copies' => 2]);

    expect($result)->toBeTrue();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://localhost:4000/api/system/print-file'
            && $request['path'] === $this->pdfPath
            && $request['printer'] === 'Test Printer'
            && $request['settings'] === ['copies' => 2];
    });
});

it('defaults to an empty printer name and empty settings', function () {
    Http::fake();

    System::printFile($this->pdfPath);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://localhost:4000/api/system/print-file'
            && $request['path'] === $this->pdfPath
            && $request['printer'] === ''
            && $request['settings'] === [];
    });
});

it('accepts a printer name string directly', function () {
    Http::fake();

    System::printFile($this->pdfPath, 'Test Printer');

    Http::assertSent(function (Request $request) {
        return $request['printer'] === 'Test Printer';
    });
});

it('returns false when the print request fails', function () {
    Http::fake([
        '*/system/print-file' => Http::response(['error' => 'native print timed out waiting for the PDF to render'], 500),
    ]);

    expect(System::printFile($this->pdfPath))->toBeFalse();
});
