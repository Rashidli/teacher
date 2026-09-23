<?php

namespace Database\Seeders\Demo;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\Scoring\AttemptScorer;
use App\Support\CodedAnswer;
use App\Support\Sector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo şagirdlər və onların cəhdləri.
 *
 * Şagird kabineti, nəticə səhifəsi və statistika (fənn üzrə irəliləyiş, zəif mövzular)
 * yalnız TAMAMLANMIŞ cəhd olanda dolur — ona görə hər sektor üçün bir demo şagird yaradılır
 * və ona bir neçə cəhd yazılır.
 *
 * Cəhd real axınla qurulur: sual siyahısı `attempt_questions`-a dondurulur, cavablar yazılır,
 * sonra `AttemptScorer` işlədilir. Yəni ballar uydurulmur, sistemin öz düsturu ilə hesablanır.
 *
 * Parol HƏR İŞƏ SALMADA yenidən təsadüfi qurulur və yalnız seeder çıxışında göstərilir —
 * nə kodda, nə də commit-də saxlanılır.
 */
class DemoStudentBuilder
{
    /** Hər demo şagird üçün cəhd sayı */
    private const ATTEMPTS_PER_STUDENT = 10;

    /** Hər neçənci cəhd yoxlanmamış yazılı cavabla qalsın (admin qiymətləndirmə ekranı üçün) */
    private const PENDING_EVERY = 5;

    /** @var array<int, array{email: string, password: string, sector: string}> */
    public array $credentials = [];

    public array $stats = ['students' => 0, 'attempts_created' => 0, 'accesses' => 0];

    public function __construct(private readonly AttemptScorer $scorer) {}

    /** @param  array<int, Exam>  $exams */
    public function build(array $exams, ?int $adminId): void
    {
        foreach (Sector::ALL as $sector) {
            $sectorExams = array_values(array_filter(
                $exams,
                fn (Exam $exam) => $exam->sector === $sector,
            ));

            if ($sectorExams === []) {
                continue;
            }

            $student = $this->student($sector);
            $this->attempts($student, $sectorExams, $adminId);
        }
    }

    private function student(string $sector): User
    {
        $email = "demo.{$sector}@example.test";
        $password = Str::password(14, symbols: false);

        $student = User::firstOrNew(['email' => $email]);

        $student->fill([
            'first_name' => 'Demo',
            'last_name' => $sector === Sector::RU ? 'Ученик' : 'Şagird',
            'password' => $password,
            'locale' => $sector,
            'sector' => $sector,
            'is_active' => true,
            'is_demo' => true,
        ]);

        $student->email_verified_at ??= now();
        $isNew = ! $student->exists;
        $student->save();

        if (! $student->hasRole('student')) {
            $student->assignRole('student');
        }

        $this->stats['students'] += $isNew ? 1 : 0;
        $this->credentials[] = ['email' => $email, 'password' => $password, 'sector' => $sector];

        return $student;
    }

    /**
     * Cəhdlər. Kateqoriya rəngarəngliyi üçün imtahanlar siyahıdan addımla seçilir,
     * beləcə statistikada bir neçə fənn və bir neçə kateqoriya görünür.
     *
     * @param  array<int, Exam>  $exams
     */
    private function attempts(User $student, array $exams, ?int $adminId): void
    {
        /*
         * Addım TƏK ədəd seçilir. İmtahanlar düyün-düyün, növ sırası ilə düzülüb (ümumi,
         * mövzu, fənn, məşq), ona görə cüt addım həmişə eyni cütlüyə — yalnız pulsuz
         * imtahanlara — düşərdi. Tək addım dördlüyü dövrə vurur: cəhdlər pullu imtahanları
         * da əhatə edir, deməli giriş hüququ axını da yoxlanıla bilir.
         */
        $step = max(1, intdiv(count($exams), self::ATTEMPTS_PER_STUDENT)) | 1;
        $taken = 0;

        for ($i = 0; $taken < self::ATTEMPTS_PER_STUDENT && $i < count($exams); $i += $step) {
            $exam = $exams[$i];

            if ($exam->questions()->count() === 0) {
                continue;
            }

            $this->grantAccess($student, $exam, $adminId);

            // Bu imtahanda artıq demo cəhd varsa yenidən yaradılmır (idempotentlik)
            $existing = ExamAttempt::where('user_id', $student->id)->where('exam_id', $exam->id)->exists();

            if (! $existing) {
                $this->attempt($student, $exam, $taken);
                $this->stats['attempts_created']++;
            }

            $taken++;
        }
    }

    /** Pullu imtahan üçün giriş hüququ — əks halda cəhd real axında yaradıla bilməzdi */
    private function grantAccess(User $student, Exam $exam, ?int $adminId): void
    {
        if ($exam->is_free) {
            return;
        }

        $access = ExamAccess::firstOrNew(['user_id' => $student->id, 'exam_id' => $exam->id]);

        if ($access->exists) {
            return;
        }

        $access->fill([
            'source' => ExamAccess::SOURCE_MANUAL,
            'granted_by' => $adminId,
            'note' => 'Demo məzmun (php artisan demo:clear ilə silinir)',
        ])->save();

        $this->stats['accesses']++;
    }

