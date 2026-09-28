<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreHolidayRequest;
use App\Models\Holiday;
use App\Services\Payroll\HolidayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function __construct(
        private readonly HolidayService $holidayService,
    ) {}

    public function index(Request $request): Response
    {
        if (! $request->user()->can('payroll.holidays.manage')) {
            abort(403);
        }

        // Keep the current and next year generated (idempotent).
        $this->holidayService->syncYear((int) now()->year);
        $this->holidayService->syncYear((int) now()->addYear()->year);

        $holidays = Holiday::query()
            ->orderBy('date')
            ->get();

        $years = $holidays
            ->pluck('year')
            ->unique()
            ->sortDesc()
            ->values();

        return Inertia::render('Payroll/Holidays/Index', [
            'holidays' => $holidays,
            'years' => $years,
        ]);
    }

    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        Holiday::create([
            ...$request->safe()->only(['date', 'name', 'is_mandatory', 'apply_extra_pay', 'notes']),
            'year' => (int) substr((string) $request->input('date'), 0, 4),
            'source' => Holiday::SOURCE_MANUAL,
        ]);

        return back()->with('success', 'Día festivo registrado.');
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        if (! $request->user()->can('payroll.holidays.manage')) {
            abort(403);
        }

        $holiday->delete();

        return back()->with('success', 'Día festivo eliminado.');
    }

    /**
     * Generate the official LFT holidays of a year.
     */
    public function sync(Request $request): RedirectResponse
    {
        if (! $request->user()->can('payroll.holidays.manage')) {
            abort(403);
        }

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $created = $this->holidayService->syncYear((int) $validated['year']);

        return back()->with(
            'success',
            $created > 0
                ? "Se generaron {$created} días festivos oficiales."
                : 'El año ya tenía todos los días festivos oficiales registrados.'
        );
    }
}
