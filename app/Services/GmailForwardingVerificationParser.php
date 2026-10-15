<?php

namespace App\Services;

class GmailForwardingVerificationParser
{
    /**
     * @return array{source: string, url: string}|null
     */
    public function parse(string $message, string $recipient): ?array
    {
        $parts = preg_split('/\r?\n\r?\n/', $message, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$headers, $body] = $parts;
        $headers = preg_replace('/\r?\n[ \t]+/', ' ', $headers) ?? $headers;
        $from = $this->headerValue($headers, 'From');
        $to = $this->headerValue($headers, 'To');

        if (! preg_match('/(?:^|<)forwarding-noreply@google\.com(?:$|>)/i', $from)
            || ! preg_match('/(?:^|[<\s,])'.preg_quote($recipient, '/').'(?:$|[>\s,])/i', $to)
            || ! preg_match('/^text\/plain\b/i', $this->headerValue($headers, 'Content-Type'))) {
            return null;
        }

        $body = match (strtolower($this->headerValue($headers, 'Content-Transfer-Encoding'))) {
            'quoted-printable' => quoted_printable_decode($body),
            'base64' => base64_decode($body, true),
            default => $body,
        };

        if (! is_string($body)
            || ! preg_match('/^\s*([^\s<>]+@[^\s<>]+)\s+has requested to automatically forward mail to your email\s+address\b/im', $body, $source)
            || ! filter_var($source[1], FILTER_VALIDATE_EMAIL)
            || ! preg_match('~https://mail-settings\.google\.com/mail/vf-[^\s<>"\']+~i', $body, $match)) {
            return null;
        }

        $url = rtrim($match[0], '.,;)]');
        $parsed = parse_url($url);

        if (! is_array($parsed)
            || strlen($url) > 2048
            || ($parsed['scheme'] ?? null) !== 'https'
            || ($parsed['host'] ?? null) !== 'mail-settings.google.com'
            || ! str_starts_with($parsed['path'] ?? '', '/mail/vf-')
            || isset($parsed['user'])
            || isset($parsed['pass'])
            || isset($parsed['port'])
            || isset($parsed['query'])
            || isset($parsed['fragment'])) {
            return null;
        }

        return ['source' => strtolower($source[1]), 'url' => $url];
    }

    private function headerValue(string $headers, string $name): string
    {
        if (preg_match('/^'.preg_quote($name, '/').':\s*([^\r\n]*)/im', $headers, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }
}
