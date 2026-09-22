<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSection;
use App\Services\Payment\ExamAccessService;
use App\Services\Payment\PaymentGatewayFactory;
use App\Support\Localization;
use App\Support\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * İctimai imtahan səhifəsi: `/imtahan/{slug}`.
 *
 * Kataloqdan (kateqoriya səhifəsi) bura gəlinir. Səhifə qonağa da açıqdır — imtahanın nə
 * olduğu giriş tələb etmədən görünsün, axtarış sistemləri də indeksləyə bilsin.
 *
 * Kabinetdə ayrıca imtahan səhifəsi YOXDUR: `student.exams.show` bura yönləndirir.
 * Daxil olmuş şagird eyni səhifədə öz vəziyyətini görür (giriş var/yox, davam edən cəhd).
 */
class ExamController extends Controller
{
    public function __construct(
        private readonly ExamAccessService $access,
        private readonly PaymentGatewayFactory $gateways,
    ) {
    }

    public function show(Request $request, Exam $exam): Response
    {
        $this->ensureVisible($request, $exam);

        $exam->load(['category', 'sections.subject:id,name']);

        $student = $request->user()?->hasRole('student') ? $request->user() : null;

        $this->shareSeo($request, $exam);

        return Inertia::render('Exam/Show', [
            'exam' => [
                'slug' => $exam->slug,
                'title' => $exam->title,
                'description' => $exam->description,
                'kind' => $exam->kind,
                'quarter' => $exam->quarter,
                'duration_minutes' => $exam->duration_minutes,
                'question_count' => (int) $exam->sections->sum('question_count'),
                'max_score' => (float) $exam->sections->sum('max_score'),
                'is_free' => $exam->is_free,
                'price' => $exam->price,
                'sections' => $exam->sections->map(fn (ExamSection $section) => [
                    'title' => $section->displayTitle(),
                    'subject' => $section->subject?->name,
                    'question_count' => $section->question_count,
                    'max_score' => $section->max_score,
                ])->all(),
            ],
            'breadcrumb' => $this->breadcrumb($exam),
            'status' => $this->status($exam, $student),
            'purchasesEnabled' => $this->gateways->available(),
            'meta' => [
                'title' => $exam->title,
                'description' => $exam->description,
            ],
        ]);
    }

    /**
     * Qonaq "Başla"/"Al" basanda gəldiyi ünvan.
     *
     * Route `auth` middleware-i altındadır: qonaq üçün Laravel intended URL-i saxlayıb
     * `/login`-ə atır, girişdən sonra şagird bura qayıdır. Burada cəhd BAŞLAMIR —
     * şagird imtahan səhifəsinə qayıdır və "Başla"nı özü təsdiqləyir (taymer o an başlayır).
     */
    public function enter(Request $request, Exam $exam): RedirectResponse
    {
        $this->ensureVisible($request, $exam);

        return redirect()->to($exam->publicUrl());
    }

    /**
     * Dərc olunmamış imtahanın səhifəsi yoxdur.
     *
     * Sektor yoxlaması yalnız DAXİL OLMUŞ şagirdə tətbiq olunur — kabineti öz sektorundadır,
     * başqa sektorun imtahanını aça bilməz. Qonaq üçün səhifə hər iki dildə açıq qalır:
     * əks halda eyni ünvan sessiyadakı sektordan asılı olaraq gah 200, gah 404 verərdi və
     * sitemap-dakı hreflang cütü (az ↔ ru) sınardı. Kataloqun özü sektora görə süzülür.
     */
    private function ensureVisible(Request $request, Exam $exam): void
    {
        abort_unless($exam->is_published && $exam->is_active, 404);

        $student = $request->user();

        if ($student?->hasRole('student')) {
            abort_unless($exam->sector === ($student->sector ?? Sector::AZ), 404);
        }
    }

    /**
     * Düymənin vəziyyəti. Qonaq üçün hədəf `exam.enter`, şagird üçün kabinet əməliyyatları.
     *
     * @return array<string, mixed>
     */
    private function status(Exam $exam, ?\App\Models\User $student): array
    {
        if ($student === null) {
            return [
                'state' => 'guest',
                'enterUrl' => Localization::route('exam.enter', ['exam' => $exam->slug]),
            ];
        }

        $activeAttempt = $student->examAttempts()
            ->where('exam_id', $exam->id)
            ->inProgress()
            ->first();

        if ($activeAttempt && $activeAttempt->remaining_time > 0) {
            return [
                'state' => 'in_progress',
                'attemptUrl' => route('student.exams.attempt', $activeAttempt),
                'remaining_minutes' => (int) ceil($activeAttempt->remaining_time / 60),
            ];
        }

        $completed = $student->examAttempts()
            ->where('exam_id', $exam->id)
            ->completed()
            ->latest()
            ->first();

        return [
            'state' => $this->access->allows($student, $exam) ? 'ready' : 'locked',
            'startUrl' => route('student.exams.start', $exam),
            'purchaseUrl' => route('student.exams.purchase', $exam),
            'lastResultUrl' => $completed ? route('student.exams.result', $completed) : null,
        ];
    }

    /** @return array<int, array{name: string, url: string}> */
    private function breadcrumb(Exam $exam): array
    {
        $locale = app()->getLocale();

        $items = [['name' => __('category_page.home'), 'url' => Localization::route('home', [], true, $locale)]];

        if ($exam->category) {
            foreach ($exam->category->ancestors()->push($exam->category) as $node) {
                $items[] = ['name' => $node->localized('name'), 'url' => $node->urlFor($locale)];
            }
        }

        $items[] = ['name' => $exam->title, 'url' => $exam->publicUrl($locale)];

        return $items;
    }

    /**
     * Canonical və JSON-LD. Kateqoriya səhifəsindəki qayda ilə eyni: sorğu atributlarında
     * saxlanılır, `seo` prop-u üzərinə yazılmır.
     *
     * İmtahanın dil variantı yoxdur (məzmun tərcümə olunmur), ona görə hreflang də yazılmır:
     * canonical həmişə sektorun əsas ünvanına göstərir. Səhifə digər dil prefiksi ilə də
     * açılır — interfeys dilini dəyişmək üçün.
     */
    private function shareSeo(Request $request, Exam $exam): void
    {
        $request->attributes->set(Localization::CANONICAL_ATTRIBUTE, $exam->canonicalUrl());
        $request->attributes->set(Localization::JSON_LD_ATTRIBUTE, [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($this->breadcrumb($exam))
                ->map(fn (array $item, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ])->all(),
        ]);
    }
}
