<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalQualification extends Model
{
    protected $table = 'prfssnalqualifictn';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = ['biodataID', 'name_of_qualfctn', 'name_of_orgnztn', 'qualfctn_date', 'membership_no', 'class_of_membrship'];

    /**
     * Get the personal info that owns this model/table/etc.
     */
    public function personalInfo()
    {
        return $this->belongsTo(PersonalInfo::class, 'biodataID', 'biodataID');
    }
}