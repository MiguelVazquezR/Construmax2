<?php

namespace App\Services\Payroll;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class HolidayService
{
    /**
     * First year of the six-year presidential transmission cycle that fell on
     * October 1st (LFT art. 74, fracción VIII).
     */
    public const TRANSMISSION_BASE_YEAR = 2024;

    /**
     * Mandatory rest days of the Federal Labor Law for a given year.
     *
     * @return array<int, array{date: CarbonImmutable, name: string}>
     */
    public function officialHolidaysFor(int $year): array
    {
        $holidays = [
            ['date' => CarbonImmutable::create($year, 1, 1), 'name' => 'Año nuevo'],
            ['date' => CarbonImmutable::parse("first monday of february {$year}"), 'name' => 'Día de la Constitución (primer lunes de febrero)'],
            ['date' => CarbonImmutable::parse("third monday of march {$year}"), 'name' => 'Natalicio de Benito Juárez (tercer lunes de marzo)'],
            ['date' => CarbonImmutable::create($year, 5, 1), 'name' => 'Día del trabajo'],
            ['date' => CarbonImmutable::create($year, 9, 16), 'name' => 'Día de la independencia'],
            ['date' => CarbonImmutable::parse("third monday of november {$year}"), 'name' => 'Revolución mexicana (tercer lunes de noviembre)'],
            ['date' => CarbonImmutable::create($year, 12, 25), 'name' => 'Navidad'],
        ];

        if (($year - self::TRANSMISSION_BASE_YEAR) % 6 === 0) {
            $holidays[] = [
                'date' => CarbonImmutable::create($year, 10, 1),
                'name' => 'Transmisión del Poder Ejecutivo Federal',
            ];
        }

        usort($holidays, fn ($a, $b) => $a['date']->timestamp <=> $b['date']->timestamp);

        return $holidays;
    }

    /**
     * Create the official holidays of a year without touching existing rows
     * (manual adjustments are preserved). Returns the number of created rows.
     */
    public function syncYear(int $year): int
    {
        $created = 0;

        foreach ($this->officialHolidaysFor($year) as $holiday) {
            $exists = Holiday::query()
                ->whereDate('date', $holiday['date']->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            Holiday::create([
                'date' => $holiday['date']->toDateString(),
                'name' => $holiday['name'],
                'year' => $year,
                'source' => Holiday::SOURCE_LFT,
                'is_mandatory' => true,
                'apply_extra_pay' => true,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Holiday registered on a date, when any.
     */
    public function forDate(CarbonInterface|string $date): ?Holiday
    {
        $value = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return Holiday::query()->whereDate('date', $value)->first();
    }

    public function isHoliday(CarbonInterface|string $date): bool
    {
        return $this->forDate($date) !== null;
    }
}
