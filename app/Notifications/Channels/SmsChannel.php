<?php

namespace App\Notifications\Channels;

use App\Contracts\SmsGateway;
use Illuminate\Notifications\Notification;

/**
 * Laravel channel wired to the SmsGateway contract.
 *
 * A notification opts in by declaring a `toSms()` method returning the text;
 * one that does not is simply skipped, so adding the channel to the config
 * never breaks an existing notification.
 */
class SmsChannel
{
    public function __construct(private readonly SmsGateway $gateway)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $to = method_exists($notifiable, 'routeNotificationForSms')
            ? $notifiable->routeNotificationForSms($notification)
            : ($notifiable->phone ?? null);

        if (! $to) {
            return;
        }

        $this->gateway->send($to, $notification->toSms($notifiable));
    }
}
