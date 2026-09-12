<?php

namespace App\Support\Filament;

use Filament\FontProviders\Contracts\FontProvider;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * FontProvider that emits nothing — combined with the self-hosted
 * public/css/fonts.css stylesheet (linked via a Filament render hook) it keeps
 * both panels font-local (no Bunny/Google network requests) while the
 * --font-family root var still points at the family name.
 */
class NullFontProvider implements FontProvider
{
    public function getHtml(string $family, ?string $url = null): Htmlable
    {
        return new HtmlString('');
    }
}
