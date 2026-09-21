<?php

namespace App\Http\Controllers;

use App\Support\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Qonağın tədris sektoru seçimi (kataloqda az/ru keçidi).
 *
 * Daxil olmuş istifadəçidə sektor profildən idarə olunur — burada dəyişmir.
 * Seçim sessiyada saxlanılır və qeydiyyat formasında defolt kimi gəlir.
 */
class SectorController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sector' => ['required', Rule::in(Sector::ALL)],
        ]);

        if (Sector::guestCanSwitch()) {
            $request->session()->put(Sector::SESSION_KEY, $validated['sector']);
        }

        return back();
    }
}
