<?php

namespace Tests\Unit\Admin\Widgets;

use App\Models\User;
use App\Support\Admin\Widgets\StatCardsWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StatCardsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_key_title_and_size()
    {
        $widget = $this->app->make(StatCardsWidget::class);

        $this->assertSame('stat-cards', $widget->key());
        $this->assertSame('Overview', $widget->title());
        $this->assertSame('full', $widget->size());
    }

    public function test_it_renders_real_counts()
    {
        User::factory()->count(3)->create();
        Role::create(['name' => 'Editor', 'guard_name' => config('admin-kit.guard')]);
        Permission::create(['name' => 'users.view', 'guard_name' => config('admin-kit.guard')]);
        Permission::create(['name' => 'users.create', 'guard_name' => config('admin-kit.guard')]);

        $html = $this->app->make(StatCardsWidget::class)->render()->render();

        $this->assertStringContainsString('Registered users', $html);
        $this->assertStringContainsString('>3<', $html);
        $this->assertStringContainsString('Roles', $html);
        $this->assertStringContainsString('>1<', $html);
        $this->assertStringContainsString('Permissions', $html);
        $this->assertStringContainsString('>2<', $html);
        $this->assertStringContainsString('Menu items', $html);
    }
}
