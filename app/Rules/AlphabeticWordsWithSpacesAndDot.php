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
 * - Periods (.)
 *
 * This rule is intended for:
 * - Full Names
 * - First Names
 * - Middle Names
 * - Last Names
 * - Maiden Names
 * - Titles and initials
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
 * - Dr. John Smith
 * - J. R. Tolkien
 * - St. John
 * - A. B. Williams
 *
 * Disallowed Examples:
 * - John123       (contains numbers)
 * - John_Doe      (contains underscores)
 * - John@Doe      (contains special characters)
 * - John#Smith    (contains invalid punctuation)
 */
class AlphabeticWordsWithSpacesAndDot implements ValidationRule
{
    /**
     * Regular expression used to validate name fields.
     *
     * Allowed characters:
     * - Letters (A-Z, a-z)
     * - Spaces
     * - Apostrophes (')
     * - Hyphens (-)
     * - Periods (.)
     *
     * Not allowed:
     * - Numbers
     * - Underscores
     * - Symbols such as @, #, $, %, &, etc.
     *
     * Regex breakdown:
     *
     * ^                 Start of string
     * [a-zA-Z' .-]+     One or more allowed characters:
     *                     - Letters
     *                     - Apostrophe (')
     *                     - Space
     *                     - Period (.)
     *                     - Hyphen (-)
     * $                 End of string
     */
    private const NAME_PATTERN = "/^[a-zA-Z' .-]+$/";

    /**
     * Validation message returned when the supplied value
     * contains one or more invalid characters.
     */
    private const NAME_REGEX_MESSAGE =
        "The :attribute may contain only letters, spaces, periods (.), hyphens (-), and apostrophes (').";

    /**
     * Validate the supplied attribute.
     *
     * This rule validates only the character set used in the
     * supplied value.
     *
     * It does NOT check whether:
     * - the field is required
     * - the value is nullable
     * - the value has a minimum or maximum length
     *
     * Those concerns should be handled using Laravel's built-in
     * validation rules such as:
     *
     * - required
     * - nullable
     * - min
     * - max
     *
     * @param string  $attribute The name of the attribute being validated.
     * @param mixed   $value     The value being validated.
     * @param Closure $fail      Callback invoked when validation fails.
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Convert the value to a string and remove any
        // leading or trailing whitespace before validation.
        $value = trim((string) $value);

        // Ensure the value contains only the permitted characters.
        if (!preg_match(self::NAME_PATTERN, $value)) {
            $fail(self::NAME_REGEX_MESSAGE);
        }
    }
}