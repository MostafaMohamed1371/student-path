<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class FullNameWordCount implements ValidationRule
{
    public function __construct(
        private readonly int $minWords = 3,
        private readonly int $maxWords = 4,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $words = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = count($words);

        if ($count < $this->minWords || $count > $this->maxWords) {
            $fail(__('dashboard.student_full_name_word_count', [
                'min' => $this->minWords,
                'max' => $this->maxWords,
            ]));
        }
    }
}
