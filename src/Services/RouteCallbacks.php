<?php

namespace MrNewport\LaravelDocSign\Services;

use Illuminate\Http\Request;
use MrNewport\LaravelDocSign\Facades\DocSign;

class RouteCallbacks
{
    public function signatureCallback($provider, Request $request)
    {
        DocSign::handleCallback($provider, $request);
        return response()->json(['status' => 'callback processed']);
    }
}
