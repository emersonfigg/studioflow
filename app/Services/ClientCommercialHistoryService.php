<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClientCommercialHistory;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ClientCommercialHistoryService
{
    /**
     * Record commercial history entries for each product sold in a closed sale.
     */
    public function recordProductSale(ProductSale $sale, ?ServiceOrder $order = null): void
    {
        if (! $sale->client_id) {
            return;
        }

        $sale->loadMissing(['items.product']);

        $occurredAt = $sale->sold_at ?? $sale->created_at ?? CarbonImmutable::now();
        $occurredAt = $occurredAt instanceof CarbonInterface
            ? CarbonImmutable::instance($occurredAt)
            : CarbonImmutable::parse((string) $occurredAt);

        $appointmentId = $sale->appointment_id ?? $order?->appointment_id;
        $fallbackProfessionalId = $sale->user_id ?? $order?->professional_id;
        $source = $appointmentId
            ? ClientCommercialHistory::SOURCE_APPOINTMENT
            : ClientCommercialHistory::SOURCE_PDV;

        DB::transaction(function () use ($sale, $appointmentId, $fallbackProfessionalId, $source, $occurredAt): void {
            foreach ($sale->items as $item) {
                /** @var Product|null $product */
                $product = $item->product;

                $recommendationDays = $product?->recommended_repurchase_days
                    ? (int) $product->recommended_repurchase_days
                    : null;

                $nextDate = $recommendationDays !== null
                    ? $occurredAt->copy()->startOfDay()->addDays($recommendationDays)->toDateString()
                    : null;

                ClientCommercialHistory::query()->create([
                    'company_id' => $sale->company_id,
                    'client_id' => $sale->client_id,
                    'item_type' => ClientCommercialHistory::ITEM_TYPE_PRODUCT,
                    'item_id' => $item->product_id,
                    'item_name_snapshot' => $product?->name ?? 'Produto',
                    'quantity' => (float) $item->quantity,
                    'unit_price_snapshot' => (float) $item->unit_price,
                    'total_amount_snapshot' => (float) $item->total_price,
                    'professional_id' => $item->seller_id ?? $fallbackProfessionalId,
                    'sale_id' => $sale->id,
                    'sale_item_id' => $item->id,
                    'appointment_id' => $appointmentId,
                    'occurred_at' => $occurredAt,
                    'recommendation_days' => $recommendationDays,
                    'next_recommendation_date' => $nextDate,
                    'source' => $source,
                ]);
            }
        });
    }

    /**
     * Record commercial history entries for each service performed in a closed order.
     * Used when an appointment / service order is concluded.
     */
    public function recordServiceOrderServices(ServiceOrder $order, ?CarbonInterface $occurredAt = null): void
    {
        if (! $order->client_id) {
            return;
        }

        $order->loadMissing(['items.service', 'appointment']);

        $serviceItems = $order->items->where('type', ServiceOrderItem::TYPE_SERVICE);

        if ($serviceItems->isEmpty()) {
            return;
        }

        $appointmentId = $order->appointment_id;
        $appointmentRow = $order->appointment;

        $eventAt = $occurredAt
            ?? $appointmentRow?->start_time
            ?? $order->closed_at
            ?? CarbonImmutable::now();
        $eventAt = $eventAt instanceof CarbonInterface
            ? CarbonImmutable::instance($eventAt)
            : CarbonImmutable::parse((string) $eventAt);

        DB::transaction(function () use ($order, $serviceItems, $appointmentId, $eventAt): void {
            foreach ($serviceItems as $item) {
                if (! $item->service_id) {
                    continue;
                }

                if ($appointmentId !== null) {
                    $duplicate = ClientCommercialHistory::query()
                        ->where('company_id', $order->company_id)
                        ->where('item_type', ClientCommercialHistory::ITEM_TYPE_SERVICE)
                        ->where('item_id', $item->service_id)
                        ->where('appointment_id', $appointmentId)
                        ->exists();

                    if ($duplicate) {
                        continue;
                    }
                }

                /** @var Service|null $service */
                $service = $item->service;

                $recommendationDays = $service?->recommended_return_days
                    ? (int) $service->recommended_return_days
                    : null;

                $nextDate = $recommendationDays !== null
                    ? $eventAt->copy()->startOfDay()->addDays($recommendationDays)->toDateString()
                    : null;

                ClientCommercialHistory::query()->create([
                    'company_id' => $order->company_id,
                    'client_id' => $order->client_id,
                    'item_type' => ClientCommercialHistory::ITEM_TYPE_SERVICE,
                    'item_id' => $item->service_id,
                    'item_name_snapshot' => $service?->name ?? ($item->description ?? 'Serviço'),
                    'quantity' => (float) ($item->quantity ?: 1),
                    'unit_price_snapshot' => (float) $item->unit_price,
                    'total_amount_snapshot' => (float) $item->total_price,
                    'professional_id' => $item->professional_id ?? $order->professional_id,
                    'sale_id' => null,
                    'sale_item_id' => null,
                    'appointment_id' => $appointmentId,
                    'occurred_at' => $eventAt,
                    'recommendation_days' => $recommendationDays,
                    'next_recommendation_date' => $nextDate,
                    'source' => $appointmentId
                        ? ClientCommercialHistory::SOURCE_APPOINTMENT
                        : ClientCommercialHistory::SOURCE_PDV,
                ]);
            }
        });
    }

    /**
     * Flag every history entry tied to a sale or appointment as canceled so
     * recommendations stop suggesting it.
     */
    public function markSaleCanceled(int $saleId): void
    {
        $entries = ClientCommercialHistory::query()
            ->where('sale_id', $saleId)
            ->get();

        foreach ($entries as $entry) {
            $metadata = $entry->metadata ?? [];
            $metadata['status'] = 'canceled';
            $metadata['canceled_at'] = CarbonImmutable::now()->toIso8601String();
            $entry->metadata = $metadata;
            $entry->save();
        }
    }

    public function markAppointmentCanceled(Appointment $appointment): void
    {
        $entries = ClientCommercialHistory::query()
            ->where('appointment_id', $appointment->id)
            ->get();

        foreach ($entries as $entry) {
            $metadata = $entry->metadata ?? [];
            $metadata['status'] = 'canceled';
            $metadata['canceled_at'] = CarbonImmutable::now()->toIso8601String();
            $entry->metadata = $metadata;
            $entry->save();
        }
    }
}
