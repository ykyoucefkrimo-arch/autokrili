<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

/**
 * Écrit chaque email sur le disque au lieu de l'envoyer.
 *
 * Le pilote `log` de Laravel noie les messages dans `laravel.log`, encodés en
 * quoted-printable : illisibles, et impossibles à montrer à quelqu'un. Ici
 * chaque email devient un fichier JSON avec son HTML intact, que le back
 * office affiche tel que le destinataire l'aurait reçu.
 *
 * Sert la démonstration et le développement. En production, un vrai SMTP.
 */
class FileTransport extends AbstractTransport
{
    public const DIRECTORY = 'mails';

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $payload = [
            'id' => (string) Str::uuid(),
            'date' => now()->toIso8601String(),
            'subject' => $email->getSubject(),
            'from' => $this->addresses($email->getFrom()),
            'to' => $this->addresses($email->getTo()),
            'cc' => $this->addresses($email->getCc()),
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
        ];

        // Horodatage en tête du nom : le tri par nom est le tri chronologique,
        // sans avoir à ouvrir un seul fichier.
        Storage::disk('local')->put(
            self::DIRECTORY.'/'.now()->format('Ymd-His').'-'.substr($payload['id'], 0, 8).'.json',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /** @return array<int, string> */
    private function addresses(array $addresses): array
    {
        return array_map(
            fn ($address) => $address->getName()
                ? $address->getName().' <'.$address->getAddress().'>'
                : $address->getAddress(),
            $addresses
        );
    }

    public function __toString(): string
    {
        return 'file://'.self::DIRECTORY;
    }
}
