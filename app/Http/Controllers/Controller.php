<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Laravel 12 ships this base class empty. The trait is added here rather
    // than in each controller so `authorize()` means the same thing everywhere
    // and no controller can quietly skip a policy for lack of it.
    use AuthorizesRequests;
}
