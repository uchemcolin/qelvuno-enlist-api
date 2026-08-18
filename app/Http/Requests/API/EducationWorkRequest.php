<?php
// app/Http/Requests/API/EducationWorkRequest.php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

/**
 * EducationWorkRequest handles validation for Step 4 of the application
 * 
 * Validates:
 * - Primary education (required)
 * - Secondary education (required)
 * - University education (required)
 * - Masters education (optional but must be complete if started)
 * - Work experience (optional, max 3 entries)
 * 
 * Chronological Validation Rules:
 * - Primary end date < Secondary end date (full date comparison)
 * - Secondary end date year <= University graduation year
 * - Secondary end date year <= Masters graduation year (if masters exists)
 * - University graduation year <= Masters graduation year (if masters exists)
 * - All end dates must be < current date
 */
class EducationWorkRequest extends FormRequest
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
            // ========== PRIMARY EDUCATION (Required) ==========
            'primary_school' => 'required|string|max:150',
            'primary_address' => 'required|string|max:150',
            'primary_state' => 'required|string|max:100',
            'primary_country' => 'required|string|max:100',
            'primary_end_date' => 'required|date|before:today', // Must be before current date

            // ========== SECONDARY EDUCATION (Required) ==========
            'secondary_school' => 'required|string|max:150',
            'secondary_address' => 'required|string|max:150',
            'secondary_state' => 'required|string|max:100',
            'secondary_country' => 'required|string|max:100',
            'secondary_end_date' => 'required|date|before:today', // Must be before current date

            // ========== UNIVERSITY EDUCATION (Required) ==========
            'uni_name' => 'required|string|max:150',
            'uni_address' => 'required|string|max:150',
            'uni_state' => 'required|string|max:100',
            'uni_country' => ['required', 'string', 'max:100'],
            'uni_discipline' => 'required|string|max:100',
            'uni_degree' => 'required|string|max:100',
            'uni_class' => 'required|string|max:100',
            'uni_matric' => 'required|string|max:50',
            'uni_graduation_year' => 'required|integer|min:1950|max:' . date('Y'),

            // ========== MASTERS EDUCATION (Optional but conditional) ==========
            'masters_institution_name' => 'nullable|string|max:150',
            'masters_address' => 'nullable|string|max:150',
            //'masters_state' => ['nullable', 'integer', Rule::exists('recruitment_state', 'State_id')],
            'masters_state' => ['nullable', 'string', 'max:100'],
            'masters_country' => ['nullable', 'string', 'max:100'],
            'masters_discipline' => 'nullable|string|max:100',
            'masters_matric_no' => 'nullable|string|max:50',
            'masters_graduation_year' => 'nullable|integer|min:1950|max:' . date('Y'),

            // ========== WORK EXPERIENCE (Optional, max 3 entries) ==========
            'experience' => 'nullable|array|max:3',
            'experience.*.position' => 'required_with:experience|string|max:100',
            'experience.*.company' => 'required_with:experience|string|max:100',
            'experience.*.start_date' => 'required_with:experience|date|before:today', // Must be before current date
            'experience.*.end_date' => 'required_with:experience|date|after_or_equal:experience.*.start_date|before:today', // Must be >= start date and < today
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
            // ========== PRIMARY EDUCATION MESSAGES ==========
            'primary_school.required' => 'Primary school name is required.',
            'primary_school.string' => 'Primary school name must be a valid text.',
            'primary_school.max' => 'Primary school name cannot exceed 150 characters.',
            'primary_address.required' => 'Primary school address is required.',
            'primary_address.string' => 'Primary school address must be a valid text.',
            'primary_address.max' => 'Primary school address cannot exceed 150 characters.',
            'primary_state.required' => 'Primary school state is required.',
            'primary_state.string' => 'Primary school state must be a valid text.',
            'primary_state.max' => 'Primary school state cannot exceed 100 characters.',
            'primary_country.required' => 'Primary school country is required.',
            'primary_country.string' => 'Primary school coutry must be a valid text.',
            'primary_country.max' => 'Primary school country cannot exceed 100 characters.',
            'primary_end_date.required' => 'Primary school completion date is required.',
            'primary_end_date.date' => 'Primary school completion date must be a valid date.',
            'primary_end_date.before' => 'Primary school completion date must be before today.',
            
            // ========== SECONDARY EDUCATION MESSAGES ==========
            'secondary_school.required' => 'Secondary school name is required.',
            'secondary_school.string' => 'Secondary school name must be a valid text.',
            'secondary_school.max' => 'Secondary school name cannot exceed 150 characters.',
            'secondary_address.required' => 'Secondary school address is required.',
            'secondary_address.string' => 'Secondary school address must be a valid text.',
            'secondary_address.max' => 'Secondary school address cannot exceed 150 characters.',
            'secondary_state.required' => 'Secondary school state is required.',
            'secondary_state.string' => 'Secondary school state must be a valid text.',
            'secondary_state.max' => 'Secondary school state cannot exceed 100 characters.',
            'secondary_country.required' => 'Secondary school country is required.',
            'secondary_country.string' => 'Secondary school country must be a valid text.',
            'secondary_country.max' => 'Secondary school country cannot exceed 100 characters.',
            'secondary_end_date.required' => 'Secondary school completion date is required.',
            'secondary_end_date.date' => 'Secondary school completion date must be a valid date.',
            'secondary_end_date.before' => 'Secondary school completion date must be before today.',
            
            // ========== UNIVERSITY EDUCATION MESSAGES ==========
            'uni_name.required' => 'University name is required.',
            'uni_name.string' => 'University name must be a valid text.',
            'uni_name.max' => 'University name cannot exceed 150 characters.',
            'uni_address.required' => 'University address is required.',
            'uni_address.string' => 'University address must be a valid text.',
            'uni_address.max' => 'University address cannot exceed 150 characters.',
            'uni_state.required' => 'University state is required.',
            'uni_state.string' => 'University state must be a valid text.',
            'uni_state.max' => 'University state cannot exceed 100 characters.',
            'uni_country.required' => 'University country is required.',
            'uni_country.string' => 'University country must be a valid text.',
            'uni_country.max' => 'University state cannot exceed 100 characters.',
            'uni_discipline.required' => 'Field of study/discipline is required.',
            'uni_discipline.string' => 'Field of study must be a valid text.',
            'uni_discipline.max' => 'Field of study cannot exceed 100 characters.',
            'uni_degree.required' => 'Degree type is required.',
            'uni_degree.string' => 'Degree type must be a valid text.',
            'uni_degree.max' => 'Degree type cannot exceed 100 characters.',
            'uni_class.required' => 'Class of degree is required.',
            'uni_class.string' => 'Class of degree must be a valid text.',
            'uni_class.max' => 'Class of degree cannot exceed 100 characters.',
            'uni_matric.required' => 'Matriculation number is required.',
            'uni_matric.string' => 'Matriculation number must be a valid text.',
            'uni_matric.max' => 'Matriculation number cannot exceed 50 characters.',
            'uni_graduation_year.required' => 'University graduation year is required.',
            'uni_graduation_year.integer' => 'University graduation year must be a valid year.',
            'uni_graduation_year.min' => 'University graduation year must be at least 1950.',
            'uni_graduation_year.max' => 'University graduation year cannot be in the future.',
            
            // ========== MASTERS EDUCATION MESSAGES ==========
            //'masters_state.exists' => 'Selected masters education state is invalid.',
            'masters_state.required' => 'Masters state is required.',
            'masters_state.string' => 'Masters state must be a valid text.',
            'masters_state.max' => 'Masters state cannot exceed 100 characters.',
            'masters_country.required' => 'Masters country is required.',
            'masters_country.string' => 'Masters country must be a valid text.',
            'masters_country.max' => 'Masters country cannot exceed 100 characters.',
            'masters_graduation_year.integer' => 'Masters graduation year must be a valid year.',
            'masters_graduation_year.min' => 'Masters graduation year must be at least 1950.',
            'masters_graduation_year.max' => 'Masters graduation year cannot be in the future.',
            
            // ========== WORK EXPERIENCE MESSAGES ==========
            'experience.max' => 'You can only add up to 3 work experiences.',
            'experience.array' => 'Experience must be provided as an array.',
            'experience.*.position.required_with' => 'Position is required for each work experience entry.',
            'experience.*.position.string' => 'Position must be a valid text.',
            'experience.*.position.max' => 'Position cannot exceed 100 characters.',
            'experience.*.company.required_with' => 'Company name is required for each work experience entry.',
            'experience.*.company.string' => 'Company name must be a valid text.',
            'experience.*.company.max' => 'Company name cannot exceed 100 characters.',
            'experience.*.start_date.required_with' => 'Start date is required for each work experience entry.',
            'experience.*.start_date.date' => 'Start date must be a valid date.',
            'experience.*.start_date.before' => 'Start date must be before today.',
            'experience.*.end_date.required_with' => 'End date is required for each work experience entry.',
            'experience.*.end_date.date' => 'End date must be a valid date.',
            'experience.*.end_date.after_or_equal' => 'End date must be after or equal to start date for each work experience.',
            'experience.*.end_date.before' => 'End date must be before today.',
        ];
    }

    /**
     * Get custom validation rules that run after the main validation.
     * 
     * Handles:
     * - Conditional validation for masters education
     * - Chronological validation using dates and years
     * - Primary end date < Secondary end date (full date comparison)
     * - Secondary end date year <= University graduation year
     * - Secondary end date year <= Masters graduation year
     * - University graduation year <= Masters graduation year
     * 
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // =============================================
            // MASTERS EDUCATION CONDITIONAL VALIDATION
            // =============================================
            // Check if any masters field is filled
            $mastersFields = [
                'masters_institution_name',
                'masters_address',
                'masters_state',
                'masters_country',
                'masters_discipline',
                'masters_matric_no',
                'masters_graduation_year',
            ];

            $hasAnyMastersInput = collect($mastersFields)->contains(fn($field) => $this->filled($field));

            // If any masters field is filled, all must be filled
            if ($hasAnyMastersInput) {
                foreach ($mastersFields as $field) {
                    if (!$this->filled($field)) {
                        $validator->errors()->add($field, 'All Masters education fields must be completed once you start filling Masters education.');
                    }
                }
            }

            // =============================================
            // PRIMARY END DATE < SECONDARY END DATE
            // (Full date comparison)
            // =============================================
            if ($this->filled('primary_end_date') && $this->filled('secondary_end_date')) {
                try {
                    $primaryEnd = Carbon::parse($this->primary_end_date);
                    $secondaryEnd = Carbon::parse($this->secondary_end_date);
                    
                    // Primary end date must be before secondary end date
                    if ($primaryEnd->greaterThanOrEqualTo($secondaryEnd)) {
                        $validator->errors()->add('secondary_end_date', 'Secondary school must end after primary school completion.');
                    }
                } catch (\Exception $e) {
                    // Date parsing will be handled by main validation
                }
            }

            // =============================================
            // EXTRACT YEAR FROM SECONDARY END DATE
            // =============================================
            $secondaryEndYear = null;
            if ($this->filled('secondary_end_date')) {
                try {
                    $secondaryEndYear = Carbon::parse($this->secondary_end_date)->year;
                } catch (\Exception $e) {
                    // Date parsing will be handled by main validation
                }
            }

            // =============================================
            // SECONDARY END DATE YEAR <= UNIVERSITY GRADUATION YEAR
            // =============================================
            if ($secondaryEndYear !== null && $this->filled('uni_graduation_year')) {
                if ($secondaryEndYear > $this->uni_graduation_year) {
                    $validator->errors()->add('uni_graduation_year', 'University graduation year must be after or equal to secondary school completion year.');
                }
            }

            // =============================================
            // SECONDARY END DATE YEAR <= MASTERS GRADUATION YEAR (if masters exists)
            // =============================================
            if ($secondaryEndYear !== null && $this->filled('masters_graduation_year')) {
                if ($secondaryEndYear > $this->masters_graduation_year) {
                    $validator->errors()->add('masters_graduation_year', 'Masters graduation year must be after or equal to secondary school completion year.');
                }
            }

            /*// =============================================
            // UNIVERSITY GRADUATION YEAR <= MASTERS GRADUATION YEAR (if masters exists)
            // =============================================
            if ($this->filled('uni_graduation_year') && $this->filled('masters_graduation_year')) {
                if ($this->uni_graduation_year > $this->masters_graduation_year) {
                    $validator->errors()->add('masters_graduation_year', 'Masters graduation year must be after or equal to university graduation year.');
                }
            }*/

            // =============================================
            // WORK EXPERIENCE ADDITIONAL VALIDATION
            // =============================================
            if ($this->has('experience') && is_array($this->experience)) {
                foreach ($this->experience as $index => $experience) {
                    // Check if start date and end date exist and are valid
                    if (isset($experience['start_date']) && isset($experience['end_date'])) {
                        try {
                            $start = Carbon::parse($experience['start_date']);
                            $end = Carbon::parse($experience['end_date']);
                            
                            // Validate start date <= end date (redundant but safe)
                            if ($start->greaterThan($end)) {
                                $validator->errors()->add(
                                    "experience.{$index}.start_date", 
                                    "Work experience #" . ($index + 1) . " start date must be before or equal to end date."
                                );
                            }
                        } catch (\Exception $e) {
                            // If dates are invalid, they'll be caught by the main validation
                        }
                    }
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     * 
     * Sanitizes input by:
     * - Trimming whitespace from all string fields
     * - Converting empty masters fields to null
     * - Cleaning work experience data
     * 
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from all string fields
        $this->merge([
            // Primary education - trim strings
            'primary_school' => trim($this->primary_school ?? ''),
            'primary_address' => trim($this->primary_address ?? ''),
            'primary_state' => trim($this->primary_state ?? ''),
            
            // Secondary education - trim strings
            'secondary_school' => trim($this->secondary_school ?? ''),
            'secondary_address' => trim($this->secondary_address ?? ''),
            'secondary_state' => trim($this->secondary_state ?? ''),
            
            // University education - trim strings
            'uni_name' => trim($this->uni_name ?? ''),
            'uni_address' => trim($this->uni_address ?? ''),
            'uni_state' => trim($this->uni_state ?? ''),
            'uni_discipline' => trim($this->uni_discipline ?? ''),
            'uni_degree' => trim($this->uni_degree ?? ''),
            'uni_class' => trim($this->uni_class ?? ''),
            'uni_matric' => trim($this->uni_matric ?? ''),
            
            // Masters education - trim strings and convert empty to null
            'masters_institution_name' => $this->masters_institution_name ? trim($this->masters_institution_name) : null,
            'masters_address' => $this->masters_address ? trim($this->masters_address) : null,
            'masters_discipline' => $this->masters_discipline ? trim($this->masters_discipline) : null,
            'masters_matric_no' => $this->masters_matric_no ? trim($this->masters_matric_no) : null,
            
            // Work experience - trim each entry
            'experience' => $this->experience ? array_map(function ($item) {
                return array_map(function ($value) {
                    return is_string($value) ? trim($value) : $value;
                }, $item);
            }, $this->experience) : null,
        ]);
    }
}