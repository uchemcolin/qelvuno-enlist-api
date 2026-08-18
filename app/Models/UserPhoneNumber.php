<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPhoneNumber extends Model
{
    protected $table = 'user_phonenumber';
    public $timestamps = false;

    protected $fillable = ['users_phonenumber'];
}