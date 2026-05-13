<?php

namespace App\Services;

use App\Models\ClientCommercialHistory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ClientRecommendationService
{
    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_DUE = 'due';
    public const STATUS_OVERDUE = 'overdue';

    public const UPCOMING_WINDOW_DAYS = 7;
    public const OVERDUE_WINDOW_DAYS = 30;

    /**
     * Build the recommendation list for a given client.
     *
     * @return Collection<int, array{
     *     item_type:string,
     *     item_id:int|null,
     *     item_name:string,
     *     last_occurrence_date:CarbonImmutable,
     *     days_since_last:int,
     *     next_recommendation_date:CarbonImmutable,
     *     status:string,
     *     message:string,
     *     history_id:int,
     *     professional_id:int|null,
     * }>
     */
    public function getRecommendationsForClient(
        int $companyId,
        int $clientId,
        ?CarbonInterface $referenceDate = null,
    ): Collection {
        $reference = $referenceDate
            ? CarbonImmutable::instance($referenceDate)->startOfDay()
            : CarbonImmutable::now()->startOfDay();

        $entries = ClientCommercialHistory::query()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->whereNotNull('next_recommendation_date')
            ->orderByDesc('occurred_at')
            ->get();

        $latestByItem = [];

        foreach ($entries as $entry) {
            if ($entry->isCanceled()) {
                continue;
            }

            $key = $entry->item_type.':'.($entry->item_id ?? 'null').':'.strtolower($entry->item_name_snapshot);

            if (! isset($latestByItem[$key])) {
                $latestByItem[$key] = $entry;
            }
        }

        $recommendations = [];

        foreach ($latestByItem as $entry) {
            $next = CarbonImmutable::parse((string) $entry->next_recommendation_date)->startOfDay();
            $occurred = CarbonImmutable::instance($entry->occurred_at);
            $diffDays = $reference->diffInDays($next, false);

            if ($diffDays > self::UPCOMING_WINDOW_DAYS) {
                continue;
            }

            $status = $this->classify($diffDays);
            $daysSinceLast = (int) $occurred->startOfDay()->diffInDays($reference, false);

            $recommendations[] = [
                'item_type' => (string) $entry->item_type,
                'item_id' => $entry->item_id,
                'item_name' => (string) $entry->item_name_snapshot,
                'last_occurrence_date' => $occurred,
                'days_since_last' => max(0, $daysSinceLast),
                'next_recommendation_date' => $next,
                'status' => $status,
                'message' => $this->buildMessage($entry->item_type, $entry->item_name_snapshot, $status, $daysSinceLast, $diffDays),
                'history_id' => (int) $entry->id,
                'professional_id' => $entry->professional_id,
            ];
        }

        return collect($recommendations)->sortBy([
            ['status', 'asc'],
            ['next_recommendation_date', 'asc'],
        ])->values();
    }

    /**
     * Classify the entry status based on how far away the next recommendation date is from reference.
     */
    private function classify(int $daysFromReferenceToNext): string
    {
        if ($daysFromReferenceToNext >= 1 && $daysFromReferenceToNext <= self::UPCOMING_WINDOW_DAYS) {
            return self::STATUS_UPCOMING;
        }

        if ($daysFromReferenceToNext < -self::OVERDUE_WINDOW_DAYS) {
            return self::STATUS_OVERDUE;
        }

        return self::STATUS_DUE;
    }

    private function buildMessage(string $itemType, string $itemName, string $status, int $daysSinceLast, int $diffDays): string
    {
        $type = $itemType === ClientCommercialHistory::ITEM_TYPE_PRODUCT
            ? 'comprou'
            : 'fez';

        return match ($status) {
            self::STATUS_UPCOMING => sprintf(
                'Cliente está próximo do prazo de %s de %s.',
                $itemType === ClientCommercialHistory::ITEM_TYPE_PRODUCT ? 'recompra' : 'retorno',
                $itemName,
            ),
            self::STATUS_OVERDUE => sprintf(
                'Cliente %s %s há %d dias. Recomendação atrasada — vale uma abordagem.',
                $type,
                $itemName,
                $daysSinceLast,
            ),
            default => sprintf(
                'Cliente %s %s há %d dias. %s',
                $type,
                $itemName,
                $daysSinceLast,
                $itemType === ClientCommercialHistory::ITEM_TYPE_PRODUCT
                    ? 'Sugira recompra.'
                    : 'Está no prazo para novo atendimento.',
            ),
        };
    }

    /**
     * Human label for a recommendation status.
     */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_UPCOMING => 'Próximo do prazo',
            self::STATUS_OVERDUE => 'Atrasado',
            self::STATUS_DUE => 'Recomendar agora',
            default => ucfirst($status),
        };
    }

    /**
     * Tailwind classes for the recommendation badge.
     */
    public static function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            self::STATUS_UPCOMING => 'border-sky-400/30 bg-sky-500/10 text-sky-100',
            self::STATUS_OVERDUE => 'border-rose-400/30 bg-rose-500/10 text-rose-100',
            self::STATUS_DUE => 'border-amber-400/30 bg-amber-500/10 text-amber-100',
            default => 'border-white/10 bg-white/5 text-white',
        };
    }
}
