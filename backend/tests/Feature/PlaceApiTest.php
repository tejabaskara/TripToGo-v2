<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
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

}
