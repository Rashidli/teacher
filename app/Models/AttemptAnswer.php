<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptAnswer extends Model
{
    /** Qiyməti avtomatik (AI) verib — admin onu dəyişə bilər */
    public const GRADE_SOURCE_AI = 'ai';

    /** Qiyməti admin əl ilə verib — AI job-u ona toxunmur */
    public const GRADE_SOURCE_ADMIN = 'admin';

    protected $fillable = [
        'attempt_id', 'question_id', 'selected_option_id', 'open_answer', 'is_correct',
        'grade_ratio', 'grade_source', 'grade_comment', 'graded_by', 'graded_at', 'score_earned',
        'ai_model', 'ai_input_tokens', 'ai_output_tokens', 'ai_graded_at', 'review_requested_at',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'score_earned' => 'decimal:2',
        'grade_ratio' => 'float',
        'graded_at' => 'datetime',
        'ai_graded_at' => 'datetime',
        'review_requested_at' => 'datetime',
        'ai_input_tokens' => 'integer',
        'ai_output_tokens' => 'integer',
    ];

    /** Qiymət avtomatik verilib və admin hələ baxmayıb */
    public function gradedByAi(): bool
    {
        return $this->grade_source === self::GRADE_SOURCE_AI;
    }

    /** Yazılı cavabı qiymətləndirən admin */
    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'selected_option_id');
    }
}
