<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlaceApiTest extends TestCase
{
    /**
     * A basic feature test example.
     */
     use RefreshDatabase;

    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_anyone_can_list_place(): void{
        \App\Models\Place::factory(10)->create();

        $response = $this->getJson('api/places');

        $response->assertStatus(200)->assertJsonCount(10,'data');
    }

    public function test_user_login_review(): void{
        $user = \App\Models\User::factory()->create();
        $place = \App\Models\Place::factory()->create();


        $response = $this->actingAs(
        $user, 'sanctum'
        )->postJson("/api/places/{$place->id}/reviews",[
            'rating' => 2,
            'comment' => "Amazing sunset"
        ]);

        $response->assertStatus(201);
    }


    public function test_duplicated_review_rejected(): void{
            $user = \App\Models\User::factory()->create();
            $place = \App\Models\Place::factory()->create();

            \App\Models\Review::factory()->create([
                'user_id' => $user->id,
                'place_id' => $place->id,
            ]);


            $response = $this->actingAs(
            $user, 'sanctum'
            )->postJson("/api/places/{$place->id}/reviews",[
                'rating' => 2,
                'comment' => "Amazing sunset"
            ]);

            $response->assertStatus(422)->assertJsonValidationErrors('comment');

        }


        public function test_invalid_rating_rejected(): void{
                $user = \App\Models\User::factory()->create();
                $place = \App\Models\Place::factory()->create();


                $response = $this->actingAs(
                $user, 'sanctum'
                )->postJson("/api/places/{$place->id}/reviews",[
                    'rating' => 9,
                    'comment' => "Amazing sunset"
                ]);

                $response->assertStatus(422)->assertJsonValidationErrors('rating');
            }



        public function test_non_admin_canot_create_place(): void{
                $user = \App\Models\User::factory()->create();


                $response = $this->actingAs(
                $user, 'sanctum'
                )->postJson("/api/places",[
                    'name' => 'Test Place',
                    'description' => 'A place for testing',
                    'latitude' => -6.2,
                    'longitude' => 106.8,
                ]);

                $response->assertStatus(403);
        }

        public function test_admin_can_create_place(): void{
                $admin = \App\Models\User::factory()->create([
                    'is_admin' => true
                ]);


                $response = $this->actingAs(
                $admin, 'sanctum'
                )->postJson("/api/places",[
                    'name' => 'Test Place',
                    'description' => 'A place for testing',
                    'latitude' => -6.2,
                    'longitude' => 106.8,
                ]);

                $response->assertStatus(201);
        }

        public function test_review_delete_policy(): void{
                $author = \App\Models\User::factory()->create();
                $stranger = \App\Models\User::factory()->create();
                $admin = \App\Models\User::factory()->create([
                    'is_admin'=>true,
                ]);

                $review = \App\Models\Review::factory()->create([
                    'user_id'=>$author->id,
                ]);

                $response = $this->actingAs($stranger, 'sanctum')
                    ->deleteJson("/api/reviews/{$review->id}");

                $response->assertStatus(403);

                $response = $this->actingAs($author, 'sanctum')
                    ->deleteJson("/api/reviews/{$review->id}");

                $response->assertStatus(204);

                $review2 = \App\Models\Review::factory()->create(['user_id' => $author->id]);

                $response = $this->actingAs($admin, 'sanctum')
                    ->deleteJson("/api/reviews/{$review2->id}");

                $response->assertStatus(204);


        }

        // ---- Photo upload ----

        private function placeData(array $extra = []): array
        {
            return array_merge([
                'name' => 'Test Place',
                'description' => 'A place for testing',
                'latitude' => -6.2,
                'longitude' => 106.8,
            ], $extra);
        }

        private function admin(): \App\Models\User
        {
            return \App\Models\User::factory()->create(['is_admin' => true]);
        }

        public function test_admin_can_create_place_with_photo(): void{
                Storage::fake('public'); // save into a fake folder, not the real one

                $photo = UploadedFile::fake()->create('beach.jpg', 500, 'image/jpeg');

                $response = $this->actingAs($this->admin(), 'sanctum')
                    ->post('/api/places', $this->placeData(['photo' => $photo]), ['Accept' => 'application/json']);

                $response->assertStatus(201);
                Storage::disk('public')->assertExists('places/'.$photo->hashName());
                $this->assertStringEndsWith('/storage/places/'.$photo->hashName(), $response->json('image'));
        }

        public function test_non_image_photo_rejected(): void{
                Storage::fake('public');

                $file = UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf');

                $response = $this->actingAs($this->admin(), 'sanctum')
                    ->post('/api/places', $this->placeData(['photo' => $file]), ['Accept' => 'application/json']);

                $response->assertStatus(422)->assertJsonValidationErrors('photo');
        }

        public function test_photo_over_2mb_rejected(): void{
                Storage::fake('public');

                $photo = UploadedFile::fake()->create('big.jpg', 3000, 'image/jpeg'); // 3000 KB

                $response = $this->actingAs($this->admin(), 'sanctum')
                    ->post('/api/places', $this->placeData(['photo' => $photo]), ['Accept' => 'application/json']);

                $response->assertStatus(422)->assertJsonValidationErrors('photo');
        }

        public function test_new_photo_replaces_and_deletes_old_one(): void{
                Storage::fake('public');
                $admin = $this->admin();

                $first = UploadedFile::fake()->create('first.jpg', 100, 'image/jpeg');
                $id = $this->actingAs($admin, 'sanctum')
                    ->post('/api/places', $this->placeData(['photo' => $first]), ['Accept' => 'application/json'])
                    ->json('id');

                // Browsers can't send files with PUT, so the form sends POST + _method=PUT
                $second = UploadedFile::fake()->create('second.png', 100, 'image/png');
                $response = $this->actingAs($admin, 'sanctum')
                    ->post("/api/places/{$id}", $this->placeData(['photo' => $second, '_method' => 'PUT']), ['Accept' => 'application/json']);

                $response->assertStatus(200);
                Storage::disk('public')->assertMissing('places/'.$first->hashName());
                Storage::disk('public')->assertExists('places/'.$second->hashName());
        }

        public function test_deleting_place_deletes_its_photo(): void{
                Storage::fake('public');
                $admin = $this->admin();

                $photo = UploadedFile::fake()->create('beach.jpg', 100, 'image/jpeg');
                $id = $this->actingAs($admin, 'sanctum')
                    ->post('/api/places', $this->placeData(['photo' => $photo]), ['Accept' => 'application/json'])
                    ->json('id');

                $this->actingAs($admin, 'sanctum')->deleteJson("/api/places/{$id}")->assertStatus(204);

                Storage::disk('public')->assertMissing('places/'.$photo->hashName());
        }

}
