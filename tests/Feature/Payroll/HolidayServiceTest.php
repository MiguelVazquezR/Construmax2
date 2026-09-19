<?php

namespace Tests\Feature\Payroll;

use App\Models\Holiday;
use App\Services\Payroll\HolidayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayServiceTest extends TestCase
{
    use RefreshDatabase;

    private HolidayService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(HolidayService::class);
    }

    public function test_official_holidays_of_2026_match_the_lft_rules(): void
    {
        $dates = collect($this->service->officialHolidaysFor(2026))
            ->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->all();

        $this->assertSame([
            '2026-01-01', // Año nuevo
            '2026-02-02', // Primer lunes de febrero
            '2026-03-16', // Tercer lunes de marzo
            '2026-05-01', // Día del trabajo
            '2026-09-16', // Independencia
            '2026-11-16', // Tercer lunes de noviembre
            '2026-12-25', // Navidad
        ], $dates);
    }

    public function test_transmission_day_is_added_every_six_years(): void
    {
        $dates2030 = collect($this->service->officialHolidaysFor(2030))
            ->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->all();

        $dates2027 = collect($this->service->officialHolidaysFor(2027))
            ->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->all();

        $this->assertContains('2030-10-01', $dates2030);
        $this->assertNotContains('2027-10-01', $dates2027);
    }

    public function test_sync_year_is_idempotent(): void
    {
        $this->assertSame(7, $this->service->syncYear(2026));
        $this->assertSame(0, $this->service->syncYear(2026));
        $this->assertSame(7, Holiday::query()->forYear(2026)->count());
    }

    public function test_sync_year_preserves_manual_rows(): void
    {
        Holiday::create([
            'date' => '2026-01-01',
            'name' => 'Fiesta local',
            'year' => 2026,
            'source' => Holiday::SOURCE_MANUAL,
        ]);

        $this->assertSame(6, $this->service->syncYear(2026));

        $holiday = Holiday::query()->whereDate('date', '2026-01-01')->first();

        $this->assertSame('Fiesta local', $holiday->name);
        $this->assertSame(Holiday::SOURCE_MANUAL, $holiday->source);
    }

    public function test_for_date_and_is_holiday(): void
    {
        $this->service->syncYear(2026);

        $this->assertSame('Día de la independencia', $this->service->forDate('2026-09-16')?->name);
        $this->assertTrue($this->service->isHoliday('2026-09-16'));
        $this->assertFalse($this->service->isHoliday('2026-09-17'));
        $this->assertNull($this->service->forDate('2026-09-17'));
    }
}
