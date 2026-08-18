<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class NigerianPhoneNumber
 *
 * Validates that a phone number is a valid Nigerian phone number.
 *
 * Supported formats:
 * - Local:           08012345678
 * - International:   2348012345678
 * - International+:  +2348012345678
 *
 * Allowed Examples:
 * - 08012345678
 * - 07012345678
 * - 08112345678
 * - 09012345678
 * - 09112345678
 * - 2348012345678
 * - +2348012345678
 *
 * Disallowed Examples:
 * - +234-8012345678   (contains hyphens)
 * - +234 8012345678   (contains spaces)
 * - (080)12345678     (contains brackets)
 * - +abc123456789     (contains letters)
 * - +14155551212      (non-Nigerian number)
 * - +447700900123     (non-Nigerian number)
 * - 1234567890        (invalid format)
 * - +23480123456789   (too long)
 *
 * Note:
 * This rule validates the format only.
 * It does not verify whether the number is assigned or active.
 */
class NigerianPhoneNumber implements ValidationRule
{
    /**
     * Regular expression used to validate Nigerian phone numbers.
     *
     * Accepted formats:
     * - 0XXXXXXXXXX
     * - 234XXXXXXXXXX
     * - +234XXXXXXXXXX
     */
    private const PHONE_PATTERN = '/^(?:\+234|234|0)[0-9]{10}$/';

    /**
     * Shared validation message returned when validation fails.
     */
    private const PHONE_MESSAGE =
        'The :attribute must be a valid Nigerian phone number. Accepted formats: 08012345678, 2348012345678, or +2348012345678.';

    /**
     * Run the validation rule.
     *
     * @param string  $attribute The field name being validated.
     * @param mixed   $value     The value being validated.
     * @param Closure $fail      Callback invoked when validation fails.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Convert the value to a string and remove leading/trailing whitespace.
        $value = trim((string) $value);

        /**
         * This rule validates only the phone number format.
         *
         * Presence validation should be handled using Laravel's
         * built-in "required" validation rule.
         *
         * For an additional character limit (including the optional '+'),
         * use Laravel's built-in "max:16" validation rule.
         */
        if (!preg_match(self::PHONE_PATTERN, $value)) {
            $fail(self::PHONE_MESSAGE);
        }
    }
}