<?php

namespace XWMS\Package\Helpers;

use Illuminate\Support\Facades\RateLimiter;

class RateLimit
{
    public static function checkRateLimit(string $key, int $cooldownSeconds, array $options = [])
    {
        $ip = request()->ip();
        $sessionId = session()->getId();
        $throttleKey = $key . ':' . sha1($ip . '|' . $sessionId);
    
        $maxFreeAttempts = $options['startAfter'] ?? 0;
        $attempts = RateLimiter::attempts($throttleKey);
    
        if ($attempts >= $maxFreeAttempts) {
            if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
                $msg = $options['message'] ?? 'before trying again.';
                if (!empty($options['removeMsg'])) {
                    throw new \Exception($msg);
                }

                $rawSeconds = RateLimiter::availableIn($throttleKey);
                $secondsRemaining = (int) ceil((float) $rawSeconds); // ✅ gegarandeerd integer
    
                throw new \Exception("Please wait {$secondsRemaining} second" . ($secondsRemaining === 1 ? '' : 's') . " {$msg}");
            }
    
            RateLimiter::hit($throttleKey, $cooldownSeconds);
        } else {
            RateLimiter::hit($throttleKey, 0);
        }
    }
}
