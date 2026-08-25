<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\Transport\FileTransport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La boîte d'envoi : les emails que la plateforme a produits.
 *
 * N'existe que lorsque le mailer `file` est actif — en démonstration et en
 * développement. En production les emails partent par SMTP et cet écran n'a
 * plus de raison d'être ; il se ferme de lui-même plutôt que d'afficher une
 * liste vide qu'on prendrait pour une panne.
 */
class MailboxController extends Controller
{
    public function index(): Response
    {
        $this->assertEnabled();

        $emails = collect(Storage::disk('local')->files(FileTransport::DIRECTORY))
            ->filter(fn (string $path) => str_ends_with($path, '.json'))
            // Le nom commence par l'horodatage : trier par nom suffit.
            ->sortDesc()
            ->take(100)
            ->map(function (string $path) {
                $mail = json_decode(Storage::disk('local')->get($path), true);

                return [
                    'file' => basename($path, '.json'),
                    'subject' => $mail['subject'] ?? '(sans objet)',
                    'to' => implode(', ', $mail['to'] ?? []),
                    'date' => isset($mail['date'])
                        ? Carbon::parse($mail['date'])->translatedFormat('d M Y à H:i')
                        : null,
                    'excerpt' => \Illuminate\Support\Str::limit(
                        trim(preg_replace('/\s+/', ' ', $mail['text'] ?? strip_tags($mail['html'] ?? ''))),
                        120
                    ),
                ];
            })
            ->values();

        return Inertia::render('Admin/Mailbox', [
            'emails' => $emails,
            'mailer' => config('mail.default'),
        ]);
    }

    /** Le corps HTML, rendu dans une iframe : tel que le destinataire le voit. */
    public function show(string $file): HttpResponse
    {
        $this->assertEnabled();

        $mail = $this->read($file);

        return response($mail['html'] ?? nl2br(e($mail['text'] ?? '')), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // L'email est du HTML arbitraire : il ne doit rien pouvoir demander
            // au reseau ni executer de script dans le back office.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:;",
        ]);
    }

    public function destroy(): RedirectResponse
    {
        $this->assertEnabled();

        Storage::disk('local')->deleteDirectory(FileTransport::DIRECTORY);

        return back()->with('success', 'Boîte d’envoi vidée.');
    }

    /** @return array<string, mixed> */
    private function read(string $file): array
    {
        // basename() coupe court a toute tentative de remonter l'arborescence.
        $path = FileTransport::DIRECTORY.'/'.basename($file).'.json';

        abort_unless(Storage::disk('local')->exists($path), 404);

        return json_decode(Storage::disk('local')->get($path), true) ?? [];
    }

    private function assertEnabled(): void
    {
        abort_unless(config('mail.default') === 'file', 404);
    }
}
