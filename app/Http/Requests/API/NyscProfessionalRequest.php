<?php
// app/Http/Requests/API/NyscProfessionalRequest.php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

/**
 * NyscProfessionalRequest handles validation for Step 5 of the application
 * 
 * Validates:
 * - NYSC certificate details (required)
 * - Professional qualifications (optional)
 */
class NyscProfessionalRequest extends FormRequest
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
            // ========== NYSC (Required) ==========
            'nysc_cert_no' => 'required|string|max:45',
            'nysc_completion_date' => 'required|date|before_or_equal:today',
            //'nysc_type' => 'required|string|max:45|in:Regular,Stream I,Stream II', // Adjust as needed
            'nysc_type' => 'required|string|max:45', // Adjust as needed
            
            // ========== PROFESSIONAL QUALIFICATION (Optional) ==========
            'prof_qualification' => 'nullable|string|max:50',
            'prof_organization' => 'nullable|string|max:50',
            'prof_membership_no' => 'nullable|string|max:50',
            'prof_date' => 'nullable|date|before_or_equal:today',
            'prof_class' => 'nullable|string|max:50',
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
            // NYSC validation messages
            'nysc_cert_no.required' => 'NYSC certificate number is required.',
            'nysc_completion_date.required' => 'NYSC completion date is required.',
            'nysc_completion_date.before_or_equal' => 'NYSC completion date cannot be in the future.',
            'nysc_type.required' => 'NYSC type is required.',
            //'nysc_type.in' => 'NYSC type must be Regular, Stream I, or Stream II.',
            
            // Professional qualification validation messages
            'prof_date.before_or_equal' => 'Professional qualification date cannot be in the future.',
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
            'nysc_cert_no' => trim($this->nysc_cert_no ?? ''),
            'nysc_type' => trim($this->nysc_type ?? ''),
            'prof_qualification' => $this->prof_qualification ? trim($this->prof_qualification) : null,
            'prof_organization' => $this->prof_organization ? trim($this->prof_organization) : null,
            'prof_membership_no' => $this->prof_membership_no ? trim($this->prof_membership_no) : null,
            'prof_class' => $this->prof_class ? trim($this->prof_class) : null,
        ]);
    }

    /**
     * Determine if the professional qualification section is completely filled.
     * This can be used in controllers to check if professional data exists.
     * 
     * @return bool
     */
    public function hasCompleteProfessionalQualification(): bool
    {
        return $this->filled('prof_qualification') && 
               $this->filled('prof_organization') && 
               $this->filled('prof_membership_no') && 
               $this->filled('prof_date') && 
               $this->filled('prof_class');
    }
}