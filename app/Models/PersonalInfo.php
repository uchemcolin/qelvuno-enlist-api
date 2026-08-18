<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class PersonalInfo extends Model
{
    /*protected $table = 'personal_info';
    protected $primaryKey = 'biodataID';*/

    protected $table = 'personal_info';
    protected $primaryKey = 'biodataID';
    public $incrementing = true;
    protected $keyType = 'int';

    /**
     * Disable Laravel's automatic timestamps handling.
     *
     * This model uses a legacy database table (personal_info)
     * that does not contain created_at and updated_at columns.
     * Therefore, timestamps are disabled to prevent SQL errors.
     */
    public $timestamps = false;

    protected $fillable = [
        // Personal details
        'session_id',
        'firstName',
        'surname',
        'middleName',
        'referenceNo',
        'passportPhotograph',
        'title',
        'maidenName',
        'dateOfBirth',
        'placeOfBirth',
        'birthCertificate',
        'gender',
        'state_of_origin',
        'local_govt',
        'nationality',
        'nin',
        'phoneNo',
        'email',
        'preferrd_offc_loc',
        'disability_id',
        'disability',
        'emailSent',
        //'maritalStatus',  // If this column exists in your table
    ];

    protected $casts = [
        'dateOfBirth' => 'date',
        'dateCreated' => 'datetime',
        'updatedDate' => 'datetime',
        'emailSent' => 'boolean',
    ];

    /**
     * Automatically append these attributes
     * to API responses.
     */
    /*protected $appends = [
        'passport_photo_url',
        'birth_certificate_url',
    ];*/

    /**
     * Get full URL for passport photograph
     * Example:
     * https://yourdomain.com/storage/uploads/passport.jpg
     */
    /*public function getPassportPhotographUrlAttribute()
    {
        return $this->passportPhotograph
            ? Storage::url($this->passportPhotograph)
            : null;
    }*/

    /**
     * Get full URL for birth certificate
     * Example:
     * https://yourdomain.com/storage/uploads/birthcert.jpg
     */
    /*public function getBirthCertificateUrlAttribute()
    {
        return $this->birthCertificate
            ? Storage::url($this->birthCertificate)
            : null;
    }*/

    // ========== RELATIONSHIPS ==========

    public function user()
    {
        return $this->belongsTo(User::class, 'biodataID', 'biodataID');
    }

    public function permanentAddress()
    {
        return $this->hasOne(PermanentAddress::class, 'biodataID', 'biodataID');
    }

    public function residentialAddress()
    {
        return $this->hasOne(ResidentialAddress::class, 'biodataID', 'biodataID');
    }

    public function nextOfKin()
    {
        return $this->hasOne(NextOfKin::class, 'biodataID', 'biodataID');
    }

    public function nysc()
    {
        return $this->hasOne(Nysc::class, 'biodataID', 'biodataID');
    }

    public function professionalQualifications()
    {
        return $this->hasMany(ProfessionalQualification::class, 'biodataID', 'biodataID');
    }

    public function prisecEducation()
    {
        return $this->hasOne(PrisecEducation::class, 'biodataID', 'biodataID');
    }

    public function universityEducation()
    {
        return $this->hasOne(UniversityEducation::class, 'biodataID', 'biodataID');
    }

    public function mastersEducation()
    {
        return $this->hasOne(MastersEducation::class, 'biodataID', 'biodataID');
    }

    public function workExperiences()
    {
        return $this->hasMany(WorkExperience::class, 'biodataID', 'biodataID');
    }
}