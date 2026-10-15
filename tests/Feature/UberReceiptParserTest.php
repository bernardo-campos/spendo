<?php

use App\Services\UberReceiptParser;

test('it extracts the Uber total and trip addresses from a quoted printable HTML email', function () {
    $html = <<<'HTML'
    <html><head><meta charset="utf-8"></head><body>
        <td data-testid="fare_breakdown_amount">ARS&nbsp;2,590.00</td>
        <td data-testid="total_fare_amount">ARS&nbsp;2,348.00</td>
        <td data-testid="address_point_0_address">Ushuaia, Santiago del Estero</td>
        <td data-testid="address_point_1_address">Gaucho Rivero, Santiago del Estero</td>
        <a href="https://click.uber.com/f/a/receipt-one">Descargar&nbsp;PDF</a>
    </body></html>
    HTML;
    $message = "From: Uber <noreply@uber.com>\r\nDate: Sun, 11 Oct 2026 23:12:06 +0000\r\nContent-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".quoted_printable_encode($html);

    expect(app(UberReceiptParser::class)->parse($message))->toBe([
        'currency' => 'ARS',
        'amount' => '2348.00',
        'origin_address' => 'Ushuaia, Santiago del Estero',
        'destination_address' => 'Gaucho Rivero, Santiago del Estero',
        'source_key' => hash('sha256', 'https://click.uber.com/f/a/receipt-one'),
        'purchase_date' => '2026-10-11',
    ]);
});

test('it extracts the total and route after Gmail forwards the receipt as multipart', function () {
    $html = <<<'HTML'
    <html><body>
        <td class="m_123total-fare-amount">ARS&nbsp;2,348.00</td>
        <td class="m_123address-point-desc">Ushuaia, Santiago del Estero</td>
        <td class="m_123address-point-desc"><a href="https://example.test/map">Gaucho Rivero</a>, Santiago del Estero</td>
        <td class="m_123address-point-desc">Ushuaia, Santiago del Estero</td>
        <td class="m_123address-point-desc">Gaucho Rivero, Santiago del Estero</td>
        <td><a href="https://click.uber.com/f/a/receipt-one"></a>Descargar&nbsp;PDF</td>
    </body></html>
    HTML;
    $message = "From: Usuario <user@example.com>\r\n"
        ."Date: Thu, 15 Oct 2026 02:29:14 -0300\r\n"
        ."Content-Type: multipart/alternative; boundary=\"receipt-boundary\"\r\n\r\n"
        ."--receipt-boundary\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nTotal ARS 2,590.00\r\n"
        ."--receipt-boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        .quoted_printable_encode($html)."\r\n--receipt-boundary--\r\n";

    expect(app(UberReceiptParser::class)->parse($message))->toBe([
        'currency' => 'ARS',
        'amount' => '2348.00',
        'origin_address' => 'Ushuaia, Santiago del Estero',
        'destination_address' => 'Gaucho Rivero, Santiago del Estero',
        'source_key' => hash('sha256', 'https://click.uber.com/f/a/receipt-one'),
        'purchase_date' => '2026-10-15',
    ]);
});

test('it recognizes Gmail class prefixes with a negative message identifier', function () {
    $html = <<<'HTML'
    <html><body>
        <td class="m_-7930254747577144633total-fare-amount">ARS&nbsp;2,348.00</td>
        <td class="m_-7930254747577144633address-point-desc">Ushuaia, Santiago del Estero</td>
        <td class="m_-7930254747577144633address-point-desc">Gaucho Rivero, Santiago del Estero</td>
        <a href="https://click.uber.com/f/a/receipt-negative-prefix">Descargar&nbsp;PDF</a>
    </body></html>
    HTML;
    $message = "Date: Thu, 15 Oct 2026 03:42:37 -0300\r\n"
        ."Content-Type: text/html; charset=UTF-8\r\n\r\n{$html}";

    expect(app(UberReceiptParser::class)->parse($message))->toBe([
        'currency' => 'ARS',
        'amount' => '2348.00',
        'origin_address' => 'Ushuaia, Santiago del Estero',
        'destination_address' => 'Gaucho Rivero, Santiago del Estero',
        'source_key' => hash('sha256', 'https://click.uber.com/f/a/receipt-negative-prefix'),
        'purchase_date' => '2026-10-15',
    ]);
});

test('it leaves unsupported messages unparsed', function () {
    $parser = app(UberReceiptParser::class);

    expect($parser->parse("Content-Type: text/plain\r\n\r\nARS 2,348.00"))->toBeNull();
    expect($parser->parse("Content-Type: text/html\r\n\r\n<td data-testid=\"total_fare_amount\">ARS 2.348,00</td>"))->toBeNull();
    expect($parser->parse("Content-Type: text/html\r\n\r\n"))->toBeNull();
});
