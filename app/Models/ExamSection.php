<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * İmtahanın bir fənn üzrə bölməsi. Tək-fənli imtahanın da bir bölməsi olur —
 * bal hesablaması və səhifələr hər imtahan üçün eyni məntiqlə işləyir.
 */
class ExamSection extends Model
{
    use HasFactory;

    protected $fillable = ['exam_id', 'subject_id', 'title', 'question_count', 'max_score', 'order'];

    protected $casts = [
        'question_count' => 'integer',
        'max_score' => 'decimal:2',
        'order' => 'integer',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** Bölmənin sualları (bankdan, pivot sırası ilə) */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_question', 'section_id', 'question_id')
            ->withPivot('order')
            ->withTimestamps()
            ->orderBy('exam_question.order');
    }

    public function displayTitle(): string
    {
        return $this->title ?: ($this->subject?->name ?? 'Bölmə');
    }
}
