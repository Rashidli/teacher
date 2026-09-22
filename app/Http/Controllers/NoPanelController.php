<?php

namespace App\Http\Controllers;

use App\Support\Panel;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Hesabın açıq paneli olmayanda görünən səhifə.
 *
 * Belə hal müəllim modulu söndürülü olanda yaranır: yalnız `teacher` rolu olan hesabın
 * gedəcəyi yer yoxdur. Əvvəl belə hesab şagird kabinetinə göndərilir və orada 403 alırdı —
 * indi səbəbi izah edən səhifə açılır.
 */
class NoPanelController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // Paneli olan hesab bura düşməməlidir
        if (Panel::hasPanel($user)) {
            return redirect(Panel::homeUrl($user));
        }

        return Inertia::render('NoPanel', [
            'isTeacher' => $user->hasRole('teacher'),
            'roles' => $user->roles->pluck('name'),
        ]);
    }
}
