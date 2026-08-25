{{--
    Bon de réservation (§6.2). Le client le présente au comptoir : tout ce dont
    l'agent a besoin doit tenir sur une page, sans avoir à ouvrir le site.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bon de réservation {{ $booking->booking_reference }}</title>
    <style>
        @page { margin: 22mm 18mm; }
        /* Helvetica est une police de base de dompdf : elle n'est pas embarquee
           dans le fichier. DejaVu Sans ajoutait 850 Ko a chaque bon telecharge,
           pour une page que le client ouvre sur son telephone. Le francais
           accentue passe en Latin-1 sans probleme. */
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 20px; margin: 0; }
        .muted { color: #6b7280; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        .ref { font-size: 15px; font-weight: bold; letter-spacing: 0.5px; }
        .box { border: 1px solid #e5e7eb; padding: 10px 12px; margin-bottom: 12px; }
        .box h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .06em;
                  color: #6b7280; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 0; }
        .label { color: #6b7280; width: 42%; }
        .total { border-top: 1px solid #111827; margin-top: 8px; padding-top: 8px;
                 font-size: 15px; font-weight: bold; }
        .notice { background: #f9fafb; border-left: 3px solid #111827; padding: 9px 12px;
                  margin-top: 14px; }
        .footer { margin-top: 26px; font-size: 9px; color: #9ca3af; text-align: center; }
        .sign { margin-top: 30px; }
        .sign td { height: 52px; border: 1px solid #e5e7eb; width: 50%;
                   vertical-align: top; padding: 6px 10px; color: #6b7280; }
    </style>
</head>
<body>

<table class="header">
    <tr>
        <td>
            <h1>{{ $booking->agency->commercial_name }}</h1>
            <p class="muted" style="margin:4px 0 0">
                {{ $booking->agency->address }}<br>
                {{ $booking->agency->commune?->name_fr }}, {{ $booking->agency->wilaya?->name_fr }}<br>
                Tél. {{ $booking->agency->phone }}
            </p>
        </td>
        <td style="text-align: right">
            <p class="ref">{{ $booking->booking_reference }}</p>
            <p class="muted" style="margin:4px 0 0">
                Bon de réservation<br>
                Émis le {{ now()->translatedFormat('d F Y') }}
            </p>
        </td>
    </tr>
</table>

<div class="box">
    <h2>Véhicule</h2>
    <table>
        <tr><td class="label">Modèle</td><td><strong>{{ $booking->vehicle?->title() ?? 'Véhicule retiré du catalogue' }}</strong></td></tr>
        <tr><td class="label">Départ</td><td>{{ $booking->start_date->translatedFormat('l d F Y') }}</td></tr>
        <tr><td class="label">Retour</td><td>{{ $booking->end_date->translatedFormat('l d F Y') }}</td></tr>
        <tr><td class="label">Durée</td><td>{{ $booking->total_days }} jour{{ $booking->total_days > 1 ? 's' : '' }}</td></tr>
        <tr><td class="label">Lieu de retrait</td><td>{{ $booking->pickup_location }}</td></tr>
        @if ($booking->with_driver)
            <tr><td class="label">Option</td><td>Avec chauffeur</td></tr>
        @endif
    </table>
</div>

<div class="box">
    <h2>Client</h2>
    <table>
        <tr><td class="label">Nom</td><td>{{ $booking->client_name }}</td></tr>
        <tr><td class="label">Téléphone</td><td>{{ $booking->client_phone }}</td></tr>
        @if ($booking->client_email)
            <tr><td class="label">Email</td><td>{{ $booking->client_email }}</td></tr>
        @endif
        @if ($booking->driver_license_number)
            <tr><td class="label">Permis n°</td><td>{{ $booking->driver_license_number }}</td></tr>
        @endif
    </table>
</div>

<div class="box">
    <h2>Montant</h2>
    <table>
        @foreach ($booking->price_breakdown ?? [] as $line)
            <tr>
                <td class="label">{{ $line['label'] }} × {{ $line['quantity'] }}</td>
                <td>{{ number_format($line['total'], 0, ',', ' ') }} DA</td>
            </tr>
        @endforeach
    </table>
    <div class="total">
        Total à régler : {{ number_format($booking->total_price_dzd, 0, ',', ' ') }} DA
    </div>
    @if ($booking->deposit_dzd > 0)
        <p class="muted" style="margin:6px 0 0">
            Caution demandée au départ : {{ number_format($booking->deposit_dzd, 0, ',', ' ') }} DA
        </p>
    @endif
</div>

{{-- Aucun paiement en ligne en v1 (§4.3) : le dire ici évite au client de
     croire que la réservation est déjà réglée. --}}
<div class="notice">
    <strong>Le règlement se fait à l'agence</strong>, au moment du départ.
    Présentez ce bon, une pièce d'identité et votre permis de conduire.
    @if ($booking->agency->min_driver_age)
        Âge minimum du conducteur : {{ $booking->agency->min_driver_age }} ans.
    @endif
</div>

@if ($booking->agency->rental_conditions)
    <div class="box" style="margin-top:12px">
        <h2>Conditions de location</h2>
        <p style="margin:0; white-space: pre-line">{{ $booking->agency->rental_conditions }}</p>
    </div>
@endif

<table class="sign">
    <tr>
        <td>Signature du client</td>
        <td>Cachet et signature de l'agence</td>
    </tr>
</table>

<p class="footer">
    Autokrili — mise en relation entre agences de location et clients.
    Le contrat de location est conclu entre le client et l'agence.
</p>

</body>
</html>
