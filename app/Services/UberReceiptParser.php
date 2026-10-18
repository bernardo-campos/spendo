<?php

namespace App\Services;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;

class UberReceiptParser
{
    public function __construct(private ReceiptEmailParts $emailParts) {}

    /**
     * @return array{currency: string, amount: string, origin_address: ?string, destination_address: ?string, purchase_date: ?string, source_key: ?string}|null
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
     * @return array{currency: string, amount: string, origin_address: ?string, destination_address: ?string, source_key: ?string}|null
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
        $total = $this->valueForTestId($xpath, 'total_fare_amount')
            ?? $this->valuesForClass($xpath, 'total-fare-amount')[0] ?? null;

        if ($total === null || ! preg_match('/^([A-Z]{3})\s*((?:\d{1,3}(?:,\d{3})+|\d+)\.\d{2})$/', $total, $matches)) {
            return null;
        }

        $addresses = $this->valuesForClass($xpath, 'address-point-desc');

        return [
            'currency' => $matches[1],
            'amount' => str_replace(',', '', $matches[2]),
            'origin_address' => $this->valueForTestId($xpath, 'address_point_0_address') ?? $addresses[0] ?? null,
            'destination_address' => $this->valueForTestId($xpath, 'address_point_1_address') ?? $addresses[1] ?? null,
            'source_key' => $this->sourceKey($xpath),
        ];
    }

    private function sourceKey(DOMXPath $xpath): ?string
    {
        foreach ($xpath->query('//a[@href]') as $node) {
            $label = $this->normalizeText($node->textContent);

            if ($label === '' && $node->parentNode !== null) {
                $label = $this->normalizeText($node->parentNode->textContent);
            }

            if (! preg_match('/^(?:descargar|download) pdf$/iu', $label)) {
                continue;
            }

            $url = $node->getAttribute('href');
            $parts = parse_url($url);

            if (is_array($parts) && ($parts['scheme'] ?? null) === 'https' && ! empty($parts['host'])) {
                return hash('sha256', $url);
            }
        }

        return null;
    }

    private function valueForTestId(DOMXPath $xpath, string $testId): ?string
    {
        $node = $xpath->query('//*[@data-testid="'.$testId.'"]')->item(0);

        if ($node === null) {
            return null;
        }

        $value = $this->normalizeText($node->textContent);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string>
     */
    private function valuesForClass(DOMXPath $xpath, string $class): array
    {
        $values = [];

        foreach ($xpath->query('//*[contains(@class, "'.$class.'")]') as $node) {
            if (! preg_match('/(?:^|\s)(?:m_-?\d+)?'.preg_quote($class, '/').'(?=\s|$)/', $node->getAttribute('class'))) {
                continue;
            }

            $value = $this->normalizeText($node->textContent);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function normalizeText(string $text): string
    {
        return trim(preg_replace('/[\p{Z}\s]+/u', ' ', $text) ?? '');
    }
}
