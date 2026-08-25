<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * The development gateway: it writes to the log instead of sending.
 *
 * Chosen over a silent null implementation so the message can be read and
 * proofread — an SMS is billed per segment, and a text nobody ever looked at
 * is a text that costs twice.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): bool
    {
        Log::channel(config('notifications.sms.log_channel'))->info('SMS', [
            'to' => $this->normalise($to),
            'segments' => (int) ceil(mb_strlen($message) / 160),
            'message' => $message,
        ]);

        return true;
    }

    /** `0555…` becomes `+213555…`: every gateway wants the international form. */
    private function normalise(string $number): string
    {
        $digits = preg_replace('/[^0-9]/', '', $number);

        return '+'.(str_starts_with($digits, '213') ? $digits : '213'.ltrim($digits, '0'));
    }
}
