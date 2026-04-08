<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tasks')->where('assigned_to', $this->integer('assigned_to')),
            ],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::enum(TaskStatus::class)],
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'title.unique' => 'This task has already been assigned to the selected user.',
        ];
    }
}
