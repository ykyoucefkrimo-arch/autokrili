<?php

/*
|--------------------------------------------------------------------------
| Canaux de notification
|--------------------------------------------------------------------------
| Le §10 demande de pouvoir brancher plus tard des SMS ou WhatsApp Business,
| très efficaces en Algérie. Les notifications lisent ce fichier plutôt que
| de coder leurs canaux en dur : activer le SMS pour les réservations est
| alors une ligne de configuration, pas une reprise du code.
|
| Une notification qui n'implémente pas `toSms()` est simplement ignorée par
| le canal : activer un canal ne casse jamais l'existant.
*/

return [
    // Canaux par défaut, pour toute notification sans réglage propre.
    'default' => ['mail'],

    /*
    | Réglages par notification, désignés par leur nom court. Le SMS est
    | réservé à ce qui est urgent ou irréversible : une demande à laquelle
    | l'agence a 24 h pour répondre, une confirmation que le client attend.
    */
    'channels' => [
        'BookingRequested' => ['mail', 'sms'],
        'BookingConfirmed' => ['mail', 'sms'],
        'BookingRefused' => ['mail'],
        'BookingCancelled' => ['mail'],
        'BookingReminder' => ['mail', 'sms'],
        'ReviewInvitation' => ['mail'],
        'AgencyApproved' => ['mail'],
        'AgencyRejected' => ['mail'],
        'AgencySuspended' => ['mail'],
        'SubscriptionExpiring' => ['mail'],
    ],

    'sms' => [
        // `log` en développement ; une implémentation de SmsGateway le jour où
        // un opérateur est choisi.
        'enabled' => (bool) env('SMS_ENABLED', false),
        'log_channel' => env('SMS_LOG_CHANNEL', 'stack'),
    ],
];
