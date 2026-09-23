<?php

namespace App\Console\Commands;

use App\Http\Controllers\SitemapController;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\Demo\DemoRoadSigns;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Demo məzmunu silir: `php artisan demo:clear`.
 *
 * Yalnız `is_demo = 1` qeydlərinə toxunur — `DemoContentSeeder`-in yaratdıqlarına. Real
 * imtahan, sual və mövzu qalır.
 *
 * İKİ QORUYUCU:
 *  1. Demo sual REAL imtahanda işlənibsə silinmir (kimsə onu real imtahana əlavə edib);
 *     hesabatda ayrıca göstərilir.
 *  2. Demo mövzuya REAL sual bağlıdırsa mövzu silinmir — əks halda `topic_id` NULL olardı
 *     və həmin sual statistikada mövzusuz qalardı.
 *
 * Cəhdlər ayrıca bayraq daşımır: demo imtahanın VƏ YA demo şagirdin cəhdi silinir. Yəni
 * real şagirdin demo imtahandakı nəticəsi də gedir — o nəticə onsuz da demo məzmundan idi.
 */
class ClearDemoContent extends Command
{
    use ConfirmableTrait;

    protected $signature = 'demo:clear
        {--dry-run : Heç nə silinmir, yalnız nəyin silinəcəyi göstərilir}
        {--force : Produksiyada təsdiq soruşmadan işləsin}';

    protected $description = 'DemoContentSeeder-in yaratdığı bütün demo məzmunu silir (is_demo)';

