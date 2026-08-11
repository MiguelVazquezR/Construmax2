<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use Illuminate\Support\Str;

class TicketDuplicateService
{
    /**
     * Stop words that carry little distinguishing meaning for project
     * names. Stored normalized (ascii, lowercase) to match the tokenizer.
     */
    private const STOP_WORDS = [
        'a', 'el', 'la', 'los', 'las', 'de', 'del', 'en', 'y', 'para', 'por',
        'con', 'un', 'una', 'unos', 'unas', 'al', 'lo', 'que', 'se', 'su',
        'general', 'generales', 'servicio', 'servicios', 'trabajo', 'trabajos',
        'proyecto', 'proyectos', 'mantenimiento',
    ];

    private const STRONG_THRESHOLD = 85;
    private const FUZZY_THRESHOLD = 60;
    private const RECENT_LIMIT = 15;
    private const DISTINCTIVE_TOKEN_MIN_LENGTH = 8;

    /**
     * Detect potential duplicates by comparing the candidate ticket against
     * the most recent tickets of the same customer.
     *
     * @param array $data Keys: customer_id, name, report_number, service_type, branch_id, ignore_id
     * @return array{tickets: array, strong_duplicates: array}
     */
    public function check(array $data): array
    {
        $customerId = $data['customer_id'] ?? null;
        $ignoreId = $data['ignore_id'] ?? null;

        if (! $customerId) {
            return ['tickets' => [], 'strong_duplicates' => []];
        }

        $name = $this->normalize($data['name'] ?? '');
        $reportNumber = $this->normalize($data['report_number'] ?? '');
        $serviceType = $this->normalize($data['service_type'] ?? '');
        $branchId = $data['branch_id'] ?? null;

        $tickets = Ticket::where('customer_id', $customerId)
            ->with('branch:id,branch_name,unit,region,country')
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->orderByDesc('created_at')
            ->limit(self::RECENT_LIMIT)
            ->get();

        $hasQuery = $name !== '' || $reportNumber !== '';

        $results = $tickets->map(function (Ticket $ticket) use ($name, $reportNumber, $serviceType, $branchId, $hasQuery) {
            return $this->buildResult($ticket, $name, $reportNumber, $serviceType, $branchId, $hasQuery);
        });

        // Only sort by similarity when there is a query; otherwise keep date order.
        if ($hasQuery) {
            $results = $results->sortByDesc('similarity')->values();
        }

        // Cancelled tickets never trip the strong warning: a repeated order
        // after a cancellation is legitimate.
        $strongDuplicates = $results
            ->filter(fn (array $item) => $item['match_type'] === 'strong'
                && ($item['ticket']['status'] ?? null) !== 'Cancelado')
            ->map(fn (array $item) => $item['ticket'])
            ->values();

        return [
            'tickets' => $results->values(),
            'strong_duplicates' => $strongDuplicates,
        ];
    }

    private function buildResult(Ticket $ticket, string $name, string $reportNumber, string $serviceType, $branchId, bool $hasQuery): array
    {
        $ticketName = $this->normalize($ticket->name);
        $ticketReport = $this->normalize($ticket->report_number ?? '');
        $ticketServiceType = $this->normalize($ticket->service_type ?? '');
        $ticketBranchId = $ticket->customer_branch_id;

        // An exact report number always wins, regardless of the name.
        if ($reportNumber !== '' && $reportNumber === $ticketReport) {
            return $this->result($ticket, 100.0, 'strong');
        }

        if (! $hasQuery || $name === '') {
            return $this->result($ticket, 0.0, 'recent');
        }

        $similarity = $this->nameSimilarity($name, $ticketName);

        // Bonuses for shared context.
        if ($serviceType !== '' && $serviceType === $ticketServiceType) {
            $similarity = min(100.0, $similarity + 6.0);
        }

        if ($branchId && $ticketBranchId && (int) $branchId === (int) $ticketBranchId) {
            $similarity = min(100.0, $similarity + 4.0);
        }

        $matchType = match (true) {
            $similarity >= self::STRONG_THRESHOLD => 'strong',
            $similarity >= self::FUZZY_THRESHOLD => 'fuzzy',
            default => 'recent',
        };

        return $this->result($ticket, $similarity, $matchType);
    }

