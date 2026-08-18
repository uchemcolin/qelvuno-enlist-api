<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PersonalInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FilePathResolver;

class ApplicationDataController extends Controller
{
    // To use the FilePathReolver helper
    use FilePathResolver;

    /**
     * Get complete application data for the authenticated user
     */
    public function getFullApplication(Request $request)
    {
        $user = $request->user();

        // Check if user has an application
        if (!$user->biodataID) {
            return response()->json([
                'success' => false,
                'message' => 'No application found. Please complete the application form first.'
            ], 404);
        }

        // Check if application is completed (has reference number)
        $personalInfo = PersonalInfo::where('biodataID', $user->biodataID)->first();
        
        if (!$personalInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Application data not found.'
            ], 404);
        }

        // Load all relationships
        $personalInfo->load([
            'permanentAddress',
            'residentialAddress',
            'nextOfKin',
            'nysc',
            'professionalQualifications',
            'prisecEducation',
            'universityEducation',
            'workExperiences',
        ]);

        // Build response
        $data = $this->formatApplicationData($personalInfo, $user);

        return response()->json([
            'success' => true,
            'message' => 'Application data retrieved successfully',
            'data' => $data
        ]);
    }

    /**
     * Get application by reference number (for sharing/downloading)
     */
    public function getApplicationByReference(Request $request, $referenceNo)
    {
        $personalInfo = PersonalInfo::where('referenceNo', $referenceNo)->first();

        if (!$personalInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found with this reference number.'
            ], 404);
        }

        // Load all relationships
        $personalInfo->load([
            'permanentAddress',
            'residentialAddress',
            'nextOfKin',
            'nysc',
            'professionalQualifications',
            'prisecEducation',
            'universityEducation',
            'workExperiences',
        ]);

        // Get the user associated with this application
        $user = User::where('biodataID', $personalInfo->biodataID)->first();

        $data = $this->formatApplicationData($personalInfo, $user);

        return response()->json([
            'success' => true,
            'message' => 'Application data retrieved successfully',
            'data' => $data
        ]);
    }

    /**
     * Format application data for response
     */
    private function formatApplicationData($personalInfo, $user)
    {
        $data = [];

        // ========== 1. REFERENCE & STATUS ==========
        $data['reference_number'] = $personalInfo->referenceNo;
        $data['submission_date'] = $personalInfo->updatedDate ? $personalInfo->updatedDate->format('Y-m-d H:i:s') : null;
        $data['is_complete'] = !is_null($personalInfo->referenceNo);

        // ========== 2. PERSONAL INFORMATION ==========
        $data['personal_info'] = [
            'full_name' => $personalInfo->firstName . ' ' . ($personalInfo->middleName ? $personalInfo->middleName . ' ' : '') . $personalInfo->surname,
            'firstName' => $personalInfo->firstName,
            'surname' => $personalInfo->surname,
            'middleName' => $personalInfo->middleName,
            'title' => $personalInfo->title,
            'maidenName' => $personalInfo->maidenName,
            'dateOfBirth' => $personalInfo->dateOfBirth,
            'placeOfBirth' => $personalInfo->placeOfBirth,
            'gender' => $personalInfo->gender,
            //'maritalStatus' => $personalInfo->maritalStatus,
            'email' => $personalInfo->email,
            'phone' => $personalInfo->phoneNo,
            'state_of_origin' => $personalInfo->state_of_origin,
            'local_govt' => $personalInfo->local_govt,
            'nationality' => $personalInfo->nationality ?? 'Nigeria',
            'nin' => $personalInfo->nin,
            //'passport_photograph' => $personalInfo->passportPhotograph ? Storage::url($personalInfo->passportPhotograph) : null,
            //'passport_photograph' => $personalInfo->passportPhotograph ? asset('storage/' . $personalInfo->passportPhotograph) : null,
            
            
            //'passport_photograph' => $personalInfo->passportPhotograph ? $this->getRecruitmentEnlistUrl() . '/public/storage/' . $personalInfo->passportPhotograph : null,
            'passport_photograph' => $this->resolveFileUrl($personalInfo->passportPhotograph),
            
            //'birth_certificate' => $personalInfo->birthCertificate ? Storage::url($personalInfo->birthCertificate) : null,
            //'birth_certificate' => $personalInfo->birthCertificate ? asset('storage/' . $personalInfo->birthCertificate) : null,
            
            
            //'birth_certificate' => $personalInfo->birthCertificate ? $this->getRecruitmentEnlistUrl() . '/public/storage/' . $personalInfo->birthCertificate : null,
            'birth_certificate' => $this->resolveFileUrl($personalInfo->birthCertificate),
        ];

        // ========== 3. PERMANENT ADDRESS ==========
        if ($personalInfo->permanentAddress) {
            $data['permanent_address'] = [
                'street' => $personalInfo->permanentAddress->street,
                'house_no' => $personalInfo->permanentAddress->house_no,
                'area' => $personalInfo->permanentAddress->area,
                'city' => $personalInfo->permanentAddress->city,
                'state' => $personalInfo->permanentAddress->state,
                'country' => $personalInfo->permanentAddress->country,
                'phone' => $personalInfo->permanentAddress->phone,
                'email' => $personalInfo->permanentAddress->email,
            ];
        }

        // ========== 4. RESIDENTIAL ADDRESS ==========
        if ($personalInfo->residentialAddress) {
            $data['residential_address'] = [
                'street' => $personalInfo->residentialAddress->street,
                'house_no' => $personalInfo->residentialAddress->house_no,
                'area' => $personalInfo->residentialAddress->area,
                'city' => $personalInfo->residentialAddress->city,
                'state' => $personalInfo->residentialAddress->state,
                'country' => $personalInfo->residentialAddress->country,
                'phone' => $personalInfo->residentialAddress->phone,
                'email' => $personalInfo->residentialAddress->email,
            ];
        }

        // ========== 5. NEXT OF KIN ==========
        if ($personalInfo->nextOfKin) {
            $data['next_of_kin'] = [
                'full_name' => $personalInfo->nextOfKin->nameOfKin,
                'address' => $personalInfo->nextOfKin->addressofkin,
                'relationship' => $personalInfo->nextOfKin->relationshipKin,
                'phone' => $personalInfo->nextOfKin->phoneOfKin,
                'email' => $personalInfo->nextOfKin->emailOfKin,
                'gender' => $personalInfo->nextOfKin->genderofkin,
            ];
        }

        // ========== 6. NYSC ==========
        if ($personalInfo->nysc) {
            $data['nysc'] = [
                'certificate_number' => $personalInfo->nysc->certificate_num,
                'completion_date' => $personalInfo->nysc->nysc_year,
                'type' => $personalInfo->nysc->nysc_type,
            ];
        }

        // ========== 7. PROFESSIONAL QUALIFICATIONS ==========
        if ($personalInfo->professionalQualifications && $personalInfo->professionalQualifications->count() > 0) {
            $data['professional_qualifications'] = [];
            foreach ($personalInfo->professionalQualifications as $pq) {
                $data['professional_qualifications'][] = [
                    'name' => $pq->name_of_qualfctn,
                    'organization' => $pq->name_of_orgnztn,
                    'date' => $pq->qualfctn_date,
                    'membership_number' => $pq->membership_no,
                    'class_of_membership' => $pq->class_of_membrship,
                ];
            }
        } else {
            $data['professional_qualifications'] = [];
        }

        // ========== 8. PRIMARY & SECONDARY EDUCATION ==========
        if ($personalInfo->prisecEducation) {
            $data['education'] = [
                'primary' => [
                    'school_name' => $personalInfo->prisecEducation->primarysch_Name,
                    'address' => $personalInfo->prisecEducation->primarysch_address,
                    'state' => $personalInfo->prisecEducation->primarysch_state,
                    'country' => $personalInfo->prisecEducation->primarysch_country,
                    'graduation_date' => $personalInfo->prisecEducation->primarysch_enddate,
                ],
                'secondary' => [
                    'school_name' => $personalInfo->prisecEducation->secondrysch_name,
                    'address' => $personalInfo->prisecEducation->secondrysch_adress,
                    'state' => $personalInfo->prisecEducation->secondrysch_state,
                    'country' => $personalInfo->prisecEducation->secondrysch_country,
                    'graduation_date' => $personalInfo->prisecEducation->secondrysch_enddate,
                ],
            ];
        }

        // ========== 9. UNIVERSITY EDUCATION ==========
        if ($personalInfo->universityEducation) {
            $data['university_education'] = [
                'institution_name' => $personalInfo->universityEducation->institutionName,
                'address' => $personalInfo->universityEducation->address,
                'state' => $personalInfo->universityEducation->university_state,
                'country' => $personalInfo->universityEducation->university_country,
                'discipline' => $personalInfo->universityEducation->discipline,
                'degree_type' => $personalInfo->universityEducation->degree_type,
                'class_of_degree' => $personalInfo->universityEducation->class_of_degree,
                'year_of_graduation' => $personalInfo->universityEducation->yearof_graduation,
                'matric_number' => $personalInfo->universityEducation->matricNo,
            ];
        }

        // ========== 10. MASTERS EDUCATION ==========
        if ($personalInfo->mastersEducation) {
            $data['masters_education'] = [
                'institution_name' => $personalInfo->mastersEducation->institutionName,
                'address' => $personalInfo->mastersEducation->address,
                'state' => $personalInfo->mastersEducation->masters_state,
                'country' => $personalInfo->mastersEducation->masters_country,
                'discipline' => $personalInfo->mastersEducation->discipline,
                'year_of_graduation' => $personalInfo->mastersEducation->yearof_graduation,
                'matric_number' => $personalInfo->mastersEducation->matricNo,
            ];
        }

        // ========== 11. WORK EXPERIENCE ==========
        if ($personalInfo->workExperiences && $personalInfo->workExperiences->count() > 0) {
            $data['work_experience'] = [];
            foreach ($personalInfo->workExperiences as $we) {
                $data['work_experience'][] = [
                    'position' => $we->position,
                    'company' => $we->company,
                    'start_date' => $we->startDate,
                    'end_date' => $we->endDate,
                ];
            }
        } else {
            $data['work_experience'] = [];
        }

        return $data;
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

        //dd('METHOD HIT');
    }
}