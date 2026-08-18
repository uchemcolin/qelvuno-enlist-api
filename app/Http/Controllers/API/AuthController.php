<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Jobs\SendPasswordChangedJob;
use App\Jobs\SendWelcomeAccountJob;
use App\Models\User;
use App\Models\UserPhoneNumber;
use App\Models\PersonalInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Helpers\FilePathResolver;
use App\Services\ApplicationService;

class AuthController extends Controller
{
    // To use the FilePathReolver helper
    use FilePathResolver;

    /**
     * @var ApplicationService
     */
    protected ApplicationService $applicationService;

    /**
     * AuthController constructor.
     * 
     * @param ApplicationService $applicationService
     */
    public function __construct(ApplicationService $applicationService)
    {
        $this->applicationService = $applicationService;
    }

    // ==================== LOGIN ====================
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = $request->login;
        $password = $request->password;

        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL);

        if ($isEmail) {
            return $this->loginWithEmail($login, $password);
        }
        return $this->loginWithPhone($login, $password);
    }

    // Login with phone number if phone number is what was supplied
    private function loginWithPhone($phone, $password)
    {
        // Check if phone is enlisted by admin
        $enlisted = UserPhoneNumber::where('users_phonenumber', $phone)->exists();
        if (!$enlisted) {
            return response()->json(['message' => 'Phone number not registered'], 404);
        }

        // Find or create user with this phone number
        $user = User::firstOrNew(['phone' => $phone]);

        // If user is new, save to store the phone number
        if (!$user->exists) {
            $user->phone = $phone;
            $user->save();

            // 🔑 LINK EXISTING APPLICATION - NEW USER
            $this->applicationService->linkExistingApplication($user);
        }

        // New user - default password is phone number
        if (!$user->password && $password === $phone) {
            $user->must_change_password = true;
            $user->save();

            // 🔑 LINK EXISTING APPLICATION - IF NOT ALREADY LINKED
            $this->applicationService->linkExistingApplication($user);

            $token = $user->createToken('temp')->plainTextToken;
            return response()->json([
                'status' => 'change_password',
                'message' => 'Set your new password',
                'token' => $token,
                'user' => ['phone' => $user->phone]
            ]);
        }

        // Existing user
        if ($user->password && Hash::check($password, $user->password)) {

            // 🔑 LINK EXISTING APPLICATION - ON EVERY LOGIN (safety net)
            $this->applicationService->linkExistingApplication($user);

            if ($user->must_change_password) {
                $token = $user->createToken('temp')->plainTextToken;
                return response()->json([
                    'status' => 'change_password',
                    'message' => 'Please change your password',
                    'token' => $token,
                    'user' => ['phone' => $user->phone]
                ]);
            }

            $token = $user->createToken('auth')->plainTextToken;
            return response()->json([
                'status' => 'success',
                'token' => $token,
                'user' => $this->getUserData($user)
            ]);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    // Login with email if email is what was supplied
    private function loginWithEmail($email, $password)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        // 🔑 LINK EXISTING APPLICATION - ON EMAIL LOGIN
        $this->applicationService->linkExistingApplication($user);

        if (!$user->password) {
            return response()->json(['message' => 'Activate using phone number first'], 400);
        }

        if (!Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ($user->must_change_password) {
            $token = $user->createToken('temp')->plainTextToken;
            return response()->json([
                'status' => 'change_password',
                'message' => 'Please change your password',
                'token' => $token,
                'user' => ['email' => $user->email]
            ]);
        }

        $token = $user->createToken('auth')->plainTextToken;
        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => $this->getUserData($user)
        ]);
    }

    // ==================== CHANGE PASSWORD ====================
    /*public function changePassword(Request $request)
    {
        $request->validate([
            'new_password' => 'required|string|min:8|confirmed'
        ]);

        $user = $request->user();
        $user->password = Hash::make($request->new_password);
        $user->must_change_password = false;
        $user->save();

        $user->tokens()->delete();
        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed',
            'token' => $token
        ]);
    }*/

    // ==================== CHANGE PASSWORD ====================
    public function changePassword(Request $request)
    {
        $user = $request->user();

        // 🔑 LINK EXISTING APPLICATION - SAFETY CHECK
        $this->applicationService->linkExistingApplication($user);
        
        // Build validation rules dynamically
        $rules = [
            'new_password' => 'required|string|min:8|confirmed'
        ];
        
        // CASE 1: User is in "must change password" state (first time login)
        if ($user->must_change_password) {
            // 🔒 STRICT EMAIL VALIDATION FOR LEGACY USERS
            $rules['email'] = [
                'required',
                'email',
                'max:100',
                function ($attribute, $value, $fail) use ($user) {
                    // Check if this email belongs to an existing application
                    $personalInfo = PersonalInfo::where('email', $value)->first();
                    
                    if ($personalInfo) {
                        // Email exists in personal_info
                        if ($user->biodataID && $user->biodataID != $personalInfo->biodataID) {
                            Log::warning('Email belongs to different application', [
                                'user_id' => $user->id,
                                'user_biodataID' => $user->biodataID,
                                'email_biodataID' => $personalInfo->biodataID,
                                'email' => $value
                            ]);
                            //$fail('This email is associated with a different application. Please use the email you originally applied with.');
                            $fail('This email is associated with a different application. Please use the email you originally applied with, or a new email if it is a fresh enlistment.');
                            return;
                        }

                        // Check if this user's phone matches the personal_info
                        if (!$user->biodataID && $user->phone && $personalInfo->phoneNo != $user->phone) {
                            Log::warning('Email/phone mismatch for legacy user', [
                                'user_id' => $user->id,
                                'user_phone' => $user->phone,
                                'personal_info_phone' => $personalInfo->phoneNo,
                                'email' => $value
                            ]);
                            //$fail('This email belongs to an application with a different phone number. Please use the correct email or contact support.');
                            $fail('This email belongs to an application with a different phone number. Please use the correct email, or a different email, or contact support.');
                            return;
                        }
                        
                        // Valid match - either already linked or will be linked later
                        return;
                    }
                    
                    // Email not found in personal_info
                    if ($user->biodataID) {
                        Log::error('User has biodataID but email not in personal_info', [
                            'user_id' => $user->id,
                            'biodataID' => $user->biodataID,
                            'email' => $value
                        ]);
                        $fail('We could not find your application with this email. Please use the email you used when applying.');
                        return;
                    }
                    
                    // User has no application yet - check if email is already registered
                    $existsInUsers = User::where('email', $value)->exists();
                    if ($existsInUsers) {
                        Log::warning('Duplicate email registration attempt', [
                            'user_id' => $user->id,
                            'email' => $value
                        ]);
                        $fail('This email is already registered. Please use a different email address.');
                        return;
                    }
                    
                    // New user with unique email - allowed
                    Log::info('New user registering email', [
                        'user_id' => $user->id,
                        'email' => $value
                    ]);
                }
            ];
            
            // Phone is optional - but if provided, must match
            $rules['phone'] = [
                'sometimes',
                'digits:11',
                function ($attribute, $value, $fail) use ($user) {
                    $personalInfo = PersonalInfo::where('phoneNo', $value)->first();
                    
                    if ($personalInfo) {
                        // Phone exists in personal_info
                        if ($user->biodataID && $user->biodataID != $personalInfo->biodataID) {
                            Log::warning('Phone belongs to different application', [
                                'user_id' => $user->id,
                                'user_biodataID' => $user->biodataID,
                                'phone_biodataID' => $personalInfo->biodataID,
                                'phone' => $value
                            ]);
                            $fail('This phone number belongs to another application.');
                            return;
                        }
                        // Valid - phone belongs to this user's application
                        return;
                    }
                    
                    // Phone not in personal_info
                    if ($user->biodataID) {
                        Log::error('User has biodataID but phone not in personal_info', [
                            'user_id' => $user->id,
                            'biodataID' => $user->biodataID,
                            'phone' => $value
                        ]);
                        $fail('We could not find your application with this phone number. Please use the phone number you used when applying.');
                        return;
                    }
                    
                    // Check if phone is already registered
                    $existsInUsers = User::where('phone', $value)->exists();
                    if ($existsInUsers) {
                        Log::warning('Duplicate phone registration attempt', [
                            'user_id' => $user->id,
                            'phone' => $value
                        ]);
                        $fail('This phone number is already registered.');
                        return;
                    }
                    
                    // New user - allowed
                    Log::info('New user registering phone', [
                        'user_id' => $user->id,
                        'phone' => $value
                    ]);
                }
            ];
        } else {
            // CASE 2: Regular password change - email is optional
            $rules['email'] = [
                'sometimes',
                'email',
                'max:100',
                Rule::unique('users', 'email')->ignore($user->id),
                function ($attribute, $value, $fail) use ($user) {
                    $query = PersonalInfo::where('email', $value);
                    
                    if ($user->biodataID) {
                        $query->where('biodataID', '!=', $user->biodataID);
                    }
                    
                    if ($query->exists()) {
                        Log::warning('Email already in personal_info', [
                            'user_id' => $user->id,
                            'email' => $value
                        ]);
                        $fail('This email is already registered. Please use a different email address.');
                    }
                }
            ];

            $rules['phone'] = [
                'sometimes',
                'digits:11',
                Rule::unique('users', 'phone')->ignore($user->id),
                function ($attribute, $value, $fail) use ($user) {
                    $query = PersonalInfo::where('phoneNo', $value);
                    
                    if ($user->biodataID) {
                        $query->where('biodataID', '!=', $user->biodataID);
                    }
                    
                    if ($query->exists()) {
                        Log::warning('Phone already in personal_info', [
                            'user_id' => $user->id,
                            'phone' => $value
                        ]);
                        $fail('This phone number is already registered. Please use a different phone number.');
                    }
                }
            ];

            // CASE 2: Regular password change requires current password
            $rules['current_password'] = 'required|string';
        }
        
        $request->validate($rules);
        
        // CASE 1: User is in "must change password" state (first time login)
        if ($user->must_change_password) {
            // Store email if provided
            if ($request->has('email')) {
                $user->email = $request->email;
            }

            // Store phone if provided
            if ($request->has('phone')) {
                $user->phone = $request->phone;
            }

            // 🔑 LINK AGAIN AFTER EMAIL/PHONE UPDATE
            $this->applicationService->linkExistingApplication($user);
            
            // Set the new password
            $user->password = Hash::make($request->new_password);
            $user->must_change_password = false;
            $user->save();

            // Dispatch welcome email job (queued)
            SendWelcomeAccountJob::dispatch($user);
            
            // Revoke old tokens, create new full token
            $user->tokens()->delete();
            $token = $user->createToken('auth')->plainTextToken;
            
            return response()->json([
                'status' => 'success',
                'message' => 'Password set successfully. Email saved.',
                'token' => $token,
                'user' => $this->getUserData($user)
            ]);
        }
        
        // CASE 2: User already has a password (normal password change)
        if (!Hash::check($request->current_password, $user->password)) {
            Log::warning('Incorrect password attempt for password change', [
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 401);
        }

        // Store email if provided
        if ($request->has('email')) {
            $user->email = $request->email;

            // If user has biodataID but no reference number (incomplete application)
            // Update personal_info.email too
            if ($user->biodataID) {
                $personalInfo = PersonalInfo::where('biodataID', $user->biodataID)->first();
                if ($personalInfo && is_null($personalInfo->referenceNo)) {
                    $personalInfo->email = $request->email;
                    $personalInfo->save();
                    
                    Log::info('Updated personal_info email for incomplete application', [
                        'user_id' => $user->id,
                        'biodataID' => $user->biodataID,
                        'old_email' => $personalInfo->getOriginal('email'),
                        'new_email' => $request->email
                    ]);
                }

                // What if personal_info.email is NULL?
                if ($personalInfo && is_null($personalInfo->referenceNo)) {
                    // Update regardless
                    $personalInfo->email = $request->email;
                    $personalInfo->save();
                }
            }
        }

        // Store phone if provided
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }

        // 🔑 LINK AGAIN AFTER EMAIL/PHONE UPDATE
        $this->applicationService->linkExistingApplication($user);
        
        // Update to new password
        $user->password = Hash::make($request->new_password);
        $user->must_change_password = false;
        $user->save();

        // Dispatch password changed alert job (queued)
        SendPasswordChangedJob::dispatch($user, $request->ip(), $request->userAgent());
        
        // Revoke all existing tokens and issue new one
        $user->tokens()->delete();
        $token = $user->createToken('auth')->plainTextToken;
        
        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully',
            'token' => $token,
            'user' => $this->getUserData($user)
        ]);
    }

    // ==================== LOGOUT ====================
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Return authenticated user's profile and application details.
     */
    public function profile(Request $request)
    {
        // Get currently authenticated user
        $user = $request->user();

        // 🔑 LINK EXISTING APPLICATION - SAFETY NET
        $this->applicationService->linkExistingApplication($user);

        // Force password update before accessing profile
        if ($user->must_change_password) {
            return response()->json([
                'message' => 'Change password first'
            ], 423);
        }

        /**
         * Initialize default response structure.
         * This ensures a consistent API response even if
         * the user has not started an application.
         */
        $response = [
            'id' => $user->id,
            'phone' => $user->phone,
            'email' => $user->email,
            'must_change_password' => $user->must_change_password,

            // Account state
            'account_status' => $user->must_change_password
                ? 'pending_password_change'
                : 'active',

            // Application tracking info
            'application_status' => [
                'has_application' => !is_null($user->biodataID),
                'is_complete' => false,
                'reference_number' => null,
                'submission_date' => null,
                'last_updated' => null,
            ],

            // Full application payload
            'application' => null,
        ];

        // Return early if user has no application record
        if (!$user->biodataID) {
            return response()->json($response);
        }

        // Fetch personal information using biodata ID
        $p = PersonalInfo::where('biodataID', $user->biodataID)->first();

        // Return base response if no personal info exists
        if (!$p) {
            return response()->json($response);
        }

        /**
         * Update application status details.
         * Application is considered complete once reference number exists.
         */
        $response['application_status']['is_complete'] = !is_null($p->referenceNo);
        $response['application_status']['reference_number'] = $p->referenceNo;

        // Submission date only available after successful submission
        /*$response['application_status']['submission_date'] = $p->referenceNo
            ? ($p->dateCreated
                ? $p->dateCreated->format('Y-m-d H:i:s')
                : null)
            : null;*/

        // Submission date only available after successful submission
        $response['application_status']['submission_date'] = $p->referenceNo
            ? ($p->updatedDate
                ? $p->updatedDate->format('Y-m-d H:i:s')
                : null)
            : null;

        // Use updated date if available, otherwise fallback to creation date
        $response['application_status']['last_updated'] =
            $p->updatedDate ?? $p->dateCreated;

        /**
         * Resolve contact information.
         * Prioritize personal info values over user table values.
         */
        $emailFromPersonal = $p->email ?? null;
        $emailFromUsers = $user->email ?? null;

        $finalEmail = !empty($emailFromPersonal)
            ? $emailFromPersonal
            : $emailFromUsers;

        $phoneFromPersonal = $p->phoneNo ?? null;
        $phoneFromUsers = $user->phone ?? null;

        $finalPhone = !empty($phoneFromPersonal)
            ? $phoneFromPersonal
            : $phoneFromUsers;

        /**
         * Eager load related application records
         * to avoid N+1 query problems.
         */
        $p->load([
            'permanentAddress',
            'residentialAddress',
            'nextOfKin',
            'nysc',
            'professionalQualifications',
            'prisecEducation',
            'universityEducation',
            'mastersEducation',
            'workExperiences',
        ]);

        /**
         * Build personal information section.
         */
        $response['application'] = [
            'personal_info' => [
                'full_name' => trim(
                    $p->firstName . ' ' .
                    ($p->middleName ? $p->middleName . ' ' : '') .
                    $p->surname
                ),

                'firstName' => $p->firstName,
                'surname' => $p->surname,
                'middleName' => $p->middleName,
                'title' => $p->title,
                'maidenName' => $p->maidenName,
                'dateOfBirth' => $p->dateOfBirth,
                'placeOfBirth' => $p->placeOfBirth,
                'gender' => $p->gender,
                //'maritalStatus' => $p->maritalStatus,
                'state_of_origin' => $p->state_of_origin,
                'local_govt' => $p->local_govt,
                'nationality' => $p->nationality,
                'nin' => $p->nin,

                // Final resolved contact info
                'phone' => $finalPhone,
                'email' => $finalEmail,

                // File URLs
                /*'passport_photograph' => $p->passportPhotograph
                    ? Storage::url($p->passportPhotograph)
                    : null,

                'birth_certificate' => $p->birthCertificate
                    ? Storage::url($p->birthCertificate)
                    : null,*/

                //'passport_photograph' => $p->passportPhotograph ? $this->getRecruitmentEnlistUrl() . '/public/storage/' . $p->passportPhotograph : null,
                'passport_photograph' => $this->resolveFileUrl($p->passportPhotograph),
                
                
                //'birth_certificate' => $p->birthCertificate ? $this->getRecruitmentEnlistUrl() . '/public/storage/' . $p->birthCertificate : null,
                'birth_certificate' => $this->resolveFileUrl($p->birthCertificate),
            ]
        ];

        /**
         * Attach permanent address if available.
         */
        if ($p->permanentAddress) {
            $response['application']['permanent_address'] =
                $p->permanentAddress->toArray();
        }

        /**
         * Attach residential address if available.
         */
        if ($p->residentialAddress) {
            $response['application']['residential_address'] =
                $p->residentialAddress->toArray();
        }

        /**
         * Attach next of kin details.
         */
        if ($p->nextOfKin) {
            $response['application']['next_of_kin'] = [
                'full_name' => $p->nextOfKin->nameOfKin,
                'address' => $p->nextOfKin->addressofkin,
                'relationship' => $p->nextOfKin->relationshipKin,
                'phone' => $p->nextOfKin->phoneOfKin,
                'email' => $p->nextOfKin->emailOfKin,
                'gender' => $p->nextOfKin->genderofkin,
            ];
        }

        /**
         * Attach NYSC details.
         */
        if ($p->nysc) {
            $response['application']['nysc'] = $p->nysc->toArray();
        }

        /**
         * Attach professional qualifications.
         */
        if ($p->professionalQualifications &&
            $p->professionalQualifications->count()) {

            $response['application']['professional_qualifications'] =
                $p->professionalQualifications->toArray();
        }

        /**
         * Attach primary and secondary education details.
         */
        if ($p->prisecEducation) {
            $response['application']['education'] = [
                'primary' => [
                    'school_name' => $p->prisecEducation->primarysch_Name,
                    'address' => $p->prisecEducation->primarysch_address,
                    'state' => $p->prisecEducation->primarysch_state,
                    'country' => $p->prisecEducation->primarysch_country,
                    'graduation_date' => $p->prisecEducation->primarysch_enddate,
                ],

                'secondary' => [
                    'school_name' => $p->prisecEducation->secondrysch_name,
                    'address' => $p->prisecEducation->secondrysch_adress,
                    'state' => $p->prisecEducation->secondrysch_state,
                    'country' => $p->prisecEducation->secondrysch_country,
                    'graduation_date' => $p->prisecEducation->secondrysch_enddate,
                ],
            ];
        }

        /**
         * Attach university education records.
         */
        if ($p->universityEducation) {
            $response['application']['university_education'] =
                $p->universityEducation->toArray();
        }

        /**
         * Attach master's degree information.
         */
        if ($p->mastersEducation) {
            $response['application']['masters_education'] = [
                'institution_name' => $p->mastersEducation->institutionName,
                'address' => $p->mastersEducation->address,
                'state' => $p->mastersEducation->masters_state,
                'country' => $p->mastersEducation->masters_country,
                'discipline' => $p->mastersEducation->discipline,
                'matric_number' => $p->mastersEducation->matricNo,
                'year_of_graduation' => $p->mastersEducation->yearof_graduation,
                'created_at' => $p->mastersEducation->created_at,
                'updated_at' => $p->mastersEducation->updated_at,
            ];
        }

        /**
         * Attach work experience records.
         */
        if ($p->workExperiences &&
            $p->workExperiences->count()) {

            $response['application']['work_experience'] =
                $p->workExperiences->toArray();
        }

        // Return fully assembled profile response
        return response()->json($response);
    }

    /**
     * Get basic user data for login response
     * This is a simplified version for login responses
     */
    private function _getUserData($user)
    {
        $data = [
            'id' => $user->id,
            'phone' => $user->phone,
            'email' => $user->email,
            'has_application' => !is_null($user->biodataID),
        ];

        // If user has application, include basic personal info
        if ($user->biodataID) {
            $p = PersonalInfo::where('biodataID', $user->biodataID)->first();
            if ($p) {
                // Get email with priority: personal_info first, fallback to users table
                $emailFromPersonal = $p->email ?? null;
                $emailFromUsers = $user->email ?? null;
                $finalEmail = !empty($emailFromPersonal) ? $emailFromPersonal : $emailFromUsers;
                
                // Get phone with priority: personal_info phoneNo first, fallback to users table
                $phoneFromPersonal = $p->phoneNo ?? null;
                $phoneFromUsers = $user->phone ?? null;
                $finalPhone = !empty($phoneFromPersonal) ? $phoneFromPersonal : $phoneFromUsers;
                
                $data['personal_info'] = [
                    'firstName' => $p->firstName,
                    'surname' => $p->surname,
                    'middleName' => $p->middleName,
                    'referenceNo' => $p->referenceNo,
                    'title' => $p->title,
                    'dateOfBirth' => $p->dateOfBirth,
                    'gender' => $p->gender,
                    'email' => $finalEmail,
                    'phoneNo' => $finalPhone,
                    'state_of_origin' => $p->state_of_origin,
                    'local_govt' => $p->local_govt,
                    'nin' => $p->nin,
                    //'passportPhotograph' => $p->passportPhotograph ? Storage::url($p->passportPhotograph) : null,

                    //'passport_photograph' => $p->passportPhotograph ? $this->getRecruitmentEnlistUrl() . '/public/storage/' . $p->passportPhotograph : null,
                    'passport_photograph' => $this->resolveFileUrl($p->passportPhotograph),
                ];
            }
        }

        return $data;
    }

    /**
     * Get complete user data for login response
     * This now returns the same structure as the profile endpoint
     */
    private function getUserData($user)
    {
        // 🔑 LINK EXISTING APPLICATION - SAFETY NET
        $this->applicationService->linkExistingApplication($user);

        // Force password update before accessing profile
        if ($user->must_change_password) {
            return [
                'id' => $user->id,
                'phone' => $user->phone,
                'email' => $user->email,
                'must_change_password' => $user->must_change_password,
                'account_status' => 'pending_password_change',
                'application_status' => [
                    'has_application' => !is_null($user->biodataID),
                    'is_complete' => false,
                    'reference_number' => null,
                    'submission_date' => null,
                    'last_updated' => null,
                ],
                'application' => null,
            ];
        }

        /**
         * Initialize default response structure.
         * This ensures a consistent API response even if
         * the user has not started an application.
         */
        $response = [
            'id' => $user->id,
            'phone' => $user->phone,
            'email' => $user->email,
            'must_change_password' => $user->must_change_password,

            // Account state
            'account_status' => $user->must_change_password
                ? 'pending_password_change'
                : 'active',

            // Application tracking info
            'application_status' => [
                'has_application' => !is_null($user->biodataID),
                'is_complete' => false,
                'reference_number' => null,
                'submission_date' => null,
                'last_updated' => null,
            ],

            // Full application payload
            'application' => null,
        ];

        // Return early if user has no application record
        if (!$user->biodataID) {
            return $response;
        }

        // Fetch personal information using biodata ID
        $p = PersonalInfo::where('biodataID', $user->biodataID)->first();

        // Return base response if no personal info exists
        if (!$p) {
            return $response;
        }

        /**
         * Update application status details.
         * Application is considered complete once reference number exists.
         */
        $response['application_status']['is_complete'] = !is_null($p->referenceNo);
        $response['application_status']['reference_number'] = $p->referenceNo;

        // Submission date only available after successful submission
        /*$response['application_status']['submission_date'] = $p->referenceNo
            ? ($p->dateCreated
                ? $p->dateCreated->format('Y-m-d H:i:s')
                : null)
            : null;*/

        // Submission date only available after successful submission
        $response['application_status']['submission_date'] = $p->referenceNo
            ? ($p->updatedDate
                ? $p->updatedDate->format('Y-m-d H:i:s')
                : null)
            : null;

        // Use updated date if available, otherwise fallback to creation date
        $response['application_status']['last_updated'] =
            $p->updatedDate ?? $p->dateCreated;

        /**
         * Resolve contact information.
         * Prioritize personal info values over user table values.
         */
        $emailFromPersonal = $p->email ?? null;
        $emailFromUsers = $user->email ?? null;

        $finalEmail = !empty($emailFromPersonal)
            ? $emailFromPersonal
            : $emailFromUsers;

        $phoneFromPersonal = $p->phoneNo ?? null;
        $phoneFromUsers = $user->phone ?? null;

        $finalPhone = !empty($phoneFromPersonal)
            ? $phoneFromPersonal
            : $phoneFromUsers;

        /**
         * Eager load related application records
         * to avoid N+1 query problems.
         */
        $p->load([
            'permanentAddress',
            'residentialAddress',
            'nextOfKin',
            'nysc',
            'professionalQualifications',
            'prisecEducation',
            'universityEducation',
            'mastersEducation',
            'workExperiences',
        ]);

        /**
         * Build personal information section.
         */
        $response['application'] = [
            'personal_info' => [
                'full_name' => trim(
                    $p->firstName . ' ' .
                    ($p->middleName ? $p->middleName . ' ' : '') .
                    $p->surname
                ),

                'firstName' => $p->firstName,
                'surname' => $p->surname,
                'middleName' => $p->middleName,
                'title' => $p->title,
                'maidenName' => $p->maidenName,
                'dateOfBirth' => $p->dateOfBirth,
                'placeOfBirth' => $p->placeOfBirth,
                'gender' => $p->gender,
                //'maritalStatus' => $p->maritalStatus,
                'state_of_origin' => $p->state_of_origin,
                'local_govt' => $p->local_govt,
                'nationality' => $p->nationality,
                'nin' => $p->nin,

                // Final resolved contact info
                'phone' => $finalPhone,
                'email' => $finalEmail,

                // File URLs
                //'passport_photograph' => $p->passportPhotograph ? $this->getRecruitmentEnlistUrl() . '/public/storage/' . $p->passportPhotograph : null,
                'passport_photograph' => $this->resolveFileUrl($p->passportPhotograph),


                //'birth_certificate' => $p->birthCertificate ? $this->getRecruitmentEnlistUrl() . '/public/storage/' . $p->birthCertificate : null,
                'birth_certificate' => $this->resolveFileUrl($p->birthCertificate),
            ]
        ];

        /**
         * Attach permanent address if available.
         */
        if ($p->permanentAddress) {
            $response['application']['permanent_address'] =
                $p->permanentAddress->toArray();
        }

        /**
         * Attach residential address if available.
         */
        if ($p->residentialAddress) {
            $response['application']['residential_address'] =
                $p->residentialAddress->toArray();
        }

        /**
         * Attach next of kin details.
         */
        if ($p->nextOfKin) {
            $response['application']['next_of_kin'] = [
                'full_name' => $p->nextOfKin->nameOfKin,
                'address' => $p->nextOfKin->addressofkin,
                'relationship' => $p->nextOfKin->relationshipKin,
                'phone' => $p->nextOfKin->phoneOfKin,
                'email' => $p->nextOfKin->emailOfKin,
                'gender' => $p->nextOfKin->genderofkin,
            ];
        }

        /**
         * Attach NYSC details.
         */
        if ($p->nysc) {
            $response['application']['nysc'] = $p->nysc->toArray();
        }

        /**
         * Attach professional qualifications.
         */
        if ($p->professionalQualifications &&
            $p->professionalQualifications->count()) {

            $response['application']['professional_qualifications'] =
                $p->professionalQualifications->toArray();
        }

        /**
         * Attach primary and secondary education details.
         */
        if ($p->prisecEducation) {
            $response['application']['education'] = [
                'primary' => [
                    'school_name' => $p->prisecEducation->primarysch_Name,
                    'address' => $p->prisecEducation->primarysch_address,
                    'state' => $p->prisecEducation->primarysch_state,
                    'country' => $p->prisecEducation->primarysch_country,
                    'graduation_date' => $p->prisecEducation->primarysch_enddate,
                ],

                'secondary' => [
                    'school_name' => $p->prisecEducation->secondrysch_name,
                    'address' => $p->prisecEducation->secondrysch_adress,
                    'state' => $p->prisecEducation->secondrysch_state,
                    'country' => $p->prisecEducation->secondrysch_country,
                    'graduation_date' => $p->prisecEducation->secondrysch_enddate,
                ],
            ];
        }

        /**
         * Attach university education records.
         */
        if ($p->universityEducation) {
            $response['application']['university_education'] =
                $p->universityEducation->toArray();
        }

        /**
         * Attach master's degree information.
         */
        if ($p->mastersEducation) {
            $response['application']['masters_education'] = [
                'institution_name' => $p->mastersEducation->institutionName,
                'address' => $p->mastersEducation->address,
                'state' => $p->mastersEducation->masters_state,
                'country' => $p->mastersEducation->masters_country,
                'discipline' => $p->mastersEducation->discipline,
                'matric_number' => $p->mastersEducation->matricNo,
                'year_of_graduation' => $p->mastersEducation->yearof_graduation,
                'created_at' => $p->mastersEducation->created_at,
                'updated_at' => $p->mastersEducation->updated_at,
            ];
        }

        /**
         * Attach work experience records.
         */
        if ($p->workExperiences &&
            $p->workExperiences->count()) {

            $response['application']['work_experience'] =
                $p->workExperiences->toArray();
        }

        // Return fully assembled user data response
        return $response;
    }

    /**
     * Get the recruitment enlistment URL from configuration.
     *
     * This method retrieves the enlistment URL defined in
     * config/recruitment_urls.php under the 'enlist' key.
     *
     * @return string|null The enlistment URL, or null if not configured.
     */
    private function getRecruitmentEnlistUrl(): ?string
    {
        return config('recruitment_urls.enlist');
    }
}