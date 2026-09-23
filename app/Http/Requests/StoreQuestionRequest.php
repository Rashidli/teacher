<?php

namespace App\Http\Requests;

use App\Models\Exam;
use App\Models\Question;
use App\Support\QuestionTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sual yaratma/redaktə (admin və müəllim tərəfi eyni qaydalardan istifadə edir).
 *
 * Variant sayı sual səviyyəsində sərbəst deyil: imtahanın `options_per_question` dəyəri
 * (4 və ya 5) nə qədərdirsə, bütün suallarda o qədər variant olmalıdır — DİM formatında
 * bir imtahan daxilində variant sayı dəyişmir.
 */
class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function exam(): Exam
    {
        return $this->route('exam');
    }

    protected function prepareForValidation(): void
    {
        // Boş sətirlər qəbul olunan cavablar siyahısını doldurmasın
        if (is_array($this->input('accepted_answers'))) {
            $this->merge([
                'accepted_answers' => array_values(array_filter(
                    array_map(fn ($value) => is_string($value) ? trim($value) : $value, $this->input('accepted_answers')),
                    fn ($value) => $value !== null && $value !== ''
                )),
            ]);
        }
    }

    public function rules(): array
    {
        $optionCount = $this->exam()->options_per_question;

        /*
         * Qaydalar tipdən asılıdır: əks halda variantlı sual göndəriləndə boş `accepted_answers`
         * "min:1" qaydasına ilişirdi (forma onu həmişə göndərir) və sual saxlanıla bilmirdi.
         */
        $isMultipleChoice = $this->input('type') === Question::TYPE_MULTIPLE_CHOICE;
        $isOpenCoded = $this->input('type') === Question::TYPE_OPEN_CODED;

        return [
            'question_text' => ['required', 'string'],
            'question_image' => ['nullable', 'image', 'max:2048'],
            // Şəkilli sualda şəkil məzmunun özüdür: ekran oxuyucusu üçün təsvir
            'question_image_alt' => ['nullable', 'string', 'max:255'],
            'remove_image' => ['boolean'],
            /*
             * Tip imtahanın KATEQORİYASINA görə məhdudlanır: sürücülükdə və MİQ-də açıq
             * sual yoxdur, dövlət qulluğunun BB/AC qrupunda da yalnız qapalı test var.
             * Qayda `config/questions.php`-dədir.
             */
            'type' => ['required', Rule::in(QuestionTypes::forExam($this->exam()))],
            'difficulty' => ['nullable', Rule::in(Question::DIFFICULTIES)],
            // Mövzu imtahanın fənninə aid olmalıdır
            'topic_id' => [
                'nullable',
                Rule::exists('topics', 'id')->where('subject_id', $this->exam()->subject_id),
            ],
            'source' => ['nullable', 'string', 'max:255'],
            // Sual imtahanın hansı bölməsinə düşür
            'section_id' => [
                'nullable',
                Rule::exists('exam_sections', 'id')->where('exam_id', $this->exam()->id),
            ],
            'explanation' => ['nullable', 'string', 'max:1000'],
            // Açıq yazılı sualın qiymətləndirmə meyarı: avtomatik yoxlama ona görə işləyir
            'grading_rubric' => ['nullable', 'string', 'max:4000'],

            // Variantlı test: variant sayı imtahandakı ilə eyni olmalıdır.
            // Açıq suallarda forma köhnə variantları göndərə bilər — onlar nəzərə alınmır.
            'options' => $isMultipleChoice
                ? ['required', 'array', 'size:'.$optionCount]
                : ['nullable', 'array'],
            'options.*.option_letter' => ['required_with:options', 'string', 'size:1'],
            'options.*.option_text' => ['required_with:options', 'string'],
            'options.*.option_image' => ['nullable', 'image', 'max:1024'],
            'options.*.is_correct' => ['required_with:options', 'boolean'],

            // Qısa cavab: ədədi cavablar AnswerNormalizer ilə tutulur, burada ən azı bir etalon lazımdır
            'accepted_answers' => $isOpenCoded
                ? ['required', 'array', 'min:1', 'max:10']
                : ['nullable', 'array'],
            'accepted_answers.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('type') !== Question::TYPE_MULTIPLE_CHOICE) {
                return;
            }

            $options = $this->input('options');

            if (! is_array($options)) {
                return;
            }

            $correctCount = collect($options)->filter(fn ($option) => filter_var(
                $option['is_correct'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            ))->count();

            if ($correctCount !== 1) {
                $validator->errors()->add('options', 'Düz bir düzgün cavab seçilməlidir.');
            }
        });
    }

    public function messages(): array
    {
        $optionCount = $this->exam()->options_per_question;

        return [
            'question_text.required' => 'Sual mətni mütləqdir.',
            'type.required' => 'Sual tipi seçilməlidir.',
            'type.in' => 'Bu imtahan növündə belə sual tipi işlənmir '
                .'(icazəli: '.implode(', ', QuestionTypes::forExam($this->exam())).').',
            'options.required' => 'Test sualı üçün variantlar mütləqdir.',
            'options.size' => "Bu imtahanda hər sualda {$optionCount} variant olmalıdır.",
            'topic_id.exists' => 'Seçilmiş mövzu bu imtahanın fənninə aid deyil.',
            'section_id.exists' => 'Seçilmiş bölmə bu imtahana aid deyil.',
            'accepted_answers.required' => 'Qısa cavablı sual üçün ən azı bir düzgün cavab yazılmalıdır.',
            'accepted_answers.min' => 'Ən azı bir düzgün cavab yazılmalıdır.',
        ];
    }
}
