<?php

namespace App\Http\Controllers;

use App\Support\MemberCardVerificationService;

class CardVerificationController extends Controller
{
    public function verify(string $token)
    {
        $service = new MemberCardVerificationService;
        $data = $service->lookup($token);

        return view('public.cards.verify', [
            'data' => $data,
            'token' => $token,
        ]);
    }
}
