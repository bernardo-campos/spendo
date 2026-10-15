<?php

namespace App\Services;

class ReceiptEmailParts
{
    /**
     * @return array<string>
     */
    public function htmlParts(string $message, int $depth = 0): array
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

    public function headerValue(string $headers, string $name): string
    {
        if (preg_match('/^'.preg_quote($name, '/').':\s*([^\r\n]*)/im', $headers, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }
}
