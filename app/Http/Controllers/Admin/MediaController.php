<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasBulkActions;
use App\Http\Controllers\Admin\Concerns\HasDataTable;
use App\Http\Requests\Admin\MediaFolderRequest;
use App\Http\Requests\Admin\MediaUploadRequest;
use App\Models\Admin\MediaFolder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends AdminController
{
    use HasBulkActions;
    use HasDataTable;

    public function index(Request $request): View
    {
        $folderId = $request->integer('folder') ?: null;

        $media = $this->paginateDataTable(
            $this->libraryQuery()->when($folderId, fn ($query) => $query->where('model_id', $folderId)),
            searchable: ['file_name', 'name'],
            sortable: ['file_name', 'size', 'created_at'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
            perPage: 24,
        );

        return view('admin::pages.media.index', [
            'media' => $media,
            'folders' => MediaFolder::query()->orderBy('name')->get(),
            'currentFolder' => $folderId,
        ]);
    }

    public function store(MediaUploadRequest $request): RedirectResponse
    {
        $folder = $this->resolveFolder($request->validated('folder_id'));
        $count = 0;

        foreach ((array) $request->file('files', []) as $file) {
            $folder->addMedia($file)->toMediaCollection();
            $count++;
        }

        return $this->redirectWithSuccess('admin.media.index', trans_choice(
            '{1}1 file uploaded.|[2,*]:count files uploaded.',
            $count,
            ['count' => $count],
        ));
    }

    public function destroy(Media $media): RedirectResponse
    {
        $media->delete();

        return $this->redirectWithSuccess('admin.media.index', __('File deleted.'));
    }

    /**
     * Plain Model::destroy() rather than HasBulkActions::bulkDestroy(): that
     * helper issues one bulk SQL DELETE, which never fires the per-model
     * `deleted` event medialibrary relies on to remove the actual files
     * from disk -- Model::destroy() loads and deletes each row individually,
     * so the observer runs for every one of them.
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = $this->bulkIds($request);

        if ($ids === []) {
            $this->flashError(__('Select at least one row.'));

            return redirect()->route('admin.media.index');
        }

        $deleted = Media::destroy($ids);

        if ($deleted > 0) {
            $this->flashSuccess(trans_choice(
                '{1}1 file deleted.|[2,*]:count files deleted.',
                $deleted,
                ['count' => $deleted],
            ));
        }

        return redirect()->route('admin.media.index');
    }

    public function storeFolder(MediaFolderRequest $request): RedirectResponse
    {
        MediaFolder::query()->create(['name' => $request->validated('name')]);

        return $this->redirectWithSuccess('admin.media.index', __('Folder created.'));
    }

    /**
     * The default folder is a permanent home for un-sorted uploads (like
     * the protected role, it can't be renamed away or deleted -- see
     * MediaFolder::DEFAULT_NAME). A folder that still has files in it is
     * left alone too, rather than silently deleting or re-homing them --
     * the admin moves or deletes them first.
     */
    public function destroyFolder(MediaFolder $folder): RedirectResponse
    {
        if ($folder->isDefault()) {
            $this->flashError(__('The Uncategorized folder cannot be deleted.'));

            return redirect()->route('admin.media.index');
        }

        if ($folder->getMedia()->isNotEmpty()) {
            $this->flashError(__('This folder still has files in it -- delete or move them first.'));

            return redirect()->route('admin.media.index');
        }

        $folder->delete();

        return $this->redirectWithSuccess('admin.media.index', __('Folder deleted.'));
    }

    /**
     * JSON feed behind <x-admin::media-picker>'s modal -- the one place in
     * this admin kit that isn't AJAX-free, because a picker embedded inside
     * another page's form can't do a full navigation to browse the library.
     */
    public function picker(Request $request): JsonResponse
    {
        $query = $this->libraryQuery();

        if ($search = trim((string) $request->string('q'))) {
            $query->where('file_name', 'like', "%{$search}%");
        }

        $media = $query->orderByDesc('created_at')->paginate(24);

        return response()->json([
            'data' => $media->through(fn (Media $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'file_name' => $item->file_name,
                'thumbnail' => $item->hasGeneratedConversion('thumbnail') ? $item->getUrl('thumbnail') : null,
                'url' => $item->getUrl(),
                'is_image' => str_starts_with((string) $item->mime_type, 'image/'),
            ])->items(),
            'next_page_url' => $media->nextPageUrl(),
        ]);
    }

    /**
     * @return Builder<Media>
     */
    private function libraryQuery(): Builder
    {
        return Media::query()->where('model_type', MediaFolder::class);
    }

    private function resolveFolder(mixed $folderId): MediaFolder
    {
        if (! $folderId) {
            return MediaFolder::default();
        }

        return MediaFolder::query()->findOrFail((int) $folderId);
    }
}
