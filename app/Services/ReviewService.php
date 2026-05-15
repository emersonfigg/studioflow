<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReviewService
{
    public function createPendingReview(Appointment $appointment): AppointmentReview
    {
        $review = AppointmentReview::query()->firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'company_id' => $appointment->company_id,
                'client_id' => $appointment->client_id,
                'professional_id' => $appointment->user_id,
                'token' => $this->generateToken(),
                'private_feedback' => true,
            ],
        );

        if ($review->token === null) {
            $review->update(['token' => $this->generateToken()]);
        }

        return $review->fresh();
    }

    public function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (AppointmentReview::query()->where('token', $token)->exists());

        return $token;
    }

    /**
     * @param  array{rating: int, comment?: ?string, service_quality_rating?: ?int, punctuality_rating?: ?int, environment_rating?: ?int}  $data
     */
    public function submitReview(string $token, array $data): AppointmentReview
    {
        /** @var AppointmentReview $review */
        $review = AppointmentReview::query()->where('token', $token)->firstOrFail();

        if ($review->submitted_at !== null) {
            return $review;
        }

        $review->update([
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'service_quality_rating' => $data['service_quality_rating'] ?? null,
            'punctuality_rating' => $data['punctuality_rating'] ?? null,
            'environment_rating' => $data['environment_rating'] ?? null,
            'submitted_at' => now(),
        ]);

        return $review->fresh();
    }

    public function getProfessionalAverageRating(int $companyId, ?int $professionalId = null): float
    {
        $query = AppointmentReview::query()
            ->where('company_id', $companyId)
            ->whereNotNull('submitted_at')
            ->whereNotNull('rating');

        if ($professionalId !== null) {
            $query->where('professional_id', $professionalId);
        }

        return round((float) $query->avg('rating'), 2);
    }

    /**
     * @return array{avg_rating: float, count: int, low_count: int}
     */
    public function getCompanyReviewSummary(int $companyId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $base = AppointmentReview::query()
            ->where('company_id', $companyId)
            ->whereNotNull('submitted_at')
            ->whereNotNull('rating')
            ->when($from, fn (Builder $q) => $q->where('submitted_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('submitted_at', '<=', $to));

        $count = (clone $base)->count();
        $avg = $count > 0 ? round((float) (clone $base)->avg('rating'), 2) : 0.0;
        $lowCount = (clone $base)->where('rating', '<=', 2)->count();

        return [
            'avg_rating' => $avg,
            'count' => $count,
            'low_count' => $lowCount,
        ];
    }

    /**
     * @return Collection<int, object{professional_id: int|null, avg_rating: float, reviews_count: int}>
     */
    public function averagesByProfessional(int $companyId, ?Carbon $from = null, ?Carbon $to = null)
    {
        return AppointmentReview::query()
            ->select([
                'professional_id',
                DB::raw('AVG(rating) as avg_rating'),
                DB::raw('COUNT(*) as reviews_count'),
            ])
            ->where('company_id', $companyId)
            ->whereNotNull('submitted_at')
            ->whereNotNull('rating')
            ->when($from, fn (Builder $q) => $q->where('submitted_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('submitted_at', '<=', $to))
            ->groupBy('professional_id')
            ->orderByDesc('avg_rating')
            ->get();
    }
}
