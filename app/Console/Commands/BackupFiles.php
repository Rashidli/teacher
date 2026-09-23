<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Yüklənmiş faylların ehtiyat nüsxəsi: `php artisan files:backup`.
 *
 * `db:backup` YALNIZ bazanı götürür. Sual şəkilləri, variant şəkilləri və nümunə yol
 * nişanları isə `storage/app/public`-dədir — baza nüsxəsində onlar YOXDUR. Baza geri
 * qaytarılsa da şəkillər itmiş qalardı (sual sətrində yol var, fayl yox → 404).
 *
 * `db:backup` ilə eyni qaydalar: arxiv `public_html`-dən KƏNARDA, yalnız son bir neçə
 * nüsxə, fayl hüququ 0600 (veb serverdən oxunmasın).
 */
class BackupFiles extends Command
{
    protected $signature = 'files:backup
        {--path= : Nüsxələrin saxlanacağı qovluq (defolt: layihə qovluğunun valideyni)}
        {--keep=3 : Neçə ən son nüsxə saxlanılsın}';

    protected $description = 'Yüklənmiş faylların (storage/app/public) arxivini çıxarır';

    /** Arxivlənən qovluq — `storage/app/` daxilindəki yol */
    private const SOURCE = 'public';

    public function handle(): int
    {
        $source = storage_path('app/'.self::SOURCE);

        if (! is_dir($source)) {
            $this->warn("Qovluq yoxdur, nüsxə alınmadı: {$source}");

            return self::SUCCESS;
        }

        $directory = rtrim($this->option('path') ?: dirname(base_path()), '/');

        if (! is_dir($directory) || ! is_writable($directory)) {
            $this->error("Qovluq yazıla bilmir: {$directory}");

            return self::FAILURE;
        }

        $file = $directory.'/backup_files_'.now()->utc()->format('Y-m-d_Hi').'.tar.gz';

        // -C ilə valideyn qovluğa keçilir: arxivdə tam yol yox, yalnız `public/…` qalır
        $result = Process::timeout(900)->run(sprintf(
            'tar -czf %s -C %s %s',
            escapeshellarg($file),
            escapeshellarg(storage_path('app')),
            escapeshellarg(self::SOURCE),
        ));

        if (! $result->successful() || ! file_exists($file) || filesize($file) === 0) {
            @unlink($file);
            $this->error('Nüsxə alınmadı: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        chmod($file, 0600);
        $this->info('Nüsxə: '.$file.' ('.number_format(filesize($file) / 1024, 1).' KB)');

        $this->prune($directory, max(1, (int) $this->option('keep')));

        return self::SUCCESS;
    }

    /** Köhnə nüsxələri silir: yalnız son `keep` ədəd qalır (fayl tarixinə görə) */
    private function prune(string $directory, int $keep): void
    {
        $files = glob($directory.'/backup_files_*.tar.gz') ?: [];

        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
            $this->line('Silindi: '.basename($old));
        }
    }
}
