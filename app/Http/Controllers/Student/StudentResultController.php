<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Şagirdin nəticə siyahısı.
 *
 * Məlumat AÇIQ şəkildə qurulur, model bütöv göndərilmir: əvvəl şablon `attempt.score` və
 * `attempt.total_questions` sahələrini oxuyurdu, halbuki `exam_attempts` cədvəlində belə
 * sütun yoxdur — "Bal" və "ümumi" sütunları boş görünürdü.
 */
class StudentResultController extends Controller
{
    public function index(): Response
    {
        $attempts = auth()->user()->examAttempts()
            // Kateqoriya zənciri: siyahıda bölmə adı və rəngi göstərilir
            ->with(['exam:id,slug,title,subject_id,category_id', 'exam.subject:id,name', 'exam.category.parent.parent'])
            /*
             * `completed()` yalnız tam bitmiş cəhdləri verirdi, yəni yazılı cavabı yoxlanan
             * cəhd siyahıda ümumiyyətlə görünmürdü. İndi vaxtı bitənlər və yoxlama gözləyənlər
             * də var — sonuncular "ilkin" nişanı ilə.
             */
            ->whereIn('status', [
                ExamAttempt::STATUS_COMPLETED,
                ExamAttempt::STATUS_TIMED_OUT,
                ExamAttempt::STATUS_PENDING_REVIEW,
            ])
            ->latest()
            ->paginate(15)
            ->through(fn (ExamAttempt $attempt) => [
                'id' => $attempt->id,
                'url' => route('student.exams.result', $attempt),
                'title' => $attempt->exam?->title,
                'subject' => $attempt->exam?->subject?->name,
                'trail' => $attempt->exam?->category?->trail(),
                // Nisbi bal (100-lük) fənlər arasında müqayisə oluna bilən yeganə ölçüdür
                'relative_score' => (float) $attempt->relative_score,
                'correct_answers' => $attempt->correct_answers,
                'wrong_answers' => $attempt->wrong_answers,
                'unanswered' => $attempt->unanswered,
                'questions' => $attempt->correct_answers + $attempt->wrong_answers + $attempt->unanswered,
                'finished_at' => $attempt->finished_at?->format('d.m.Y H:i'),
                // Yazılı cavablar yoxlanmayıbsa bal ilkindir
                'awaiting_review' => $attempt->status === ExamAttempt::STATUS_PENDING_REVIEW,
            ]);

        return Inertia::render('Student/Results/Index', [
            'attempts' => $attempts,
        ]);
    }
}
