<?php

use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminExamAccessController;
use App\Http\Controllers\Admin\AdminExamController;
use App\Http\Controllers\Admin\AdminExamGenerationController;
use App\Http\Controllers\Admin\AdminExamSectionController;
use App\Http\Controllers\Admin\AdminGradingController;
use App\Http\Controllers\Admin\AdminGroupController;
use App\Http\Controllers\Admin\AdminQuestionBankController;
use App\Http\Controllers\Admin\AdminQuestionController;
use App\Http\Controllers\Admin\AdminQuestionImportController;
use App\Http\Controllers\Admin\AdminSubjectController;
use App\Http\Controllers\Admin\AdminTopicController;
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
use App\Http\Controllers\Payments\FakeGatewayController;
use App\Http\Controllers\Payments\PaymentCallbackController;
use App\Http\Controllers\Student\StudentPaymentController;
use App\Http\Controllers\CategoryController;
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

        // Categories (kateqoriya ağacı)
        Route::resource('categories', AdminCategoryController::class)->except(['show']);

        // Sual bankı
        Route::get('/questions', [AdminQuestionBankController::class, 'index'])->name('questions.index');
        Route::delete('/questions/{question}', [AdminQuestionBankController::class, 'destroy'])->name('questions.destroy');

        // Topics (fənn mövzuları)
        Route::get('/topics', [AdminTopicController::class, 'index'])->name('topics.index');
        Route::post('/topics', [AdminTopicController::class, 'store'])->name('topics.store');
        Route::put('/topics/{topic}', [AdminTopicController::class, 'update'])->name('topics.update');
        Route::delete('/topics/{topic}', [AdminTopicController::class, 'destroy'])->name('topics.destroy');

        // Subjects
        Route::get('/subjects', [AdminSubjectController::class, 'index'])->name('subjects.index');
        Route::post('/subjects', [AdminSubjectController::class, 'store'])->name('subjects.store');
        Route::put('/subjects/{subject}', [AdminSubjectController::class, 'update'])->name('subjects.update');
        Route::post('/subjects/{subject}/toggle-active', [AdminSubjectController::class, 'toggleActive'])->name('subjects.toggle-active');

        // Yazılı cavabların qiymətləndirmə növbəsi
        Route::get('/grading', [AdminGradingController::class, 'index'])->name('grading.index');
        Route::get('/grading/{attempt}', [AdminGradingController::class, 'show'])->name('grading.show');
        Route::post('/grading/{attempt}/answers/{answer}', [AdminGradingController::class, 'update'])->name('grading.update');

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

        // Bankdan imtahan generasiyası (resource route-lardan ƏVVƏL: /exams/generate)
        Route::get('/exams/generate', [AdminExamGenerationController::class, 'create'])->name('exams.generate');
        Route::post('/exams/generate', [AdminExamGenerationController::class, 'store'])->name('exams.generate.store');

        // Exams
        Route::resource('exams', AdminExamController::class);
        Route::post('/exams/{exam}/toggle-active', [AdminExamController::class, 'toggleActive'])->name('exams.toggle-active');
        Route::post('/exams/{exam}/toggle-publish', [AdminExamController::class, 'togglePublish'])->name('exams.toggle-publish');

        // İmtahan bölmələri (çoxfənli imtahan)
        Route::post('/exams/{exam}/sections', [AdminExamSectionController::class, 'store'])->name('exams.sections.store');
        Route::put('/exams/{exam}/sections/{section}', [AdminExamSectionController::class, 'update'])->name('exams.sections.update');
        Route::delete('/exams/{exam}/sections/{section}', [AdminExamSectionController::class, 'destroy'])->name('exams.sections.destroy');

        // İmtahana giriş hüququ (əl ilə vermə / ləğv)
        Route::get('/exams/{exam}/access', [AdminExamAccessController::class, 'index'])->name('exams.access.index');
        Route::post('/exams/{exam}/access', [AdminExamAccessController::class, 'store'])->name('exams.access.store');
        Route::delete('/exams/{exam}/access/{access}', [AdminExamAccessController::class, 'destroy'])->name('exams.access.destroy');

        // Questions (imtahan daxilində)
        Route::prefix('exams/{exam}')->name('exams.')->group(function () {
            // Toplu import: /questions/{question} route-larından ƏVVƏL, "import" ID kimi oxunmasın
            Route::get('/questions/import', [AdminQuestionImportController::class, 'create'])->name('questions.import');
            Route::get('/questions/import/template', [AdminQuestionImportController::class, 'template'])->name('questions.import.template');
            Route::post('/questions/import/preview', [AdminQuestionImportController::class, 'preview'])->name('questions.import.preview');
            Route::post('/questions/import', [AdminQuestionImportController::class, 'store'])->name('questions.import.store');

            Route::get('/questions/create', [AdminQuestionController::class, 'create'])->name('questions.create');
            Route::post('/questions', [AdminQuestionController::class, 'store'])->name('questions.store');
            Route::get('/questions/{question}/edit', [AdminQuestionController::class, 'edit'])->name('questions.edit');
            Route::put('/questions/{question}', [AdminQuestionController::class, 'update'])->name('questions.update');
            Route::delete('/questions/{question}', [AdminQuestionController::class, 'destroy'])->name('questions.destroy');
            Route::post('/questions/{question}/move/{direction}', [AdminQuestionController::class, 'move'])->name('questions.move');
            // Bankdan mövcud sual əlavə etmə və cəhdlərdə işlənmiş sualın kopyası
            Route::post('/questions/attach', [AdminQuestionController::class, 'attach'])->name('questions.attach');
            Route::post('/questions/{question}/duplicate', [AdminQuestionController::class, 'duplicate'])->name('questions.duplicate');
            Route::post('/questions/{question}/replace', [AdminQuestionController::class, 'replace'])->name('questions.replace');
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
        Route::post('/exams/{exam}/purchase', [StudentPaymentController::class, 'store'])->name('exams.purchase');

        // İmtahan cəhdi
        Route::get('/attempts/{attempt}', [StudentExamController::class, 'attempt'])->name('exams.attempt');
        Route::post('/attempts/{attempt}/save-answer', [StudentExamController::class, 'saveAnswer'])->name('exams.save-answer');
        Route::post('/attempts/{attempt}/finish', [StudentExamController::class, 'finish'])->name('exams.finish');
        Route::get('/attempts/{attempt}/result', [StudentExamController::class, 'result'])->name('exams.result');

        // Nəticələr
        Route::get('/results', [StudentResultController::class, 'index'])->name('results.index');
    });
});

