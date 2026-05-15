<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MembershipPlanService extends Pivot
{
    protected $table = 'membership_plan_services';

    public $incrementing = true;

    protected $fillable = [
        'company_id',
        'membership_plan_id',
        'service_id',
        'quantity_per_cycle',
        'discount_percent',
        'included',
    ];

    protected function casts(): array
    {
        return [
            'quantity_per_cycle' => 'integer',
            'discount_percent' => 'decimal:2',
            'included' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
