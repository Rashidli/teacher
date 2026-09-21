<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'roman_numeral', 'number', 'description', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'number' => 'integer',
    ];

    public function subjectScores(): HasMany
    {
        return $this->hasMany(SubjectGroupScore::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
