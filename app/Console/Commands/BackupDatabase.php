<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Bazanın ehtiyat nüsxəsi: `php artisan db:backup`.
 *
 * Fayl `public_html`-dən KƏNARDA saxlanılır (veb serverdən yüklənə bilməsin) və yalnız
 * son bir neçə nüsxə qalır. Deploy addımlarında migration-dan ƏVVƏL çağırılır.
 *
 * Parol əmr sətrində görünmür: `MYSQL_PWD` mühit dəyişəni ilə ötürülür.
 *
 * Fayl adındakı vaxt UTC-dir (tətbiqin saat qurşağı), serverin yerli saatı ilə fərqlənə bilər.
 * Köhnə nüsxələr fayl tarixinə görə silinir, ada görə yox — qarışıqlıq olmur.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--path= : Nüsxələrin saxlanacağı qovluq (defolt: layihə qovluğunun valideyni)}
        {--keep=3 : Neçə ən son nüsxə saxlanılsın}';

    protected $description = 'MySQL/MariaDB bazasının ehtiyat nüsxəsini çıxarır (public_html-dən kənarda)';

    public function handle(): int
    {
        $connection = config('database.default');

        if ($connection !== 'mysql') {
            $this->error("Bu əmr yalnız mysql/mariadb üçündür (cari: {$connection}).");

            return self::FAILURE;
        }

        $config = config("database.connections.{$connection}");
        $directory = rtrim($this->option('path') ?: dirname(base_path()), '/');

        if (! is_dir($directory) || ! is_writable($directory)) {
            $this->error("Qovluq yazıla bilmir: {$directory}");

            return self::FAILURE;
        }

        $file = $directory.'/backup_db_'.now()->utc()->format('Y-m-d_Hi').'.sql';

        $result = Process::timeout(600)
            ->env(['MYSQL_PWD' => (string) $config['password']])
            ->run($this->command($config, $file));

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

    /** @param  array<string, mixed>  $config */
    private function command(array $config, string $file): string
    {
        $binary = $this->binary();

        return sprintf(
            '%s --no-defaults --user=%s --host=%s --port=%s --protocol=TCP --single-transaction --routines %s > %s',
            $binary,
            escapeshellarg((string) $config['username']),
            escapeshellarg((string) ($config['host'] ?: '127.0.0.1')),
            escapeshellarg((string) ($config['port'] ?: 3306)),
            escapeshellarg((string) $config['database']),
            escapeshellarg($file),
        );
    }

    /** Sistemdə hansı dump proqramı var (MariaDB 11-də `mysqldump` köhnəlmiş addır) */
    private function binary(): string
    {
        return Process::run('command -v mariadb-dump')->successful() ? 'mariadb-dump' : 'mysqldump';
    }

    /** Köhnə nüsxələri silir: yalnız son `keep` ədəd qalır */
    private function prune(string $directory, int $keep): void
    {
        $files = glob($directory.'/backup_db_*.sql') ?: [];

        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
            $this->line('Silindi: '.basename($old));
        }
    }
}
