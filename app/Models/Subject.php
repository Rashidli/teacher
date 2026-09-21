<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'category', 'icon', 'is_active', 'order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot(['question_count', 'options_per_question', 'max_score', 'order'])
            ->withTimestamps();
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_teacher');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('order')->orderBy('name');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function groupScores(): HasMany
    {
        return $this->hasMany(SubjectGroupScore::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHumanitarian($query)
    {
        return $query->where('category', 'humanitarian');
    }

    public function scopeTechnical($query)
    {
        return $query->where('category', 'technical');
    }
}