// =====================================================
// ÖDƏNİŞ
// =====================================================
// Bankın cavabı: sessiya yoxdur, imza ilə yoxlanılır (PaymentCallbackController).
Route::match(['get', 'post'], '/payments/callback/{provider}', PaymentCallbackController::class)
    ->name('payments.callback');

// Sınaq bank səhifəsi: yalnız fake driver seçiləndə və produksiyadan kənarda mövcuddur
if (config('payments.driver') === 'fake' && ! app()->isProduction()) {
    Route::get('/payments/fake/{payment}', [FakeGatewayController::class, 'show'])
        ->middleware('auth:student')
        ->name('payments.fake.show');
}

// Profile routes (shared)
Route::middleware('auth:admin,teacher,student')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// =====================================================
// KATEQORİYA SƏHİFƏLƏRİ (catch-all)
// =====================================================
// DİQQƏT: bu blok faylın SONUNDA olmalıdır. Laravel ilk uyğun gələn route-u seçir, ona görə
// /login, /admin/…, /student/… kimi ünvanlar yuxarıda qeydiyyatdan keçdiyi üçün buraya düşmür.
// Yeni route əlavə edəndə onu bu blokdan ƏVVƏL yazın (RouteRegistrationTest bunu yoxlayır).
$categoryRoutes = function () {
    Route::get('/{path}', [CategoryController::class, 'show'])
        ->where('path', '[A-Za-z0-9\-]+(?:/[A-Za-z0-9\-]+)*')
        ->name('category.show');
};

// Dil prefiksli variant ƏVVƏL: prefikssiz "/{path}" onsuz da "ru/abituriyent"i tutardı
foreach (array_diff(config('locales.supported'), [config('locales.default')]) as $locale) {
    Route::group(['prefix' => $locale, 'as' => $locale.'.', 'localized' => true], $categoryRoutes);
}

Route::group(['localized' => true], $categoryRoutes);
