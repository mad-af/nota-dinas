<?php

namespace App\Services\Esign\Support;

class PayloadMasker
{
    public static function maskRequest(array $payload): array
    {
        $masked = $payload;
        $keys = ['passphrase', 'totp', 'pdfPassword', 'file', 'signatureProperties.imageBase64'];
        foreach ($keys as $k) {
            self::applyMask($masked, explode('.', $k));
        }
        return $masked;
    }

    protected static function applyMask(&$node, array $path): void
    {
        if (empty($path)) {
            return;
        }
        $key = array_shift($path);
        if (! is_array($node)) {
            return;
        }
        if (array_key_exists($key, $node)) {
            if (empty($path)) {
                if ($node[$key] !== null) {
                    $node[$key] = '***';
                }
                return;
            }
            self::applyMask($node[$key], $path);
            return;
        }
        foreach ($node as &$child) {
            self::applyMask($child, array_merge([$key], $path));
        }
        unset($child);
    }

    public static function maskResponse(string $body, int $limit = 5000): string
    {
        $arr = json_decode($body, true);
        if (! is_array($arr)) {
            return mb_strimwidth($body, 0, $limit, '...');
        }
        self::maskResponseFiles($arr);
        $encoded = json_encode($arr, JSON_UNESCAPED_UNICODE);
        return mb_strimwidth((string) $encoded, 0, $limit, '...');
    }

    protected static function maskResponseFiles(&$node): void
    {
        if (! is_array($node)) {
            return;
        }
        if (array_key_exists('file', $node)) {
            if (is_array($node['file'])) {
                foreach ($node['file'] as $i => $v) {
                    $node['file'][$i] = '***';
                }
            } elseif ($node['file'] !== null) {
                $node['file'] = '***';
            }
        }
        foreach ($node as &$child) {
            if (is_array($child)) {
                self::maskResponseFiles($child);
            }
        }
        unset($child);
    }
}

