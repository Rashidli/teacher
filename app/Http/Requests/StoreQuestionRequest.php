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
        $type = $this->input('type');
        $isMultipleChoice = $type === Question::TYPE_MULTIPLE_CHOICE;
        $isOpenCoded = $type === Question::TYPE_OPEN_CODED;
        $subtype = $isOpenCoded ? ($this->input('subtype') ?: Question::CODED_NUMERIC) : $this->input('subtype');

        // Kodlaşdırılan alt növlərdən hansıları variant siyahısı tələb edir
        $needsOptions = in_array($subtype, [Question::CODED_MULTI_SELECT, Question::CODED_ORDERING], true);
        $needsPairs = $subtype === Question::CODED_MATCHING;

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
            /*
             * Alt növ: kodlaşdırılanda yoxlama qaydasını seçir, yazılıda isə yalnız
             * məlumat üçündür (bal qaydası dəyişmir). Tipə uyğun olmalıdır.
             */
            'subtype' => ['nullable', Rule::in(Question::subtypesFor($type))],
            // Mətn/mənbə əsaslı yazılı tapşırıq: bir mətnə bir neçə sual bağlana bilər
            'passage_id' => ['nullable', Rule::exists('passages', 'id')],
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
            'options' => match (true) {
                $isMultipleChoice => ['required', 'array', 'size:'.$optionCount],
                // Seçim və ardıcıllıq: variant sayı sərbəstdir, amma ən azı iki bənd lazımdır
                $needsOptions => ['required', 'array', 'min:2', 'max:8'],
                default => ['nullable', 'array'],
            },
            'options.*.option_letter' => ['required_with:options', 'string', 'size:1'],
            'options.*.option_text' => ['required_with:options', 'string'],
            'options.*.option_image' => ['nullable', 'image', 'max:1024'],
            'options.*.is_correct' => ['required_with:options', 'boolean'],

            // Uyğunluq: sol-sağ cütlər SIRALI saxlanılır, şagird tərəfdə sağ sütun qarışır
            'pairs' => $needsPairs ? ['required', 'array', 'min:2', 'max:8'] : ['nullable', 'array'],
            'pairs.*.left' => ['required_with:pairs', 'string', 'max:255'],
            'pairs.*.right' => ['required_with:pairs', 'string', 'max:255'],

            /*
             * Etalon cavab yalnız HESABLAMA alt növündə lazımdır: seçim, ardıcıllıq və
             * uyğunluqda düzgün cavab variantlardan/cütlərdən hesablanır (`CodedAnswer`).
             */
            'accepted_answers' => $isOpenCoded && $subtype === Question::CODED_NUMERIC
                ? ['required', 'array', 'min:1', 'max:10']
                : ['nullable', 'array'],
            'accepted_answers.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $options = $this->input('options');

            if (! is_array($options)) {
                return;
            }

            $correctCount = collect($options)->filter(fn ($option) => filter_var(
                $option['is_correct'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            ))->count();

            if ($this->input('type') === Question::TYPE_MULTIPLE_CHOICE) {
                if ($correctCount !== 1) {
                    $validator->errors()->add('options', 'Düz bir düzgün cavab seçilməlidir.');
                }

                return;
            }

            /*
             * Seçim tapşırığında ən azı iki düzgün variant olmalıdır: bir düzgün variant
             * adi test sualıdır, hamısı düzgün olanda isə seçim mənasını itirir.
             */
            if ($this->input('subtype') === Question::CODED_MULTI_SELECT
                && ($correctCount < 2 || $correctCount === count($options))) {
                $validator->errors()->add(
                    'options',
                    'Seçim tapşırığında ən azı iki düzgün variant olmalıdır və hamısı düzgün ola bilməz.'
                );
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
            'accepted_answers.required' => 'Hesablama tapşırığı üçün ən azı bir düzgün cavab yazılmalıdır.',
            'accepted_answers.min' => 'Ən azı bir düzgün cavab yazılmalıdır.',
            'subtype.in' => 'Bu sual tipində belə alt növ yoxdur.',
            'options.min' => 'Ən azı iki bənd olmalıdır.',
            'pairs.required' => 'Uyğunluq tapşırığı üçün sol-sağ cütləri doldurulmalıdır.',
            'pairs.min' => 'Ən azı iki cüt olmalıdır.',
        ];
    }
}
