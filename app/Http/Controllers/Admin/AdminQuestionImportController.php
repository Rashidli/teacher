<?php

namespace App\Http\Controllers\Admin;

use App\Exports\QuestionTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\QuestionImport\ImportedQuestionRow;
use App\Services\QuestionImport\QuestionImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Toplu sual importu: fayl yüklənir → sətir-sətir önizləmə və xətalar → təsdiqdən sonra yazma.
 *
 * Fayl önizləmə ilə təsdiq arasında `storage/app/private/question-imports` altında saxlanılır
 * (sessiyada saxlamaq böyük fayllarda uyğun deyil) və istifadədən sonra dərhal silinir.
 */
class AdminQuestionImportController extends Controller
{
    private const DISK = 'local';

    private const DIRECTORY = 'question-imports';

    public function __construct(private readonly QuestionImportService $import)
    {
    }

    public function create(Exam $exam): Response
    {
        return Inertia::render('Admin/Questions/Import', [
            'exam' => $exam->load('subject'),
            'rows' => null,
            'token' => null,
        ]);
    }

    /** İmtahana uyğun .xlsx şablonu (nümunə sətirlər + izah vərəqi) */
    public function template(Exam $exam): BinaryFileResponse
    {
        $name = 'sual-sablonu-'.Str::slug($exam->title).'.xlsx';

        return Excel::download(new QuestionTemplateExport($exam), $name);
    }

    /** Fayl oxunur və yoxlanır; baza dəyişmir. */
    public function preview(Request $request, Exam $exam): Response
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ], [
            'file.mimes' => 'Yalnız .xlsx, .xls və ya UTF-8 .csv faylı yükləyin.',
            'file.max' => 'Fayl 10 MB-dan böyük olmamalıdır.',
        ]);

        $this->pruneAbandonedUploads();

        $path = $validated['file']->store(self::DIRECTORY, self::DISK);
        $rows = $this->import->parse(Storage::disk(self::DISK)->path($path), $exam);

        return Inertia::render('Admin/Questions/Import', [
            'exam' => $exam->load('subject'),
            'rows' => collect($rows)->map(fn (ImportedQuestionRow $row) => $row->toArray())->all(),
            'token' => basename($path),
        ]);
    }

    /** Təsdiq: fayl yenidən oxunur, xəta yoxdursa bir tranzaksiyada yazılır. */
    public function store(Request $request, Exam $exam): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $path = self::DIRECTORY.'/'.basename($validated['token']);

        if (! Storage::disk(self::DISK)->exists($path)) {
            return back()->withErrors([
                'file' => 'Yüklənmiş fayl tapılmadı — yenidən yükləyin.',
            ]);
        }

        $rows = $this->import->parse(Storage::disk(self::DISK)->path($path), $exam);

        $invalid = collect($rows)->reject(fn (ImportedQuestionRow $row) => $row->isValid());

        if ($rows === [] || $invalid->isNotEmpty()) {
            return back()->withErrors([
                'file' => $rows === []
                    ? 'Faylda sual tapılmadı.'
                    : 'Faylda xətalı sətirlər var: heç bir sual yazılmadı.',
            ]);
        }

        $count = $this->import->import($exam, $rows);

        Storage::disk(self::DISK)->delete($path);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', "{$count} sual import edildi.");
    }

    /**
     * Önizləməsi təsdiqlənməyən yükləmələr diskdə qalır: bir gündən köhnələri silinir.
     */
    private function pruneAbandonedUploads(): void
    {
        $disk = Storage::disk(self::DISK);
        $cutoff = now()->subDay()->getTimestamp();

        foreach ($disk->files(self::DIRECTORY) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }
}
