<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResidentialAddress extends Model
{
    protected $table = 'residential_address';
    protected $primaryKey = 'addressID';
    protected $fillable = ['biodataID', 'street', 'house_no', 'area', 'city', 'state', 'country', 'phone', 'email'];

    /**
     * Get the personal info that owns this model/table/etc.
     */
    public function personalInfo()
    {
        return $this->belongsTo(PersonalInfo::class, 'biodataID', 'biodataID');
    }
}