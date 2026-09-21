<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Group extends Model
{
    use HasFactory;

    /** groups.stage — yanlış cavabın cəriməsini müəyyən edir (config/scoring.php) */
    public const STAGE_SECOND = 'second_stage';

    public const STAGE_FIRST = 'first_stage';

    public const STAGE_FINAL = 'final';

    public const STAGE_APTITUDE = 'aptitude';

    protected $fillable = [
        'parent_id', 'code', 'name', 'roman_numeral', 'stage', 'is_testable',
        'number', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_testable' => 'boolean',
        'number' => 'integer',
    ];

    /** Altqrup (I-RK, III-DT …) üçün baş qrup */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Group::class, 'parent_id');
    }

    /** Ballar baş qrupda saxlanılır: altqrupun balı valideynindir. */
    public function scoringGroup(): Group
    {
        return $this->parent ?? $this;
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function subjectScores(): HasMany
    {
        return $this->hasMany(SubjectGroupScore::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /** Ağacda bu qrupu göstərən kateqoriya düyünü (varsa) */
    public function category(): HasOne
    {
        return $this->hasOne(Category::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
