<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\CustomerBlock;
use App\Models\CustomerNoShow;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CustomerBlockService
{
    public const DEFAULT_NO_SHOW_HOURS = 24;

    public function recordNoShow(Appointment $appointment, ?User $actor, ?string $reason = null): CustomerNoShow
    {
        return DB::transaction(function () use ($appointment, $actor, $reason): CustomerNoShow {
            $noShow = CustomerNoShow::query()->create([
                'company_id' => $appointment->company_id,
                'client_id' => $appointment->client_id,
                'appointment_id' => $appointment->id,
                'recorded_by' => $actor?->id,
                'reason' => $reason,
                'occurred_at' => now(),
            ]);

            $ends = now()->addHours(self::DEFAULT_NO_SHOW_HOURS);
            $this->ensureNoShowBlock(
                (int) $appointment->company_id,
                (int) $appointment->client_id,
                $ends,
                $reason ?? 'Falta ao agendamento',
                $actor?->id,
            );

            return $noShow;
        });
    }

    public function ensureNoShowBlock(int $companyId, int $clientId, CarbonInterface $endsAt, ?string $reason, ?int $createdBy): void
    {
        $active = $this->getActiveBlockForClient($companyId, $clientId);

        if ($active && $active->type === CustomerBlock::TYPE_NO_SHOW) {
            if ($active->ends_at === null || $active->ends_at->lt($endsAt)) {
                $active->update(['ends_at' => $endsAt]);
            }

            return;
        }

        if ($active) {
            return;
        }

        CustomerBlock::query()->create([
            'company_id' => $companyId,
            'client_id' => $clientId,
            'type' => CustomerBlock::TYPE_NO_SHOW,
            'reason' => $reason,
            'starts_at' => now(),
            'ends_at' => $endsAt,
            'active' => true,
            'created_by' => $createdBy,
        ]);
    }

    public function blockCustomer(
        int $companyId,
        int $clientId,
        string $type,
        ?string $reason,
        CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
        ?int $createdBy,
    ): CustomerBlock {
        $existing = $this->getActiveBlockForClient($companyId, $clientId);

        if ($existing) {
            return $existing;
        }

        return CustomerBlock::query()->create([
            'company_id' => $companyId,
            'client_id' => $clientId,
            'type' => $type,
            'reason' => $reason,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'active' => true,
            'created_by' => $createdBy,
        ]);
    }

    public function isBlocked(Client $client): bool
    {
        return $this->getActiveBlockForClient((int) $client->company_id, $client->id) !== null;
    }

    public function getActiveBlock(Client $client): ?CustomerBlock
    {
        return $this->getActiveBlockForClient((int) $client->company_id, $client->id);
    }

    public function getActiveBlockForClient(int $companyId, int $clientId): ?CustomerBlock
    {
        /** @var CustomerBlock|null $block */
        $block = CustomerBlock::query()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->where('active', true)
            ->where('starts_at', '<=', now())
            ->where(function ($q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->orderByDesc('starts_at')
            ->first();

        return $block;
    }

    public function unblock(CustomerBlock $block, User $actor): void
    {
        $block->update([
            'active' => false,
            'removed_by' => $actor->id,
            'removed_at' => now(),
        ]);
    }
}
