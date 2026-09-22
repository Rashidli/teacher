<?php

namespace App\Http\Controllers\Teacher\Auth;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\TeacherProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Müəllimliyə keçid: yeni hesab YARATMIR.
 *
 * Bir hesabın bir neçə rolu ola bilər — daxil olmuş istifadəçi (adətən şagird)
 * bu formadan öz hesabına "teacher" rolu və müəllim profili əlavə edir.
 */
class TeacherRegisterController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Teacher/Auth/Register', [
            'subjects' => Subject::active()->orderBy('category')->orderBy('order')->get(),
            'alreadyTeacher' => $request->user()->hasRole('teacher'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subjects' => 'required|array|min:1',
            'subjects.*' => 'exists:subjects,id',
        ]);

        $user = $request->user();

        if (! $user->hasRole('teacher')) {
            $user->assignRole('teacher');
        }

        // Profil bir dəfə yaradılır: təkrar müraciətdə təsdiq statusu sıfırlanmır
        TeacherProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['is_verified' => true],
        );

        $user->subjects()->syncWithoutDetaching($validated['subjects']);

        return redirect()->route('teacher.dashboard')
            ->with('success', 'Hesabınıza müəllim rolu əlavə olundu.');
    }
}
