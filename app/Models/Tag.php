<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * İmtahan etiketi.
 *
 * Kateqoriya ağacına ORTOQONALDIR: kateqoriya imtahanın növünü bildirir, etiket isə
 * əlavə əlaməti — ən əsası sinif səviyyəsini (2-ci … 11-ci sinif). Bir imtahanda
 * neçə etiket ola bilər.
 */
class Tag extends Model
{
    /** Sinif səviyyəsi — kataloqda ayrıca filtr bölməsi və kartda nişan */
    public const KIND_GRADE = 'grade';

    /** Sərbəst etiket (mövzu, format, hazırlıq səviyyəsi …) */
    public const KIND_OTHER = 'other';

    public const KINDS = [self::KIND_GRADE, self::KIND_OTHER];

    protected $fillable = ['slug', 'name', 'kind', 'order', 'is_active'];

    protected $casts = [
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class)->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeGrades(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_GRADE);
    }

    /** Filtr panelində və admin siyahısında eyni sıra */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('CASE WHEN kind = ? THEN 0 ELSE 1 END', [self::KIND_GRADE])
            ->orderBy('order')
            ->orderBy('name');
    }

    public function isGrade(): bool
    {
        return $this->kind === self::KIND_GRADE;
    }
}
