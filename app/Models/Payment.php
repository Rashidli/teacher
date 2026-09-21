<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Ödəniş. Polimorfdur: hazırda yalnız Exam satılır, sonra paket/abunə əlavə oluna bilər.
 *
 * Status yalnız irəli gedir: pending → paid | failed, paid → refunded.
 * Geri qayıdış yoxdur — bank iki dəfə callback göndərsə status dəyişmir.
 */
class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    /** @var array<string, array<int, string>> */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_PAID, self::STATUS_FAILED],
        self::STATUS_PAID => [self::STATUS_REFUNDED],
        self::STATUS_FAILED => [],
        self::STATUS_REFUNDED => [],
    ];

    protected $fillable = [
        'user_id', 'purchasable_type', 'purchasable_id', 'amount', 'currency',
        'status', 'provider', 'provider_ref', 'payload', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::ALLOWED_TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Statusu dəyişir. İcazəsiz keçid (məs. paid → pending) xəta verir — təkrar callback
     * ödənişi geri qaytara bilməsin.
     */
    public function transitionTo(string $status, array $attributes = []): void
    {
        if (! $this->canTransitionTo($status)) {
            throw new RuntimeException(
                "Ödəniş statusu \"{$this->status}\" vəziyyətindən \"{$status}\" vəziyyətinə keçə bilməz."
            );
        }

        $this->update(array_merge($attributes, [
            'status' => $status,
            'paid_at' => $status === self::STATUS_PAID ? now() : $this->paid_at,
        ]));
    }
}
