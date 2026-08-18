<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = ['phone', 'email', 'biodataID', 'password', 'must_change_password'];
    protected $hidden = ['password'];

    public function personalInfo()
    {
        return $this->belongsTo(PersonalInfo::class, 'biodataID', 'biodataID');
    }
}