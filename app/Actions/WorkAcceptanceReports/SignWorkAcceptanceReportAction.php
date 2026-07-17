<?php

namespace App\Actions\WorkAcceptanceReports;

use App\Models\WorkAcceptanceReport;

class SignWorkAcceptanceReportAction
{
    /**
     * Sign and lock the work acceptance report.
     * Once signed, the document becomes read-only.
     */
    public function execute(WorkAcceptanceReport $report, array $data): WorkAcceptanceReport
    {
        $report->update([
            'signature_data'  => $data['signature_data'],
            'signatory_name'  => $data['signatory_name'],
            'manager_name'    => $data['manager_name'] ?? null,
            'client_comments' => $data['client_comments'] ?? null,
        ]);

        $report->lock();

        return $report->fresh();
    }
}
