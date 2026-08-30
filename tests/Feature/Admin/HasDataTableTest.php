<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\Concerns\HasDataTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Exercises the HasDataTable trait directly (search, sort allow-list,
 * pagination, namespacing) through throwaway routes, independent of any
 * concrete listing page. AdminUsersTest covers the real Users page built on
 * top of it.
 */
class HasDataTableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Register a throwaway authenticated route whose controller runs the
     * trait with the given options and returns the resulting names as JSON.
     */
    private function dataTableRoute(string $path, array $options = []): string
    {
        Route::middleware(['web', 'auth'])->get("/admin/_dt-{$path}", function () use ($options) {
            $controller = new class
            {
                use HasDataTable;

                public function handle(array $options): array
                {
                    $query = User::query();

                    $paginator = $this->paginateDataTable(
                        $query,
                        searchable: $options['searchable'] ?? ['name', 'email'],
                        sortable: $options['sortable'] ?? ['name', 'email'],
                        defaultSort: $options['defaultSort'] ?? 'name',
                        defaultDirection: $options['defaultDirection'] ?? 'asc',
                        perPage: $options['perPage'] ?? 15,
                        name: $options['name'] ?? '',
                    );

                    return [
                        'names' => $paginator->pluck('name')->all(),
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                    ];
                }
            };

            return response()->json($controller->handle($options));
        });

        return "/admin/_dt-{$path}";
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['name' => '__actor__']));
    }

    public function test_it_paginates_with_a_default_sort()
    {
        User::factory()->createMany([
            ['name' => 'Charlie', 'email' => 'charlie@example.com'],
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ]);

        $url = $this->dataTableRoute('sort-default');

        $names = $this->getJson($url)->json('names');

        $this->assertSame(['Alice', 'Bob', 'Charlie', '__actor__'], $names);
    }

    public function test_it_sorts_by_an_allowed_column_in_either_direction()
    {
        User::factory()->createMany([
            ['name' => 'Charlie'],
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ]);

        $url = $this->dataTableRoute('sort-desc');

        $names = $this->getJson("{$url}?sort=name&direction=desc")->json('names');

        $this->assertSame(['__actor__', 'Charlie', 'Bob', 'Alice'], $names);
    }

    public function test_an_unknown_sort_column_falls_back_to_the_default_instead_of_erroring()
    {
        User::factory()->create(['name' => 'Zed']);

        $url = $this->dataTableRoute('sort-fallback');

        // "password" is a real column but not in the allow-list -- exposing
        // it to sort would be a step toward leaking data through timing/order,
        // and is exactly what the allow-list exists to prevent.
        $response = $this->getJson("{$url}?sort=password&direction=asc");

        $response->assertOk();
        $this->assertSame(['Zed', '__actor__'], $response->json('names'));
    }

    public function test_search_matches_across_every_searchable_column()
    {
        User::factory()->create(['name' => 'Findable Name', 'email' => 'nomatch@example.com']);
        User::factory()->create(['name' => 'No Match', 'email' => 'findable@example.com']);
        User::factory()->create(['name' => 'Neither', 'email' => 'neither@example.com']);

        $url = $this->dataTableRoute('search');

        $names = $this->getJson("{$url}?q=findable")->json('names');

        $this->assertEqualsCanonicalizing(['Findable Name', 'No Match'], $names);
    }

    public function test_an_empty_search_term_returns_everyone()
    {
        User::factory()->count(3)->create();

        $url = $this->dataTableRoute('search-empty');

        $this->getJson("{$url}?q=")->assertJsonCount(4, 'names');
    }

    public function test_a_percent_sign_in_the_search_term_still_behaves_as_a_sql_wildcard()
    {
        // Documented trade-off, not a bug: escaping it would need a LIKE …
        // ESCAPE clause, which has no fluent-builder API in this Laravel
        // version and would mean raw SQL built from a caller-supplied column
        // name. The term itself is still bound as a parameter throughout, so
        // this is a UX quirk (same as most admin search boxes), not an
        // injection risk.
        User::factory()->create(['name' => '100% Real']);
        User::factory()->create(['name' => 'Totally Real']);

        $url = $this->dataTableRoute('search-escape');

        $names = $this->getJson("{$url}?q=".urlencode('%'))->json('names');

        $this->assertEqualsCanonicalizing(['100% Real', 'Totally Real', '__actor__'], $names);
    }

    public function test_search_is_disabled_when_no_searchable_columns_are_given()
    {
        User::factory()->create(['name' => 'Only Match']);
        User::factory()->create(['name' => 'Should Also Appear']);

        $url = $this->dataTableRoute('search-disabled', ['searchable' => []]);

        // The "q" parameter is simply ignored -- every row still comes back.
        $response = $this->getJson("{$url}?q=Only");

        $this->assertSame(3, $response->json('total'));
    }

    public function test_per_page_is_respected_up_to_a_hard_cap()
    {
        User::factory()->count(5)->create();

        $url = $this->dataTableRoute('per-page');

        $response = $this->getJson("{$url}?per_page=2");

        $this->assertSame(2, $response->json('per_page'));
        $this->assertCount(2, $response->json('names'));
    }

    public function test_per_page_cannot_exceed_one_hundred()
    {
        User::factory()->count(3)->create();

        $url = $this->dataTableRoute('per-page-cap');

        $response = $this->getJson("{$url}?per_page=5000");

        $this->assertSame(100, $response->json('per_page'));
    }

    public function test_the_page_parameter_advances_results()
    {
        User::factory()->count(5)->create();

        $url = $this->dataTableRoute('page-advance');

        $page1 = $this->getJson("{$url}?per_page=2&page=1")->json('names');
        $page2 = $this->getJson("{$url}?per_page=2&page=2")->json('names');

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
        $this->assertEmpty(array_intersect($page1, $page2));
    }

    public function test_a_named_table_reads_its_own_namespaced_query_keys()
    {
        User::factory()->create(['name' => 'Should Not Match']);
        User::factory()->create(['name' => 'Namespaced Match']);

        $url = $this->dataTableRoute('namespaced', ['name' => 'members']);

        // The bare "q" key belongs to a different, unnamed table -- a
        // namespaced instance must not read it.
        $response = $this->getJson("{$url}?q=Should&members_q=Namespaced");

        $this->assertSame(['Namespaced Match'], $response->json('names'));
    }
}
