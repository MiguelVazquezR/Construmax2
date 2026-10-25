<?php

namespace App\Services\Payroll\FaceRecognition;

use App\Models\PayrollSetting;
use App\Services\Payroll\FaceRecognition\Exceptions\FaceNotDetectedException;
use App\Services\Payroll\FaceRecognition\Exceptions\MultipleFacesDetectedException;
use Aws\Rekognition\Exception\RekognitionException;
use Aws\Rekognition\RekognitionClient;
use RuntimeException;

class AwsFaceRecognitionService implements FaceRecognitionService
{
    private ?RekognitionClient $client = null;

    public function isConfigured(): bool
    {
        return filled(config('services.aws.key')) && filled(config('services.aws.secret'));
    }

    public function collectionId(): string
    {
        return (string) (config('services.aws.rekognition_collection')
            ?: PayrollSetting::current()->rekognition_collection_id
            ?: 'construmax-attendance');
    }

    public function indexFace(string $externalImageId, string $imageBase64): array
    {
        $this->ensureCollection();

        $result = $this->client()->indexFaces([
            'CollectionId' => $this->collectionId(),
            'Image' => ['Bytes' => $this->decodeImage($imageBase64)],
            'ExternalImageId' => $externalImageId,
            'MaxFaces' => 1,
            'QualityFilter' => 'AUTO',
        ]);

        $record = $result['FaceRecords'][0] ?? null;

        if (!$record) {
            throw new FaceNotDetectedException('No face was detected in the image.');
        }

        return [
            'face_id' => (string) $record['Face']['FaceId'],
            'quality' => isset($record['FaceDetail']['Quality']['Sharpness'])
                ? (float) $record['FaceDetail']['Quality']['Sharpness']
                : null,
        ];
    }

    public function search(string $imageBase64): ?array
    {
        $this->ensureCollection();

        $threshold = (float) PayrollSetting::current()->face_match_threshold;

        try {
            $result = $this->client()->searchFacesByImage([
                'CollectionId' => $this->collectionId(),
                'Image' => ['Bytes' => $this->decodeImage($imageBase64)],
                'MaxFaces' => 1,
                'FaceMatchThreshold' => $threshold,
            ]);
        } catch (RekognitionException $exception) {
            // Rekognition answers with InvalidParameterException instead of an
            // empty result when the photo has no face (or several) and when the
            // collection has no indexed faces yet.
            $result = $this->searchResultForFailure($exception);
        }

        $match = $result['FaceMatches'][0] ?? null;

        if (!$match) {
            return null;
        }

        return [
            'external_image_id' => (string) ($match['Face']['ExternalImageId'] ?? ''),
            'face_id' => (string) ($match['Face']['FaceId'] ?? ''),
            'similarity' => (float) ($match['Similarity'] ?? 0),
        ];
    }

    public function removeFace(string $faceId): void
    {
        if ($faceId === '') {
            return;
        }

        $this->client()->deleteFaces([
            'CollectionId' => $this->collectionId(),
            'FaceIds' => [$faceId],
        ]);
    }

    private function client(): RekognitionClient
    {
        return $this->client ??= new RekognitionClient([
            'version' => 'latest',
            'region' => config('services.aws.region', 'us-east-1'),
            'credentials' => [
                'key' => config('services.aws.key'),
                'secret' => config('services.aws.secret'),
            ],
        ]);
    }

    /**
     * Rekognition rejects the search instead of returning zero matches when the
     * photo has no face, more than one face, or the collection has no faces.
     * Only the last case is a real "no match"; the others must be reported so
     * the collaborator knows how to retry.
     *
     * @return array{FaceMatches: array<int, mixed>}
     */
    private function searchResultForFailure(RekognitionException $exception): array
    {
        $message = strtolower((string) $exception->getAwsErrorMessage());

        if ($exception->getAwsErrorCode() === 'InvalidParameterException') {
            if (str_contains($message, 'multiple faces')) {
                throw new MultipleFacesDetectedException('More than one face was detected in the image.');
            }

            if (str_contains($message, 'no faces in the image') || str_contains($message, 'at least 1')) {
                throw new FaceNotDetectedException('No face was detected in the image.');
            }

            if (str_contains($message, 'contain any faces')) {
                return ['FaceMatches' => []];
            }
        }

        throw $exception;
    }

    /**
     * Create the collection once; AWS replies with an error when it already exists.
     */
    private function ensureCollection(): void
    {
        try {
            $this->client()->createCollection(['CollectionId' => $this->collectionId()]);
        } catch (RekognitionException $exception) {
            if ($exception->getAwsErrorCode() !== 'ResourceAlreadyExistsException') {
                throw $exception;
            }
        }
    }

    /**
     * Accepts raw base64 or a data URL (data:image/jpeg;base64,...).
     */
    private function decodeImage(string $imageBase64): string
    {
        if (str_contains($imageBase64, ',')) {
            $imageBase64 = substr($imageBase64, strpos($imageBase64, ',') + 1);
        }

        $decoded = base64_decode($imageBase64, true);

        if ($decoded === false || $decoded === '') {
            throw new RuntimeException('The provided image is not valid base64.');
        }

        return $decoded;
    }
}
