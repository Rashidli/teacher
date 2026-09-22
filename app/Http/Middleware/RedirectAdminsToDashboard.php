<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /admin/login üçün "guest" əvəzi.
 *
 * Adi `guest` middleware-i daxil olmuş HƏR kəsi öz panelinə atırdı — şagird hesabı ilə
 * brauzer açıq olanda admin forması ümumiyyətlə görünmürdü. İndi yalnız artıq admin olan
 * istifadəçi panelə yönləndirilir; qalanlar formanı görüb başqa hesabla daxil ola bilir.
 */
class RedirectAdminsToDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