    /** Bir cəhd: sualları dondur, cavabla, sonra sistemin öz hesablaması ilə qiymətləndir */
    private function attempt(User $student, Exam $exam, int $index): void
    {
        $pendingReview = ($index + 1) % self::PENDING_EVERY === 0;
        $startedAt = now()->subDays(60 - $index * 5)->setTime(10, 0);

        $attempt = DB::transaction(function () use ($student, $exam, $startedAt) {
            $attempt = ExamAttempt::create([
                'user_id' => $student->id,
                'exam_id' => $exam->id,
                'group_id' => $exam->group_id,
                'status' => ExamAttempt::STATUS_IN_PROGRESS,
                'started_at' => $startedAt,
                'finished_at' => $startedAt->copy()->addMinutes(max(5, (int) round($exam->duration_minutes * 0.8))),
                'time_spent_seconds' => (int) round($exam->duration_minutes * 0.8 * 60),
            ]);

            $snapshot = $exam->questions()->get()
                ->mapWithKeys(fn (Question $question, int $order) => [
                    $question->id => [
                        'section_id' => $question->pivot->section_id,
                        'order' => $question->pivot->order ?: $order + 1,
                    ],
                ])
                ->all();

            $attempt->questions()->attach($snapshot);

            return $attempt;
        });

        $this->answer($attempt, $index, $pendingReview);
        $this->scorer->score($attempt->fresh());
    }

    /**
     * Cavablar. Nəticə deterministikdir, amma şagirdlər arasında fərqlidir: hər 3-cü sual
     * səhv, hər 7-ci boş buraxılır — beləcə statistikada zəif mövzular da, güclü mövzular
     * da görünür.
     */
    private function answer(ExamAttempt $attempt, int $index, bool $pendingReview): void
    {
        $questions = $attempt->questions()->with('options')->get();

        foreach ($questions->values() as $position => $question) {
            $seed = $position + $index;

            // Hər 7-ci sual boş qalır: "cavabsız" göstəricisi də real olsun
            if ($seed % 7 === 6) {
                continue;
            }

            $correct = $seed % 3 !== 2;

            $attempt->answers()->create($this->answerFor($question, $correct, $pendingReview));
        }
    }

    /** @return array<string, mixed> */
    private function answerFor(Question $question, bool $correct, bool $pendingReview): array
    {
        $base = ['question_id' => $question->id];

        if ($question->type === Question::TYPE_MULTIPLE_CHOICE) {
            $option = $correct
                ? $question->options->firstWhere('is_correct', true)
                : $question->options->firstWhere('is_correct', false);

            return $base + ['selected_option_id' => ($option ?? $question->options->first())?->id];
        }

        /*
         * Kodlaşdırılan tapşırıq: cavab KOD kimi yazılır (bax `App\Support\CodedAnswer`).
         * Düzgün cavab hesablanır, yanlış cavab isə ondan fərqli olan hər hansı koddur —
         * belə olmasa demo nəticələrdə bu tapşırıqlar həmişə "düz" görünərdi.
         */
        if ($question->type === Question::TYPE_OPEN_CODED) {
            if ($question->codedSubtype() === Question::CODED_NUMERIC) {
                $accepted = (array) ($question->accepted_answers ?? []);

                return $base + ['open_answer' => $correct ? (string) ($accepted[0] ?? '1') : 'demo'];
            }

            $code = (string) CodedAnswer::correct($question);

            return $base + ['open_answer' => $correct ? $code : $this->wrongCode($question, $code)];
        }

        // Yazılı cavab: qiymətləndirilməyəndə cəhd `pending_review` olur (admin ekranı üçün)
        return $base + [
            'open_answer' => 'Demo cavab: anlayışın tərifi verilir, ardınca mövzuya uyğun '
                .'bir nümunə göstərilir və nəticə bir cümlə ilə ümumiləşdirilir.',
            'grade_ratio' => $pendingReview ? null : ($correct ? 1 : 0.5),
            'graded_at' => $pendingReview ? null : now(),
        ];
    }

    /**
     * Düzgün koddan zəmanətli fərqlənən cavab: ilk iki bənd yerini dəyişir.
     * Bir bəndli kod ola bilməz (hər alt növdə ən azı iki bənd var), amma ehtiyat üçün
     * belə halda sadəcə boş cavab qaytarılır — o da yanlış sayılır.
     */
    private function wrongCode(Question $question, string $code): string
    {
        $parts = explode(CodedAnswer::SEPARATOR, $code);

        if (count($parts) < 2) {
            return '';
        }

        [$parts[0], $parts[1]] = [$parts[1], $parts[0]];

        $wrong = implode(CodedAnswer::SEPARATOR, $parts);

        // Seçimdə sıra əhəmiyyətsizdir: yer dəyişmək cavabı dəyişmir, ona görə bənd atılır
        return CodedAnswer::matches($question, $wrong) ? $parts[0] : $wrong;
    }
}
