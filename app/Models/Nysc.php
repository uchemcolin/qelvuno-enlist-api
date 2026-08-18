<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nysc extends Model
{
    protected $table = 'nysc';
    protected $primaryKey = 'nyscID';
    protected $fillable = ['biodataID', 'nysc_year', 'certificate_num', 'nysc_type', 'preferrd_offc_loc'];

    /**
     * Get the personal info that owns this model/table/etc.
     */
    public function personalInfo()
    {
        return $this->belongsTo(PersonalInfo::class, 'biodataID', 'biodataID');
    }
}