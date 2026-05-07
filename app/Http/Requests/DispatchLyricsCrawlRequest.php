<?php

namespace App\Http\Requests;

use App\Models\Song;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DispatchLyricsCrawlRequest extends FormRequest
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
        return [];
    }
}
