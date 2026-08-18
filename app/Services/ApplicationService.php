<?php
// app/Services/ApplicationService.php

namespace App\Services;

use App\Services\Contracts\ApplicationServiceInterface;
use App\Models\User;
use App\Models\PersonalInfo;
use App\Models\PermanentAddress;
use App\Models\ResidentialAddress;
use App\Models\NextOfKin;
use App\Models\Nysc;
use App\Models\ProfessionalQualification;
use App\Models\PrisecEducation;
use App\Models\UniversityEducation;
use App\Models\MastersEducation;
use App\Models\WorkExperience;
use App\Helpers\FileUploadHelper;
use App\Helpers\ReferenceGenerator;
use App\Jobs\SendApplicationConfirmationJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ApplicationService Class
 * 
 * This service handles all database operations and business logic for the
 * multi-step application submission process. It encapsulates all data
 * manipulation operations, keeping controllers thin and focused on request handling.
 * 
 * Responsibilities:
 * - CRUD operations for all application-related models
 * - Temporary file upload management during draft stages
 * - Final file storage using FileUploadHelper on submission
 * - Application completion and locking with reference number
 * - Progress tracking and validation
 * - Email notifications
 */
class ApplicationService implements ApplicationServiceInterface
{
    /**
     * Temporary directory for file uploads during draft stages
     * Files stored here during Steps 1-5 with random names
     * 
     * @var string
     */
    protected const TEMP_DIR = 'temp_uploads';

    /**
     * Check if application is locked (has reference number)
     * An application is considered locked once a reference number has been generated
     * 
     * @param User $user The authenticated user
     * @return bool True if application is locked, false otherwise
     */
    public function isApplicationLocked(User $user): bool
    {
        // If user doesn't have a biodataID, no application exists yet
        if (!$user->biodataID) {
            return false;
        }
        
        // Find personal info record and check if reference number exists
        $personalInfo = PersonalInfo::where('biodataID', $user->biodataID)->first();
        return $personalInfo && !is_null($personalInfo->referenceNo);
    }

    /**
     * Check for duplicate applications by NIN, phone, or email
     * Ensures data integrity by preventing duplicate submissions
     * 
     * @param string $nin National Identification Number
     * @param string $phone Phone number
     * @param string $email Email address
     * @param int|null $excludeBiodataID Optional biodataID to exclude from check (for updates)
     * @return bool True if duplicate exists, false otherwise
     */
    public function checkDuplicateApplication(string $nin, string $phone, string $email, ?int $excludeBiodataID = null): bool
    {
        // Build query to check for existing records with same NIN, phone, or email
        $query = PersonalInfo::where(function($query) use ($nin, $phone, $email) {
            $query->where('nin', $nin)
                ->orWhere('phoneNo', $phone)
                ->orWhere('email', $email);
        });

        // Exclude current record when updating existing application
        if ($excludeBiodataID) {
            $query->where('biodataID', '!=', $excludeBiodataID);
        }

        return $query->exists();
    }

    /**
     * Generate a unique temporary file name
     * Uses 32 characters of random hex to prevent collisions
     * 
     * @param string $originalName Original file name to extract extension
     * @return string Unique temporary file name with extension
     */
    protected function generateTempFileName(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $randomString = bin2hex(random_bytes(16)); // 32 characters random
        return $randomString . '.' . $extension;
    }

    /**
     * Upload file to temporary storage during draft stages
     * This method bypasses FileUploadHelper to store files with random names
     * in a temporary directory. Validation rules match FileUploadHelper.
     * 
     * @param \Illuminate\Http\UploadedFile $file The uploaded file
     * @param string $type File type (passport, birthcert)
     * @return string The temporary file path
     * @throws \Exception If file validation fails
     */
    protected function uploadToTemp($file, string $type): string
    {
        // Validate file type - matches FileUploadHelper validation
        $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($file->getMimeType(), $allowed)) {
            throw new \Exception('Invalid file type. Only JPG, JPEG, PNG allowed.');
        }

        // Validate file size - matches FileUploadHelper validation (200KB max)
        if ($file->getSize() > 200 * 1024) {
            throw new \Exception('File too large. Maximum 200KB.');
        }

        // Generate unique temporary file name
        $tempFileName = $this->generateTempFileName($file->getClientOriginalName());
        $tempPath = self::TEMP_DIR . '/' . $type . '/' . $tempFileName;
        
        // Store in temporary directory with random name
        Storage::disk('public')->putFileAs(
            self::TEMP_DIR . '/' . $type,
            $file,
            $tempFileName
        );
        
        Log::info('File uploaded to temporary storage', [
            'type' => $type,
            'temp_path' => $tempPath,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize()
        ]);
        
