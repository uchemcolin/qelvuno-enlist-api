<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkExperience extends Model
{
    protected $table = 'work_experience';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = ['biodataID', 'position', 'company', 'startDate', 'endDate'];

    /**
     * Get the personal info that owns this model/table/etc.
     */
    public function personalInfo()
    {
        return $this->belongsTo(PersonalInfo::class, 'biodataID', 'biodataID');
    }
}