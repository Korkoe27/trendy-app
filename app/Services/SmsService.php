<?php

namespace App\Services;

use App\Support\Phone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends SMS through Hubtel. Credentials come from config/services.php (HUBTEL_SMS_* env vars).
 * The message body is never logged outside local/testing because it can contain a PIN or a code.
 */
class SmsService
{
    public function isConfigured(): bool
    {
        $config = config('services.hubtel_sms');

        return ! empty($config['url']) && ! empty($config['user']) && ! empty($config['key']);
    }

    public function send(string $to, string $message): bool
    {
        $recipient = Phone::international($to);

        if (! $recipient) {
            Log::warning('SMS not sent: invalid recipient number.');

            return false;
        }

        if (! $this->isConfigured()) {
            Log::warning('SMS not sent: Hubtel SMS credentials are not configured.', app()->environment(['local', 'testing']) ? ['to' => $recipient, 'message' => $message] : []);

            return false;
        }

        $config = config('services.hubtel_sms');

        // The configured URL may carry a query string (test URLs often do); the credentials are sent as Basic auth instead.
        $url = strtok($config['url'], '?');

        try {
            $response = Http::withBasicAuth($config['user'], $config['key'])
                ->acceptJson()
                ->timeout(10)
                ->post($url, [
                    'From' => $config['from'],
                    'To' => $recipient,
                    'Content' => $message,
                ]);
        } catch (\Throwable $e) {
            Log::error('SMS request failed', ['error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::error('SMS rejected by gateway', ['status' => $response->status()]);

            return false;
        }

        return true;
    }
}
