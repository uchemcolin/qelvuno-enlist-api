<?php
// app/Http/Requests/API/PersonalInfoRequest.php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Validation\Rule;
use App\Rules\AlphabeticWordsWithSpaces;
use App\Models\PersonalInfo;
use App\Rules\AlphabeticWords;
// use App\Rules\AlphabeticWordsWithSpaces; // Uncomment if middleName should allow spaces

/**
 * PersonalInfoRequest handles validation for Step 1 of the application
 * 
 * Validates:
 * - Personal details with proper regex patterns
 * - NIN must be numeric and exactly 11 digits
 * - Names must contain only alphabets, spaces, and hyphens
 * - File uploads with conditional requirements
 */
class PersonalInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Only authenticated users can submit personal information.
     * 
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     * Rules are conditional based on whether this is a new application or update.
     * 
     * @return array
     */
    public function rules(): array
    {
        $user = $this->user();
        
        // Check if personal info already exists to determine file requirements
        $existingPersonalInfo = null;
        if ($user && $user->biodataID) {
            $existingPersonalInfo = PersonalInfo::where('biodataID', $user->biodataID)->first();
        }

        // Determine if files are required (only if they don't exist yet)
        // This allows updates without re-uploading files
        $requirePassport = true;
        $requireBirthCert = true;

        if ($existingPersonalInfo) {
            $requirePassport = empty($existingPersonalInfo->passportPhotograph);
            $requireBirthCert = empty($existingPersonalInfo->birthCertificate);
        }

        // Build email uniqueness rule across both tables
        $emailRule = ['required', 'email', 'max:100'];
        
        /*if ($existingPersonalInfo) {
            // For update: check uniqueness across both tables, excluding current record
            $emailRule[] = Rule::unique('users', 'email')->ignore($user->id);
            $emailRule[] = Rule::unique('personal_info', 'email')
                ->where('biodataID', '!=', $user->biodataID);
        } else {
            // For new: check uniqueness across both tables
            $emailRule[] = Rule::unique('users', 'email')->ignore($user->id);
            $emailRule[] = Rule::unique('personal_info', 'email');
        }*/

        if ($existingPersonalInfo) {
            $emailRule[] = function ($attribute, $value, $fail) use ($user, $existingPersonalInfo) {
                // Check users table (excluding current user)
                $userExists = User::where('email', $value)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if ($userExists) {
                    $fail('This email address is already registered in the users table.');
                }

                // Check personal_info table (excluding current record)
                $personalInfoExists = PersonalInfo::where('email', $value)
                    ->where('biodataID', '!=', $user->biodataID)
                    ->exists();

                if ($personalInfoExists) {
                    $fail('This email address is already registered in the personal_info table.');
                }
            };
        } else {
            // For new: check uniqueness across both tables
            $emailRule[] = Rule::unique('users', 'email')->ignore($user->id);
            $emailRule[] = Rule::unique('personal_info', 'email');
        }

        // Build phone uniqueness rule across both tables
        // Initialize $phoneRule as an array with required validation
        $phoneRule = ['required', 'string', 'max:11', 'regex:/^[0-9]+$/'];

        if ($existingPersonalInfo) {
            /*// For users table: exclude current user's phone using ignore()
            $phoneRule[] = Rule::unique('users', 'phone')->ignore($user->id);
            
            // For personal_info table: exclude current record by biodataID
            $phoneRule[] = Rule::unique('personal_info', 'phoneNo')
                ->where('biodataID', '!=', $user->biodataID);*/

            // Use a custom rule to handle the uniqueness check
            $phoneRule[] = function ($attribute, $value, $fail) use ($user, $existingPersonalInfo) {
                // Check users table (excluding current user)
                $userExists = User::where('phone', $value)
                    ->where('id', '!=', $user->id)
                    ->exists();
                
                if ($userExists) {
                    $fail('This phone number is already registered in the users table.');
                }
                
                // Check personal_info table (excluding current record)
                $personalInfoExists = PersonalInfo::where('phoneNo', $value)
                    ->where('biodataID', '!=', $user->biodataID)
                    ->exists();
                
                if ($personalInfoExists) {
                    $fail('This phone number is already registered in the personal_info table.');
                }
            };
        } else {
            // For new: check uniqueness across both tables
            $phoneRule[] = Rule::unique('users', 'phone')->ignore($user->id);
            $phoneRule[] = Rule::unique('personal_info', 'phoneNo');
        }

        // Base validation rules for all personal information fields
        $rules = [
            // Title is required and must be a valid string
            'title'                 => 'required|string|max:20',
            
            /**
             * Name fields validation using custom AlphabeticWords rule.
             * 
             * All name fields (surname, firstName, maidenName) use AlphabeticWords
             * which allows only letters, hyphens (-), and apostrophes (').
             * 
             * Allowed for surname, firstName, maidenName:
             * - John
             * - Mary-Jane
             * - O'Connor
             * - D'Arcy-Smith
             * 
             * Not allowed for surname, firstName, maidenName:
             * - John Doe      (contains a space)
             * - John123       (contains numbers)
             * - John_Doe      (contains an underscore)
             * - John@Doe      (contains a special character)
             * 
             * Middle name (middleName) is currently using the same AlphabeticWords rule.
             * If you want to allow spaces in middle names (e.g., "Anne Marie"),
             * uncomment the AlphabeticWordsWithSpaces rule below and comment out the current one.
             */
            'surname'    => ['required', 'string', 'max:100', new AlphabeticWords()],
            'firstName'  => ['required', 'string', 'max:100', new AlphabeticWords()],
            
            /**
             * Middle Name - Currently uses AlphabeticWordsWithSpaces (spaces allowed).
             * 
             * To disallow spaces in middle names (e.g., "Anne Marie", "Jean Paul"),
             * replace the current rule with:
             * 'middleName' => ['nullable', 'string', 'max:100', new AlphabeticWords()],
             */
            //'middleName' => ['nullable', 'string', 'max:100', new AlphabeticWords()],
            'middleName' => ['nullable', 'string', 'max:100', new AlphabeticWordsWithSpaces()], // Uncomment to allow spaces
            
            'maidenName' => ['nullable', 'string', 'max:100', new AlphabeticWords()],
            
            // Date of birth must be a valid date and not in the future
            //'dateOfBirth'           => 'required|date|before:today',
            'dateOfBirth' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . Carbon::now()->subYears(18)->format('Y-m-d'),
                'after_or_equal:' . Carbon::now()->subYears(50)->format('Y-m-d'),
            ],
            
            // Place of birth allows alphabets, spaces, dots, commas, and hyphens
            'placeOfBirth'          => 'required|string|max:100|regex:/^[a-zA-Z\s\.,\-]+$/',
            
            // Gender must be Male or Female
            'gender'                => 'required|in:Male,Female',
            
            // Email must be valid format
            //'email'                 => 'required|email|max:100',
            'email'                 => $emailRule,
            
            // Phone number must be numeric only (no spaces or special chars)
            //'phoneNo'               => 'required|string|max:11|regex:/^[0-9]+$/',
            'phoneNo'               => $phoneRule,
            
            // State and local government (free text but required)
            'state_of_origin'       => 'required|string|max:100',
            'local_govt'            => 'required|string|max:100',
            
            // Nationality with default value 'Nigerian'
            'nationality'           => 'nullable|string|max:100',
            
            // Disability ID must exist in the recruitment_disability table if provided
            //'disability_id'         => 'nullable|integer|exists:recruitment_disability,disability_id',
            //'disability_id'         => 'nullable|integer|exists:disability,disability_id',
            'disability_id'         => 'nullable|integer',
            
            // NIN must be exactly 11 numeric digits (no letters or spaces)
            'nin'                   => 'required|string|size:11|regex:/^[0-9]+$/',
        ];

        // Conditional file validation based on whether files already exist
        if ($requirePassport) {
            $rules['passportPhotograph'] = 'required|file|mimes:jpg,jpeg,png|max:150';
        } else {
            $rules['passportPhotograph'] = 'nullable|file|mimes:jpg,jpeg,png|max:150';
        }

        if ($requireBirthCert) {
            $rules['birthCertificate'] = 'required|file|mimes:jpg,jpeg,png|max:150';
        } else {
            $rules['birthCertificate'] = 'nullable|file|mimes:jpg,jpeg,png|max:150';
        }

        return $rules;
    }

    /**
     * Get custom error messages for validation rules.
     * Provides user-friendly error messages for all validation failures.
     * 
     * @return array
     */
    public function messages(): array
    {
        $messages = [
            // Personal info field errors
            'title.required' => 'Title is required.',
            
            // Surname validation messages
            'surname.required' => 'Surname is required.',
            
            // First Name validation messages
            'firstName.required' => 'First name is required.',
            
            // Middle Name validation messages
            'middleName' => 'Middle name may contain only letters, hyphens (-), and apostrophes (\').',
            
            // Maiden Name validation messages
            'maidenName' => 'Maiden name may contain only letters, hyphens (-), and apostrophes (\').',
            
            // Date of Birth validation messages
            'dateOfBirth.required' => 'Date of birth is required.',
            'dateOfBirth.before' => 'Date of birth must be a past date.',
            'dateOfBirth.before_or_equal' => 'Applicant must be at least 18 years old.',
            'dateOfBirth.after_or_equal' => 'Applicant must not be older than 50 years.',
            
            // Place of Birth validation messages
            'placeOfBirth.required' => 'Place of birth is required.',
            'placeOfBirth.regex' => 'Place of birth must contain only alphabets, spaces, dots, commas, or hyphens.',
            
            // Gender validation messages
            'gender.required' => 'Gender is required.',
            'gender.in' => 'Gender must be Male or Female.',
            
            // Email validation messages
            'email.required' => 'Email is required.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'This email is already registered. Please use a different email address.',
            
            // Phone validation messages
            'phoneNo.required' => 'Phone number is required.',
            'phoneNo.regex' => 'Phone number must contain only digits.',
            'phoneNo.unique' => 'This phone number is already registered. Please use a different phone number.',
            
            // Origin validation messages
            'state_of_origin.required' => 'State of origin is required.',
            'local_govt.required' => 'Local government is required.',
            
            // NIN validation messages
            'nin.required' => 'NIN is required.',
            'nin.size' => 'NIN must be exactly 11 characters.',
            'nin.regex' => 'NIN must contain only numeric digits (0-9).',
            
            // Disability validation messages
            'disability_id.exists' => 'Selected disability type is invalid.',
        ];

        // Add file error messages only if files are required
        if (isset($this->rules()['passportPhotograph']) && strpos($this->rules()['passportPhotograph'], 'required') === 0) {
            $messages['passportPhotograph.required'] = 'Passport photograph is required.';
            $messages['passportPhotograph.mimes'] = 'Passport photograph must be JPG, JPEG, or PNG format.';
            $messages['passportPhotograph.max'] = 'Passport photograph must not exceed 200KB in size.';
        }

        if (isset($this->rules()['birthCertificate']) && strpos($this->rules()['birthCertificate'], 'required') === 0) {
            $messages['birthCertificate.required'] = 'Birth certificate is required.';
            $messages['birthCertificate.mimes'] = 'Birth certificate must be JPG, JPEG, or PNG format.';
            $messages['birthCertificate.max'] = 'Birth certificate must not exceed 200KB in size.';
        }

        return $messages;
    }

    /**
     * Prepare the data for validation.
     * Cleans up input data before validation rules are applied.
     * 
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from name fields to prevent spaces-only values
        // Remove spaces from NIN (users might copy-paste with spaces)
        $this->merge([
            'surname' => trim($this->surname),
            'firstName' => trim($this->firstName),
            'middleName' => $this->middleName ? trim($this->middleName) : null,
            'maidenName' => $this->maidenName ? trim($this->maidenName) : null,
            'nin' => preg_replace('/\s+/', '', $this->nin), // Remove all whitespace from NIN
            'phoneNo' => preg_replace('/\s+/', '', $this->phoneNo), // Remove all whitespace from phone
        ]);
    }
}