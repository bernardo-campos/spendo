<?php

namespace App\Services;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;

class UberReceiptParser
{
    /**
     * @return array{currency: string, amount: string, origin_address: ?string, destination_address: ?string, purchase_date: ?string, source_key: ?string}|null
     */
    public function parse(string $message): ?array
    {
        $parts = preg_split('/\r?\n\r?\n/', $message, 2);
        $date = count($parts) === 2 ? $this->headerValue($parts[0], 'Date') : '';
        $purchaseDate = $date !== '' ? DateTimeImmutable::createFromFormat(DATE_RFC2822, $date) : false;

        foreach ($this->htmlParts($message) as $html) {
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
     * @return array<string>
     */
    private function htmlParts(string $message, int $depth = 0): array
    {
        if ($depth > 5) {
            return [];
        }

        $parts = preg_split('/\r?\n\r?\n/', $message, 2);

        if (count($parts) !== 2) {
            return [];
        }

        [$headers, $body] = $parts;
        $headers = preg_replace('/\r?\n[ \t]+/', ' ', $headers);
        $contentType = $this->headerValue($headers, 'Content-Type');

        if (preg_match('/^multipart\//i', $contentType)) {
            if (! preg_match('/\bboundary\s*=\s*(?:"([^"]+)"|([^;\s]+))/i', $contentType, $matches)) {
                return [];
            }

            $boundary = $matches[1] !== '' ? $matches[1] : $matches[2];
            $htmlParts = [];

            foreach (explode('--'.$boundary, $body) as $part) {
                $part = ltrim($part, "\r\n");

                if ($part === '' || str_starts_with($part, '--')) {
                    continue;
                }

                array_push($htmlParts, ...$this->htmlParts($part, $depth + 1));
            }

            return $htmlParts;
        }

        if (! preg_match('/^text\/html\b/i', $contentType) || preg_match('/^attachment\b/i', $this->headerValue($headers, 'Content-Disposition'))) {
            return [];
        }

        $encoding = strtolower($this->headerValue($headers, 'Content-Transfer-Encoding'));
        $html = match ($encoding) {
            'quoted-printable' => quoted_printable_decode($body),
            'base64' => base64_decode($body, true),
            default => $body,
        };

        return is_string($html) && $html !== '' && mb_check_encoding($html, 'UTF-8') ? [$html] : [];
    }

    private function headerValue(string $headers, string $name): string
    {
        if (preg_match('/^'.preg_quote($name, '/').':\s*([^\r\n]*)/im', $headers, $matches)) {
            return trim($matches[1]);
        }

        return '';
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

            $url = $node->getAttribute('href');

            if (preg_match('/^(?:descargar|download) pdf$/iu', $label) && parse_url($url, PHP_URL_HOST) === 'click.uber.com') {
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
