<?php

namespace App\Helpers;

use App\Models\PersonalInfo;
use Illuminate\Support\Facades\DB;

class ReferenceGenerator
{
    public static function generate()
    {
        $monthYear = date('my');
        $hash = substr(hash('sha256', microtime() . rand()), 0, 8);
        $referenceNo = 'FIRS-IA-' . $monthYear . strtoupper($hash);

        // Check uniqueness
        $exists = PersonalInfo::where('referenceNo', $referenceNo)->exists();
        if ($exists) {
            return self::generate(); // Recursive retry
        }

        return $referenceNo;
    }
}