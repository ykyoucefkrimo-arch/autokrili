<?php

namespace App\Contracts;

/**
 * Sending an SMS. Deliberately reduced to one method: SMS and WhatsApp
 * Business are very effective in Algeria (§10), and the day a provider is
 * chosen, only an implementation of this contract has to be written.
 */
interface SmsGateway
{
    /**
     * @param  string  $to       Algerian number, any accepted format.
     * @param  string  $message  Plain text, no markup.
     * @return bool  whether the gateway accepted the message
     */
    public function send(string $to, string $message): bool;
}
