<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Server-rendered QR codes for print artefacts (member cards, certificates).
 *
 * Uses the committed `bacon/bacon-qr-code` dependency so the printed QR is a
 * real, scannable code — unlike the decorative JS pattern in the card print
 * view, which only looks like a QR.
 */
class QrCodeRenderer
{
    public static function svg(string $data, int $size = 160): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 0),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($data);
    }
}
