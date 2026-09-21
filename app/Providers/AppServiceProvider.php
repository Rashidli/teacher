<?php

namespace App\Providers;

use App\Models\Exam;
use App\Models\User;
use App\Policies\ExamPolicy;
use App\Support\Localization;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Exam::class, ExamPolicy::class);

        $this->configurePasswordResetMail();
    }

    /**
     * Parol sıfırlama məktubu istifadəçinin dilində (User::preferredLocale) göndərilir:
     * Laravel bildirişi həmin dildə qurur, link də həmin dilin səhifəsinə aparır (/ru/reset-password/...).
     */
    private function configurePasswordResetMail(): void
    {
        $resetUrl = fn (User $user, string $token) => Localization::route(
            'password.reset',
            ['token' => $token, 'email' => $user->getEmailForPasswordReset()],
            locale: $user->preferredLocale(),
        );

        ResetPassword::createUrlUsing($resetUrl);

        ResetPassword::toMailUsing(function (User $user, string $token) use ($resetUrl) {
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject(__('mail.reset.subject'))
                ->greeting(__('mail.reset.greeting', ['name' => $user->first_name]))
                ->line(__('mail.reset.intro'))
                ->action(__('mail.reset.action'), $resetUrl($user, $token))
                ->line(__('mail.reset.expire', ['count' => $minutes]))
                ->line(__('mail.reset.ignore'))
                ->salutation(__('mail.reset.salutation'));
        });
    }
}
