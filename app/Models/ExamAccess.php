<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Şagirdin imtahana giriş hüququ. Mənbə: ödəniş, adminin əl ilə verdiyi icazə və ya pulsuz imtahan.
 *
 * Ləğv sətri silmir (`revoked_at`): qeyd, kimin verdiyi və ödəniş bağlantısı audit üçün qalır.
 */
class ExamAccess extends Model
{
    use HasFactory;

    public const SOURCE_PAYMENT = 'payment';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_FREE = 'free';

    protected $fillable = [
        'user_id', 'exam_id', 'source', 'payment_id', 'expires_at',
        'attempts_allowed', 'granted_by', 'note', 'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'attempts_allowed' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** İcazəni verən admin (yalnız əl ilə veriləndə dolur) */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** Ləğv edilməyib və müddəti keçməyib (expires_at = null → müddətsiz) */
    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
    }
}
