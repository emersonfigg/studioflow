<?php

namespace App\Console\Commands;

use App\Models\CustomerMembership;
use App\Services\LegacyMembershipClassifier;
use Illuminate\Console\Command;

class InspectLegacyMemberships extends Command
{
    protected $signature = 'memberships:inspect-legacy {company : ID da empresa}';

    protected $description = 'Inventário JSON somente leitura; não altera contratos nem autoriza benefícios.';

    public function handle(LegacyMembershipClassifier $classifier): int
    {
        $companyId = filter_var($this->argument('company'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($companyId === false) {
            $this->error('Informe um ID de empresa válido.');

            return self::INVALID;
        }

        CustomerMembership::query()->where('company_id', $companyId)->whereNull('legacy_classified_at')
            ->select('id')->chunkById(100, function ($memberships) use ($classifier, $companyId) {
                foreach ($memberships as $membership) {
                    $result = $classifier->classify($companyId, $membership->id);
                    $this->line(json_encode(['classification' => $result['classification']->value, 'evidence' => $result['evidence']], JSON_THROW_ON_ERROR));
                }
            });

        return self::SUCCESS;
    }
}
