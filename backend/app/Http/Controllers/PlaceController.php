<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlaceRequest;
use App\Http\Requests\UpdatePlaceRequest;
use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlaceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $category = $request-> input('category');

        $query = Place::withCount('reviews')->withAvg('reviews', 'rating');

        if($search){
            $query->where('name', 'like', "%{$search}%");
        }

        if($category){
            $query->where('category', $category);
        }

        return $query->paginate(15);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePlaceRequest $request)
    {
        $place = Place::create($this->withPhoto($request));

        return response()->json($place, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Place $place)
    {
        // Only send the reviewer's id and name - never their email or is_admin
        $place->load('reviews.user:id,name');
        $place->loadCount('reviews');
        $place->loadAvg('reviews', 'rating');

        return $place;

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePlaceRequest $request, Place $place)
    {
        $oldImage = $place->image;

        $place->update($this->withPhoto($request));

        // The picture changed: remove the old uploaded file (if it was one of ours)
        if ($oldImage !== $place->image) {
            $this->deleteUploadedPhoto($oldImage);
        }

        return $place;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Place $place)
    {
        // Second safety layer: the 'admin' middleware already checks this
        abort_unless($request->user()?->is_admin, 403);

        $place->delete();
        $this->deleteUploadedPhoto($place->image);

        return response()->noContent();
    }

    /**
     * The validated data, with `image` set to the uploaded photo's address
     * when a photo file was sent (an uploaded photo wins over a pasted link).
     */
    private function withPhoto(Request $request): array
    {
        $data = $request->validated();
        unset($data['photo']); // not a column in the places table

        if ($request->hasFile('photo')) {
            // Saved as storage/app/public/places/<random name>.jpg
            $path = $request->file('photo')->store('places', 'public');
            // Full address, e.g. http://localhost:8000/storage/places/<random name>.jpg
            $data['image'] = Storage::disk('public')->url($path);
        }

        return $data;
    }

    /**
     * Delete a photo file we saved earlier. Links to other websites are left alone.
     */
    private function deleteUploadedPhoto(?string $image): void
    {
        $prefix = Storage::disk('public')->url('places/');

        if ($image && Str::startsWith($image, $prefix)) {
            Storage::disk('public')->delete('places/'.Str::after($image, $prefix));
        }
    }
}