    public function handle(): int
    {
        $plan = $this->plan();

        $this->table(['Nə silinir', 'Say'], array_map(
            fn (string $label, int $count) => [$label, $count],
            array_keys($plan['counts']),
            array_values($plan['counts']),
        ));

        foreach ($plan['skipped'] as $note) {
            $this->warn($note);
        }

        if (array_sum($plan['counts']) === 0) {
            $this->info('Silinəcək demo məzmun yoxdur.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('--dry-run: heç nə silinmədi.');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed('Demo məzmun silinəcək')) {
            return self::FAILURE;
        }

        DB::transaction(fn () => $this->delete($plan));

        $this->deleteRoadSignImages();

        SitemapController::forget();

        $this->info('Demo məzmun silindi.');

        return self::SUCCESS;
    }

    /**
     * Nümunə yol nişanı SVG-lərini silir — AYRICA TƏSDİQLƏ.
     *
     * Bu addım fayl sisteminə toxunur, ona görə bazadakı silmədən daha ehtiyatlı davranır:
     *
     *  - `testing` mühitində disk SAXTA deyilsə ümumiyyətlə silmir. Testlər produksiya
     *    qovluğunda işləyir (ayrıca mühit yoxdur — ROADMAP P2.5), ona görə fake olmayan
     *    disklə işləyən test real şəkilləri silərdi. Bir dəfə məhz belə oldu.
     *  - Digər mühitlərdə `--force` yoxdursa təsdiq soruşulur: qovluq və fayl sayı göstərilir.
     */
    private function deleteRoadSignImages(): void
    {
        $disk = Storage::disk('public');
        $directory = DemoRoadSigns::DIRECTORY;

        if (! $disk->exists($directory)) {
            return;
        }

        $files = count($disk->files($directory));

        // Saxta disk `storage/framework/testing` altında yaşayır; real disk yox
        $isFake = str_contains((string) $disk->path(''), 'framework'.DIRECTORY_SEPARATOR.'testing');

        if (app()->environment('testing') && ! $isFake) {
            $this->warn(
                'Şəkil qovluğuna toxunulmadı: test mühitində disk saxta deyil. '
                .'`Storage::fake(\'public\')` olmadan bu əmr real faylları silərdi.'
            );

            return;
        }

        if (! $this->option('force')
            && ! $this->confirm("«{$directory}» qovluğundakı {$files} nümunə şəkil silinsin?", false)) {
            $this->warn('Şəkil qovluğu saxlanıldı.');

            return;
        }

        $disk->deleteDirectory($directory);

        $this->line("Silindi: {$files} nümunə şəkil ({$directory}).");
    }

    /**
     * Silinəcəklərin siyahısı. Əvvəlcə hesablanır ki, `--dry-run` də, təsdiq mətni də
     * eyni rəqəmləri göstərsin.
     *
     * @return array{ids: array<string, array<int, int>>, counts: array<string, int>, skipped: array<int, string>}
     */
    private function plan(): array
    {
        $examIds = Exam::withTrashed()->where('is_demo', true)->pluck('id')->all();
        $userIds = User::where('is_demo', true)->pluck('id')->all();

        $attemptIds = DB::table('exam_attempts')
            ->when($examIds !== [], fn ($query) => $query->orWhereIn('exam_id', $examIds))
            ->when($userIds !== [], fn ($query) => $query->orWhereIn('user_id', $userIds))
            ->pluck('id')
            ->all();

        [$questionIds, $keptQuestions] = $this->deletableQuestions($examIds);
        [$topicIds, $keptTopics] = $this->deletableTopics($questionIds);

        $skipped = [];

        if ($keptQuestions > 0) {
            $skipped[] = "{$keptQuestions} demo sual real imtahanda işləndiyi üçün saxlanıldı.";
        }

        if ($keptTopics > 0) {
            $skipped[] = "{$keptTopics} demo mövzuya real sual bağlı olduğu üçün saxlanıldı.";
        }

        return [
            'ids' => [
                'exams' => $examIds,
                'users' => $userIds,
                'attempts' => $attemptIds,
                'questions' => $questionIds,
                'topics' => $topicIds,
            ],
            'counts' => [
                'İmtahan' => count($examIds),
                'Bölmə' => $this->count('exam_sections', 'exam_id', $examIds),
                'Sual' => count($questionIds),
                'Sual variantı' => $this->count('question_options', 'question_id', $questionIds),
                'Mövzu' => count($topicIds),
                'Cəhd' => count($attemptIds),
                'Cavab' => $this->count('attempt_answers', 'attempt_id', $attemptIds),
                'Giriş hüququ' => $this->accessCount($examIds, $userIds),
                'Ödəniş' => $this->paymentCount($examIds, $userIds),
                'Demo şagird' => count($userIds),
            ],
            'skipped' => $skipped,
        ];
    }

    /**
     * Silinə bilən demo suallar: REAL (demo olmayan) imtahanda işlənənlər saxlanılır.
     *
     * @param  array<int, int>  $demoExamIds
     * @return array{0: array<int, int>, 1: int}
     */
    private function deletableQuestions(array $demoExamIds): array
    {
        $all = Question::where('is_demo', true)->pluck('id')->all();

        if ($all === []) {
            return [[], 0];
        }

        $usedElsewhere = DB::table('exam_question')
            ->whereIn('question_id', $all)
            ->when($demoExamIds !== [], fn ($query) => $query->whereNotIn('exam_id', $demoExamIds))
            ->distinct()
            ->pluck('question_id')
            ->all();

        $deletable = array_values(array_diff($all, $usedElsewhere));

        return [$deletable, count($usedElsewhere)];
    }

    /**
     * Silinə bilən demo mövzular: silinməyən (real) suallar bağlıdırsa mövzu qalır.
     *
     * @param  array<int, int>  $deletableQuestionIds
     * @return array{0: array<int, int>, 1: int}
     */
    private function deletableTopics(array $deletableQuestionIds): array
    {
        $all = Topic::where('is_demo', true)->pluck('id')->all();

        if ($all === []) {
            return [[], 0];
        }

        $stillUsed = DB::table('questions')
            ->whereIn('topic_id', $all)
            ->when($deletableQuestionIds !== [], fn ($query) => $query->whereNotIn('id', $deletableQuestionIds))
            ->distinct()
            ->pluck('topic_id')
            ->all();

        $deletable = array_values(array_diff($all, $stillUsed));

        return [$deletable, count($stillUsed)];
    }

    /** @param  array{ids: array<string, array<int, int>>}  $plan */
    private function delete(array $plan): void
    {
        ['exams' => $examIds, 'users' => $userIds, 'attempts' => $attemptIds,
            'questions' => $questionIds, 'topics' => $topicIds] = $plan['ids'];

        // Cəhd zənciri: cavablar → dondurulmuş suallar → bölmə nəticələri → cəhdlər
        if ($attemptIds !== []) {
            DB::table('attempt_answers')->whereIn('attempt_id', $attemptIds)->delete();
            DB::table('attempt_questions')->whereIn('attempt_id', $attemptIds)->delete();
            DB::table('attempt_sections')->whereIn('attempt_id', $attemptIds)->delete();
            DB::table('exam_attempts')->whereIn('id', $attemptIds)->delete();
        }

        // Alış zənciri: giriş hüququ ödənişə istinad etdiyi üçün əvvəl o silinir
        DB::table('exam_accesses')
            ->when($examIds !== [], fn ($query) => $query->orWhereIn('exam_id', $examIds))
            ->when($userIds !== [], fn ($query) => $query->orWhereIn('user_id', $userIds))
            ->delete();

        DB::table('payments')
            ->when($examIds !== [], fn ($query) => $query->orWhere(fn ($sub) => $sub
                ->where('purchasable_type', Exam::class)
                ->whereIn('purchasable_id', $examIds)))
            ->when($userIds !== [], fn ($query) => $query->orWhereIn('user_id', $userIds))
            ->delete();

        if ($examIds !== []) {
            DB::table('exam_tag')->whereIn('exam_id', $examIds)->delete();
            DB::table('exam_question')->whereIn('exam_id', $examIds)->delete();
            DB::table('exam_sections')->whereIn('exam_id', $examIds)->delete();
            Exam::withTrashed()->whereIn('id', $examIds)->forceDelete();
        }

        if ($questionIds !== []) {
            DB::table('exam_question')->whereIn('question_id', $questionIds)->delete();
            DB::table('question_options')->whereIn('question_id', $questionIds)->delete();
            DB::table('questions')->whereIn('id', $questionIds)->delete();
        }

        if ($topicIds !== []) {
            DB::table('topics')->whereIn('id', $topicIds)->delete();
        }

        if ($userIds !== []) {
            DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->whereIn('model_id', $userIds)
                ->delete();

            DB::table('users')->whereIn('id', $userIds)->delete();
        }
    }

    /** @param  array<int, int>  $ids */
    private function count(string $table, string $column, array $ids): int
    {
        return $ids === [] ? 0 : DB::table($table)->whereIn($column, $ids)->count();
    }

    /**
     * @param  array<int, int>  $examIds
     * @param  array<int, int>  $userIds
     */
    private function accessCount(array $examIds, array $userIds): int
    {
        if ($examIds === [] && $userIds === []) {
            return 0;
        }

        return DB::table('exam_accesses')
            ->when($examIds !== [], fn ($query) => $query->orWhereIn('exam_id', $examIds))
            ->when($userIds !== [], fn ($query) => $query->orWhereIn('user_id', $userIds))
            ->count();
    }

    /**
     * @param  array<int, int>  $examIds
     * @param  array<int, int>  $userIds
     */
    private function paymentCount(array $examIds, array $userIds): int
    {
        if ($examIds === [] && $userIds === []) {
            return 0;
        }

        return DB::table('payments')
            ->when($examIds !== [], fn ($query) => $query->orWhere(fn ($sub) => $sub
                ->where('purchasable_type', Exam::class)
                ->whereIn('purchasable_id', $examIds)))
            ->when($userIds !== [], fn ($query) => $query->orWhereIn('user_id', $userIds))
            ->count();
    }
}
