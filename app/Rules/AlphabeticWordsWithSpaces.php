<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class AlphabeticWordsWithSpaces
 *
 * Validates that a name field contains only:
 * - Alphabetic characters (A-Z, a-z)
 * - Spaces
 * - Hyphens (-)
 * - Apostrophes (')
 *
 * This rule is intended for:
 * - Full Names
 * - Surnames with multiple words
 * - First Names
 * - Middle Names
 * - Maiden Names
 *
 * Allowed Examples:
 * - John
 * - John Smith
 * - Mary Jane
 * - O'Connor
 * - Mary-Jane
 * - D'Arcy Smith
 * - D'Arcy-Smith
 * - Anne Marie O'Neill
 *
 * Disallowed Examples:
 * - John123       (contains numbers)
 * - John_Doe      (contains underscores)
 * - John@Doe      (contains special characters)
 * - John. Doe     (contains periods)
 */
class AlphabeticWordsWithSpaces implements ValidationRule
{
    /**
     * Regular expression used to validate name fields.
     *
     * Allowed:
     * - a-z and A-Z (alphabetic characters)
     * - Spaces
     * - Apostrophes (')
     * - Hyphens (-)
     *
     * Not Allowed:
     * - Numbers
     * - Underscores
     * - Other special characters
     */
    private const NAME_PATTERN = "/^[a-zA-Z' -]+$/";

    /**
     * Shared validation message returned when validation fails.
     */
    private const NAME_REGEX_MESSAGE =
        "The :attribute may contain only letters, spaces, hyphens (-), and apostrophes (').";

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
         * This rule only validates character format.
         *
         * Presence/required validation should be handled
         * by Laravel's built-in "required" validation rule.
         */
        if (!preg_match(self::NAME_PATTERN, $value)) {
            $fail(self::NAME_REGEX_MESSAGE);
        }
    }
}