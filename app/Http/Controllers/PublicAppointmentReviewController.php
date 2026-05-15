<?php

namespace App\Http\Controllers;

use App\Models\AppointmentReview;
use App\Services\ReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicAppointmentReviewController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $review = AppointmentReview::query()->where('token', $token)->first();

        if (! $review) {
            return view('public-reviews.invalid');
        }

        if ($review->submitted_at !== null) {
            return view('public-reviews.thanks', [
                'company' => $review->loadMissing('company')->company,
                'already' => true,
            ]);
        }

        $review->loadMissing('company');

        return view('public-reviews.show', [
            'review' => $review,
            'company' => $review->company,
        ]);
    }

    public function store(Request $request, string $token, ReviewService $reviewService): View|RedirectResponse
    {
        $review = AppointmentReview::query()->where('token', $token)->firstOrFail();

        if ($review->submitted_at !== null) {
            return redirect()->route('public-reviews.show', ['token' => $token]);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'service_quality_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'punctuality_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'environment_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $reviewService->submitReview($token, $data);

        $review->refresh();

        return view('public-reviews.thanks', [
            'company' => $review->loadMissing('company')->company,
            'already' => false,
        ]);
    }
}
