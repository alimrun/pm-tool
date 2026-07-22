<?php

namespace App\Http\Requests;

use App\Models\Release;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may collaborate on tasks.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(array_keys(Task::STATUSES))],
            'assignee_id' => ['nullable', Rule::exists('users', 'id')],
            'due_date' => ['nullable', 'date'],
            'phase' => ['nullable', Rule::in(array_keys(Release::PHASES))],
        ];
    }
}
