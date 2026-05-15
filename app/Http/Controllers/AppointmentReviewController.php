<?php

namespace App\Http\Controllers;

use App\Models\AppointmentReview;
use App\Models\User;
use App\Services\ReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AppointmentReviewController extends Controller
{
    public function index(Request $request, ReviewService $reviewService): View
    {
        abort_unless($request->user()->hasFinancialPrivileges(), 403);

        $companyId = (int) $request->user()->company_id;
        $from = $request->filled('from') ? CarbonImmutable::parse((string) $request->input('from'))->startOfDay() : null;
        $to = $request->filled('to') ? CarbonImmutable::parse((string) $request->input('to'))->endOfDay() : null;
        $professionalId = $request->integer('professional_id') ?: null;
        $ratingMax = $request->integer('rating_max') ?: null;

        $summary = $reviewService->getCompanyReviewSummary($companyId, $from, $to);
        $byProfessional = $reviewService->averagesByProfessional($companyId, $from, $to);

        $reviews = AppointmentReview::query()
            ->with(['appointment.services', 'appointment.service', 'client', 'professional'])
            ->where('company_id', $companyId)
            ->whereNotNull('submitted_at')
            ->when($from, fn ($q) => $q->where('submitted_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('submitted_at', '<=', $to))
            ->when($professionalId, fn ($q) => $q->where('professional_id', $professionalId))
            ->when($ratingMax !== null, fn ($q) => $q->where('rating', '<=', $ratingMax))
            ->orderByDesc('submitted_at')
            ->paginate(25)
            ->withQueryString();

        $professionals = User::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('reviews.index', [
            'reviews' => $reviews,
            'summary' => $summary,
            'byProfessional' => $byProfessional,
            'professionals' => $professionals,
            'filters' => [
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'professional_id' => $professionalId,
                'rating_max' => $ratingMax,
            ],
        ]);
    }
}
