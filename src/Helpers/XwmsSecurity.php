<?php

namespace XWMS\Package\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Exception;

class XwmsSecurity
{
    private static array $memoryNonces = [];
    private static function getKey(): string
    {
        if (!config("xwms.client_key")){
            throw new Exception("Client Key is required");
        }
        return hash('sha256', config("xwms.client_key"));
    }

    public static function secureEncrypt($data, string|null $ip = null): string
    {
        $key = self::getKey();
        $iv = random_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $salt = Str::random(16);

        $payload = [
            'data' => $data,
            'timestamp' => time(),
            'nonce' => Str::random(32),
            'ip' => $ip ?? request()->ip(), // IP vastleggen
        ];

        $stretchedKey = hash_pbkdf2('sha256', $key, $salt, 10000, 32, true);

        $encrypted = openssl_encrypt(
            json_encode($payload),
            'AES-256-CBC',
            $stretchedKey,
            0,
            $iv
        );

        $hmac = hash_hmac('sha256', $encrypted, $stretchedKey, true);

        return base64_encode($salt . $iv . $hmac . $encrypted);
    }

    public static function secureDecrypt($payload, int $maxAgeSeconds = 60, bool $checkIp = true)
    {
        $raw = base64_decode($payload);
        $salt = substr($raw, 0, 16);
        $iv = substr($raw, 16, openssl_cipher_iv_length('AES-256-CBC'));
        $hmac = substr($raw, 16 + openssl_cipher_iv_length('AES-256-CBC'), 32);
        $encrypted = substr($raw, 16 + openssl_cipher_iv_length('AES-256-CBC') + 32);

        $key = self::getKey();
        $stretchedKey = hash_pbkdf2('sha256', $key, $salt, 10000, 32, true);

        $calculatedHmac = hash_hmac('sha256', $encrypted, $stretchedKey, true);
        if (!hash_equals($hmac, $calculatedHmac)) {
            throw new Exception('Invalid HMAC - possible tampering.');
        }

        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $stretchedKey,
            0,
            $iv
        );

        $payload = json_decode($decrypted, true);

        if (!$payload || !isset($payload['timestamp']) || !isset($payload['nonce']) || !isset($payload['data'])) {
            throw new Exception('Invalid payload structure.');
        }

        if (abs(time() - $payload['timestamp']) > $maxAgeSeconds) {
            throw new Exception('Payload expired.');
        }

        if ($checkIp) {
            $expectedIp = $payload['ip'] ?? null;
            $actualIp = request()->ip() ?? null;
    
            $allowedProxies = [
                // self::$xwms_api, // intern VPN IP
                $expectedIp,  // originele
            ];
    
            if (!in_array($actualIp, $allowedProxies, true)) {
                throw new Exception("IP address mismatch.");
            }
        }

        // Controleer nonce
        self::checkNonce($payload['nonce']);

        return $payload['data'];
    }

    private static function checkNonce(string $nonce)
    {
        $cacheKey = 'xwms_nonce_' . $nonce;

        try {
            if (Cache::has($cacheKey)) {
                throw new Exception('Replay attack detected: Nonce already used (cache).');
            }

            Cache::put($cacheKey, true, now()->addMinutes(2));
        } catch (\Throwable $e) {
            if (isset(self::$memoryNonces[$nonce])) {
                throw new Exception('Replay attack detected: Nonce already used (memory fallback).');
            }

            self::$memoryNonces[$nonce] = true;
        }
    }

    public static function verifyRequest(Request $request, int $maxAgeSeconds = 60, bool $checkIp = true): array
    {
        $payload = $request->input('payload');

        if (!$payload) {
            throw new Exception('Missing payload.');
        }

        return self::secureDecrypt($payload, $maxAgeSeconds, $checkIp);
    }

    public static function sign(array $data, bool $includeIp = true): string
    {
        return self::secureEncrypt($data, $includeIp ? request()->ip() : null);
    }

    public static function validateSigned(string $payload, int $maxAgeSeconds = 60, bool $checkIp = true): array
    {
        return self::secureDecrypt($payload, $maxAgeSeconds, $checkIp);
    }

    public static function matchDomain(string $input, string $stored): bool
    {
        try {
            $inputHost = parse_url(trim($input), PHP_URL_HOST);
            $storedHost = parse_url(trim($stored), PHP_URL_HOST);

            // Strip eventueel poort en www.
            $inputHost = preg_replace('/^www\./', '', explode(':', $inputHost)[0]);
            $storedHost = preg_replace('/^www\./', '', explode(':', $storedHost)[0]);

            return strtolower($inputHost) === strtolower($storedHost);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
