<?php

namespace App\Services\Payroll\FaceRecognition;

use RuntimeException;

/**
 * Fallback implementation used when AWS credentials are not configured yet:
 * the kiosk keeps working with the employee number + PIN fallback.
 */
class NullFaceRecognitionService implements FaceRecognitionService
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function collectionId(): string
    {
        return 'construmax-attendance';
    }

    public function indexFace(string $externalImageId, string $imageBase64): array
    {
        throw new RuntimeException('AWS Rekognition is not configured.');
    }

    public function search(string $imageBase64): ?array
    {
        throw new RuntimeException('AWS Rekognition is not configured.');
    }

    public function removeFace(string $faceId): void
    {
        // Nothing to remove without a provider.
    }
}
