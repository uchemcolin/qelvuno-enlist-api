<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class InternationalPhoneNumber
 *
 * Validates that a phone number:
 * - Contains only numeric digits
 * - May optionally begin with a plus sign (+)
 * - Contains between 10 and 15 digits
 * - Supports both local and international formats
 *
 * Allowed Examples:
 * - +2348012345678
 * - 2348012345678
 * - +14155551212
 * - 14155551212
 * - +447700900123
 *
 * Disallowed Examples:
 * - +234-8012345678   (contains hyphens)
 * - +234 8012345678   (contains spaces)
 * - (234)8012345678   (contains brackets)
 * - +abc123456789     (contains letters)
 * - +123              (too short)
 * - 123               (too short)
 * - +1234567890123456 (too long)
 *
 * Note:
 * This rule validates format only.
 * It does not verify whether the country code or number
 * actually exists.
 *
 * E.164 Standard:
 * - Allows up to 15 digits.
 * - Commonly uses a leading plus sign (+).
 * - Contains digits only after the plus sign.
 */
class InternationalPhoneNumber implements ValidationRule
{
    /**
     * Regular expression used to validate phone numbers.
     *
     * Format:
     * - Optional leading plus sign (+)
     * - Digits only
     * - Between 10 and 15 digits
     *
     * Valid:
     * - +2348012345678
     * - 2348012345678
     */
    private const PHONE_PATTERN = '/^\+?[0-9]{10,15}$/';

    /**
     * Shared validation message returned when validation fails.
     */
    private const PHONE_MESSAGE =
        'The :attribute must contain only digits and may optionally begin with a plus sign (+).';

    /**
     * Run the validation rule.
     *
     * @param string  $attribute The field name being validated.
     * @param mixed   $value     The value being validated.
     * @param Closure $fail      Callback invoked when validation fails.
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Convert the value to a string and remove leading/trailing whitespace.
        $value = trim((string) $value);

        /**
         * This rule only validates phone number format.
         *
         * Presence/required validation should be handled
         * by Laravel's built-in "required" validation rule.
         */
        if (!preg_match(self::PHONE_PATTERN, $value)) {
            $fail(self::PHONE_MESSAGE);
        }
    }
}