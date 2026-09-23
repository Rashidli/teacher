<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mətn və ya mənbə: DİM-in "mətnə əsaslanan" və "mənbəyə əsaslanan" yazılı tapşırıqlarında
 * bir mətnə BİR NEÇƏ sual bağlanır (III qrupda dil/ədəbiyyat, II–III qrupda tarix).
 *
 * Mətn sualın özündə saxlanılmır: eyni mətn hər sualda təkrarlanardı və düzəliş ediləndə
 * bir-birindən ayrılardı.
 */
class Passage extends Model
{
    use HasFactory;

    protected $fillable = ['language', 'title', 'body', 'source', 'is_active', 'is_demo'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    /** `DemoContentSeeder`-in yaratdığı nümunə mətnlər (`demo:clear` bunları silir) */
    public function scopeDemo($query)
    {
        return $query->where('is_demo', true);
    }
}