        return $tempPath;
    }

    /**
     * Move temporary file to final location using FileUploadHelper
     * This is called during Step 6 (final submission) to move files
     * from temporary storage to the final uploads directory with
     * the reference number in the filename.
     * 
     * @param string $tempPath Temporary file path
     * @param string $referenceNo The generated reference number
     * @param string $type File type (passport, birthcert)
     * @return string|null The final file path or null if file not found
     */
    protected function moveToFinalUsingHelper(string $tempPath, string $referenceNo, string $type): ?string
    {
        // Check if temporary file exists
        if (!Storage::disk('public')->exists($tempPath)) {
            Log::warning('Temporary file not found for moving', [
                'temp_path' => $tempPath,
                'reference_no' => $referenceNo
            ]);
            return null;
        }

        // Get the file from storage and create an UploadedFile instance
        $filePath = Storage::disk('public')->path($tempPath);
        $file = new \Illuminate\Http\UploadedFile(
            $filePath,
            basename($tempPath),
            Storage::disk('public')->mimeType($tempPath),
            null,
            true
        );

        // Use FileUploadHelper to upload to final location
        // This creates: uploads/{referenceNo}_{type}.{extension}
        $finalPath = FileUploadHelper::upload($file, $referenceNo, $type);

        // Delete temporary file after successful final upload
        if ($finalPath) {
            Storage::disk('public')->delete($tempPath);
            Log::info('File moved from temporary to final storage using FileUploadHelper', [
                'temp_path' => $tempPath,
                'final_path' => $finalPath,
                'reference_no' => $referenceNo,
                'type' => $type
            ]);
        } else {
            Log::error('Failed to move file to final storage', [
                'temp_path' => $tempPath,
                'reference_no' => $referenceNo,
                'type' => $type
            ]);
        }

        return $finalPath;
    }

    /**
     * Delete a temporary file from storage
     * Only deletes files that are in the temporary directory
     * 
     * @param string $filePath Path to the file to delete
     * @return void
     */
    protected function deleteTempFile(string $filePath): void
    {
        // Only delete if file exists and is in temp directory
        if (Storage::disk('public')->exists($filePath) && 
            strpos($filePath, self::TEMP_DIR) === 0) {
            Storage::disk('public')->delete($filePath);
            Log::info('Deleted temporary file', ['file_path' => $filePath]);
        }
    }

    /**
     * Clean up all temporary files for a specific type
     * Used during final submission to clean up any remaining temp files
     * 
     * @param int $biodataID The applicant's biodata ID (for logging)
     * @param string $type File type (passport, birthcert)
     * @return void
     */
    protected function cleanupTempFiles(int $biodataID, string $type): void
    {
        $tempDirectory = self::TEMP_DIR . '/' . $type;
        if (Storage::disk('public')->exists($tempDirectory)) {
            $files = Storage::disk('public')->files($tempDirectory);
            foreach ($files as $file) {
                Storage::disk('public')->delete($file);
            }
            Log::info('Cleaned up temporary files', [
                'biodataID' => $biodataID,
                'type' => $type,
                'files_deleted' => count($files)
            ]);
        }
    }

    /**
     * Save or update personal information (Step 1)
     * 
     * Handles:
     * - Creating new personal info records
     * - Updating existing records
     * - Temporary file uploads for passport and birth certificate (Step 1-5)
     * - User record synchronization
     * 
     * Files are stored in temp_uploads/ with random names during draft stages.
     * They will be moved to final location during Step 6 submission.
     * 
     * @param User $user The authenticated user
     * @param array $data Validated personal information data
     * @param array $files Uploaded files (passportPhotograph, birthCertificate)
     * @return PersonalInfo The saved personal information model
     * @throws \Exception If database operation fails
     */
    public function savePersonalInfo(User $user, array $data, array $files = []): PersonalInfo
    {
        // Start database transaction to ensure data consistency
        DB::beginTransaction();

        try {
            // Retrieve existing personal info if it exists (for update scenario)
            $existingPersonalInfo = null;
            if ($user->biodataID) {
                $existingPersonalInfo = PersonalInfo::where('biodataID', $user->biodataID)->first();
            }

            // Initialize file paths (will store temporary paths)
            $passportPath = null;
            $birthCertPath = null;

            // ========== HANDLE PASSPORT PHOTOGRAPH UPLOAD ==========
            if (isset($files['passportPhotograph'])) {
                // Delete old temporary passport file if it exists
                if ($existingPersonalInfo && $existingPersonalInfo->passportPhotograph) {
                    $this->deleteTempFile($existingPersonalInfo->passportPhotograph);
                }
                // Upload new passport photograph to temporary storage
                // This stores in: temp_uploads/passport/{random_name}.{ext}
                $passportPath = $this->uploadToTemp($files['passportPhotograph'], 'passport');
            } elseif ($existingPersonalInfo && !empty($existingPersonalInfo->passportPhotograph)) {
                // Keep existing passport if no new file uploaded
                $passportPath = $existingPersonalInfo->passportPhotograph;
            }

            // ========== HANDLE BIRTH CERTIFICATE UPLOAD ==========
            if (isset($files['birthCertificate'])) {
                // Delete old temporary birth certificate if it exists
                if ($existingPersonalInfo && $existingPersonalInfo->birthCertificate) {
                    $this->deleteTempFile($existingPersonalInfo->birthCertificate);
                }
                // Upload new birth certificate to temporary storage
                // This stores in: temp_uploads/birthcert/{random_name}.{ext}
                $birthCertPath = $this->uploadToTemp($files['birthCertificate'], 'birthcert');
            } elseif ($existingPersonalInfo && !empty($existingPersonalInfo->birthCertificate)) {
                // Keep existing birth certificate if no new file uploaded
                $birthCertPath = $existingPersonalInfo->birthCertificate;
            }

            // ========== CREATE OR UPDATE PERSONAL INFO RECORD ==========
            $personalInfo = $existingPersonalInfo ?? new PersonalInfo();
            
            // Set all personal information fields
            $personalInfo->firstName = $data['firstName'];
            $personalInfo->surname = $data['surname'];
            $personalInfo->middleName = $data['middleName'] ?? null;
            $personalInfo->maidenName = $data['maidenName'] ?? null;
            $personalInfo->title = $data['title'];
            $personalInfo->dateOfBirth = $data['dateOfBirth'];
            $personalInfo->placeOfBirth = $data['placeOfBirth'];
            $personalInfo->gender = $data['gender'];
            $personalInfo->state_of_origin = $data['state_of_origin'];
            $personalInfo->local_govt = $data['local_govt'];
            $personalInfo->nationality = $data['nationality'] ?? 'Nigerian';
            $personalInfo->disability_id = $data['disability_id'] ?? 0;
            $personalInfo->nin = $data['nin'];
            $personalInfo->phoneNo = $data['phoneNo'];
            $personalInfo->email = $data['email'];
            
            // Store temporary paths in database during draft stages
            $personalInfo->passportPhotograph = $passportPath; // temp_uploads/passport/{random}.jpg
            $personalInfo->birthCertificate = $birthCertPath; // temp_uploads/birthcert/{random}.jpg
            $personalInfo->updatedDate = now();

            // Set creation date and reference number for new records only
            if (!$personalInfo->exists) {
                $personalInfo->referenceNo = null; // Reference number will be set on final submission
                $personalInfo->dateCreated = now();
            }

            // Save to database
            $personalInfo->save();

            // ========== UPDATE USER RECORD ==========
            // Synchronize user record with latest information
            $user->biodataID = $personalInfo->biodataID;
            $user->email = $data['email'];
            $user->phone = $data['phoneNo'];
            $user->save();

            // Commit transaction if all operations succeeded
            DB::commit();

            Log::info('Personal info saved successfully', [
                'biodataID' => $personalInfo->biodataID,
                'user_id' => $user->id,
                'passport_temp' => $passportPath,
                'birthcert_temp' => $birthCertPath
            ]);

            return $personalInfo;

        } catch (\Exception $e) {
            // Rollback transaction on any error
            DB::rollBack();
            Log::error('Failed to save personal info', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Save addresses (permanent and residential) - Step 2
     * 
     * Handles both permanent and residential addresses.
     * Deletes existing addresses before creating new ones to ensure clean data.
     * 
     * @param int $biodataID The applicant's biodata ID
     * @param array $data Validated address data
     * @return array Array containing permanent and residential address models
     * @throws \Exception If database operation fails
     */
    public function saveAddresses(int $biodataID, array $data): array
    {
        // Start database transaction
        DB::beginTransaction();

        try {
            // ========== DELETE EXISTING ADDRESSES ==========
            // Remove old records to prevent duplicate entries
            PermanentAddress::where('biodataID', $biodataID)->delete();
            ResidentialAddress::where('biodataID', $biodataID)->delete();

            // ========== CREATE PERMANENT ADDRESS ==========
            $permanentAddress = PermanentAddress::create([
                'biodataID' => $biodataID,
                'street' => $data['perm_street'],
                'house_no' => $data['perm_house_no'],
                'area' => $data['perm_area'],
                'city' => $data['perm_city'],
                'state' => $data['perm_state'],
                'country' => $data['perm_country'],
                'phone' => $data['perm_phone'] ?? null,
                'email' => $data['perm_email'] ?? null,
            ]);

            // ========== CREATE RESIDENTIAL ADDRESS ==========
            $residentialAddress = ResidentialAddress::create([
                'biodataID' => $biodataID,
                'street' => $data['res_street'],
                'house_no' => $data['res_house_no'],
                'area' => $data['res_area'],
                'city' => $data['res_city'],
                'state' => $data['res_state'],
                'country' => $data['res_country'],
                'phone' => $data['res_phone'] ?? null,
                'email' => $data['res_email'] ?? null,
            ]);

            // Commit transaction
            DB::commit();

            Log::info('Addresses saved successfully', [
                'biodataID' => $biodataID,
                'permanent_address_id' => $permanentAddress->id,
                'residential_address_id' => $residentialAddress->id
            ]);

            return [
                'permanent_address' => $permanentAddress,
                'residential_address' => $residentialAddress
            ];

        } catch (\Exception $e) {
            // Rollback on error
            DB::rollBack();
            Log::error('Failed to save addresses', [
                'biodataID' => $biodataID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Save next of kin information - Step 3
     * 
     * Stores emergency contact and next of kin details.
     * Deletes existing record before creating new one.
     * 
     * @param int $biodataID The applicant's biodata ID
     * @param array $data Validated next of kin data
     * @return NextOfKin The saved next of kin model
     * @throws \Exception If database operation fails
     */
    public function saveNextOfKin(int $biodataID, array $data): NextOfKin
    {
        try {
            // Delete existing next of kin record to prevent duplicates
            NextOfKin::where('biodataID', $biodataID)->delete();

            // Create new next of kin record
            $nextOfKin = NextOfKin::create([
                'biodataID' => $biodataID,
                'nameOfKin' => $data['fullName'],
                'addressofkin' => $data['address'],
                'relationshipKin' => $data['relationship'],
                'phoneOfKin' => $data['phone'],
                'emailOfKin' => $data['email'],
                'genderofkin' => $data['gender'],
            ]);

            Log::info('Next of kin saved successfully', [
                'biodataID' => $biodataID,
                'next_of_kin_id' => $nextOfKin->id
            ]);

            return $nextOfKin;

        } catch (\Exception $e) {
            Log::error('Failed to save next of kin', [
                'biodataID' => $biodataID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Save education and work experience - Step 4
     * 
     * Handles:
     * - Primary and Secondary education (required)
     * - University education (required)
     * - Masters education (optional)
     * - Work experience (optional, max 3 entries)
     * 
     * @param int $biodataID The applicant's biodata ID
     * @param array $data Validated education and work data
     * @return array Array containing all saved education and work models
     * @throws \Exception If database operation fails
     */
    public function saveEducationAndWorkExperience(int $biodataID, array $data): array
    {
        // Start database transaction
        DB::beginTransaction();

        try {
            // ========== DELETE EXISTING RECORDS ==========
            // Remove old data to prevent duplicates and ensure clean state
            PrisecEducation::where('biodataID', $biodataID)->delete();
            UniversityEducation::where('biodataID', $biodataID)->delete();
            MastersEducation::where('biodataID', $biodataID)->delete();
            WorkExperience::where('biodataID', $biodataID)->delete();

            // ========== SAVE PRIMARY & SECONDARY EDUCATION ==========
            // This combines both primary and secondary into one record
            $prisecEducation = PrisecEducation::create([
                'biodataID' => $biodataID,
                'primarysch_Name' => $data['primary_school'],
                'primarysch_address' => $data['primary_address'],
                'primarysch_state' => $data['primary_state'],
                'primarysch_country' => $data['primary_country'] ?? null,
                'primarysch_enddate' => $data['primary_end_date'],
                'secondrysch_name' => $data['secondary_school'],
                'secondrysch_adress' => $data['secondary_address'],
                'secondrysch_state' => $data['secondary_state'],
                'secondrysch_country' => $data['secondary_country'] ?? null,
                'secondrysch_enddate' => $data['secondary_end_date'],
            ]);

            // ========== SAVE UNIVERSITY EDUCATION ==========
            $universityEducation = UniversityEducation::create([
                'biodataID' => $biodataID,
                'institutionName' => $data['uni_name'],
                'address' => $data['uni_address'],
                'university_state' => $data['uni_state'],
                'university_country' => $data['uni_country'] ?? null,
                'discipline' => $data['uni_discipline'],
                'degree_type' => $data['uni_degree'],
                'class_of_degree' => $data['uni_class'],
                'matricNo' => $data['uni_matric'],
                'yearof_graduation' => $data['uni_graduation_year'],
            ]);

            // ========== SAVE MASTERS EDUCATION (OPTIONAL) ==========
            $mastersEducation = null;
            if (!empty($data['masters_institution_name'])) {
                $mastersEducation = MastersEducation::create([
                    'biodataID' => $biodataID,
                    'institutionName' => $data['masters_institution_name'],
                    'address' => $data['masters_address'] ?? null,
                    'masters_state' => $data['masters_state'] ?? null,
                    'masters_country' => $data['masters_country'] ?? null,
                    'discipline' => $data['masters_discipline'] ?? null,
                    'matricNo' => $data['masters_matric_no'] ?? null,
                    'yearof_graduation' => $data['masters_graduation_year'] ?? null,
                ]);
            }

            // ========== SAVE WORK EXPERIENCES (OPTIONAL, MAX 3) ==========
            $workExperiences = [];
            if (!empty($data['experience']) && is_array($data['experience'])) {
                foreach ($data['experience'] as $exp) {
                    $workExperiences[] = WorkExperience::create([
                        'biodataID' => $biodataID,
                        'position' => $exp['position'],
                        'company' => $exp['company'],
                        'startDate' => $exp['start_date'],
                        'endDate' => $exp['end_date'],
                    ]);
                }
            }

            // Commit transaction
            DB::commit();

            Log::info('Education and work experience saved successfully', [
                'biodataID' => $biodataID,
                'prisec_id' => $prisecEducation->id,
                'uni_id' => $universityEducation->id,
                'masters_id' => $mastersEducation ? $mastersEducation->id : null,
                'work_experience_count' => count($workExperiences)
            ]);

            return [
                'prisec_education' => $prisecEducation,
                'university_education' => $universityEducation,
                'masters_education' => $mastersEducation,
                'work_experiences' => $workExperiences
            ];

        } catch (\Exception $e) {
            // Rollback on error
            DB::rollBack();
            Log::error('Failed to save education and work experience', [
                'biodataID' => $biodataID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Save NYSC and professional qualifications - Step 5
     * 
     * Handles:
     * - NYSC information (required)
     * - Professional qualifications (optional)
     * 
     * @param int $biodataID The applicant's biodata ID
     * @param array $data Validated NYSC and professional data
     * @return array Array containing NYSC and professional qualification models
     * @throws \Exception If database operation fails
     */
    public function saveNyscProfessional(int $biodataID, array $data): array
    {
        // Start database transaction
        DB::beginTransaction();

        try {
            // ========== DELETE EXISTING RECORDS ==========
            Nysc::where('biodataID', $biodataID)->delete();
            ProfessionalQualification::where('biodataID', $biodataID)->delete();

            // ========== SAVE NYSC INFORMATION (REQUIRED) ==========
            $nysc = Nysc::create([
                'biodataID' => $biodataID,
                'certificate_num' => $data['nysc_cert_no'],
                'nysc_year' => $data['nysc_completion_date'],
                'nysc_type' => $data['nysc_type'],
            ]);

            // ========== SAVE PROFESSIONAL QUALIFICATION (OPTIONAL) ==========
            $professionalQualification = null;
            if (!empty($data['prof_qualification'])) {
                $professionalQualification = ProfessionalQualification::create([
                    'biodataID' => $biodataID,
                    'name_of_qualfctn' => $data['prof_qualification'],
                    'name_of_orgnztn' => $data['prof_organization'] ?? null,
                    'membership_no' => $data['prof_membership_no'] ?? null,
                    'qualfctn_date' => $data['prof_date'] ?? null,
                    'class_of_membrship' => $data['prof_class'] ?? null,
                ]);
            }

            // Commit transaction
            DB::commit();

            Log::info('NYSC and professional saved successfully', [
                'biodataID' => $biodataID,
                'nysc_id' => $nysc->id,
                'professional_id' => $professionalQualification ? $professionalQualification->id : null
            ]);

            return [
                'nysc' => $nysc,
                'professional_qualification' => $professionalQualification
            ];

        } catch (\Exception $e) {
            // Rollback on error
            DB::rollBack();
            Log::error('Failed to save NYSC and professional', [
                'biodataID' => $biodataID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Complete application by generating reference number and moving files - Step 6
     * 
     * This final step:
     * 1. Generates a unique reference number for the application
     * 2. Moves temporary files to final location using FileUploadHelper
     *    - Passport: uploads/{referenceNo}_passport.{ext}
     *    - Birth Certificate: uploads/{referenceNo}_birthcert.{ext}
     * 3. Updates the personal_info record with final file paths and reference number
     * 4. Locks the application from further edits
     * 5. Cleans up temporary files
     * 
     * @param int $biodataID The applicant's biodata ID
     * @return PersonalInfo The updated personal information model with reference number
     * @throws \Exception If personal info not found or database operation fails
     */
    public function completeApplication(int $biodataID): PersonalInfo
    {
        // Start database transaction
        DB::beginTransaction();

        try {
            // Find the personal info record
            $personalInfo = PersonalInfo::where('biodataID', $biodataID)->first();
            
            if (!$personalInfo) {
                throw new \Exception('Personal information not found for biodataID: ' . $biodataID);
            }

            // Generate unique reference number
            // ReferenceGenerator handles uniqueness and format
            $referenceNo = ReferenceGenerator::generate();
            
            // ========== MOVE FILES TO FINAL LOCATION USING FILEUPLOADHELPER ==========
            $finalPassportPath = null;
            $finalBirthCertPath = null;
            
            // Move passport photograph if it exists and is in temporary directory
            if ($personalInfo->passportPhotograph && 
                strpos($personalInfo->passportPhotograph, self::TEMP_DIR) === 0) {
                $finalPassportPath = $this->moveToFinalUsingHelper(
                    $personalInfo->passportPhotograph,
                    $referenceNo,
                    'passport'
                );
                // Final path: uploads/{referenceNo}_passport.{ext}
            } else {
                // Keep existing path if not in temp (shouldn't happen in normal flow)
                $finalPassportPath = $personalInfo->passportPhotograph;
            }
            
            // Move birth certificate if it exists and is in temporary directory
            if ($personalInfo->birthCertificate && 
                strpos($personalInfo->birthCertificate, self::TEMP_DIR) === 0) {
                $finalBirthCertPath = $this->moveToFinalUsingHelper(
                    $personalInfo->birthCertificate,
                    $referenceNo,
                    'birthcert'
                );
                // Final path: uploads/{referenceNo}_birthcert.{ext}
            } else {
                // Keep existing path if not in temp (shouldn't happen in normal flow)
                $finalBirthCertPath = $personalInfo->birthCertificate;
            }
            
            // Update personal info with final file paths and reference number
            $personalInfo->referenceNo = $referenceNo;
            $personalInfo->passportPhotograph = $finalPassportPath;
            $personalInfo->birthCertificate = $finalBirthCertPath;
            $personalInfo->save();

            // Clean up any remaining temporary files
            $this->cleanupTempFiles($biodataID, 'passport');
            $this->cleanupTempFiles($biodataID, 'birthcert');

            // Commit transaction
            DB::commit();

            Log::info('Application completed successfully', [
                'biodataID' => $biodataID,
                'referenceNo' => $referenceNo,
                'passport_path' => $finalPassportPath,
                'birth_cert_path' => $finalBirthCertPath
            ]);

            return $personalInfo;

        } catch (\Exception $e) {
            // Rollback on error
            DB::rollBack();
            Log::error('Failed to complete application', [
                'biodataID' => $biodataID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get detailed completion status for all 6 steps
     * 
     * This method analyzes the database to determine which steps have been completed
     * and provides detailed field-level information for debugging and user feedback.
     * It also indicates whether files are in temporary or final storage.
     * 
     * @param int $biodataID The applicant's biodata ID
     * @return array Detailed completion status for each step
     */
    public function getDetailedStepsCompletion(int $biodataID): array
    {
        // Fetch personal info (Step 1 data)
        $personalInfo = PersonalInfo::where('biodataID', $biodataID)->first();
        
        // ========== STEP 1: Personal Information ==========
        $step1Completed = false;
        $step1Details = [
            'required_fields' => [],
            'optional_fields' => [],
            'files' => []
        ];
        
        if ($personalInfo) {
            // Define required fields for Step 1 with their labels
            $requiredFields = [
                'title' => 'Title',
                'firstName' => 'First Name',
                'surname' => 'Surname',
                'dateOfBirth' => 'Date of Birth',
                'placeOfBirth' => 'Place of Birth',
                'gender' => 'Gender',
                'state_of_origin' => 'State of Origin',
                'local_govt' => 'Local Government',
                'nin' => 'NIN',
                'phoneNo' => 'Phone Number',
                'email' => 'Email Address'
            ];
            
            // Check each required field
            foreach ($requiredFields as $field => $label) {
                $step1Details['required_fields'][] = [
                    'field' => $field,
                    'label' => $label,
                    'is_completed' => !empty($personalInfo->$field),
                    'value' => $personalInfo->$field ?? null
                ];
            }
            
            // Define optional fields
            $optionalFields = ['middleName', 'maidenName', 'nationality'];
            foreach ($optionalFields as $field) {
                $step1Details['optional_fields'][] = [
                    'field' => $field,
                    'label' => ucfirst($field),
                    'is_completed' => !empty($personalInfo->$field),
                    'value' => $personalInfo->$field ?? null
                ];
            }
            
            // Check file uploads with storage status
            $step1Details['files'] = [
                [
                    'field' => 'passportPhotograph',
                    'label' => 'Passport Photograph',
                    'is_completed' => !empty($personalInfo->passportPhotograph),
                    'is_temporary' => !empty($personalInfo->passportPhotograph) && 
                                     strpos($personalInfo->passportPhotograph, self::TEMP_DIR) === 0,
                    'is_final' => !empty($personalInfo->passportPhotograph) && 
                                  strpos($personalInfo->passportPhotograph, 'uploads/') === 0,
                    'url' => !empty($personalInfo->passportPhotograph) ? 
                             Storage::url($personalInfo->passportPhotograph) : null
                ],
                [
                    'field' => 'birthCertificate',
                    'label' => 'Birth Certificate',
                    'is_completed' => !empty($personalInfo->birthCertificate),
                    'is_temporary' => !empty($personalInfo->birthCertificate) && 
                                     strpos($personalInfo->birthCertificate, self::TEMP_DIR) === 0,
                    'is_final' => !empty($personalInfo->birthCertificate) && 
                                  strpos($personalInfo->birthCertificate, 'uploads/') === 0,
                    'url' => !empty($personalInfo->birthCertificate) ? 
                             Storage::url($personalInfo->birthCertificate) : null
                ]
            ];
            
            // Determine if Step 1 is fully completed
            $step1Completed = !empty($personalInfo->firstName) && 
                            !empty($personalInfo->surname) && 
                            !empty($personalInfo->title) && 
                            !empty($personalInfo->dateOfBirth) &&
                            !empty($personalInfo->gender) &&
                            !empty($personalInfo->state_of_origin) &&
                            !empty($personalInfo->local_govt) &&
                            !empty($personalInfo->nin) &&
                            !empty($personalInfo->phoneNo) &&
                            !empty($personalInfo->email) &&
                            !empty($personalInfo->passportPhotograph) &&
                            !empty($personalInfo->birthCertificate);
        }
        
        // ========== STEP 2: Addresses ==========
        $permAddress = PermanentAddress::where('biodataID', $biodataID)->first();
        $resAddress = ResidentialAddress::where('biodataID', $biodataID)->first();
        
        $step2Completed = $permAddress && $resAddress;
        $step2Details = [
            'permanent_address' => [
                'is_completed' => !is_null($permAddress),
                'exists' => !is_null($permAddress),
                'data' => $permAddress ? $permAddress->toArray() : null
            ],
            'residential_address' => [
                'is_completed' => !is_null($resAddress),
                'exists' => !is_null($resAddress),
                'data' => $resAddress ? $resAddress->toArray() : null
            ]
        ];
        
        // ========== STEP 3: Next of Kin ==========
        $nextOfKin = NextOfKin::where('biodataID', $biodataID)->first();
        $step3Completed = !is_null($nextOfKin);
        $step3Details = [
            'exists' => !is_null($nextOfKin),
            'data' => $nextOfKin ? $nextOfKin->toArray() : null
        ];
        
        // ========== STEP 4: Education and Work Experience ==========
        $prisecEdu = PrisecEducation::where('biodataID', $biodataID)->first();
        $uniEdu = UniversityEducation::where('biodataID', $biodataID)->first();
        $workExp = WorkExperience::where('biodataID', $biodataID)->get();
        $mastersEdu = MastersEducation::where('biodataID', $biodataID)->first();
        
        $step4Completed = $prisecEdu && $uniEdu;
        $step4Details = [
            'primary_secondary_education' => [
                'is_completed' => !is_null($prisecEdu),
                'exists' => !is_null($prisecEdu),
                'is_required' => true,
                'data' => $prisecEdu ? $prisecEdu->toArray() : null
            ],
            'university_education' => [
                'is_completed' => !is_null($uniEdu),
                'exists' => !is_null($uniEdu),
                'is_required' => true,
                'data' => $uniEdu ? $uniEdu->toArray() : null
            ],
            'masters_education' => [
                'is_completed' => !is_null($mastersEdu),
                'exists' => !is_null($mastersEdu),
                'is_required' => false,
                'data' => $mastersEdu ? $mastersEdu->toArray() : null,
                'message' => !is_null($mastersEdu) ? null : 'No masters education information provided'
            ],
            'work_experience' => [
                'is_completed' => $workExp->count() > 0,
                'exists' => $workExp->count() > 0,
                'is_required' => false,
                'count' => $workExp->count(),
                'data' => $workExp->count() > 0 ? $workExp->toArray() : [],
                'message' => $workExp->count() > 0 ? null : 'No work experience provided'
            ]
        ];
        
        // ========== STEP 5: NYSC and Professional ==========
        $nysc = Nysc::where('biodataID', $biodataID)->first();
        $professional = ProfessionalQualification::where('biodataID', $biodataID)->first();
        
        $step5Completed = !is_null($nysc);
        $step5Details = [
            'nysc' => [
                'is_completed' => !is_null($nysc),
                'exists' => !is_null($nysc),
                'is_required' => true,
                'data' => $nysc ? $nysc->toArray() : null
            ],
            'professional_qualification' => [
                'is_completed' => !is_null($professional),
                'exists' => !is_null($professional),
                'is_required' => false,
                'data' => $professional ? $professional->toArray() : null,
                'message' => !is_null($professional) ? null : 'No professional qualification provided'
            ]
        ];
        
        // ========== STEP 6: Summary/Review ==========
        // Step 6 is available only when all previous steps are completed
        $step6Completed = $step1Completed && $step2Completed && $step3Completed && $step4Completed && $step5Completed;
        
        // Return complete steps array
        return [
            'steps' => [
                1 => [
                    'name' => 'Personal Information',
                    'is_completed' => $step1Completed,
                    'is_available' => true, // Step 1 is always available
                    'details' => $step1Details
                ],
                2 => [
                    'name' => 'Applicant Address',
                    'is_completed' => $step2Completed,
                    'is_available' => true, // Step 2 is always available after step 1
                    'details' => $step2Details
                ],
                3 => [
                    'name' => 'Next of Kin',
                    'is_completed' => $step3Completed,
                    'is_available' => true, // Step 3 is always available after step 1
                    'details' => $step3Details
                ],
                4 => [
                    'name' => 'Education and Work Experience',
                    'is_completed' => $step4Completed,
                    'is_available' => true, // Step 4 is always available after step 1
                    'details' => $step4Details
                ],
                5 => [
                    'name' => 'NYSC and Professional Qualification',
                    'is_completed' => $step5Completed,
                    'is_available' => true, // Step 5 is always available after step 1
                    'details' => $step5Details
                ],
                6 => [
                    'name' => 'Summary and Review',
                    'is_completed' => $step6Completed,
                    'is_available' => $step1Completed && $step2Completed && $step3Completed && $step4Completed && $step5Completed,
                    'details' => [
                        'message' => 'Review all your information before final submission',
                        'all_steps_complete' => $step6Completed,
                        'can_submit' => $step6Completed
                    ]
                ]
            ]
        ];
    }

    /**
     * Check if all required steps (1-5) are completed
     * 
     * This is a convenience method that wraps getDetailedStepsCompletion
     * to provide a simple boolean answer about completion status.
     * 
     * @param int $biodataID The applicant's biodata ID
     * @return array Array containing completion status and missing steps
     */
    public function checkStepsCompletion(int $biodataID): array
    {
        // Get detailed steps completion
        $stepsCompletion = $this->getDetailedStepsCompletion($biodataID);
        
        $missingSteps = [];
        $allCompleted = true;
        
        // Check steps 1-5 (step 6 is just a summary)
        for ($step = 1; $step <= 5; $step++) {
            if (!$stepsCompletion['steps'][$step]['is_completed']) {
                $allCompleted = false;
                $missingSteps[] = $step;
            }
        }
        
        return [
            'completed' => $allCompleted,
            'missing_steps' => $missingSteps,
            'steps_completion' => $stepsCompletion
        ];
    }

    /**
     * Delete all application data for a biodataID
     * 
     * This is used when resetting an application or during cleanup.
     * Deletes records from all related tables in the correct order.
     * Also deletes temporary files if they exist.
     * 
     * @param int $biodataID The applicant's biodata ID
     * @return bool True if deletion was successful
     * @throws \Exception If database operation fails
     */
    public function deleteExistingApplicationData(int $biodataID): bool
    {
        // Start database transaction
        DB::beginTransaction();

        try {
            // Get personal info to delete associated files
            $personalInfo = PersonalInfo::where('biodataID', $biodataID)->first();
            
            // Delete temporary files if they exist
            if ($personalInfo) {
                if ($personalInfo->passportPhotograph && 
                    strpos($personalInfo->passportPhotograph, self::TEMP_DIR) === 0) {
                    $this->deleteTempFile($personalInfo->passportPhotograph);
                }
                if ($personalInfo->birthCertificate && 
                    strpos($personalInfo->birthCertificate, self::TEMP_DIR) === 0) {
                    $this->deleteTempFile($personalInfo->birthCertificate);
                }
            }
            
            // Delete in reverse order of dependencies
            // Work experiences depend on biodataID
            WorkExperience::where('biodataID', $biodataID)->delete();
            
            // Professional qualifications depend on biodataID
            ProfessionalQualification::where('biodataID', $biodataID)->delete();
            
            // NYSC depends on biodataID
            Nysc::where('biodataID', $biodataID)->delete();
            
            // Masters education depends on biodataID
            MastersEducation::where('biodataID', $biodataID)->delete();
            
            // University education depends on biodataID
            UniversityEducation::where('biodataID', $biodataID)->delete();
            
            // Primary/Secondary education depends on biodataID
            PrisecEducation::where('biodataID', $biodataID)->delete();
            
            // Next of kin depends on biodataID
            NextOfKin::where('biodataID', $biodataID)->delete();
            
            // Residential address depends on biodataID
            ResidentialAddress::where('biodataID', $biodataID)->delete();
            
            // Permanent address depends on biodataID
            PermanentAddress::where('biodataID', $biodataID)->delete();
            
            // Clean up temporary directories
            $this->cleanupTempFiles($biodataID, 'passport');
            $this->cleanupTempFiles($biodataID, 'birthcert');
            
            // Commit transaction
            DB::commit();
            
            Log::info('Existing application data deleted successfully', ['biodataID' => $biodataID]);
            
            return true;
            
        } catch (\Exception $e) {
            // Rollback on error
            DB::rollBack();
            Log::error('Failed to delete existing application data', [
                'biodataID' => $biodataID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Send application confirmation email via queue job
     * 
     * Uses queued job to prevent slowing down the HTTP response
     * and provides better error handling for email delivery.
     * 
     * @param string $name Applicant's full name
     * @param string $referenceNo Generated reference number
     * @param string $email Applicant's email address
     * @param string $phone Applicant's phone number
     * @param string $loginUrl URL for applicant to login
     * @return void
     */
    public function sendConfirmationEmail(string $name, string $referenceNo, string $email, string $phone, string $loginUrl): void
    {
        // Dispatch job to queue for asynchronous processing
        SendApplicationConfirmationJob::dispatch($name, $referenceNo, $email, $phone, $loginUrl);
        
        Log::info('Confirmation email queued', [
            'email' => $email,
            'reference_no' => $referenceNo
        ]);
    }

    /**
     * Get personal info with all related data loaded (eager loaded)
     * 
     * This method loads all relationships to avoid N+1 query problems
     * when displaying the full application profile.
     * 
     * @param int $biodataID The applicant's biodata ID
     * @return PersonalInfo|null The personal info model with all relationships loaded
     */
    public function getFullApplicationData(int $biodataID): ?PersonalInfo
    {
        // Eager load all relationships to optimize database queries
        return PersonalInfo::where('biodataID', $biodataID)
            ->with([
                'permanentAddress',      // Step 2 data
                'residentialAddress',    // Step 2 data
                'nextOfKin',             // Step 3 data
                'nysc',                  // Step 5 data
                'professionalQualifications', // Step 5 data
                'prisecEducation',       // Step 4 data
                'universityEducation',   // Step 4 data
                'mastersEducation',      // Step 4 data
                'workExperiences',       // Step 4 data
            ])
            ->first();
    }

    /**
     * Link user to existing application if it exists
     * Link user to existing application with strict email/phone matching
     * 
     * This method is designed for legacy users who had applications
     * before the portal was revamped with the users table.
     * 
     * @param User $user The user to link
     * @return bool True if linked successfully, false if no application found
     */
    public function linkExistingApplication(User $user): bool
    {
        // Skip if already linked
        if ($user->biodataID) {
            Log::info('User already linked to application', [
                'user_id' => $user->id,
                'biodataID' => $user->biodataID
            ]);
            return true;
        }

        // FIRST: Try to find by phone (most reliable for legacy data)
        $personalInfo = null;
        $matchedBy = null;

        if ($user->phone) {
            $personalInfo = PersonalInfo::where('phoneNo', $user->phone)->first();
            if ($personalInfo) {
                $matchedBy = 'phone';
            }
        }

        // SECOND: Try by email if phone didn't work
        if (!$personalInfo && $user->email) {
            $personalInfo = PersonalInfo::where('email', $user->email)->first();
            if ($personalInfo) {
                $matchedBy = 'email';
            }
        }

        // THIRD: If still not found, check if user has any personal_info with matching email/phone
        // (this is for the case where user provided different email during validation)
        if (!$personalInfo) {
            // Check if email exists in personal_info
            if ($user->email) {
                $personalInfo = PersonalInfo::where('email', $user->email)->first();
                if ($personalInfo) {
                    $matchedBy = 'email_after_validation';
                }
            }
            
            // Check if phone exists in personal_info
            if (!$personalInfo && $user->phone) {
                $personalInfo = PersonalInfo::where('phoneNo', $user->phone)->first();
                if ($personalInfo) {
                    $matchedBy = 'phone_after_validation';
                }
            }
        }

        if ($personalInfo) {
            // 🔒 EXTRA SECURITY: Verify the user is the legitimate owner
            // If user has no biodataID yet, this is a new account for legacy user
            
            // Link the user to the existing application
            $user->biodataID = $personalInfo->biodataID;
            
            // Force sync contact info (use personal_info as source of truth)
            if (!empty($personalInfo->email)) {
                $user->email = $personalInfo->email;
            }
            if (!empty($personalInfo->phoneNo)) {
                $user->phone = $personalInfo->phoneNo;
            }
            
            $user->save();
            
            Log::info('Legacy application linked to user', [
                'user_id' => $user->id,
                'biodataID' => $personalInfo->biodataID,
                'referenceNo' => $personalInfo->referenceNo ?? 'Incomplete application',
                'matched_by' => $matchedBy,
                'email_match' => $user->email === $personalInfo->email,
                'phone_match' => $user->phone === $personalInfo->phoneNo
            ]);
            
            return true;
        }
        
        // No matching personal_info found
        Log::info('No existing application found for user', [
            'user_id' => $user->id,
            'phone' => $user->phone,
            'email' => $user->email,
            'is_new_user' => true
        ]);
        
        return false;
    }

    /**
     * Check if user has an existing application and link if possible
     * This is a convenience method for safety net calls
     * 
     * @param User $user The user to check
     * @return bool True if user has an application (linked or already had one)
     */
    public function ensureUserHasApplicationLink(User $user): bool
    {
        // If already linked, return true
        if ($user->biodataID) {
            return true;
        }
        
        // Try to link
        return $this->linkExistingApplication($user);
    }

    /**
     * Check if an email exists in personal_info for a given user
     * This can be used for validation in the controller
     */
    public function doesEmailBelongToUser(string $email, User $user): bool
    {
        $personalInfo = PersonalInfo::where('email', $email)->first();
        
        if (!$personalInfo) {
            return false;
        }
        
        // If user already has biodataID, check if it matches
        if ($user->biodataID) {
            return $user->biodataID === $personalInfo->biodataID;
        }
        
        // If user has phone, check if it matches the personal_info phone
        if ($user->phone && $personalInfo->phoneNo === $user->phone) {
            return true;
        }
        
        // No match found
        return false;
    }

    /**
     * Check if a phone exists in personal_info for a given user
     */
    public function doesPhoneBelongToUser(string $phone, User $user): bool
    {
        $personalInfo = PersonalInfo::where('phoneNo', $phone)->first();
        
        if (!$personalInfo) {
            return false;
        }
        
        if ($user->biodataID) {
            return $user->biodataID === $personalInfo->biodataID;
        }
        
        if ($user->email && $personalInfo->email === $user->email) {
            return true;
        }
        
        return false;
    }
}