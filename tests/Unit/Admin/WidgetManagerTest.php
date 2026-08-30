<?php

namespace Tests\Unit\Admin;

use App\Support\Admin\AdminWidget;
use App\Support\Admin\MenuManager;
use App\Support\Admin\WidgetManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFacade;
use Tests\TestCase;

class WidgetManagerTest extends TestCase
{
    private function manager(array $classes): WidgetManager
    {
        config()->set('admin-kit.widgets', $classes);

        return $this->app->make(WidgetManager::class);
    }

    public function test_it_resolves_widgets_from_config_in_order()
    {
        $widgets = $this->manager([
            SecondFakeWidget::class,
            FirstFakeWidget::class,
        ])->all();

        $this->assertCount(2, $widgets);
        $this->assertInstanceOf(SecondFakeWidget::class, $widgets[0]);
        $this->assertInstanceOf(FirstFakeWidget::class, $widgets[1]);
    }

    public function test_it_resolves_widgets_through_the_container_so_they_can_have_dependencies()
    {
        $widgets = $this->manager([DependentFakeWidget::class])->all();

        $this->assertInstanceOf(DependentFakeWidget::class, $widgets[0]);
    }

    public function test_an_empty_config_yields_no_widgets()
    {
        $this->assertSame([], $this->manager([])->all());
    }
}

class FirstFakeWidget implements AdminWidget
{
    public function key(): string
    {
        return 'first';
    }

    public function title(): string
    {
        return 'First';
    }

    public function size(): string
    {
        return 'sm';
    }

    public function render(): View
    {
        return ViewFacade::make('admin::pages.dashboard');
    }
}

class SecondFakeWidget implements AdminWidget
{
    public function key(): string
    {
        return 'second';
    }

    public function title(): string
    {
        return 'Second';
    }

    public function size(): string
    {
        return 'sm';
    }

    public function render(): View
    {
        return ViewFacade::make('admin::pages.dashboard');
    }
}

class DependentFakeWidget implements AdminWidget
{
    public function __construct(private readonly MenuManager $menu) {}

    public function key(): string
    {
        return 'dependent';
    }

    public function title(): string
    {
        return 'Dependent';
    }

    public function size(): string
    {
        return 'sm';
    }

    public function render(): View
    {
        $this->menu->items();

        return ViewFacade::make('admin::pages.dashboard');
    }
}
