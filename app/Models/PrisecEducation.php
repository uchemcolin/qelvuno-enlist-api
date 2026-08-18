<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrisecEducation extends Model
{
    protected $table = 'prisec_education';
    protected $primaryKey = 'educationID';
    protected $fillable = ['biodataID', 'primarysch_Name', 'primarysch_address', 'primarysch_state', 'primarysch_country', 'primarysch_enddate', 'secondrysch_name', 'secondrysch_adress', 'secondrysch_state', 'secondrysch_country', 'secondrysch_enddate'];

    /**
     * Get the personal info that owns this model/table/etc.
     */
    public function personalInfo()
    {
        return $this->belongsTo(PersonalInfo::class, 'biodataID', 'biodataID');
    }
}