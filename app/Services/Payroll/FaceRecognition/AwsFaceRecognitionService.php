<?php

namespace App\Services\Payroll\FaceRecognition;

use App\Models\PayrollSetting;
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
            throw new RuntimeException('No face was detected in the image.');
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

        $result = $this->client()->searchFacesByImage([
            'CollectionId' => $this->collectionId(),
            'Image' => ['Bytes' => $this->decodeImage($imageBase64)],
            'MaxFaces' => 1,
            'FaceMatchThreshold' => $threshold,
        ]);

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
