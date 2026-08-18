<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NextOfKin extends Model
{
    protected $table = 'next_of_kin';
    protected $primaryKey = 'kinID';
    protected $fillable = ['biodataID', 'nameOfKin', 'addressofkin', 'relationshipKin', 'phoneOfKin', 'emailOfKin', 'genderofkin'];


    /**
     * Get the personal info that owns this model/table/etc.
     */
    public function personalInfo()
    {
        return $this->belongsTo(PersonalInfo::class, 'biodataID', 'biodataID');
    }
    

}