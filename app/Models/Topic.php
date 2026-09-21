<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fənn mövzusu. `quarter` (1–4) rüb üzrə mövzu sınağı üçündür; rübə bağlı olmayan
 * mövzularda (sürücülük, dövlət qulluğu) null qalır.
 */
class Topic extends Model
{
    use HasFactory;

    protected $fillable = ['subject_id', 'name', 'slug', 'quarter', 'order', 'is_active'];

    protected $casts = [
        'quarter' => 'integer',
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForQuarter(Builder $query, int $quarter, bool $cumulative = false): Builder
    {
        return $cumulative
            ? $query->where('quarter', '<=', $quarter)
            : $query->where('quarter', $quarter);
    }
}
