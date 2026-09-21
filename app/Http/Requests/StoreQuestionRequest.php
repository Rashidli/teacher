<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_text' => ['required', 'string'],
            'question_image' => ['nullable', 'image', 'max:2048'],
            'type' => ['required', 'in:multiple_choice,open_ended'],
            'explanation' => ['nullable', 'string', 'max:1000'],
            'options' => ['required_if:type,multiple_choice', 'array', 'min:2', 'max:5'],
            'options.*.option_letter' => ['required_with:options', 'string', 'size:1'],
            'options.*.option_text' => ['required_with:options', 'string'],
            'options.*.option_image' => ['nullable', 'image', 'max:1024'],
            'options.*.is_correct' => ['required_with:options', 'boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->type === 'multiple_choice' && $this->options) {
                $correctCount = collect($this->options)->where('is_correct', true)->count();
                if ($correctCount !== 1) {
                    $validator->errors()->add('options', 'Düz bir düzgün cavab seçilməlidir.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'question_text.required' => 'Sual mətni mütləqdir.',
            'type.required' => 'Sual tipi seçilməlidir.',
            'options.required_if' => 'Test sualı üçün variantlar mütləqdir.',
            'options.min' => 'Minimum 2 variant olmalıdır.',
            'options.max' => 'Maksimum 5 variant ola bilər.',
        ];
    }
}
