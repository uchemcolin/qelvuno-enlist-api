<?php
// app/Services/Contracts/ApplicationServiceInterface.php

namespace App\Services\Contracts;

use App\Models\User;
use App\Models\PersonalInfo;
use App\Models\NextOfKin;

/**
 * Interface for Application Service
 * Defines all methods that the application service must implement
 */
interface ApplicationServiceInterface
{
    /**
     * Check if application is locked (has reference number)
     * 
     * @param User $user
     * @return bool
     */
    public function isApplicationLocked(User $user): bool;

    /**
     * Check for duplicate applications by NIN, phone, or email
     * 
     * @param string $nin
     * @param string $phone
     * @param string $email
     * @param int|null $excludeBiodataID
     * @return bool
     */
    public function checkDuplicateApplication(string $nin, string $phone, string $email, ?int $excludeBiodataID = null): bool;

    /**
     * Save or update personal information
     * 
     * @param User $user
     * @param array $data
     * @param array $files
     * @return PersonalInfo
     */
    public function savePersonalInfo(User $user, array $data, array $files = []): PersonalInfo;

    /**
     * Save addresses (permanent and residential)
     * 
     * @param int $biodataID
     * @param array $data
     * @return array
     */
    public function saveAddresses(int $biodataID, array $data): array;

    /**
     * Save next of kin information
     * 
     * @param int $biodataID
     * @param array $data
     * @return NextOfKin
     */
    public function saveNextOfKin(int $biodataID, array $data): NextOfKin;

    /**
     * Save education and work experience
     * 
     * @param int $biodataID
     * @param array $data
     * @return array
     */
    public function saveEducationAndWorkExperience(int $biodataID, array $data): array;

    /**
     * Save NYSC and professional qualifications
     * 
     * @param int $biodataID
     * @param array $data
     * @return array
     */
    public function saveNyscProfessional(int $biodataID, array $data): array;

    /**
     * Complete application by generating reference number and locking it
     * 
     * @param int $biodataID
     * @return PersonalInfo
     */
    public function completeApplication(int $biodataID): PersonalInfo;

    /**
     * Get detailed completion status for all steps
     * 
     * @param int $biodataID
     * @return array
     */
    public function getDetailedStepsCompletion(int $biodataID): array;

    /**
     * Check if all required steps are completed
     * 
     * @param int $biodataID
     * @return array
     */
    public function checkStepsCompletion(int $biodataID): array;

    /**
     * Delete all application data for a biodataID
     * 
     * @param int $biodataID
     * @return bool
     */
    public function deleteExistingApplicationData(int $biodataID): bool;

    /**
     * Send application confirmation email
     * 
     * @param string $name
     * @param string $referenceNo
     * @param string $email
     * @param string $phone
     * @param string $loginUrl
     * @return void
     */
    public function sendConfirmationEmail(string $name, string $referenceNo, string $email, string $phone, string $loginUrl): void;

    /**
     * Get personal info with all related data loaded
     * 
     * @param int $biodataID
     * @return PersonalInfo|null
     */
    public function getFullApplicationData(int $biodataID): ?PersonalInfo;

    /**
     * Link user to existing application if it exists
     * 
     * @param User $user The user to link
     * @return bool True if linked successfully
     */
    public function linkExistingApplication(User $user): bool;

    /**
     * Ensure user has an application link (for safety checks)
     * 
     * @param User $user The user to check
     * @return bool True if user has an application linked
     */
    public function ensureUserHasApplicationLink(User $user): bool;
}