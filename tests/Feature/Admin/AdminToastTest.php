<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AdminController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminToastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * The flash payload the container hands to Alpine, decoded.
     *
     * @return list<array{type: string, message: string}>
     */
    private function flashPayload(string $html): array
    {
        $this->assertMatchesRegularExpression('/x-ref="flash">(.*?)<\/script>/s', $html);

        preg_match('/x-ref="flash">(.*?)<\/script>/s', $html, $matches);

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true) ?? [];
    }

    public function test_the_container_renders_in_the_panel_layout()
    {
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('nk-toasts', escape: false)
            ->assertSee('nk-modal__dialog', escape: false)
            ->assertSee('aria-modal="true"', escape: false);
    }

    public function test_nothing_is_queued_without_flash_messages()
    {
        $html = $this->get(route('admin.dashboard'))->getContent();

        $this->assertSame([], $this->flashPayload($html));
    }

    public function test_a_success_flash_becomes_a_success_toast()
    {
        $html = $this->withSession(['success' => 'Saved successfully'])
            ->get(route('admin.dashboard'))
            ->getContent();

        $this->assertSame(
            [['type' => 'success', 'message' => 'Saved successfully']],
            $this->flashPayload($html),
        );
    }

    public function test_an_error_flash_maps_to_the_danger_variant()
    {
        $html = $this->withSession(['error' => 'Could not save'])
            ->get(route('admin.dashboard'))
            ->getContent();

        $this->assertSame(
            [['type' => 'danger', 'message' => 'Could not save']],
            $this->flashPayload($html),
        );
    }

    public function test_every_flash_key_is_picked_up()
    {
        $html = $this->withSession([
            'success' => 'A',
            'error' => 'B',
            'warning' => 'C',
            'info' => 'D',
        ])->get(route('admin.dashboard'))->getContent();

        $this->assertSame(
            ['success', 'danger', 'warning', 'info'],
            array_column($this->flashPayload($html), 'type'),
        );
    }

    public function test_multiple_toasts_can_be_queued_in_one_response()
    {
        $html = $this->withSession(['nk_toasts' => [
            ['type' => 'info', 'message' => 'First'],
            ['type' => 'success', 'message' => 'Second'],
        ]])->get(route('admin.dashboard'))->getContent();

        $this->assertSame(
            [
                ['type' => 'info', 'message' => 'First'],
                ['type' => 'success', 'message' => 'Second'],
            ],
            $this->flashPayload($html),
        );
    }

    public function test_validation_errors_produce_one_summary_toast()
    {
        Route::middleware(['web', 'auth'])->post('/admin/_toast-validation-probe', function (Request $request) {
            $request->validate(['email' => 'required', 'name' => 'required']);
        });

        // A real validation failure, not a hand-built session payload: Laravel
        // serializes "errors" as plain arrays under the array/json session
        // driver and reconstructs the ViewErrorBag on the next request's
        // Store::start(). Asserting on the session here (rather than after)
        // would start() that same in-process Store a second time before our
        // follow-up GET -- and marshalErrorBag() isn't idempotent against its
        // own already-reconstructed output, so it would quietly empty the bag.
        // The response status is proof enough that validation failed.
        $this->from(route('admin.dashboard'))
            ->post('/admin/_toast-validation-probe', [])
            ->assertStatus(302);

        $html = $this->get(route('admin.dashboard'))->getContent();
        $payload = $this->flashPayload($html);

        $this->assertCount(1, $payload);
        $this->assertSame('danger', $payload[0]['type']);
        $this->assertStringContainsString('2 fields', $payload[0]['message']);
    }

    public function test_a_message_containing_markup_cannot_break_out_of_the_payload()
    {
        $hostile = '</script><script>alert(1)</script>';

        $html = $this->withSession(['success' => $hostile])
            ->get(route('admin.dashboard'))
            ->getContent();

        // The raw closing tag must not appear -- that is what would end the
        // JSON block early and let the rest execute.
        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $this->assertSame($hostile, $this->flashPayload($html)[0]['message']);
    }

    public function test_the_flash_helpers_queue_what_the_container_reads()
    {
        $controller = new class extends AdminController
        {
            public function run(): void
            {
                $this->flashSuccess('One');
                $this->flashToast('warning', 'Two');
                $this->flashToast('info', 'Three');
            }
        };

        $controller->run();

        $html = $this->get(route('admin.dashboard'))->getContent();

        $this->assertSame(
            [
                ['type' => 'success', 'message' => 'One'],
                ['type' => 'warning', 'message' => 'Two'],
                ['type' => 'info', 'message' => 'Three'],
            ],
            $this->flashPayload($html),
        );
    }

    public function test_the_confirm_dialog_labels_are_translatable()
    {
        $html = $this->get(route('admin.dashboard'))->getContent();

        preg_match('/x-ref="labels">(.*?)<\/script>/s', $html, $matches);
        $labels = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        $this->assertSame(
            ['title', 'confirmLabel', 'cancelLabel'],
            array_keys($labels),
        );
        $this->assertNotEmpty($labels['confirmLabel']);
    }
}
