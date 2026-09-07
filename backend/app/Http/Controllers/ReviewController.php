<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Place $place)
    {
        return $place->reviews()->with('user')->latest()->paginate(15);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Place $place)
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string'],
        ]);

        $alreadyReviewed = $place->reviews()
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($alreadyReviewed) {
            throw ValidationException::withMessages([
                'comment' => ['You have already reviewed this place.'],
            ]);
        }

        // user_id comes from the token, NEVER from the request body - otherwise
        // anyone could post a review as anyone else.
        $review = $place->reviews()->create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return response()->json($review->load('user'), 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Review $review)
    {
        Gate::authorize('update', $review);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string'],
        ]);

        $review->update($validated);

        return $review->load('user');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Review $review)
    {
        Gate::authorize('delete', $review);

        $review->delete();

        return response()->noContent();
    }
}
