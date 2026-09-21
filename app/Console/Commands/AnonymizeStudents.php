<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Staging bazasındakı şəxsi məlumatları anonimləşdirir: `php artisan staging:anonymize`.
 *
 * Produksiyada İŞLƏMİR — əmr APP_ENV=production olanda dərhal dayanır.
 * Admin hesabları toxunulmur (staging-ə giriş üçün lazımdır), yalnız şagird və müəllim
 * hesablarının adı, emaili, telefonu və parolu dəyişdirilir.
 */
class AnonymizeStudents extends Command
{
    protected $signature = 'staging:anonymize {--password=staging-parol : Bütün hesablara qoyulacaq parol}';

    protected $description = 'Staging nüsxəsindəki şagird/müəllim məlumatlarını anonimləşdirir';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Bu əmr produksiyada işləmir (APP_ENV=production).');

            return self::FAILURE;
        }

        if (! $this->confirm('Bazadakı bütün şagird və müəllim məlumatları dəyişdiriləcək. Davam edilsin?', false)) {
            return self::FAILURE;
        }

        $password = Hash::make((string) $this->option('password'));
        $changed = 0;

        User::query()
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'admin'))
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($password, &$changed) {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update([
                        'first_name' => 'Test',
                        'last_name' => 'İstifadəçi '.$user->id,
                        'email' => 'user'.$user->id.'@staging.local',
                        'phone' => $user->phone === null ? null : '+994500000'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
                        'password' => $password,
                        'remember_token' => null,
                    ]);

                    $changed++;
                }
            });

        $this->info("{$changed} hesab anonimləşdirildi. Parol: --password ilə verilən dəyər.");

        return self::SUCCESS;
    }
}
