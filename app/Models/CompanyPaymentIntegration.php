<?php

namespace App\Models;

use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPaymentIntegration extends Model
{
    protected $fillable = [
        'company_id',
        'provider',
        'name',
        'api_key',
        'access_token',
        'refresh_token',
        'public_key',
        'webhook_secret',
        'account_identifier',
        'environment',
        'active',
        'default_for_memberships',
        'expires_at',
        'connected_at',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'active' => 'boolean',
            'default_for_memberships' => 'boolean',
            'api_key' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'public_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'provider' => PaymentProvider::class,
            'environment' => PaymentIntegrationEnvironment::class,
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function maskSecret(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        $len = strlen($plain);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return '****'.substr($plain, -4);
    }

    public function getApiKeyMaskedAttribute(): ?string
    {
        return self::maskSecret($this->api_key);
    }

    public function getAccessTokenMaskedAttribute(): ?string
    {
        return self::maskSecret($this->access_token);
    }

    public function getPublicKeyMaskedAttribute(): ?string
    {
        return self::maskSecret($this->public_key);
    }

    public function getRefreshTokenMaskedAttribute(): ?string
    {
        return self::maskSecret($this->refresh_token);
    }

    public function getWebhookSecretMaskedAttribute(): ?string
    {
        return self::maskSecret($this->webhook_secret);
    }

    public function usesMercadoPagoOAuth(): bool
    {
        return $this->provider === PaymentProvider::MercadoPago;
    }

    public function isConnected(): bool
    {
        return $this->active
            && $this->status === 'connected'
            && filled($this->access_token);
    }

    public function tokenExpiresSoon(int $days = 10): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->lte(now()->addDays($days));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function mergeMetadata(array $metadata): void
    {
        $current = $this->metadata ?? [];
        $this->metadata = array_merge($current, $metadata);
        $this->save();
    }
}
