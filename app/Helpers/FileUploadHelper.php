<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadHelper
{
    public static function upload($file, $referenceNo, $type)
    {
        if (!$file || !$file->isValid()) {
            return null;
        }

        // Validate file type
        $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($file->getMimeType(), $allowed)) {
            throw new \Exception('Invalid file type. Only JPG, JPEG, PNG allowed.');
        }

        // Validate file size (200KB max)
        if ($file->getSize() > 200 * 1024) {
            throw new \Exception('File too large. Maximum 200KB.');
        }

        $extension = $file->getClientOriginalExtension();
        $filename = $referenceNo . '_' . $type . '.' . $extension;
        $path = $file->storeAs('uploads', $filename, 'public');

        return $path;
    }
}