<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminExamController;
use App\Http\Controllers\Admin\AdminGroupController;
use App\Http\Controllers\Admin\AdminQuestionController;
use App\Http\Controllers\Admin\AdminSubjectController;
use App\Http\Controllers\Admin\AdminTeacherController;
use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Teacher\TeacherDashboardController;
use App\Http\Controllers\Teacher\TeacherExamController;
use App\Http\Controllers\Teacher\TeacherQuestionController;
use App\Http\Controllers\Teacher\Auth\TeacherLoginController;
use App\Http\Controllers\Teacher\Auth\TeacherRegisterController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Student\StudentExamController;
use App\Http\Controllers\Student\StudentResultController;
use App\Http\Controllers\Student\Auth\StudentLoginController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$auth = require __DIR__.'/auth.php';

// =====================================================
// İCTİMAİ SƏHİFƏLƏR (iki dildə)
// az: prefikssiz (/qaydalar), ru: /ru prefiksi və "ru." adı (/ru/qaydalar, ru.terms).
// 'localized' => true: SetLocale dili URL-dən götürür, səhifələr canonical/hreflang alır.
// =====================================================
$publicRoutes = function () use ($auth) {
    Route::get('/', function () {
        return Inertia::render('Welcome');
    })->name('home');

    // Qaydalar: məzmun cari dildə props kimi ötürülür (lang/{az,ru}/terms.php, şablon mətn)
    Route::get('/qaydalar', fn () => Inertia::render('Terms', [
        'content' => __('terms'),
    ]))->name('terms');

    // Kateqoriya səhifələri (müvəqqəti placeholder, testlər hazır olanda controller-ə keçiriləcək)
    foreach (['abituriyent', 'mekteb', 'magistratura', 'dovlet-qullugu', 'miq', 'suruculuk-imtahani'] as $slug) {
        Route::inertia('/'.$slug, 'Category/Show', ['slug' => $slug])->name('category.'.$slug);
    }

    // Giriş, qeydiyyat, parol bərpası
    $auth['guest']();
};

Route::group(['localized' => true], $publicRoutes);

foreach (array_diff(config('locales.supported'), [config('locales.default')]) as $locale) {
    Route::group(['prefix' => $locale, 'as' => $locale.'.', 'localized' => true], $publicRoutes);
}

// Çıxış, parol dəyişmə, email təsdiqi (dil prefiksi olmadan)
$auth['account']();

// =====================================================
// ADMIN ROUTES
// =====================================================
Route::prefix('admin')->name('admin.')->group(function () {
    // Guest routes - xüsusi middleware ilə
    Route::middleware('guest.admin')->group(function () {
        Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
        Route::post('/login', [AdminLoginController::class, 'store']);
    });

    // Authenticated routes
    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Subjects
        Route::get('/subjects', [AdminSubjectController::class, 'index'])->name('subjects.index');
        Route::post('/subjects', [AdminSubjectController::class, 'store'])->name('subjects.store');
        Route::put('/subjects/{subject}', [AdminSubjectController::class, 'update'])->name('subjects.update');
        Route::post('/subjects/{subject}/toggle-active', [AdminSubjectController::class, 'toggleActive'])->name('subjects.toggle-active');

        // Groups & Scores
        Route::get('/groups', [AdminGroupController::class, 'index'])->name('groups.index');
        Route::put('/groups/{group}/scores', [AdminGroupController::class, 'updateScores'])->name('groups.update-scores');

        // Teachers (müəllim modulu: config/features.php, FEATURE_TEACHERS)
        if (config('features.teachers')) {
            Route::get('/teachers', [AdminTeacherController::class, 'index'])->name('teachers.index');
            Route::get('/teachers/{teacher}', [AdminTeacherController::class, 'show'])->name('teachers.show');
            Route::post('/teachers/{teacher}/verify', [AdminTeacherController::class, 'verify'])->name('teachers.verify');
            Route::post('/teachers/{teacher}/unverify', [AdminTeacherController::class, 'unverify'])->name('teachers.unverify');
        }

        // Exams
        Route::resource('exams', AdminExamController::class);
        Route::post('/exams/{exam}/toggle-active', [AdminExamController::class, 'toggleActive'])->name('exams.toggle-active');
        Route::post('/exams/{exam}/toggle-publish', [AdminExamController::class, 'togglePublish'])->name('exams.toggle-publish');

        // Questions (imtahan daxilində)
        Route::prefix('exams/{exam}')->name('exams.')->group(function () {
            Route::get('/questions/create', [AdminQuestionController::class, 'create'])->name('questions.create');
            Route::post('/questions', [AdminQuestionController::class, 'store'])->name('questions.store');
            Route::get('/questions/{question}/edit', [AdminQuestionController::class, 'edit'])->name('questions.edit');
            Route::put('/questions/{question}', [AdminQuestionController::class, 'update'])->name('questions.update');
            Route::delete('/questions/{question}', [AdminQuestionController::class, 'destroy'])->name('questions.destroy');
            Route::post('/questions/{question}/move/{direction}', [AdminQuestionController::class, 'move'])->name('questions.move');
        });
    });
});

