<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('teacher');
    }

    public function view(User $user, Exam $exam): bool
    {
        return $this->owns($user, $exam);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('teacher') && $user->teacherProfile?->is_verified;
    }

    public function update(User $user, Exam $exam): bool
    {
        return $this->owns($user, $exam) && !$exam->is_published;
    }

    public function delete(User $user, Exam $exam): bool
    {
        return $this->owns($user, $exam) && !$exam->is_published;
    }

    // teacher_id null ola bilər (müəllim silinibsə): sahibsiz imtahan heç bir müəllimə aid deyil
    private function owns(User $user, Exam $exam): bool
    {
        return $exam->teacher_id !== null && $user->id === (int) $exam->teacher_id;
    }
}
