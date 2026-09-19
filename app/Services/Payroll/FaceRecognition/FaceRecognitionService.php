<?php

namespace App\Services\Payroll\FaceRecognition;

/**
 * Face recognition provider (AWS Rekognition in production, a null
 * implementation when no credentials are configured).
 */
interface FaceRecognitionService
{
    /**
     * Whether the provider has credentials and can be used.
     */
    public function isConfigured(): bool;

    public function collectionId(): string;

    /**
     * Index a reference face for a collaborator.
     *
     * @return array{face_id: string, quality: float|null}
     */
    public function indexFace(string $externalImageId, string $imageBase64): array;

    /**
     * Search a face in the collection (1:N).
     *
     * @return array{external_image_id: string, face_id: string, similarity: float}|null
     */
    public function search(string $imageBase64): ?array;

    public function removeFace(string $faceId): void;
}
