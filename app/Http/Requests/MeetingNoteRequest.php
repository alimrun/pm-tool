<?php

namespace App\Http\Requests;

use App\Support\HtmlSanitizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeetingNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // Only ongoing releases may be linked — except a note may keep the
        // release it is already linked to, even if that release completed since.
        $keptReleaseId = $this->route('meetingNote')?->release_id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'meeting_date' => ['required', 'date'],
            'release_id' => ['nullable', 'integer',
                Rule::exists('releases', 'id')->where(
                    fn ($q) => $q->where(fn ($q) => $q->whereNull('completed_at')->orWhere('id', $keptReleaseId))
                ),
            ],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'body' => ['required', 'string', 'max:20000'],
        ];
    }

    public function messages(): array
    {
        return [
            'release_id.exists' => 'Completed releases cannot be linked to a new meeting note.',
        ];
    }

    /** Trix can submit markup with no visible text — treat that as empty. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $validator->errors()->has('body') && HtmlSanitizer::isEmpty($this->input('body'))) {
                $validator->errors()->add('body', 'The note cannot be empty.');
            }
        });
    }
}