    /**
     * Flexible similarity between two normalized project names.
     *
     * Combines:
     *  - Jaccard over tokens (detects reordering and exact overlap)
     *  - Token-to-token Levenshtein ratio (detects typos and plurals)
     *  - A bonus when sharing a long distinctive token (>= 8 chars)
     */
    private function nameSimilarity(string $name, string $ticketName): float
    {
        $tokens = $this->tokens($name);
        $ticketTokens = $this->tokens($ticketName);

        if (empty($tokens) || empty($ticketTokens)) {
            return 0.0;
        }

        // The same tokens in a different order are the same project.
        if ($tokens === $ticketTokens) {
            return 100.0;
        }

        $jaccard = $this->jaccard($tokens, $ticketTokens);
        $levenshtein = $this->levenshteinRatio($tokens, $ticketTokens);

        $similarity = ($jaccard * 0.6 + $levenshtein * 0.4) * 100.0;

        $distinctiveTokens = array_filter($tokens, fn (string $t) => mb_strlen($t) >= self::DISTINCTIVE_TOKEN_MIN_LENGTH);
        $distinctiveTicketTokens = array_filter($ticketTokens, fn (string $t) => mb_strlen($t) >= self::DISTINCTIVE_TOKEN_MIN_LENGTH);

        if (! empty($distinctiveTokens) && ! empty($distinctiveTicketTokens) && array_intersect($distinctiveTokens, $distinctiveTicketTokens)) {
            $similarity = min(100.0, $similarity + 8.0);
        }

        return max(0.0, min(100.0, $similarity));
    }

    /**
     * Jaccard index: intersection / union of the tokens.
     */
    private function jaccard(array $a, array $b): float
    {
        $union = array_unique(array_merge($a, $b));
        if (empty($union)) {
            return 0.0;
        }

        return count(array_intersect($a, $b)) / count($union);
    }

    /**
     * Levenshtein ratio: for each token, find the closest match in the
     * other set (allowing reordering and typos), averaging the best match
     * of each token.
     */
    private function levenshteinRatio(array $a, array $b): float
    {
        $scores = [];

        foreach ($a as $tokenA) {
            $best = 0.0;
            foreach ($b as $tokenB) {
                $ratio = $this->tokenLevenshteinRatio($tokenA, $tokenB);
                if ($ratio > $best) {
                    $best = $ratio;
                }
            }
            $scores[] = $best;
        }

        if (empty($scores)) {
            return 0.0;
        }

        return array_sum($scores) / count($scores);
    }

    private function tokenLevenshteinRatio(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $maxLen = max(mb_strlen($a), mb_strlen($b));
        if ($maxLen === 0) {
            return 1.0;
        }

        return 1 - (levenshtein($a, $b) / $maxLen);
    }

    private function tokens(string $normalized): array
    {
        $parts = preg_split('/\s+/', $normalized) ?: [];

        return array_values(array_filter($parts, function (string $token) {
            return $token !== '' && ! in_array($token, self::STOP_WORDS, true);
        }));
    }

    private function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }

    private function result(Ticket $ticket, float $similarity, string $matchType): array
    {
        return [
            'ticket' => [
                'id' => $ticket->id,
                'folio' => $ticket->folio,
                'name' => $ticket->name,
                'service_type' => $ticket->service_type,
                'report_number' => $ticket->report_number,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'created_at' => $ticket->created_at?->toDateTimeString(),
                'branch' => $ticket->branch ? [
                    'id' => $ticket->branch->id,
                    'label' => trim(implode(' - ', array_filter([$ticket->branch->branch_name, $ticket->branch->unit])))
                        . ($ticket->branch->region ? " ({$ticket->branch->region})" : ''),
                ] : null,
            ],
            'similarity' => round($similarity, 1),
            'match_type' => $matchType,
        ];
    }
}