<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_career_profile_page_loads(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/career-profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CareerProfile/Index')
                ->where('profile.id', $user->careerProfile->id)
                ->where('completion.percentage', 0));
    }

    public function test_can_create_experience(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/career-profile')
            ->post('/career-profile/experiences', [
                'organization' => 'Test Company',
                'job_title' => 'Software Engineer',
                'start_date' => '2023-01-01',
                'end_date' => '2024-01-01',
                'is_current' => false,
                'description' => 'Built cool stuff',
            ]);

        $response->assertRedirect('/career-profile')
            ->assertSessionHas('success', 'Pengalaman ditambahkan.');

        $this->assertDatabaseHas('profile_experiences', [
            'organization' => 'Test Company',
            'job_title' => 'Software Engineer',
        ]);
    }

    public function test_can_reorder_experiences(): void
    {
        $user = User::factory()->create();
        $profile = $user->careerProfile()->create();

        $exp1 = $profile->experiences()->create([
            'organization' => 'Company A',
            'job_title' => 'Role A',
            'sort_order' => 0,
        ]);
        $exp2 = $profile->experiences()->create([
            'organization' => 'Company B',
            'job_title' => 'Role B',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->from('/career-profile')
            ->post('/career-profile/experiences/reorder', [
                'ids' => [$exp2->id, $exp1->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(0, $exp2->fresh()->sort_order);
        $this->assertEquals(1, $exp1->fresh()->sort_order);
    }

    public function test_other_user_cannot_access_experience(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user2->careerProfile()->create(); // user2 needs a profile for the ownership check to work

        $profile1 = $user1->careerProfile()->create();
        $experience = $profile1->experiences()->create([
            'organization' => 'Company A',
            'job_title' => 'Role A',
            'start_date' => '2023-01-01',
            'end_date' => '2024-01-01',
        ]);

        $this->actingAs($user2)
            ->from('/career-profile')
            ->patch("/career-profile/experiences/{$experience->id}", [
                'organization' => 'Hacked',
                'job_title' => 'Role A',
                'start_date' => '2023-01-01',
                'end_date' => '2024-01-01',
            ])
            ->assertForbidden();
    }
}
