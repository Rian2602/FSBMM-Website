<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\CertificateService;

class CertificateVerificationController extends Controller
{
    public function verify(string $token)
    {
        return view('public.certificates.verify', [
            'certificate' => app(CertificateService::class)->validateToken($token),
            'token' => $token,
        ]);
    }
}
