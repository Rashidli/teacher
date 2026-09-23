<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            // Humanitarian
            ['name' => 'Azərbaycan dili', 'category' => 'humanitarian', 'order' => 1],
            // DİM-də "Tarix" bir fəndir; "Azərbaycan tarixi" və "Ümumi tarix" onun mövzu qruplarıdır
            ['name' => 'Tarix', 'category' => 'humanitarian', 'order' => 2],
            ['name' => 'Ədəbiyyat', 'category' => 'humanitarian', 'order' => 4],
            ['name' => 'İngilis dili', 'category' => 'humanitarian', 'is_language' => true, 'order' => 5],
            ['name' => 'Rus dili', 'category' => 'humanitarian', 'is_language' => true, 'order' => 6],
            ['name' => 'Fransız dili', 'category' => 'humanitarian', 'is_language' => true, 'order' => 7],
            ['name' => 'Alman dili', 'category' => 'humanitarian', 'is_language' => true, 'order' => 8],
            // Technical
            ['name' => 'Riyaziyyat', 'category' => 'technical', 'order' => 9],
            ['name' => 'Fizika', 'category' => 'technical', 'order' => 10],
            ['name' => 'Kimya', 'category' => 'technical', 'order' => 11],
            ['name' => 'Biologiya', 'category' => 'technical', 'order' => 12],
            ['name' => 'Coğrafiya', 'category' => 'technical', 'order' => 13],
            ['name' => 'İnformatika', 'category' => 'technical', 'order' => 14],
            // Dövlət qulluğu və magistratura üçün (fənlər kateqoriyalar arasında paylaşılır)
            ['name' => 'Qanunvericilik', 'category' => 'humanitarian', 'order' => 15],
            ['name' => 'Məntiq', 'category' => 'technical', 'order' => 16],
            /*
             * Abituriyent qəbulundan kənar kateqoriyaların öz fənləri. Heç bir DİM qrupuna
             * bağlanmır, ona görə `subject_group_scores` matrisinə təsir etmir — maksimal
             * balları `category_subject.max_score`-dan gəlir.
             */
            ['name' => 'Yol hərəkəti qaydaları', 'category' => 'technical', 'order' => 17],
            ['name' => 'Kurikulum və metodika', 'category' => 'humanitarian', 'order' => 18],
        ];

        // Idempotent: seeder təkrar işlədiləndə mövcud fənlər yenilənir, dublikat yaranmır.
        // is_active yalnız yaradılanda təyin olunur — admin deaktiv etdiyi fənn geri açılmasın.
        foreach ($subjects as $subject) {
            $model = Subject::firstOrNew(['slug' => Str::slug($subject['name'])]);

            $model->fill([
                'name' => $subject['name'],
                'category' => $subject['category'],
                // Xarici dillər generasiya formasında tək seçim kimi göstərilir
                'is_language' => $subject['is_language'] ?? false,
                'order' => $subject['order'],
            ]);

            if (! $model->exists) {
                $model->is_active = true;
            }

            $model->save();
        }
    }
}
