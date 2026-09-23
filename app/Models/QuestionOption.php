<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class QuestionOption extends Model
{
    protected $fillable = [
        'question_id', 'option_letter', 'option_text', 'option_image', 'is_correct', 'order',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'order' => 'integer',
    ];

    /** Variant şəklinin ictimai URL-i (bax `Question::imageUrl()`) */
    public function imageUrl(): ?string
    {
        return filled($this->option_image)
            ? Storage::disk('public')->url($this->option_image)
            : null;
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
