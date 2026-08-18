<?php
// app/Http/Controllers/API/ApplicationController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\API\PersonalInfoRequest;
use App\Http\Requests\API\AddressesRequest;
use App\Http\Requests\API\NextOfKinRequest;
use App\Http\Requests\API\EducationWorkRequest;
use App\Http\Requests\API\NyscProfessionalRequest;
use App\Services\ApplicationService;
use App\Models\PersonalInfo;
use App\Helpers\FilePathResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ApplicationController handles the multi-step application submission process
 * 
 * This controller manages a 6-step application workflow:
 * 
 * Step 1: Personal Information and file uploads
 *   - Personal details (name, DOB, gender, NIN, etc.)
 *   - Passport photograph upload
 *   - Birth certificate upload
 * 
 * Step 2: Permanent and Residential Addresses
 *   - Complete address details for both permanent and current residence
 * 
 * Step 3: Next of Kin information
 *   - Emergency contact details
 * 
 * Step 4: Education (Primary, Secondary, University, Masters) and Work Experience
 *   - Educational background from primary to university
 *   - Optional masters degree
 *   - Optional work experience (max 3 entries)
 * 
 * Step 5: NYSC and Professional Qualifications
 *   - NYSC certificate details
 *   - Optional professional certifications
 * 
 * Step 6: Final submission and application locking
 *   - Generates reference number
 *   - Locks application from further edits
 *   - Sends confirmation email
 * 
 * All database operations are delegated to ApplicationService for better 
 * separation of concerns, testability, and maintainability.
 * 
 * @package App\Http\Controllers\API
 */
class ApplicationController extends Controller
{
    // Use the FilePathResolver helper trait for file URL resolution
    use FilePathResolver;

    /**
     * @var ApplicationService
     */
    protected ApplicationService $applicationService;

    /**
     * ApplicationController constructor.
     * 
     * Injects the ApplicationService dependency which handles all database operations.
     * This promotes loose coupling and makes the controller easier to test.
     * 
     * @param ApplicationService $applicationService
     */
    public function __construct(ApplicationService $applicationService)
    {
        $this->applicationService = $applicationService;
    }

    // ==================== STEP 1: PERSONAL INFO + FILES ====================
    
