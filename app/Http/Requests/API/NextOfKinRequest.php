<?php
// app/Http/Requests/API/NextOfKinRequest.php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\InternationalPhoneNumber;
use App\Rules\AlphabeticWordsWithSpacesAndDot; // Added

/**
 * NextOfKinRequest handles validation for Step 3 of the application
 *
 * Validates:
 * - Next of kin personal details
 * - Contact information with proper formats
 * - Gender must be Male or Female
 */
class NextOfKinRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            // Full name can contain alphabets, spaces, dots and hyphens
            'fullName' => [
                'required',
                'string',
                'max:255',
                new AlphabeticWordsWithSpacesAndDot(),

                // Previous regex validation
                // 'regex:/^[a-zA-Z.\s-]+$/',
            ],

            // Address can be any string (includes numbers, etc.)
            'address' => 'required|string|max:500',

            // Relationship can contain alphabets, spaces, and hyphens
            'relationship' => 'required|string|max:100|regex:/^[a-zA-Z\s\-]+$/',

            'phone' => [
                'required',
                'string',
                new InternationalPhoneNumber(),
            ],

            // Email must be valid format
            'email' => 'required|email|max:100',

            // Gender must be Male or Female
            'gender' => 'required|in:Male,Female',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'fullName.required' => 'Next of kin full name is required.',
            'fullName.regex' => 'Next of kin name must contain only alphabets, spaces, or hyphens.',
            'address.required' => 'Next of kin address is required.',
            'relationship.required' => 'Relationship to applicant is required.',
            'relationship.regex' => 'Relationship must contain only alphabets, dot, spaces, or hyphens.',
            'phone.required' => 'Next of kin phone number is required.',
            'phone.string' => 'Next of kin phone number has to follow the rule.',
            'email.required' => 'Next of kin email address is required.',
            'email.email' => 'Next of kin email must be a valid email address.',
            'gender.required' => 'Next of kin gender is required.',
            'gender.in' => 'Next of kin gender must be Male or Female.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from all string fields
        $this->merge([
            'fullName' => trim($this->fullName ?? ''),
            'address' => trim($this->address ?? ''),
            'relationship' => trim($this->relationship ?? ''),
            'phone' => preg_replace('/\s+/', '', $this->phone ?? ''), // Remove spaces from phone
        ]);
    }
}