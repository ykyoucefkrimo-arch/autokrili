<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use App\Mail\Transport\FileTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Le transport « file » n'existe pas dans Laravel : il faut le declarer
        // avant que le gestionnaire de mail ne resolve la configuration.
        Mail::extend('file', fn () => new FileTransport());

        Vite::prefetch(concurrency: 3);
    }
}
