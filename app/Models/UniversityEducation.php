<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniversityEducation extends Model
{
    protected $table = 'university_education';
    protected $primaryKey = 'educationID';
    protected $fillable = ['biodataID', 'institutionName', 'address', 'university_state', 'university_country', 'discipline', 'degree_type', 'class_of_degree', 'yearof_graduation', 'matricNo'];

    /**
     * Get the personal info that owns this model/table/etc.
     */
    public function personalInfo()
    {
        return $this->belongsTo(PersonalInfo::class, 'biodataID', 'biodataID');
    }
}