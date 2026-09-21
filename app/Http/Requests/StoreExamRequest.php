<?php

namespace App\Http\Requests;

use App\Rules\TeacherOwnsSubject;
use Illuminate\Foundation\Http\FormRequest;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'exists:subjects,id', new TeacherOwnsSubject],
            'group_id' => ['required', 'exists:groups,id'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'İmtahan adı mütləqdir.',
            'subject_id.required' => 'Fənn seçilməlidir.',
            'group_id.required' => 'Qrup seçilməlidir.',
            'duration_minutes.required' => 'İmtahan müddəti mütləqdir.',
            'duration_minutes.min' => 'İmtahan minimum 10 dəqiqə olmalıdır.',
            'duration_minutes.max' => 'İmtahan maksimum 180 dəqiqə ola bilər.',
        ];
    }
}
