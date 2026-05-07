<?php

namespace App\Http\Requests;

use App\Models\Song;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class SaveLyricsSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        $song = $this->route('song');

        return $song instanceof Song && ($this->user()?->can('update', $song) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lyrics' => ['required', 'string'],
            'segments' => ['array'],
            'segments.*.text' => ['required', 'string'],
            'segments.*.starts_at_ms' => ['nullable', 'integer', 'min:0'],
            'segments.*.ends_at_ms' => ['nullable', 'integer', 'min:0'],
            'segments.*.is_instrumental_gap' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [function (): void {
            $previousStart = null;

            foreach ($this->input('segments', []) as $index => $segment) {
                $start = $segment['starts_at_ms'] ?? null;
                $end = $segment['ends_at_ms'] ?? null;

                if ($start !== null && $end !== null && $end < $start) {
                    throw ValidationException::withMessages([
                        "segments.{$index}.ends_at_ms" => 'A segment cannot end before it starts.',
                    ]);
                }

                if ($start !== null && $previousStart !== null && $start < $previousStart) {
                    throw ValidationException::withMessages([
                        "segments.{$index}.starts_at_ms" => 'Segment start times must stay in ascending order.',
                    ]);
                }

                if ($start !== null) {
                    $previousStart = $start;
                }
            }
        }];
    }
}
