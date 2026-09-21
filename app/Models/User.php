<?php

namespace App\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements HasLocalePreference
{
    use HasFactory, Notifiable, HasRoles;

    // Spatie Permission üçün default guard - bütün rollar 'web' guard-da saxlanılır
    protected $guard_name = 'web';

    protected $fillable = [
        'first_name', 'last_name', 'name', 'email', 'password', 'phone', 'locale', 'sector', 'is_active'
    ];

    /**
     * "name" sütunu (starter kit-dən qalıb, NOT NULL) formada soruşulmur:
     * həmişə first_name + last_name-dən avtomatik doldurulur.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty(['first_name', 'last_name']) || blank($user->name)) {
                $user->name = trim("{$user->first_name} {$user->last_name}");
            }
        });
    }

    /** Bildirişlər və email-lər (məs. parol sıfırlama) bu dildə göndərilir */
    public function preferredLocale(): string
    {
        return \App\Support\Localization::isSupported($this->locale)
            ? $this->locale
            : \App\Support\Localization::default();
    }

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'teacher_id');
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function completedExams()
    {
        return $this->examAttempts()->completed()->with('exam');
    }

    public function scopeTeachers($query)
    {
        return $query->role('teacher');
    }

    public function scopeStudents($query)
    {
        return $query->role('student');
    }

    public function scopeAdmins($query)
    {
        return $query->role('admin');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerifiedTeachers($query)
    {
        return $query->role('teacher')
            ->whereHas('teacherProfile', fn($q) => $q->where('is_verified', true));
    }

    public function isVerifiedTeacher(): bool
    {
        return $this->hasRole('teacher') && $this->teacherProfile?->is_verified;
    }
}
