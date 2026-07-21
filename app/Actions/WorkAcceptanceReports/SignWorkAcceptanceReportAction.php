<?php

namespace App\Actions\WorkAcceptanceReports;

use App\Models\WorkAcceptanceReport;

class SignWorkAcceptanceReportAction
{
    /**
     * Sign and lock the work acceptance report.
     * Once signed, the document becomes read-only.
     *
     * The signature is saved as a PNG file in storage/app/public/signatures/
     * and the path is stored in the database instead of the base64 blob.
     */
    public function execute(WorkAcceptanceReport $report, array $data): WorkAcceptanceReport
    {
        $signaturePath = $this->saveSignatureToDisk($data['signature_data'], $report->id);

        $report->update([
            'signature_path'  => $signaturePath,
            'signatory_name'  => $data['signatory_name'],
            'manager_name'    => $data['manager_name'] ?? null,
            'client_comments' => $data['client_comments'] ?? null,
        ]);

        $report->lock();

        return $report->fresh();
    }

    /**
     * Decode the base64 signature and persist it to the public disk.
     */
    private function saveSignatureToDisk(string $base64Data, int $reportId): string
    {
        // Strip data URI prefix if present (e.g. "data:image/png;base64,")
        if (str_starts_with($base64Data, 'data:image')) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        }

        $decoded = base64_decode($base64Data);

        $relativePath = 'signatures/' . $reportId . '_' . now()->timestamp . '.png';

        \Illuminate\Support\Facades\Storage::disk('public')->put($relativePath, $decoded);

        return $relativePath;
    }
}
