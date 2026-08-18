<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MastersEducation extends Model
{
    use HasFactory;

    protected $table = 'masters_education';
    protected $primaryKey = 'educationID';
    
    protected $fillable = [
        'biodataID',
        'institutionName',
        'address',
        'masters_state',
        'masters_country',
        'yearof_graduation',
        'discipline',
        'matricNo',
    ];

    protected $casts = [
        'yearof_graduation' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the personal info that owns this masters education.
     */
    public function personalInfo()
    {
        return $this->belongsTo(PersonalInfo::class, 'biodataID', 'biodataID');
    }
}