<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Admin\AdminWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_widgets_render_on_the_dashboard()
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-widget-grid', $html);
        $this->assertStringContainsString('data-widget-key="stat-cards"', $html);
        $this->assertStringContainsString('data-widget-key="users-growth-chart"', $html);
        $this->assertStringContainsString('data-widget-drag', $html);
        $this->assertStringContainsString('data-chart=', $html);
        $this->assertStringContainsString('nk-widget--full', $html);
        $this->assertStringContainsString('nk-widget--lg', $html);
    }

    /**
     * The browser hands JS the *decoded* attribute value (HTML entities
     * resolved), not the literal markup -- this reproduces that decoding
     * step so a regression like `@js()` (which wraps output as a JS
     * expression, not raw JSON safe for a data-* attribute) fails here
     * instead of only failing silently in a real browser's console.
     */
    public function test_the_chart_widgets_data_attribute_decodes_to_valid_json()
    {
        User::factory()->count(2)->create();
        $this->actingAs(User::factory()->create());

        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-chart="([^"]+)"/', $html);
        preg_match('/data-chart="([^"]+)"/', $html, $matches);

        $decoded = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        $chart = json_decode($decoded, true);

        $this->assertIsArray($chart);
        $this->assertSame('line', $chart['type']);
        $this->assertCount(6, $chart['labels']);
        $this->assertCount(6, $chart['datasets'][0]['data']);
    }

    public function test_a_project_supplied_widget_is_picked_up_from_config()
    {
        config()->set('admin-kit.widgets', [DashboardTestFakeWidget::class]);

        $this->actingAs(User::factory()->create());

        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-widget-key="fake-widget"', $html);
        $this->assertStringContainsString('Fake widget content', $html);
        $this->assertStringNotContainsString('data-widget-key="stat-cards"', $html);
    }

    public function test_an_empty_widget_list_renders_an_empty_grid_without_error()
    {
        config()->set('admin-kit.widgets', []);

        $this->actingAs(User::factory()->create());

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-widget-grid', escape: false);
    }
}

class DashboardTestFakeWidget implements AdminWidget
{
    public function key(): string
    {
        return 'fake-widget';
    }

    public function title(): string
    {
        return 'Fake widget';
    }

    public function size(): string
    {
        return 'sm';
    }

    public function render(): View
    {
        return view()->file(base_path('tests/Fixtures/fake-widget.blade.php'));
    }
}
