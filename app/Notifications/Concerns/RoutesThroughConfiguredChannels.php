<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\SmsChannel;

/**
 * Reads the channels of a notification from `config/notifications.php`
 * instead of hard-coding them in `via()`.
 *
 * That indirection is what §10 asks for: the day an SMS provider is chosen,
 * turning SMS on for booking requests is a config line, not a sweep through
 * every notification class.
 */
trait RoutesThroughConfiguredChannels
{
    public function via(object $notifiable): array
    {
        $name = class_basename(static::class);
        $channels = config("notifications.channels.{$name}", config('notifications.default'));

        return array_values(array_filter(array_map(
            fn (string $channel) => $this->resolveChannel($channel),
            $channels
        )));
    }

    private function resolveChannel(string $channel): ?string
    {
        if ($channel !== 'sms') {
            return $channel;
        }

        // Un canal SMS actif sans passerelle configurée enverrait dans le vide
        // tout en faisant croire que le client a été prévenu.
        return config('notifications.sms.enabled') ? SmsChannel::class : null;
    }
}