    /**
     * Submit or update personal information (Step 1 of the application process)
     * 
     * This endpoint handles:
     * - Personal details (name, DOB, gender, NIN, etc.)
     * - File uploads (passport photograph and birth certificate)
     * - Duplicate checking (prevents multiple applications with same NIN/phone/email)
     * - User record synchronization (updates user table with latest info)
     * 
     * Validation is handled by PersonalInfoRequest which includes:
     * - Name fields must be alphabetic only
     * - NIN must be numeric and exactly 11 digits
     * - Phone numbers must be numeric
     * - Conditional file validation (files only required if not already uploaded)
     * 
     * @param PersonalInfoRequest $request - Validated request with personal info data
     * @return \Illuminate\Http\JsonResponse
     * 
     * @response 201 {
     *   "message": "Step 1 completed: Personal info saved successfully",
     *   "biodata_id": 12345,
     *   "existing_files_kept": {
     *     "passport": false,
     *     "birth_certificate": false
     *   }
     * }
     * 
     * @response 409 {
     *   "message": "Another application already exists with this NIN, Phone, or Email"
     * }
     * 
     * @response 423 {
     *   "message": "Change password first"
     * }
     */
    public function submitPersonalInfo(PersonalInfoRequest $request)
    {
        $user = $request->user();

        // Check if application is already locked (has reference number)
        // Locked applications cannot be modified
        if ($this->applicationService->isApplicationLocked($user)) {
            return response()->json([
                'message' => 'Application already completed. Cannot edit.'
            ], 409);
        }

        // Security check: User must change temporary password before proceeding
        if ($user->must_change_password) {
            return response()->json([
                'message' => 'Change password first'
            ], 423);
        }

        // Get validated data from form request (automatically validated)
        $validatedData = $request->validated();
        
        // Extract files from request for separate handling
        $files = [];
        if ($request->hasFile('passportPhotograph')) {
            $files['passportPhotograph'] = $request->file('passportPhotograph');
        }
        if ($request->hasFile('birthCertificate')) {
            $files['birthCertificate'] = $request->file('birthCertificate');
        }

        // Log duplicate check information for debugging and audit purposes
        Log::info('Checking for duplicate applications', [
            'user_id' => $user->id,
            'nin' => $validatedData['nin'],
            'phone' => $user->phone,
            'email' => $validatedData['email']
        ]);

        // Check for duplicate applications (same NIN, phone, or email)
        // Exclude current biodataID if it exists (for update scenarios)
        $exists = $this->applicationService->checkDuplicateApplication(
            $validatedData['nin'],
            $user->phone,
            $validatedData['email'],
            $user->biodataID
        );

        if ($exists) {
            return response()->json([
                'message' => 'Another application already exists with this NIN, Phone, or Email'
            ], 409);
        }

        try {
            // Save or update personal information using the service
            // This handles both create and update scenarios automatically
            $personalInfo = $this->applicationService->savePersonalInfo($user, $validatedData, $files);

            // Determine if this was an update or new creation for the response message
            $wasUpdated = $user->biodataID && $personalInfo->wasRecentlyCreated === false;
            $message = $wasUpdated 
                ? 'Step 1 completed: Personal info updated successfully'
                : 'Step 1 completed: Personal info saved successfully';

            // Return success response with useful metadata
            return response()->json([
                'message' => $message,
                'biodata_id' => $personalInfo->biodataID,
                'existing_files_kept' => [
                    'passport' => (!$request->hasFile('passportPhotograph') && !empty($personalInfo->passportPhotograph)),
                    'birth_certificate' => (!$request->hasFile('birthCertificate') && !empty($personalInfo->birthCertificate))
                ]
            ], 201);

        } catch (\Exception $e) {
            // Log error details for debugging
            Log::error('Personal info submission failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return user-friendly error message
            return response()->json([
                'message' => 'Submission failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== STEP 2: APPLICANT ADDRESSES ====================
    
    /**
     * Submit permanent and residential addresses (Step 2 of the application process)
     * 
     * This endpoint handles:
     * - Permanent address details (where applicant is originally from)
     * - Residential address details (where applicant currently lives)
     * - Deletes and recreates address records to ensure clean data
     * 
     * Validation is handled by AddressesRequest which requires all address fields
     * and validates optional phone/email formats.
     * 
     * @param AddressesRequest $request - Validated request with address data
     * @return \Illuminate\Http\JsonResponse
     * 
     * @response 200 {
     *   "message": "Step 2 completed: Addresses saved successfully",
     *   "data": {
     *     "permanent_address": {...},
     *     "residential_address": {...}
     *   }
     * }
     * 
     * @response 400 {
     *   "message": "Complete personal info first (Step 1)"
     * }
     * 
     * @response 409 {
     *   "message": "Application already completed. Cannot edit."
     * }
     */
    public function submitAddresses(AddressesRequest $request)
    {
        $user = $request->user();

        // Validate that Step 1 is completed (biodataID must exist)
        if (!$user->biodataID) {
            return response()->json([
                'message' => 'Complete personal info first (Step 1)'
            ], 400);
        }

        // Check if application is already locked
        if ($this->applicationService->isApplicationLocked($user)) {
            return response()->json([
                'message' => 'Application already completed. Cannot edit.'
            ], 409);
        }

        try {
            // Save addresses using the service
            // This deletes existing addresses and creates new ones
            $addresses = $this->applicationService->saveAddresses(
                $user->biodataID,
                $request->validated()
            );

            return response()->json([
                'message' => 'Step 2 completed: Addresses saved successfully',
                'data' => $addresses
            ]);

        } catch (\Exception $e) {
            Log::error('Address submission failed', [
                'user_id' => $user->id,
                'biodataID' => $user->biodataID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== STEP 3: NEXT OF KIN ====================
    
    /**
     * Submit next of kin information (Step 3 of the application process)
     * 
     * This endpoint handles:
     * - Next of kin personal details (name, address, relationship)
     * - Contact information for emergency/administrative purposes
     * - Deletes existing record and creates new one
     * 
     * Validation is handled by NextOfKinRequest which ensures:
     * - Names contain only alphabetic characters
     * - Phone numbers are numeric
     * - Email addresses are valid
     * - Gender is either Male or Female
     * 
     * @param NextOfKinRequest $request - Validated request with next of kin data
     * @return \Illuminate\Http\JsonResponse
     * 
     * @response 200 {
     *   "message": "Step 3 completed: Next of kin saved successfully",
     *   "data": {...}
     * }
     * 
     * @response 400 {
     *   "message": "Complete personal info first (Step 1)"
     * }
     */
    public function submitNextOfKin(NextOfKinRequest $request)
    {
        $user = $request->user();

        // Validate that Step 1 is completed
        if (!$user->biodataID) {
            return response()->json([
                'message' => 'Complete personal info first (Step 1)'
            ], 400);
        }

        // Check if application is already locked
        if ($this->applicationService->isApplicationLocked($user)) {
            return response()->json([
                'message' => 'Application already completed. Cannot edit.'
            ], 409);
        }

        try {
            // Save next of kin using the service
            // This deletes existing record and creates a new one
            $nextOfKin = $this->applicationService->saveNextOfKin(
                $user->biodataID,
                $request->validated()
            );

            return response()->json([
                'message' => 'Step 3 completed: Next of kin saved successfully',
                'data' => $nextOfKin
            ]);

        } catch (\Exception $e) {
            Log::error('Next of kin submission failed', [
                'user_id' => $user->id,
                'biodataID' => $user->biodataID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== STEP 4: EDUCATION AND WORK EXPERIENCE ====================
    
    /**
     * Submit education and work experience (Step 4 of the application process)
     * 
     * This endpoint handles:
     * - Primary education (required)
     * - Secondary education (required)
     * - University education (required)
     * - Masters education (optional but must be complete if started)
     * - Work experience (optional, up to 3 entries)
     * 
     * Validation is handled by EducationWorkRequest which includes:
     * - Date sequence validation (secondary after primary)
     * - Conditional validation for masters education
     * - Work experience entry limits
     * 
     * @param EducationWorkRequest $request - Validated request with education and work data
     * @return \Illuminate\Http\JsonResponse
     * 
     * @response 200 {
     *   "message": "Step 4 completed: Education and Work Experience saved successfully",
     *   "data": {
     *     "prisec_education": {...},
     *     "university_education": {...},
     *     "masters_education": {...},
     *     "work_experiences": [...]
     *   }
     * }
     * 
     * @response 400 {
     *   "message": "Complete personal info first (Step 1)"
     * }
     */
    public function submitEducationAndWorkExperience(EducationWorkRequest $request)
    {
        $user = $request->user();

        // Validate that Step 1 is completed
        if (!$user->biodataID) {
            return response()->json([
                'message' => 'Complete personal info first (Step 1)'
            ], 400);
        }

        // Check if application is already locked
        if ($this->applicationService->isApplicationLocked($user)) {
            return response()->json([
                'message' => 'Application already completed. Cannot edit.'
            ], 409);
        }

        try {
            // Save education and work experience using the service
            // This deletes existing records and creates new ones
            $result = $this->applicationService->saveEducationAndWorkExperience(
                $user->biodataID,
                $request->validated()
            );

            return response()->json([
                'message' => 'Step 4 completed: Education and Work Experience saved successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Education submission failed', [
                'user_id' => $user->id,
                'biodataID' => $user->biodataID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== STEP 5: NYSC AND PROFESSIONAL QUALIFICATION ====================
    
    /**
     * Submit NYSC and professional qualifications (Step 5 of the application process)
     * 
     * This endpoint handles:
     * - NYSC certificate details (required for Nigerian graduates)
     * - Professional qualifications (optional, e.g., ICAN, CIPM, etc.)
     * 
     * Validation is handled by NyscProfessionalRequest which validates:
     * - NYSC certificate number format
     * - Completion date is not in the future
     * - NYSC type is valid
     * - Professional qualification dates are valid if provided
     * 
     * @param NyscProfessionalRequest $request - Validated request with NYSC and professional data
     * @return \Illuminate\Http\JsonResponse
     * 
     * @response 200 {
     *   "message": "Step 5 completed: NYSC and Professional saved successfully",
     *   "data": {
     *     "nysc": {...},
     *     "professional_qualification": {...}
     *   }
     * }
     * 
     * @response 400 {
     *   "message": "Complete personal info first (Step 1)"
     * }
     */
    public function submitNyscProfessional(NyscProfessionalRequest $request)
    {
        $user = $request->user();

        // Validate that Step 1 is completed
        if (!$user->biodataID) {
            return response()->json([
                'message' => 'Complete personal info first (Step 1)'
            ], 400);
        }

        // Check if application is already locked
        if ($this->applicationService->isApplicationLocked($user)) {
            return response()->json([
                'message' => 'Application already completed. Cannot edit.'
            ], 409);
        }

        try {
            // Save NYSC and professional qualifications using the service
            // This deletes existing records and creates new ones
            $result = $this->applicationService->saveNyscProfessional(
                $user->biodataID,
                $request->validated()
            );

            return response()->json([
                'message' => 'Step 5 completed: NYSC and Professional saved successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('NYSC/Professional submission failed', [
                'user_id' => $user->id,
                'biodataID' => $user->biodataID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== FINAL SUBMIT (LOCK APPLICATION) ====================
    
    /**
     * Complete and lock the application (Step 6 - Final submission)
     * 
     * This endpoint is the final step of the application process:
     * 1. Validates that all steps 1-5 are completed
     * 2. Generates a unique reference number for the application
     * 3. Locks the application from further edits
     * 4. Queues a confirmation email to the applicant (async)
     * 5. Returns the complete application profile with reference number
     * 
     * Once completed, the application cannot be modified further.
     * The reference number should be used for all future communications.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @response 200 {
     *   "message": "Application completed successfully! Your reference number has been generated.",
     *   "reference_number": "REF2024123456789",
     *   "profile": {...}
     * }
     * 
     * @response 400 {
     *   "message": "Cannot complete application. Please complete all required steps first.",
     *   "missing_steps": [2, 3],
     *   "steps_status": {...}
     * }
     * 
     * @response 404 {
     *   "message": "No application found. Please start with Step 1."
     * }
     * 
     * @response 409 {
     *   "message": "Application already completed and locked"
     * }
     */
    public function completeApplication(Request $request)
    {
        $user = $request->user();

        // Validate that application exists (user must have started step 1)
        if (!$user->biodataID) {
            return response()->json([
                'message' => 'No application found. Please start with Step 1.'
            ], 404);
        }

        // Check if application is already locked (prevent double submission)
        if ($this->applicationService->isApplicationLocked($user)) {
            return response()->json([
                'message' => 'Application already completed and locked'
            ], 409);
        }

        // Verify personal info record exists
        $personalInfo = PersonalInfo::where('biodataID', $user->biodataID)->first();
        if (!$personalInfo) {
            return response()->json([
                'message' => 'Application data incomplete'
            ], 400);
        }

        // Validate all steps 1-5 are complete before final submission
        // This prevents incomplete applications from being submitted
        $stepsCheck = $this->applicationService->checkStepsCompletion($user->biodataID);
        
        if (!$stepsCheck['completed']) {
            return response()->json([
                'message' => 'Cannot complete application. Please complete all required steps first.',
                'missing_steps' => $stepsCheck['missing_steps'],
                'steps_status' => $stepsCheck['steps_completion']['steps']
            ], 400);
        }

        try {
            // Generate reference number and lock application
            // This sets the referenceNo field which acts as a lock
            $personalInfo = $this->applicationService->completeApplication($user->biodataID);

            // Load all relationships for the complete profile response
            // Eager loading prevents N+1 queries
            $fullApplication = $this->applicationService->getFullApplicationData($user->biodataID);
            
            // Format the full profile for API response (includes file URLs, etc.)
            $fullProfile = $this->formatFullProfile($fullApplication, $user);

            // Queue confirmation email for asynchronous sending
            // This prevents delaying the API response
            $frontendUrl = config('recruitment_urls.frontend');
            $loginUrl = $frontendUrl . '/login';
            $name = $personalInfo->firstName . ' ' . $personalInfo->surname;
            $email = $user->email ?? $personalInfo->email;
            $phone = $user->phone;

            $this->applicationService->sendConfirmationEmail(
                $name,
                $personalInfo->referenceNo,
                $email,
                $phone,
                $loginUrl
            );

            // Return success response with reference number and complete profile
            return response()->json([
                'message' => 'Application completed successfully! Your reference number has been generated.',
                'reference_number' => $personalInfo->referenceNo,
                'profile' => $fullProfile
            ]);

        } catch (\Exception $e) {
            Log::error('Application completion failed', [
                'user_id' => $user->id,
                'biodataID' => $user->biodataID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Completion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== GET APPLICATION PROGRESS ====================
    
    /**
     * Get the current progress of the application
     * 
     * This endpoint provides detailed information about the application status:
     * - Which steps have been completed
     * - Which steps are currently available
     * - Overall completion percentage
     * - Current step the user should work on
     * - Whether the application is ready for final submission
     * 
     * This is useful for:
     * - Displaying progress bars in the frontend
     * - Determining which step to redirect to
     * - Checking if the application can be submitted
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @response 200 {
     *   "application_started": true,
     *   "application_completed": false,
     *   "ready_for_submission": false,
     *   "current_step": 2,
     *   "available_steps": [1, 2],
     *   "completed_steps": [1],
     *   "summary": {
     *     "total_steps": 6,
     *     "completed_count": 1,
     *     "percentage": 16.7
     *   },
     *   "biodata_id": 12345,
     *   "message": "Please complete all required steps."
     * }
     */
    public function getApplicationProgress(Request $request)
    {
        $user = $request->user();

        // Case 1: No application started yet
        if (!$user->biodataID) {
            return response()->json([
                'application_started' => false,
                'application_completed' => false,
                'current_step' => 1,
                'available_steps' => [1],
                'completed_steps' => [],
                'summary' => [
                    'total_steps' => 6,
                    'completed_count' => 0,
                    'percentage' => 0
                ],
                'message' => 'No application found. Please start with Step 1: Personal Information.'
            ]);
        }

        // Get personal information record
        $personalInfo = PersonalInfo::where('biodataID', $user->biodataID)->first();

        // Case 2: Application is fully completed and locked
        if ($personalInfo && !empty($personalInfo->referenceNo)) {
            return response()->json([
                'application_started' => true,
                'application_completed' => true,
                'completed_at' => $personalInfo->updatedDate ? $personalInfo->updatedDate->format('Y-m-d H:i:s') : null,
                'reference_number' => $personalInfo->referenceNo,
                'current_step' => 'completed',
                'available_steps' => [1, 2, 3, 4, 5, 6],
                'completed_steps' => [1, 2, 3, 4, 5, 6],
                'summary' => [
                    'total_steps' => 6,
                    'completed_count' => 6,
                    'percentage' => 100
                ],
                'biodata_id' => $user->biodataID,
                'message' => 'Application has been fully submitted and locked.'
            ]);
        }

        // Case 3: Application in progress
        // Get detailed step completion from service
        $stepsCompletion = $this->applicationService->getDetailedStepsCompletion($user->biodataID);
        
        // Calculate current step (first incomplete step among 1-5, or step 6 if all 1-5 are complete)
        $current_step = 1;
        $allStepsCompleted = true;
        
        for ($step = 1; $step <= 5; $step++) {
            if (!$stepsCompletion['steps'][$step]['is_completed']) {
                $current_step = $step;
                $allStepsCompleted = false;
                break;
            }
            $current_step = $step + 1;
        }
        
        // Determine available steps based on completion
        // Steps 1-5 are always available once started
        $availableSteps = [1, 2, 3, 4, 5];
        if ($allStepsCompleted) {
            $availableSteps[] = 6; // Step 6 (summary) becomes available only when steps 1-5 are done
            if ($current_step > 5) {
                $current_step = 6;
            }
        }
        
        // Build list of completed steps (1-5 only, step 6 is handled separately)
        $completedStepsList = [];
        foreach ($stepsCompletion['steps'] as $stepNum => $stepData) {
            if ($stepData['is_completed'] && $stepNum <= 5) {
                $completedStepsList[] = $stepNum;
            }
        }
        
        // Add step 6 to completed if all steps 1-5 are done
        if ($allStepsCompleted) {
            $completedStepsList[] = 6;
        }

        // Check if ready for final submission
        $ready_for_submission = $allStepsCompleted && !($personalInfo && $personalInfo->referenceNo);

        // Return progress information
        return response()->json([
            'application_started' => true,
            'application_completed' => false,
            'completed_at' => null,
            'ready_for_submission' => $ready_for_submission,
            'current_step' => $current_step,
            'available_steps' => $availableSteps,
            'completed_steps' => $completedStepsList,
            'summary' => [
                'total_steps' => 6,
                'completed_count' => count($completedStepsList),
                'percentage' => round((count($completedStepsList) / 6) * 100, 1)
            ],
            'biodata_id' => $user->biodataID,
            'message' => $ready_for_submission ? 'All steps completed! Ready for final submission.' : 'Please complete all required steps.'
        ]);
    }

    // ==================== HELPER METHODS ====================
    
    /**
     * Format the full application profile for API response
     * 
     * This method transforms the application data into a structured array
     * suitable for JSON response. It handles:
     * - Converting file paths to full URLs
     * - Handling optional/missing data gracefully
     * - Structuring related data in a logical way
     * - Providing consistent null values for missing required data
     * 
     * @param PersonalInfo|null $personalInfo The personal info model with relations loaded
     * @param User $user The authenticated user
     * @return array Formatted profile data
     */
    private function formatFullProfile(?PersonalInfo $personalInfo, User $user): array
    {
        // Handle case where personal info is not found (should not happen)
        if (!$personalInfo) {
            return [];
        }

        // Determine final email and phone (prioritize personal info, fallback to user)
        $emailFromPersonal = $personalInfo->email ?? null;
        $emailFromUsers = $user->email ?? null;
        $finalEmail = !empty($emailFromPersonal) ? $emailFromPersonal : $emailFromUsers;

        $phoneFromPersonal = $personalInfo->phoneNo ?? null;
        $phoneFromUsers = $user->phone ?? null;
        $finalPhone = !empty($phoneFromPersonal) ? $phoneFromPersonal : $phoneFromUsers;

        // ========== STEP 1: Personal Information ==========
        $data = [
            'reference_number' => $personalInfo->referenceNo,
            'submission_date' => $personalInfo->updatedDate ? $personalInfo->updatedDate->format('Y-m-d H:i:s') : null,
            'personal_info' => [
                'biodataID' => $personalInfo->biodataID,
                'title' => $personalInfo->title,
                'firstName' => $personalInfo->firstName,
                'surname' => $personalInfo->surname,
                'middleName' => $personalInfo->middleName,
                'maidenName' => $personalInfo->maidenName,
                'dateOfBirth' => $personalInfo->dateOfBirth,
                'placeOfBirth' => $personalInfo->placeOfBirth,
                'gender' => $personalInfo->gender,
                'state_of_origin' => $personalInfo->state_of_origin,
                'local_govt' => $personalInfo->local_govt,
                'nationality' => $personalInfo->nationality,
                'nin' => $personalInfo->nin,
                'phoneNo' => $finalPhone,
                'email' => $finalEmail,
                'preferrd_offc_loc' => $personalInfo->preferrd_offc_loc ?? null,
                'disability_id' => $personalInfo->disability_id,
                // Convert file paths to full URLs using the FilePathResolver trait
                'passport_photograph' => $this->resolveFileUrl($personalInfo->passportPhotograph),
                'birth_certificate' => $this->resolveFileUrl($personalInfo->birthCertificate),
            ]
        ];

        // ========== STEP 2: Addresses ==========
        // Return null if not found (required but may be missing if step not completed)
        $data['permanent_address'] = $personalInfo->permanentAddress ? $personalInfo->permanentAddress->toArray() : null;
        $data['residential_address'] = $personalInfo->residentialAddress ? $personalInfo->residentialAddress->toArray() : null;

        // ========== STEP 3: Next of Kin ==========
        $data['next_of_kin'] = $personalInfo->nextOfKin ? $personalInfo->nextOfKin->toArray() : null;

        // ========== STEP 4: Education ==========
        // Required education (should exist if step completed)
        $data['primary_secondary_education'] = $personalInfo->prisecEducation ? $personalInfo->prisecEducation->toArray() : null;
        $data['university_education'] = $personalInfo->universityEducation ? $personalInfo->universityEducation->toArray() : null;
        
        // Optional education with metadata
        $data['masters_education'] = $personalInfo->mastersEducation 
            ? $personalInfo->mastersEducation->toArray() 
            : [
                'exists' => false,
                'data' => null,
                'message' => 'No masters education information provided'
            ];
        
        // Work experience with count metadata
        $data['work_experience'] = [
            'exists' => $personalInfo->workExperiences && $personalInfo->workExperiences->count() > 0,
            'count' => $personalInfo->workExperiences ? $personalInfo->workExperiences->count() : 0,
            'data' => $personalInfo->workExperiences && $personalInfo->workExperiences->count() > 0 
                ? $personalInfo->workExperiences->toArray() 
                : []
        ];

        // ========== STEP 5: NYSC & Professional ==========
        $data['nysc'] = $personalInfo->nysc ? $personalInfo->nysc->toArray() : null;
        
        $data['professional_qualifications'] = [
            'exists' => $personalInfo->professionalQualifications && $personalInfo->professionalQualifications->count() > 0,
            'count' => $personalInfo->professionalQualifications ? $personalInfo->professionalQualifications->count() : 0,
            'data' => $personalInfo->professionalQualifications && $personalInfo->professionalQualifications->count() > 0 
                ? $personalInfo->professionalQualifications->toArray() 
                : []
        ];

        return $data;
    }
}