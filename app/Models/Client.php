<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Client $client): void {
            if (! $client->isDirty('active')) {
                $client->active = true;
            }

            $client->cpf_normalized = self::normalizeCpf($client->cpf);

            if (! $client->client_code && $client->company_id) {
                $client->client_code = self::nextClientCodeForCompany((int) $client->company_id);
            }
        });

        static::updating(function (Client $client): void {
            if ($client->isDirty('cpf')) {
                $client->cpf_normalized = self::normalizeCpf($client->cpf);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'client_code',
        'active',
        'name',
        'phone',
        'cpf',
        'cpf_normalized',
        'email',
        'google_id',
        'avatar',
        'email_verified_at',
        'birthday',
        'notes',
        'last_visit_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'birthday' => 'date',
            'email_verified_at' => 'datetime',
            'last_visit_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Client>  $query
     * @return Builder<Client>
     */
    public function scopeActive(Builder $query): Builder
    {
        if (! self::supportsActiveFlag()) {
            return $query;
        }

        return $query->where('clients.active', true);
    }

    public static function supportsActiveFlag(): bool
    {
        return Schema::hasColumn('clients', 'active');
    }

    public function isOperationallyActive(): bool
    {
        if (! self::supportsActiveFlag()) {
            return true;
        }

        return (bool) $this->active;
    }

    public function hasOperationalHistory(): bool
    {
        return $this->appointments()->exists()
            || $this->serviceOrders()->exists()
            || $this->payments()->exists()
            || $this->productSales()->exists();
    }

    public static function normalizeCpf(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? ''));

        if ($digits === '' || strlen($digits) !== 11) {
            return null;
        }

        return $digits;
    }

    private static function nextClientCodeForCompany(int $companyId): string
    {
        return DB::transaction(function () use ($companyId): string {
            $company = Company::query()
                ->whereKey($companyId)
                ->lockForUpdate()
                ->firstOrFail();

            $next = ((int) ($company->client_code_counter ?? 0)) + 1;

            $company->update(['client_code_counter' => $next]);

            return 'C'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Get the company that owns the client.
     *
     * @return BelongsTo<Company, Client>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the appointments for the client.
     *
     * @return HasMany<Appointment>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get the payments for the client.
     *
     * @return HasMany<Payment>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the product sales for the client.
     *
     * @return HasMany<ProductSale>
     */
    public function productSales(): HasMany
    {
        return $this->hasMany(ProductSale::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    /**
     * Get the commercial history entries for the client.
     *
     * @return HasMany<ClientCommercialHistory>
     */
    public function commercialHistories(): HasMany
    {
        return $this->hasMany(ClientCommercialHistory::class);
    }
}
