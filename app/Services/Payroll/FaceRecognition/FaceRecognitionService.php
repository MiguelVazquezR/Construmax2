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

    /**
     * Every face indexed in the collection.
     *
     * @return array<int, array{face_id: string, external_image_id: string, image_id: string}>
     */
    public function listFaces(): array;

    public function removeFace(string $faceId): void;

    /**
     * Delete several faces at once.
     *
     * @param  array<int, string>  $faceIds
     * @return array<int, string> the face ids the provider confirmed as deleted
     */
    public function removeFaces(array $faceIds): array;
}
