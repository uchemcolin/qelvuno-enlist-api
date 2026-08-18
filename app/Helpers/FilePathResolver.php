<?php

namespace App\Helpers;

/**
 * Central file path resolver for BOTH legacy and Laravel uploads.
 *
 * This ensures backward compatibility with legacy database records
 * without modifying existing data.
 */
trait FilePathResolver
{
    /**
     * Base URL for serving uploaded files.
     */
    private function baseFileUrl(): ?string
    {
        return config('recruitment_urls.enlist');
    }

    /**
     * Resolve file path into a publicly accessible URL.
     *
     * Supports:
     * 1. Legacy format:
     *    assets/applicant_uploads/filename.ext
     *
     * 2. Laravel format:
     *    uploads/filename.ext
     *
     * @param string|null $path
     * @return string|null
     */
    private function resolveFileUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $baseUrl = $this->baseFileUrl();

        /*
        |--------------------------------------------------------------
        | LEGACY FILE HANDLING
        |--------------------------------------------------------------
        | Legacy files were stored under:
        | assets/applicant_uploads/
        |
        | We already migrated physical files into:
        | storage/app/public/uploads/
        |
        | So we only extract filename and map to uploads folder.
        */
        if (str_starts_with($path, 'assets/applicant_uploads/')) {
            $filename = basename($path);

            return $baseUrl . '/public/storage/uploads/' . $filename;
        }

        /*
        |--------------------------------------------------------------
        | LARAVEL FILE HANDLING
        |--------------------------------------------------------------
        | Stored using:
        | Storage::disk('public')->storeAs('uploads', ...)
        |
        | DB already contains:
        | uploads/filename.ext
        */
        return $baseUrl . '/public/storage/' . $path;
    }
}