<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Visit;
use App\Services\VisitService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_page_visits_are_recorded(): void
    {
        $this->get('/')->assertOk();
        $this->get('/docs')->assertOk();

        $this->assertSame(2, Visit::count());
        $this->assertSame(['/', '/docs'], Visit::orderBy('id')->pluck('path')->all());
    }

    public function test_admin_pages_bots_and_non_get_requests_are_not_recorded(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/activity-log')->assertOk();
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/docs');
        $this->post('/docs');

        $this->assertSame(0, Visit::count());
    }

    public function test_visit_stats_and_daily_series(): void
    {
        $this->get('/');
        $this->get('/docs');
        Visit::create(['path' => '/old', 'visitor_hash' => 'x'])->forceFill(['created_at' => now()->subDays(3)])->save();

        $service = app(VisitService::class);
        $stats = $service->stats();

        $this->assertSame(2, $stats['today']);
        $this->assertSame(3, $stats['week']);
        $this->assertSame(1, $stats['uniqueToday']);

        $daily = $service->daily(7);
        $this->assertCount(7, $daily);
        $this->assertSame(2, end($daily)['count']);
        $this->assertSame(1, $daily[3]['count']);
    }

    public function test_admin_sees_site_stats_on_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.visits_per_day'))
            ->assertSee(__('dashboard.overview'));
    }

    public function test_member_sees_only_personal_stats(): void
    {
        $user = User::factory()->create();
        Post::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.my_posts'))
            ->assertDontSee(__('dashboard.visits_per_day'))
            ->assertDontSee(__('dashboard.overview'));
    }
}
