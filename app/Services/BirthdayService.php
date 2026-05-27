<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BirthdayService
{
    public const DEFAULT_MESSAGE = 'Olá, {nome}! A equipe {empresa} deseja um feliz aniversário! Que seu dia seja especial!';

    /**
     * @return Collection<int, Client>
     */
    public function clientsWithBirthday(int $companyId, string $range = 'day', ?Carbon $reference = null): Collection
    {
        $today = ($reference ?? now())->copy()->startOfDay();

        return Client::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->whereNotNull('birthday')
            ->when($range === 'month', fn (Builder $query) => $query->whereMonth('birthday', $today->month))
            ->when($range === 'day', fn (Builder $query) => $query->whereMonth('birthday', $today->month)->whereDay('birthday', $today->day))
            ->when($range === 'week', function (Builder $query) use ($today): void {
                $days = collect(range(0, 6))->map(fn (int $offset) => $today->copy()->addDays($offset));

                $query->where(function (Builder $inner) use ($days): void {
                    foreach ($days as $day) {
                        $inner->orWhere(fn (Builder $q) => $q->whereMonth('birthday', $day->month)->whereDay('birthday', $day->day));
                    }
                });
            })
            ->orderBy('birthday')
            ->orderBy('name')
            ->get();
    }

    public function messageTemplate(Company $company): string
    {
        $stored = trim((string) ($company->birthday_congratulations_message ?? ''));

        return $stored !== '' ? $stored : self::DEFAULT_MESSAGE;
    }

    public function resolveMessage(Company $company, Client $client, ?string $template = null): string
    {
        $template = $template ?? $this->messageTemplate($company);

        return str_replace(
            ['{nome}', '{empresa}'],
            [(string) $client->name, (string) $company->name],
            $template
        );
    }

    public function whatsAppUrl(Client $client, string $message): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $client->phone);

        if ($digits === '') {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }
}
