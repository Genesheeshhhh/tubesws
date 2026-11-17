<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Policies are what stop a seller reaching another seller's listing, so the
    // trait is on the base controller rather than added per action.
    use AuthorizesRequests;
}
