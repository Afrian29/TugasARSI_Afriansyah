<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * UI language switcher (PRD §9: English and Indonesian interface, D-21).
 */
class LanguageController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, ['en', 'id'], true)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);

        return back();
    }
}
