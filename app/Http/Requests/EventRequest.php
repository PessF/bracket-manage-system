<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('patch') ? 'sometimes' : 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'venue' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $event = $this->route('event');
            $start = $this->input('starts_on', $event?->starts_on?->format('Y-m-d'));
            $end = $this->input('ends_on', $event?->ends_on?->format('Y-m-d'));
            if ($start && $end && $end < $start) {
                $validator->errors()->add('ends_on', __('events.date_order'));
            }
        }];
    }
}
