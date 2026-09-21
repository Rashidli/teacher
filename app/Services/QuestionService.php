<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sual yazma məntiqi bir yerdə: admin və müəllim controller-ləri eyni servisi çağırır.
 * Şəkillər "public" diskinə yazılır (public/storage simvolik linki quraşdırılıb).
 */
class QuestionService
{
    private const QUESTION_IMAGE_DIR = 'questions';

    private const OPTION_IMAGE_DIR = 'question-options';

    public function create(Exam $exam, array $data): Question
    {
        return DB::transaction(function () use ($exam, $data) {
            $attributes = $this->questionAttributes($data);
            $attributes['order'] = ($exam->questions()->max('order') ?? 0) + 1;

            if (($data['question_image'] ?? null) instanceof UploadedFile) {
                $attributes['question_image'] = $data['question_image']->store(self::QUESTION_IMAGE_DIR, 'public');
            }

            $question = $exam->questions()->create($attributes);

            $this->syncOptions($question, $data);

            return $question;
        });
    }

    public function update(Question $question, array $data): Question
    {
        return DB::transaction(function () use ($question, $data) {
            $attributes = $this->questionAttributes($data);

            if (($data['question_image'] ?? null) instanceof UploadedFile) {
                $this->deleteFile($question->question_image);
                $attributes['question_image'] = $data['question_image']->store(self::QUESTION_IMAGE_DIR, 'public');
            } elseif (filter_var($data['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $this->deleteFile($question->question_image);
                $attributes['question_image'] = null;
            }

            $question->update($attributes);

            $this->syncOptions($question, $data);

            return $question->refresh();
        });
    }

    public function delete(Question $question): void
    {
        DB::transaction(function () use ($question) {
            $this->deleteFile($question->question_image);
            $this->deleteOptionImages($question);

            $question->delete();
        });
    }

    /**
     * Sualı bir mövqe yuxarı/aşağı sürüşdürür: qonşu sualla "order" dəyərlərini dəyişir.
     */
    public function move(Question $question, string $direction): void
    {
        DB::transaction(function () use ($question, $direction) {
            $neighbour = $question->exam->questions()
                ->when($direction === 'up',
                    fn ($query) => $query->where('order', '<', $question->order)->orderByDesc('order'),
                    fn ($query) => $query->where('order', '>', $question->order)->orderBy('order'))
                ->first();

            if (! $neighbour) {
                return;
            }

            $questionOrder = $question->order;

            $question->update(['order' => $neighbour->order]);
            $neighbour->update(['order' => $questionOrder]);
        });
    }

    /**
     * Sıra nömrələrini 1-dən başlayaraq boşluqsuz yenidən yazır (silinmədən sonra).
     */
    public function resequence(Exam $exam): void
    {
        DB::transaction(function () use ($exam) {
            $exam->questions()->orderBy('order')->get()
                ->each(fn (Question $question, int $index) => $question->update(['order' => $index + 1]));
        });
    }

    /**
     * Tipə uyğun olmayan sahələr təmizlənir: variantlı sualda accepted_answers,
     * açıq sualda isə variantlar saxlanılmır.
     */
    private function questionAttributes(array $data): array
    {
        $type = $data['type'];

        return [
            'question_text' => $data['question_text'],
            'type' => $type,
            'explanation' => $data['explanation'] ?? null,
            'accepted_answers' => $type === Question::TYPE_OPEN_CODED
                ? array_values($data['accepted_answers'] ?? [])
                : null,
        ];
    }

    private function syncOptions(Question $question, array $data): void
    {
        if ($question->type !== Question::TYPE_MULTIPLE_CHOICE) {
            $this->deleteOptionImages($question);
            $question->options()->delete();

            return;
        }

        $options = array_values($data['options'] ?? []);

        /*
         * Variantlar hər dəfə yenidən yazılır (sıra və hərflər dəyişə bilər), amma şəkillər
         * hərfə görə saxlanılır: admin yalnız mətni düzəldəndə mövcud variant şəkli itməməlidir.
         */
        $existingImages = $question->options()->pluck('option_image', 'option_letter')->all();
        $keptImages = [];

        $question->options()->delete();

        foreach ($options as $index => $option) {
            $image = ($option['option_image'] ?? null) instanceof UploadedFile
                ? $option['option_image']->store(self::OPTION_IMAGE_DIR, 'public')
                : ($existingImages[$option['option_letter']] ?? null);

            if ($image) {
                $keptImages[] = $image;
            }

            $question->options()->create([
                'option_letter' => $option['option_letter'],
                'option_text' => $option['option_text'],
                'option_image' => $image,
                'is_correct' => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'order' => $index + 1,
            ]);
        }

        // Artıq heç bir variantın istifadə etmədiyi şəkillər diskdən silinir
        foreach ($existingImages as $path) {
            if ($path && ! in_array($path, $keptImages, true)) {
                $this->deleteFile($path);
            }
        }
    }

    private function deleteOptionImages(Question $question): void
    {
        $question->options()->pluck('option_image')
            ->each(fn (?string $path) => $this->deleteFile($path));
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
