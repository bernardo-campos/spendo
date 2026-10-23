<?php

use App\Services\DidiReceiptParser;

function didiReceiptHtml(string $url = 'https://mpub-proxy.didiglobal.com/hera/click?d=receipt-one'): string
{
    return '<html><body><p>dom, 10 may, 2026</p><table><tr><td>Total</td><td>$3,320.00</td></tr></table>'
        .'<div><strong><span>07:20 pm</span></strong></div><div>La Campiña Fiambres Y Lacteos</div>'
        .'<div><strong><span>07:38 pm</span></strong></div><div>Gaucho Rivero 352</div>'
        .'<td><a href="'.$url.'"></a>Duda sobre la tarifa</td></body></html>';
}

test('it extracts a DiDi trip from base64 and forwarded quoted printable HTML', function () {
    $html = didiReceiptHtml();
    $original = "From: DiDi <didi@ar.didiglobal.com>\r\nDate: Sun, 10 May 2026 22:39:09 +0000\r\n"
        ."Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        .base64_encode($html);
    $forwarded = "From: Usuario <user@example.com>\r\nDate: Thu, 15 Oct 2026 14:28:18 -0300\r\n"
        ."Content-Type: multipart/alternative; boundary=\"didi-boundary\"\r\n\r\n"
        ."--didi-boundary\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nTotal $3,320.00\r\n"
        ."--didi-boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        .quoted_printable_encode($html)."\r\n--didi-boundary--\r\n";
    $parser = app(DidiReceiptParser::class);
    $expected = [
        'currency' => 'ARS',
        'amount' => '3320.00',
        'origin_address' => 'La Campiña Fiambres Y Lacteos',
        'destination_address' => 'Gaucho Rivero 352',
        'source_key' => hash('sha256', implode("\n", [
            'didi',
            'https://mpub-proxy.didiglobal.com/hera/click?d=receipt-one',
            'dom, 10 may, 2026',
            '07:20 pm',
            'La Campiña Fiambres Y Lacteos',
            '07:38 pm',
            'Gaucho Rivero 352',
            '3320.00',
        ])),
    ];

    expect($parser->parse($original))->toBe([...$expected, 'purchase_date' => '2026-05-10']);
    expect($parser->parse($forwarded))->toBe([...$expected, 'purchase_date' => '2026-10-15']);
    $anotherTrip = str_replace(base64_encode($html), base64_encode(str_replace('dom, 10 may, 2026', 'lun, 11 may, 2026', $html)), $original);
    expect($parser->parse($anotherTrip)['source_key'])
        ->not->toBe($expected['source_key']);
});

test('it parses an Express receipt with local currency formatting and a timezone comment', function (string $total, string $amount) {
    $url = 'https://mpub-proxy.didiglobal.com/hera/click?d=express-receipt';
    $html = '<html><body><p>jue, 22 oct, 2026</p><table><tr><td>Total</td><td>'.$total.'</td></tr></table>'
        .'<div><strong><span>07:13 am</span></strong></div><div>Calle Uno 100</div>'
        .'<div><strong><span>07:26 am</span></strong></div><div>Calle Dos 200</div>'
        .'<td><a href="'.$url.'"></a>Duda sobre la tarifa</td></body></html>';
    $message = "Date: Thu, 22 Oct 2026 10:26:36 +0000 (UTC)\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}";

    expect(app(DidiReceiptParser::class)->parse($message))->toBe([
        'currency' => 'ARS',
        'amount' => $amount,
        'origin_address' => 'Calle Uno 100',
        'destination_address' => 'Calle Dos 200',
        'source_key' => hash('sha256', implode("\n", [
            'didi', $url, 'jue, 22 oct, 2026', '07:13 am', 'Calle Uno 100', '07:26 am', 'Calle Dos 200', $amount,
        ])),
        'purchase_date' => '2026-10-22',
    ]);
})->with([
    'whole pesos with a thousands separator' => ['$2.200', '2200.00'],
    'pesos and cents with local separators' => ['$2.200,50', '2200.50'],
]);

test('it does not use an unrelated link as a DiDi trip identifier', function () {
    $parser = app(DidiReceiptParser::class);
    $html = didiReceiptHtml('https://mpub-proxy.didiglobal.com.evil.test/hera/click?d=receipt-one');
    $message = "Date: Sun, 10 May 2026 22:39:09 +0000\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}";

    expect($parser->parse($message)['source_key'])->toBeNull();
    expect($parser->parse("Content-Type: text/html\r\n\r\n<table><tr><td>Total</td><td>$3,320.00</td></tr></table>"))->toBeNull();
});
