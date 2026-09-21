<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectGroupScore extends Model
{
    /** max_score: fənnin həmin qrupdakı MAKSİMAL balı (100/150), bir sualın balı deyil */
    protected $fillable = ['subject_id', 'group_id', 'max_score'];

    protected $casts = [
        'max_score' => 'decimal:2',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
