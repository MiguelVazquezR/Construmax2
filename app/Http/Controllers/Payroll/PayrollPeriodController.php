<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StorePayrollAdjustmentRequest;
use App\Http\Requests\Payroll\UpdateAttendanceDayOverrideRequest;
use App\Models\AttendanceDayOverride;
use App\Models\AttendanceLog;
use App\Models\Incident;
use App\Models\PayrollAdjustment;
use App\Models\PayrollNote;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Models\Shift;
use App\Models\User;
use App\Services\Export\XlsxWriterService;
use App\Services\Payroll\PayrollCalculatorService;
use App\Services\Payroll\PayrollPeriodService;
use App\Services\Payroll\PayslipService;
use App\Services\Payroll\ScheduleResolverService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollPeriodController extends Controller
{
    public function __construct(
        private readonly PayrollPeriodService $periodService,
        private readonly PayrollCalculatorService $calculator,
        private readonly ScheduleResolverService $scheduleResolver,
        private readonly PayslipService $payslipService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePeriods($request);

        $periods = PayrollPeriod::query()
            ->withCount('payslips')
            ->orderByDesc('start_date')
            ->paginate(12)
            ->withQueryString();

        $openPeriod = $this->periodService->currentOpenPeriod();

        return Inertia::render('Payroll/Periods/Index', [
            'periods' => $periods,
            'openPeriod' => $openPeriod,
            'payrollEmployees' => $this->calculator->payrollSubjects()->count(),
            'typeLabels' => PayrollSetting::PERIOD_TYPES,
        ]);
    }

    /**
     * Create the first period from the configured type and anchor date.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeClose($request);

        if ($this->periodService->currentOpenPeriod()) {
            return back()->with('error', 'Ya existe un periodo de nómina abierto.');
        }

        $period = $this->periodService->createFirstPeriod();

        return back()->with('success', 'Periodo de nómina creado: '.$period->label().'.');
    }

    public function show(Request $request, PayrollPeriod $period): Response
    {
        $this->authorizePeriods($request);

        [$rows, $stats] = $this->periodRows($period);

        return Inertia::render('Payroll/Periods/Show', [
            'period' => $period,
            'rows' => $rows,
            'stats' => $stats,
            'adjustments' => $period->adjustments()->with('user:id,name')->get(),
            'incidents' => $this->periodIncidents($period),
            'notes' => $this->periodNotes($period),
            'typeLabels' => PayrollSetting::PERIOD_TYPES,
        ]);
    }

    /**
     * Comments written about every collaborator of the period.
     *
     * @return array<int, array<string, mixed>>
     */
    private function periodNotes(PayrollPeriod $period): array
    {
        return $period->notes()
            ->with('author:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (PayrollNote $note) => [
                'id' => $note->id,
                'user_id' => $note->user_id,
                'body' => $note->body,
                'author_name' => $note->author?->name,
                'created_at' => $note->created_at?->format('d/m/Y H:i'),
                'updated_at' => $note->updated_at?->format('d/m/Y H:i'),
                'is_edited' => $note->updated_at?->greaterThan($note->created_at) ?? false,
            ])
            ->values()
            ->all();
    }

    /**
     * Incidents that overlap the period so the pre-payroll detail is the only
     * place needed to review and correct them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function periodIncidents(PayrollPeriod $period): array
    {
        return Incident::query()
            ->overlapping($period->start_date, $period->end_date)
            ->with('user:id,name')
            ->orderBy('start_date')
            ->get()
            ->map(fn (Incident $incident) => [
                'id' => $incident->id,
                'user_id' => $incident->user_id,
                'user_name' => $incident->user?->name,
                'type' => $incident->type,
                'type_label' => $incident->type_label,
                'start_date' => $incident->start_date->toDateString(),
                'end_date' => $incident->end_date?->toDateString(),
                'days' => (float) $incident->days,
                'is_paid' => $incident->is_paid,
                'resolved_is_paid' => $incident->resolvedIsPaid(),
                'notes' => $incident->notes,
                'support_url' => $incident->support_url,
            ])
            ->values()
            ->all();
    }

    /**
     * Pre-payroll sheet of the whole period: every collaborator with their
     * totals, concept lines and day-by-day detail (live for open periods,
     * frozen for closed ones).
     */
    public function prePayroll(Request $request, PayrollPeriod $period): Response
    {
        $this->authorizePeriods($request);

        $rows = $this->payslipService->prePayrollPayloads($period);

        return Inertia::render('Payroll/Periods/PrePayroll', [
            'period' => [
                'id' => $period->id,
                'label' => $period->label(),
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $period->status,
            ],
            'rows' => $rows,
            'stats' => [
                'employees' => $rows->count(),
                'days_paid' => round($rows->sum('days_paid'), 2),
                'overtime_hours' => round($rows->sum('overtime_minutes') / 60, 2),
                'total_gross' => round($rows->sum('total_gross'), 2),
                'total_deductions' => round($rows->sum('total_deductions'), 2),
                'total_net' => round($rows->sum('total_net'), 2),
            ],
            'notes' => $this->periodNotes($period),
            'incidents' => $this->periodIncidents($period),
            'typeLabels' => PayrollSetting::PERIOD_TYPES,
        ]);
    }

    /**
     * Day-by-day detail of a collaborator inside the period (live for open
     * periods, frozen payslip days for closed ones) plus the weekly schedule
     * and the raw punches with their evidence. Accepts an optional "from" /
     * "to" range (clamped to the period) to narrow the days and punches.
     */
    public function days(Request $request, PayrollPeriod $period, User $user): JsonResponse
    {
        $this->authorizePeriods($request);

        [$from, $to] = $this->resolveDaysRange($request, $period);

        $days = $this->calculator->daysFor($user, $period);

        $days = array_values(array_filter(
            $days,
            fn (array $day) => $day['date'] >= $from && $day['date'] <= $to
        ));

        $logs = AttendanceLog::forUser($user->id)
            ->whereDate('punched_at', '>=', $from)
            ->whereDate('punched_at', '<=', $to)
            ->with('device:id,name')
            ->orderBy('punched_at')
            ->get()
            ->groupBy(fn (AttendanceLog $log) => $log->punched_at->toDateString());

        foreach ($days as $index => $day) {
            $days[$index]['punches'] = collect($logs[$day['date']] ?? [])
                ->map(fn (AttendanceLog $log) => [
                    'id' => $log->id,
                    'type' => $log->type,
                    'type_label' => $log->type_label,
                    'time' => $log->punched_at->format('H:i'),
                    'punched_at' => $log->punched_at->format('Y-m-d H:i'),
                    'source' => $log->source,
                    'identifier_method' => $log->identifier_method,
                    'capture_url' => $log->capture_url,
                    'latitude' => $log->latitude,
                    'longitude' => $log->longitude,
                    'device' => $log->device?->name,
                    'edited' => $log->edited_by !== null,
                    'edit_reason' => $log->edit_reason,
                ])
                ->values()
                ->all();
        }

        return response()->json([
            'days' => $days,
            'weekly_schedule' => $this->weeklyScheduleGrid($user),
            'range' => ['from' => $from, 'to' => $to],
        ]);
    }

    /**
     * Requested day range, clamped to the period limits. Anything invalid
     * falls back to the whole period.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveDaysRange(Request $request, PayrollPeriod $period): array
    {
        $periodStart = $period->start_date->toDateString();
        $periodEnd = $period->end_date->toDateString();

        $from = $this->parseRangeDate($request->input('from')) ?? $periodStart;
        $to = $this->parseRangeDate($request->input('to')) ?? $periodEnd;

        $from = max($from, $periodStart);
        $to = min($to, $periodEnd);

        if ($from > $to) {
            return [$periodStart, $periodEnd];
        }

        return [$from, $to];
    }

    private function parseRangeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Manual override of a day inside the period (ignore a late arrival, add notes).
     */
    public function updateOverride(UpdateAttendanceDayOverrideRequest $request, PayrollPeriod $period, User $user): RedirectResponse
    {
        AttendanceDayOverride::updateOrCreate(
            ['user_id' => $user->id, 'date' => $request->validated('date')],
            [
                'late_ignored' => $request->boolean('late_ignored'),
                'notes' => $request->validated('notes'),
                'updated_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Día actualizado.');
    }

    public function storeAdjustment(StorePayrollAdjustmentRequest $request, PayrollPeriod $period): RedirectResponse
    {
        if (! $period->isOpen()) {
            return back()->with('error', 'Solo puedes agregar ajustes a periodos abiertos.');
        }

        PayrollAdjustment::create([
            ...$request->validated(),
            'payroll_period_id' => $period->id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Ajuste registrado.');
    }

    public function destroyAdjustment(Request $request, PayrollAdjustment $adjustment): RedirectResponse
    {
        if (! $request->user()->can('payroll.periods.manage')) {
            abort(403);
        }

        if (! $adjustment->period->isOpen()) {
            return back()->with('error', 'Reabre el periodo para modificar sus ajustes.');
        }

        $adjustment->delete();

        return back()->with('success', 'Ajuste eliminado.');
    }

    public function close(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $this->authorizeClose($request);

        $this->periodService->close($period, $request->user());

        return back()->with('success', 'Periodo cerrado: recibos generados y gasto de nómina registrado.');
    }

    public function reopen(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $this->authorizeClose($request);

        $this->periodService->reopen($period, $request->user());

        return back()->with('success', 'Periodo reabierto: los recibos y el gasto fueron eliminados.');
    }

    /**
     * Excel report with the pre-payroll (or frozen payroll) of the period.
     */
    public function export(Request $request, PayrollPeriod $period, XlsxWriterService $writer)
    {
        $this->authorizePeriods($request);

        [$rows] = $this->periodRows($period);

        $exportRows = array_map(fn ($row) => [
            $row['name'],
            $row['employee_number'],
            $row['department'],
            $row['days_worked'],
            $row['days_paid'],
            $row['unpaid_days'],
            $row['late_minutes'],
            $row['late_discount'],
            round($row['overtime_minutes'] / 60, 2),
            $row['overtime_amount'],
            $row['holiday_days'],
            $row['holiday_amount'],
            $row['vacation_days'],
            $row['incapacity_days'],
            $row['adjustments_earnings'],
            $row['adjustments_deductions'],
            $row['total_gross'],
            $row['total_deductions'],
            $row['total_net'],
        ], $rows);

        $sum = fn (int $index) => array_sum(array_map(fn ($row) => (float) $row[$index], $exportRows));

        $footer = array_fill(0, 19, '');
        $footer[0] = 'Totales';

        foreach (range(3, 18) as $index) {
            $footer[$index] = round($sum($index), 2);
        }

        $path = $writer->generate(
            'Nómina '.$period->start_date->format('Y-m-d'),
            [
                'Colaborador', 'Número', 'Departamento', 'Días trabajados', 'Días pagados', 'Días no pagados',
                'Retardos (min)', 'Descuento retardos', 'Horas extra', 'Importe extra', 'Días festivos',
                'Importe festivo', 'Días vacaciones', 'Días incapacidad', 'Ajustes percepciones',
                'Ajustes deducciones', 'Total percepciones', 'Total deducciones', 'Neto',
            ],
            $exportRows,
            [24, 12, 18, 14, 12, 14, 12, 16, 12, 14, 12, 14, 14, 14, 18, 18, 16, 16, 14],
            [$footer]
        );

        return response()->download($path, 'nomina_'.$period->start_date->format('Y-m-d').'.xlsx')->deleteFileAfterSend();
    }

    /**
     * Rows (one per payroll subject) and aggregated stats of the period.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, float|int>}
     */
    private function periodRows(PayrollPeriod $period): array
    {
        $rows = [];

        if ($period->isOpen()) {
            foreach ($this->calculator->payrollSubjects($period->start_date) as $user) {
                $result = $this->calculator->calculateFor($user, $period);
                $totals = $result['totals'];

                $rows[] = $this->buildRow(
                    $user->id,
                    $user->name,
                    $result['snapshot']['employee_number'],
                    $result['snapshot']['department'],
                    $totals,
                    null,
                    $result['snapshot']['termination_date'],
                );
            }
        } else {
            $payslips = $period->payslips()->with(['user:id,name', 'user.payrollProfile'])->get();

            foreach ($payslips as $payslip) {
                $rows[] = $this->buildRow(
                    $payslip->user_id,
                    $payslip->user?->name,
                    $payslip->employee_number,
                    $payslip->department,
                    [
                        'days_worked' => (float) $payslip->days_worked,
                        'days_paid' => (float) $payslip->days_paid,
                        'unpaid_days' => (float) $payslip->unpaid_days,
                        'late_minutes' => $payslip->late_minutes,
                        'late_discount' => (float) $payslip->late_discount,
                        'overtime_double_minutes' => $payslip->overtime_double_minutes,
                        'overtime_triple_minutes' => $payslip->overtime_triple_minutes,
                        'overtime_amount' => (float) $payslip->overtime_amount,
                        'holiday_days' => (float) $payslip->holiday_days,
                        'holiday_amount' => (float) $payslip->holiday_amount,
                        'vacation_days' => (float) $payslip->vacation_days,
                        'incapacity_days' => (float) $payslip->incapacity_days,
                        'incapacity_amount' => (float) $payslip->incapacity_amount,
                        'adjustments_earnings' => (float) $payslip->adjustments_earnings,
                        'adjustments_deductions' => (float) $payslip->adjustments_deductions,
                        'total_gross' => (float) $payslip->total_gross,
                        'total_deductions' => (float) $payslip->total_deductions,
                        'total_net' => (float) $payslip->total_net,
                    ],
                    $payslip->id,
                    $payslip->user?->payrollProfile?->termination_date?->toDateString(),
                );
            }
        }

        $stats = [
            'employees' => count($rows),
            'total_gross' => round(array_sum(array_column($rows, 'total_gross')), 2),
            'total_deductions' => round(array_sum(array_column($rows, 'total_deductions')), 2),
            'total_net' => round(array_sum(array_column($rows, 'total_net')), 2),
            'overtime_hours' => round(array_sum(array_column($rows, 'overtime_minutes')) / 60, 2),
            'late_minutes' => (int) array_sum(array_column($rows, 'late_minutes')),
            'unpaid_days' => round(array_sum(array_column($rows, 'unpaid_days')), 2),
            'days_paid' => round(array_sum(array_column($rows, 'days_paid')), 2),
        ];

        return [$rows, $stats];
    }

    /**
     * @param  array<string, mixed>  $totals
     * @return array<string, mixed>
     */
    private function buildRow(int $userId, ?string $name, ?string $employeeNumber, ?string $department, array $totals, ?int $payslipId, ?string $terminationDate = null): array
    {
        return [
            'user_id' => $userId,
            'name' => $name,
            'employee_number' => $employeeNumber,
            'department' => $department,
            'termination_date' => $terminationDate,
            'days_worked' => round((float) $totals['days_worked'], 2),
            'days_paid' => round((float) $totals['days_paid'], 2),
            'unpaid_days' => round((float) $totals['unpaid_days'], 2),
            'late_minutes' => (int) $totals['late_minutes'],
            'late_discount' => round((float) $totals['late_discount'], 2),
            'overtime_minutes' => (int) $totals['overtime_double_minutes'] + (int) $totals['overtime_triple_minutes'],
            'overtime_amount' => round((float) $totals['overtime_amount'], 2),
            'holiday_days' => round((float) $totals['holiday_days'], 2),
            'holiday_amount' => round((float) $totals['holiday_amount'], 2),
            'vacation_days' => round((float) $totals['vacation_days'], 2),
            'incapacity_days' => round((float) $totals['incapacity_days'], 2),
            'incapacity_amount' => round((float) $totals['incapacity_amount'], 2),
            'adjustments_earnings' => round((float) $totals['adjustments_earnings'], 2),
            'adjustments_deductions' => round((float) $totals['adjustments_deductions'], 2),
            'total_gross' => round((float) $totals['total_gross'], 2),
            'total_deductions' => round((float) $totals['total_deductions'], 2),
            'total_net' => round((float) $totals['total_net'], 2),
            'payslip_id' => $payslipId,
        ];
    }

    /**
     * Weekly schedule (current week) of a collaborator for the reference drawer.
     *
     * @return array<int, array<string, mixed>>
     */
    private function weeklyScheduleGrid(User $user): array
    {
        $weekStart = CarbonImmutable::today()->startOfWeek();
        $grid = $this->scheduleResolver->weeklyScheduleFor($user, $weekStart);
        $days = [];

        foreach (Shift::DAYS as $weekday => $label) {
            $schedule = $grid[$weekday] ?? null;
            $date = $weekStart->addDays($weekday - 1);

            $days[] = [
                'weekday' => $weekday,
                'label' => $label,
                'date' => $date->toDateString(),
                'shift' => $schedule?->shift->name,
                'start' => $schedule?->expectedStart()?->format('H:i'),
                'end' => $schedule?->expectedEnd()?->format('H:i'),
                'expected_minutes' => $schedule?->expectedDailyMinutes() ?? 0,
                'workday' => $schedule?->isWorkday() ?? false,
                'flexible' => $schedule?->shift->isFlexible() ?? false,
            ];
        }

        return $days;
    }

    private function authorizePeriods(Request $request): void
    {
        if (! $request->user()->can('payroll.periods.index')) {
            abort(403);
        }
    }

    private function authorizeClose(Request $request): void
    {
        if (! $request->user()->can('payroll.periods.close')) {
            abort(403);
        }
    }
}