// =====================================================
// TEACHER ROUTES
// =====================================================
// Müəllim modulu MVP-də söndürülüb (FEATURE_TEACHERS=false): route-lar qeydiyyatdan keçmir və 404 qaytarır.
if (config('features.teachers')) {
    Route::prefix('teacher')->name('teacher.')->group(function () {
        // Guest routes - xüsusi middleware ilə
        Route::middleware('guest.teacher')->group(function () {
            Route::get('/login', [TeacherLoginController::class, 'create'])->name('login');
            Route::post('/login', [TeacherLoginController::class, 'store']);
            Route::get('/register', [TeacherRegisterController::class, 'create'])->name('register');
            Route::post('/register', [TeacherRegisterController::class, 'store']);
        });

        // Authenticated routes
        Route::middleware('auth:teacher')->group(function () {
            Route::post('/logout', [TeacherLoginController::class, 'destroy'])->name('logout');

            // Awaiting verification page (no verified middleware)
            Route::get('/awaiting-verification', function () {
                return Inertia::render('Teacher/AwaitingVerification');
            })->name('awaiting-verification');

            // Verified teacher routes
            Route::middleware('teacher.verified')->group(function () {
                Route::get('/dashboard', [TeacherDashboardController::class, 'index'])->name('dashboard');
                Route::resource('exams', TeacherExamController::class);

                Route::prefix('exams/{exam}')->name('exams.')->group(function () {
                    Route::get('/questions/create', [TeacherQuestionController::class, 'create'])->name('questions.create');
                    Route::post('/questions', [TeacherQuestionController::class, 'store'])->name('questions.store');
                    Route::get('/questions/{question}/edit', [TeacherQuestionController::class, 'edit'])->name('questions.edit');
                    Route::put('/questions/{question}', [TeacherQuestionController::class, 'update'])->name('questions.update');
                    Route::delete('/questions/{question}', [TeacherQuestionController::class, 'destroy'])->name('questions.destroy');
                    Route::post('/questions/reorder', [TeacherQuestionController::class, 'reorder'])->name('questions.reorder');
                });
            });
        });
    });
}

// =====================================================
// STUDENT ROUTES
// =====================================================
Route::prefix('student')->name('student.')->group(function () {
    // Guest routes - xüsusi middleware ilə
    // Köhnə şagird giriş/qeydiyyat ünvanları: vahid /login və /register səhifələrinə yönləndirilir.
    // Route adları saxlanılır ki, route('student.login') istinadları işləsin.
    Route::middleware('guest.student')->group(function () {
        Route::permanentRedirect('/login', '/login')->name('login');
        Route::permanentRedirect('/register', '/register')->name('register');
    });

    // Authenticated routes
    Route::middleware('auth:student')->group(function () {
        Route::post('/logout', [StudentLoginController::class, 'destroy'])->name('logout');
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');

        // İmtahanlar
        Route::get('/exams', [StudentExamController::class, 'index'])->name('exams.index');
        Route::get('/exams/{exam}', [StudentExamController::class, 'show'])->name('exams.show');
        Route::post('/exams/{exam}/start', [StudentExamController::class, 'start'])->name('exams.start');

        // İmtahan cəhdi
        Route::get('/attempts/{attempt}', [StudentExamController::class, 'attempt'])->name('exams.attempt');
        Route::post('/attempts/{attempt}/save-answer', [StudentExamController::class, 'saveAnswer'])->name('exams.save-answer');
        Route::post('/attempts/{attempt}/finish', [StudentExamController::class, 'finish'])->name('exams.finish');
        Route::get('/attempts/{attempt}/result', [StudentExamController::class, 'result'])->name('exams.result');

        // Nəticələr
        Route::get('/results', [StudentResultController::class, 'index'])->name('results.index');
    });
});

// Profile routes (shared)
Route::middleware('auth:admin,teacher,student')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
