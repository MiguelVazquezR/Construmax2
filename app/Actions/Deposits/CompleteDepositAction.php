<?php

namespace App\Actions\Deposits;

use App\Models\Deposit;
use App\Models\TechnicianPayment;
use App\Services\Media\ImageOptimizerService;

class CompleteDepositAction
{
    public function __construct(
        private readonly ImageOptimizerService $imageOptimizer,
    ) {}

    public function execute(Deposit $deposit, array $data): Deposit
    {
        // 1. Create the automatic payment to the technician
        $payment = TechnicianPayment::create([
            'budget_id'      => $deposit->budget_id,
            'user_id'        => $deposit->technician->user_id,
            'amount'         => $deposit->amount,
            'payment_date'   => now()->toDateString(),
            'payment_method' => 'Transferencia',
            'reference'      => "Depósito #{$deposit->id}",
            'notes'          => 'Generado automáticamente desde el módulo de Depósitos.',
        ]);

        // 2. Save commission and voucher
        $deposit->update([
            'status'                => 'completed',
            'completed_at'          => now(),
            'commission_amount'     => $data['commission_amount'] ?? null,
            'technician_payment_id' => $payment->id,
        ]);

        if (isset($data['voucher'])) {
            $file    = $data['voucher'];
            $isImage = str_starts_with($file->getMimeType(), 'image/');

            if ($isImage) {
                $optimizedPath = $this->imageOptimizer->optimize($file);
                $deposit->addMedia($optimizedPath)
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('voucher');
            } else {
                $deposit->addMedia($file)->toMediaCollection('voucher');
            }
        }

        return $deposit->fresh();
    }
}
