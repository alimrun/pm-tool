<?php

namespace App\Http\Requests;

use App\Models\Release;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'project_id' => ['required', Rule::exists('projects', 'id')],
            'team_id' => ['required', Rule::exists('teams', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'quarter' => ['required', 'integer', 'between:1,4'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];

        foreach (array_keys(Release::PHASES) as $key) {
            $rules["phases.$key.start"] = ['required', 'date'];
            $rules["phases.$key.end"] = ['required', 'date', "after_or_equal:phases.$key.start"];
        }

        return $rules;
    }

    /**
     * Structural cross-field rule: every phase window must sit inside the
     * release's overall window. (Team overlap is intentionally NOT validated
     * here — it is a warning, handled in the controller.)
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $start = $this->parseDate($this->input('start_date'));
            $end = $this->parseDate($this->input('end_date'));

            if (! $start || ! $end) {
                return;
            }

            foreach (Release::PHASES as $key => $label) {
                $pStart = $this->parseDate($this->input("phases.$key.start"));
                $pEnd = $this->parseDate($this->input("phases.$key.end"));

                if ($pStart && $pStart->lt($start)) {
                    $validator->errors()->add(
                        "phases.$key.start",
                        "{$label} cannot start before the release start date."
                    );
                }

                if ($pEnd && $pEnd->gt($end)) {
                    $validator->errors()->add(
                        "phases.$key.end",
                        "{$label} cannot end after the release end date."
                    );
                }
            }
        });
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function attributes(): array
    {
        return [
            'project_id' => 'project',
            'team_id' => 'team',
            'phases.development.start' => 'development start',
            'phases.development.end' => 'development end',
            'phases.qa.start' => 'QA start',
            'phases.qa.end' => 'QA end',
            'phases.retest.start' => 'retest start',
            'phases.retest.end' => 'retest end',
            'phases.release.start' => 'release-phase start',
            'phases.release.end' => 'release-phase end',
        ];
    }
}
