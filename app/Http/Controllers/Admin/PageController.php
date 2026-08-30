<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Admin\PageStatus;
use App\Http\Controllers\Admin\Concerns\HasBulkActions;
use App\Http\Controllers\Admin\Concerns\HasDataTable;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Admin\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PageController extends AdminController
{
    use HasBulkActions;
    use HasDataTable;

    public function index(Request $request): View
    {
        $pages = $this->paginateDataTable(
            Page::query(),
            searchable: ['title', 'slug'],
            sortable: ['title', 'status', 'updated_at'],
            defaultSort: 'updated_at',
            defaultDirection: 'desc',
        );

        return view('admin::pages.pages.index', ['pages' => $pages]);
    }

    public function create(): View
    {
        return view('admin::pages.pages.create', $this->formData());
    }

    public function store(PageRequest $request): RedirectResponse
    {
        $page = Page::query()->create($this->attributes($request));
        $page->syncSeoMeta($request->validated('seo', []));

        return $this->redirectWithSuccess('admin.pages.index', __('Page created.'));
    }

    public function edit(Page $page): View
    {
        $page->loadMissing('seoMeta');

        return view('admin::pages.pages.edit', ['page' => $page] + $this->formData());
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        $page->update($this->attributes($request));
        $page->syncSeoMeta($request->validated('seo', []));

        return $this->redirectWithSuccess('admin.pages.index', __('Page updated.'));
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return $this->redirectWithSuccess('admin.pages.index', __('Page deleted.'));
    }

    /**
     * Named distinctly from HasBulkActions::bulkDestroy() -- see
     * UserController::bulkDelete() for why.
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        return $this->bulkDestroy($request, Page::query(), 'admin.pages.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(PageRequest $request): array
    {
        $status = PageStatus::from((string) $request->validated('status'));

        return [
            'title' => $request->validated('title'),
            'slug' => $request->validated('slug'),
            'content' => $request->validated('content'),
            'template' => $request->validated('template'),
            'status' => $status,
            // Published with no explicit date means "right now"; Scheduled
            // always carries the future date PageRequest required; Draft
            // never carries one.
            'published_at' => match ($status) {
                PageStatus::Published => $request->validated('published_at') ?? now(),
                PageStatus::Scheduled => $request->validated('published_at'),
                PageStatus::Draft => null,
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'statusOptions' => PageStatus::options(),
            'templateOptions' => (array) config('admin-kit.cms.templates', []),
        ];
    }
}
