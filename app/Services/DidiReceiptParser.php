<?php

namespace App\Services;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;

class DidiReceiptParser
{
    public function __construct(private ReceiptEmailParts $emailParts) {}

    /**
     * @return array{currency: string, amount: string, origin_address: string, destination_address: string, purchase_date: ?string, source_key: ?string}|null
     */
    public function parse(string $message): ?array
    {
        $parts = preg_split('/\r?\n\r?\n/', $message, 2);
        $date = count($parts) === 2 ? $this->emailParts->headerValue($parts[0], 'Date') : '';
        $date = preg_replace('/\s+\([^()]*\)$/', '', $date) ?? $date;
        $purchaseDate = $date !== '' ? DateTimeImmutable::createFromFormat(DATE_RFC2822, $date) : false;

        foreach ($this->emailParts->htmlParts($message) as $html) {
            $parsed = $this->parseHtml($html);

            if ($parsed !== null) {
                return [
                    ...$parsed,
                    'purchase_date' => $purchaseDate === false ? null : $purchaseDate->format('Y-m-d'),
                ];
            }
        }

        return null;
    }

    /**
     * @return array{currency: string, amount: string, origin_address: string, destination_address: string, source_key: ?string}|null
     */
    private function parseHtml(string $html): ?array
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;

        try {
            if (! $document->loadHTML('<?xml encoding="UTF-8"?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return null;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $totalNode = $xpath->query('//tr[td[1][normalize-space(.)="Total"]]/td[2]')->item(0);
        $total = $totalNode === null ? '' : $this->normalizeText($totalNode->textContent);

        if (preg_match('/^\$\s*((?:\d{1,3}(?:,\d{3})+|\d+)\.\d{2})$/', $total, $matches)) {
            $amount = str_replace(',', '', $matches[1]);
        } elseif (preg_match('/^\$\s*(\d{1,3}(?:\.\d{3})+(?:,\d{2})?|\d+(?:,\d{2})?)$/', $total, $matches)) {
            $amount = str_replace(',', '.', str_replace('.', '', $matches[1]));
            $amount = str_contains($amount, '.') ? $amount : $amount.'.00';
        } else {
            return null;
        }

        $addresses = [];
        $times = [];

        foreach ($xpath->query('//div[strong/span]') as $timeNode) {
            $time = $this->normalizeText($timeNode->textContent);

            if (! preg_match('/^\d{1,2}:\d{2}\s*(?:am|pm)$/i', $time)) {
                continue;
            }

            $addressNode = $xpath->query('following-sibling::div[1]', $timeNode)->item(0);
            $address = $addressNode === null ? '' : $this->normalizeText($addressNode->textContent);

            if ($address !== '') {
                $addresses[] = $address;
                $times[] = $time;
            }
        }

        if (count($addresses) < 2) {
            return null;
        }

        preg_match('/\b(?:lun|mar|mié|mie|jue|vie|sáb|sab|dom),\s*\d{1,2}\s+\p{L}{3,}\s*,\s*\d{4}/iu', $this->normalizeText($document->textContent), $rideDate);
        $sourceKey = $this->sourceKey($xpath, $amount, $rideDate[0] ?? '', $times, $addresses);

        return [
            'currency' => 'ARS',
            'amount' => $amount,
            'origin_address' => $addresses[0],
            'destination_address' => $addresses[1],
            'source_key' => $sourceKey,
        ];
    }

    /**
     * @param  array<string>  $times
     * @param  array<string>  $addresses
     */
    private function sourceKey(DOMXPath $xpath, string $amount, string $rideDate, array $times, array $addresses): ?string
    {
        foreach ($xpath->query('//a[@href]') as $node) {
            $label = $this->normalizeText($node->textContent);

            if ($label === '' && $node->parentNode !== null) {
                $label = $this->normalizeText($node->parentNode->textContent);
            }

            if ($label !== 'Duda sobre la tarifa') {
                continue;
            }

            $url = $node->getAttribute('href');
            $parts = parse_url($url);

            if (! is_array($parts)
                || ($parts['scheme'] ?? null) !== 'https'
                || ($parts['host'] ?? null) !== 'mpub-proxy.didiglobal.com'
                || ($parts['path'] ?? null) !== '/hera/click') {
                continue;
            }

            return hash('sha256', implode("\n", [
                'didi', $url, $rideDate, $times[0], $addresses[0], $times[1], $addresses[1], $amount,
            ]));
        }

        return null;
    }

    private function normalizeText(string $text): string
    {
        return trim(preg_replace('/[\p{Z}\s]+/u', ' ', $text) ?? '');
    }
}
