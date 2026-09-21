<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory, SoftDeletes;

    /** Tam model sınaq imtahanı */
    public const KIND_GENERAL = 'general';

    /** Rüb üzrə mövzu sınağı */
    public const KIND_TOPIC_TRIAL = 'topic_trial';

    /** Tək fənn üzrə sınaq */
    public const KIND_SUBJECT = 'subject';

    /** Məşq testi (taymersiz) */
    public const KIND_PRACTICE = 'practice';

    public const KINDS = [self::KIND_GENERAL, self::KIND_TOPIC_TRIAL, self::KIND_SUBJECT, self::KIND_PRACTICE];


    protected $fillable = [
        'teacher_id', 'subject_id', 'group_id', 'category_id', 'kind', 'sector', 'quarter', 'is_cumulative',
        'title', 'description',
        'duration_minutes', 'options_per_question', 'price', 'is_free', 'is_active', 'is_published',
        'published_at', 'created_by_admin'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_free' => 'boolean',
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'duration_minutes' => 'integer',
        'options_per_question' => 'integer',
        'created_by_admin' => 'boolean',
        'quarter' => 'integer',
        'is_cumulative' => 'boolean',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** İmtahanın fənn bölmələri (tək-fənli imtahanda da bir bölmə olur) */
    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class)->orderBy('order');
    }

    /** İmtahanın sualları bankdan gəlir; sıra pivotdadır */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class)
            ->withPivot(['order', 'section_id'])
            ->withTimestamps()
            ->orderBy('exam_question.section_id')
            ->orderBy('exam_question.order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }


    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSector($query, string $sector)
    {
        return $query->where('sector', $sector);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeFree($query)
    {
        return $query->where('is_free', true);
    }

    public function scopeBySubject($query, $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeByGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    public function scopeByTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }
}
