<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Support\CertificateService;
use DomainException;
use InvalidArgumentException;

/**
 * Panel-authenticated certificate printing (registered in BOTH panel
 * providers as `/certificates/{record}/print`). Issues the certificate on
 * first print and renders the print-ready view.
 */
class CertificatePrintController extends Controller
{
    public function __invoke(Course $record)
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        try {
            $certificate = app(CertificateService::class)->issue($user, $record);
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }

        $certificate->load(['user', 'course']);

        return view('public.certificates.print', ['certificate' => $certificate]);
    }
}
