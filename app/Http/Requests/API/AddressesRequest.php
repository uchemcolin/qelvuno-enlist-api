<?php
// app/Http/Requests/API/AddressesRequest.php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\InternationalPhoneNumber;
use App\Rules\NigerianPhoneNumber;

/**
 * AddressesRequest handles validation for Step 2 of the application
 * 
 * Validates:
 * - Permanent address fields (all required)
 * - Residential address fields (all required)
 * - Optional phone and email fields with proper formats
 */
class AddressesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Only authenticated users can submit address information.
     * 
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     * Both permanent and residential addresses are required.
     * 
     * @return array
     */
    public function rules(): array
    {
        return [
            // ========== PERMANENT ADDRESS (Required) ==========
            'perm_street' => 'required|string|max:255',
            'perm_house_no' => 'required|string|max:50',
            'perm_area' => 'required|string|max:255',
            'perm_city' => 'required|string|max:100',
            'perm_state' => 'required|string|max:100',
            'perm_country' => 'required|string|max:100',
            
            // Optional: Phone must go with the validation rule
            'perm_phone' => [
                'nullable',
                'string',
                //new InternationalPhoneNumber(),
                new NigerianPhoneNumber(),
            ],
            
            // Optional: Email must be valid if provided
            'perm_email' => 'nullable|email|max:100',

            // ========== RESIDENTIAL ADDRESS (Required) ==========
            'res_street' => 'required|string|max:255',
            'res_house_no' => 'required|string|max:50',
            'res_area' => 'required|string|max:255',
            'res_city' => 'required|string|max:100',
            'res_state' => 'required|string|max:100',
            'res_country' => 'required|string|max:100',
            
            // Optional: Phone must be numeric if provided
            //'res_phone' => 'nullable|string|max:11|regex:/^[0-9]+$/',
            'res_phone' => [
                'nullable',
                'string',
                //new InternationalPhoneNumber(),
                new NigerianPhoneNumber(),
            ],
            
            // Optional: Email must be valid if provided
            'res_email' => 'nullable|email|max:100',
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
            // Permanent address error messages
            'perm_street.required' => 'Permanent address street is required.',
            'perm_house_no.required' => 'Permanent address house number is required.',
            'perm_area.required' => 'Permanent address area/locality is required.',
            'perm_city.required' => 'Permanent address city is required.',
            'perm_state.required' => 'Permanent address state is required.',
            'perm_country.required' => 'Permanent address country is required.',
            
            // Permanent address optional field validation
            'perm_phone.regex' => 'Permanent address phone number must contain only digits.',
            'perm_email.email' => 'Permanent address email must be a valid email address.',
            
            // Residential address error messages
            'res_street.required' => 'Residential address street is required.',
            'res_house_no.required' => 'Residential address house number is required.',
            'res_area.required' => 'Residential address area/locality is required.',
            'res_city.required' => 'Residential address city is required.',
            'res_state.required' => 'Residential address state is required.',
            'res_country.required' => 'Residential address country is required.',
            
            // Residential address optional field validation
            //'res_phone.regex' => 'Residential address phone number must contain only digits.',
            'res_email.email' => 'Residential address email must be a valid email address.',
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
            'perm_street' => trim($this->perm_street ?? ''),
            'perm_house_no' => trim($this->perm_house_no ?? ''),
            'perm_area' => trim($this->perm_area ?? ''),
            'perm_city' => trim($this->perm_city ?? ''),
            'perm_state' => trim($this->perm_state ?? ''),
            'perm_country' => trim($this->perm_country ?? ''),
            'res_street' => trim($this->res_street ?? ''),
            'res_house_no' => trim($this->res_house_no ?? ''),
            'res_area' => trim($this->res_area ?? ''),
            'res_city' => trim($this->res_city ?? ''),
            'res_state' => trim($this->res_state ?? ''),
            'res_country' => trim($this->res_country ?? ''),
        ]);
    }
}