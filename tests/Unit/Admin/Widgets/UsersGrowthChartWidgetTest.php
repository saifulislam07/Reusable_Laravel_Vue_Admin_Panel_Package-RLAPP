<?php

namespace Tests\Unit\Admin\Widgets;

use App\Models\User;
use App\Support\Admin\Widgets\UsersGrowthChartWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersGrowthChartWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_key_title_and_size()
    {
        $widget = new UsersGrowthChartWidget;

        $this->assertSame('users-growth-chart', $widget->key());
        $this->assertSame('New users', $widget->title());
        $this->assertSame('lg', $widget->size());
    }

    public function test_it_buckets_users_by_the_month_they_were_created_in()
    {
        User::factory()->count(2)->create(['created_at' => now()]);
        User::factory()->create(['created_at' => now()->subMonths(2)]);

        $view = (new UsersGrowthChartWidget)->render();
        $data = $view->getData();

        $this->assertCount(6, $data['labels']);
        $this->assertCount(6, $data['data']);

        // Last bucket is the current month.
        $this->assertSame(2, $data['data'][5]);

        // Two months back (index 3 of a 6-wide, 0-indexed, oldest-first window).
        $this->assertSame(1, $data['data'][3]);

        // No users created 5 months ago -- that bucket is zero, not omitted.
        $this->assertSame(0, $data['data'][0]);
    }

    public function test_it_renders_without_error_when_there_are_no_users_in_the_window()
    {
        User::query()->delete();

        $view = (new UsersGrowthChartWidget)->render();

        $this->assertSame(array_fill(0, 6, 0), $view->getData()['data']);
        $this->assertNotEmpty($view->render());
    }
}
