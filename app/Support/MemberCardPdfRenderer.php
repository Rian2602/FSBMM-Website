<?php

namespace App\Support;

use App\Models\MemberCard;
use Illuminate\Http\Response;
use PDF;
use Throwable;

class MemberCardPdfRenderer
{
    /**
     * Serve the member card as on-demand PDF, or as a print-ready HTML page
     * when the Snappy backend (wkhtmltopdf) is not installed on the host.
     */
    public static function render(MemberCard $card): Response
    {
        $html = view('public.cards.print', ['card' => $card])->render();

        try {
            $pdf = PDF::loadHTML($html)
                ->setOption('page-size', 'A5')
                ->setOption('margin-top', '8mm')
                ->setOption('margin-bottom', '8mm')
                ->setOption('margin-left', '10mm')
                ->setOption('margin-right', '10mm')
                ->setOption('enable-local-file-access', true)
                ->setOption('encoding', 'UTF-8')
                ->output();

            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="kartu-' . $card->card_number . '.pdf"');
        } catch (Throwable $e) {
            // (** executed: the wkhtmltopdf binary is not guaranteed on every
            // host (this dev box has none), so the card falls back to the same
            // print-ready HTML the browser view uses — served inline. **)
            report($e);

            return response($html)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }
    }
}
