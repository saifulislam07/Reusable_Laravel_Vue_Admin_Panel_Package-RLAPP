<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\MediaFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The Media Library (Phase 12): upload, grid listing, folders, and the
 * reusable picker other features (User avatar, CMS content images) select
 * from. Every management route is gated behind media.manage; the picker
 * JSON feed is deliberately not, since it's used from inside contexts that
 * are already gated on their own terms (see routes/admin.php).
 */
class AdminMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('media-library.disk_name'));
        Permission::findOrCreate('media.manage', config('admin-kit.guard'));
    }

    private function actingAsManager(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('media.manage');
        $this->actingAs($user);

        return $user;
    }

    // -- access control ---------------------------------------------------

    public function test_guests_cannot_reach_the_media_library()
    {
        $this->get(route('admin.media.index'))->assertRedirect(route('admin.login'));
    }

    public function test_a_user_without_media_manage_is_forbidden()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.media.index'))->assertForbidden();
    }

    // -- upload -------------------------------------------------------------

    public function test_an_image_can_be_uploaded_and_generates_conversions()
    {
        $this->actingAsManager();

        $this->post(route('admin.media.store'), [
            'files' => [UploadedFile::fake()->image('photo.jpg', 400, 400)],
        ])->assertRedirect(route('admin.media.index'));

        $media = Media::query()->firstOrFail();

        $this->assertSame('photo.jpg', $media->file_name);
        $this->assertTrue($media->hasGeneratedConversion('thumbnail'));
        $this->assertTrue($media->hasGeneratedConversion('medium'));
        Storage::disk((string) config('media-library.disk_name'))->assertExists($media->getPathRelativeToRoot('thumbnail'));
    }

    public function test_uploading_with_no_folder_lands_in_the_default_uncategorized_folder()
    {
        $this->actingAsManager();

        $this->post(route('admin.media.store'), [
            'files' => [UploadedFile::fake()->image('photo.jpg')],
        ]);

        $media = Media::query()->firstOrFail();
        $folder = MediaFolder::find($media->model_id);

        $this->assertTrue($folder->isDefault());
    }

    public function test_uploading_into_a_chosen_folder()
    {
        $this->actingAsManager();
        $folder = MediaFolder::factory()->create(['name' => 'Banners']);

        $this->post(route('admin.media.store'), [
            'files' => [UploadedFile::fake()->image('photo.jpg')],
            'folder_id' => $folder->id,
        ]);

        $media = Media::query()->firstOrFail();
        $this->assertSame($folder->id, $media->model_id);
    }

    public function test_multiple_files_can_be_uploaded_at_once()
    {
        $this->actingAsManager();

        $this->post(route('admin.media.store'), [
            'files' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ],
        ]);

        $this->assertSame(2, Media::query()->count());
    }

    public function test_at_least_one_file_is_required()
    {
        $this->actingAsManager();

        $this->post(route('admin.media.store'), [])->assertSessionHasErrors('files');
    }

    // -- listing / search -----------------------------------------------------

    public function test_the_index_lists_uploaded_files()
    {
        $manager = $this->actingAsManager();
        MediaFolder::default()->addMedia(UploadedFile::fake()->image('report.jpg'))->toMediaCollection();
        $this->actingAs($manager);

        $this->get(route('admin.media.index'))->assertOk()->assertSee('report.jpg');
    }

    public function test_files_can_be_searched_by_filename()
    {
        $manager = $this->actingAsManager();
        MediaFolder::default()->addMedia(UploadedFile::fake()->image('invoice.jpg'))->toMediaCollection();
        MediaFolder::default()->addMedia(UploadedFile::fake()->image('receipt.jpg'))->toMediaCollection();
        $this->actingAs($manager);

        $this->get(route('admin.media.index', ['q' => 'invoice']))
            ->assertSee('invoice.jpg')
            ->assertDontSee('receipt.jpg');
    }

    public function test_the_folder_filter_narrows_the_listing()
    {
        $manager = $this->actingAsManager();
        $folder = MediaFolder::factory()->create();
        $folder->addMedia(UploadedFile::fake()->image('in-folder.jpg'))->toMediaCollection();
        MediaFolder::default()->addMedia(UploadedFile::fake()->image('uncategorized.jpg'))->toMediaCollection();
        $this->actingAs($manager);

        $this->get(route('admin.media.index', ['folder' => $folder->id]))
            ->assertSee('in-folder.jpg')
            ->assertDontSee('uncategorized.jpg');
    }

    // -- delete ---------------------------------------------------------------

    public function test_deleting_a_file_removes_it_from_disk()
    {
        $this->actingAsManager();
        $media = MediaFolder::default()->addMedia(UploadedFile::fake()->image('gone.jpg'))->toMediaCollection();
        $disk = Storage::disk((string) config('media-library.disk_name'));
        $disk->assertExists($media->getPathRelativeToRoot());

        $this->delete(route('admin.media.destroy', $media))->assertRedirect(route('admin.media.index'));

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        $disk->assertMissing($media->getPathRelativeToRoot());
    }

    /**
     * The important thing here isn't just that the DB rows go -- it's that
     * each file is actually removed from disk too, which only happens if
     * every model's own `deleted` event fires (see MediaController::
     * bulkDelete()'s docblock for why a raw bulk query delete would not
     * have done this).
     */
    public function test_bulk_delete_removes_every_selected_files_row_and_disk_file()
    {
        $this->actingAsManager();

        $items = collect(range(1, 3))->map(
            fn ($i) => MediaFolder::default()->addMedia(UploadedFile::fake()->image("f{$i}.jpg"))->toMediaCollection(),
        );
        $disk = Storage::disk((string) config('media-library.disk_name'));

        $this->post(route('admin.media.bulk-destroy'), [
            'ids' => $items->pluck('id')->all(),
        ])->assertRedirect(route('admin.media.index'));

        $this->assertSame(0, Media::query()->count());

        $items->each(fn ($item) => $disk->assertMissing($item->getPathRelativeToRoot()));
    }

    // -- folders ------------------------------------------------------------

    public function test_a_folder_can_be_created()
    {
        $this->actingAsManager();

        $this->post(route('admin.media.folders.store'), ['name' => 'Banners'])
            ->assertRedirect(route('admin.media.index'));

        $this->assertDatabaseHas('media_folders', ['name' => 'Banners']);
    }

    public function test_the_default_folder_cannot_be_deleted()
    {
        $this->actingAsManager();
        $default = MediaFolder::default();

        $this->delete(route('admin.media.folders.destroy', $default))
            ->assertRedirect(route('admin.media.index'));

        $this->assertDatabaseHas('media_folders', ['id' => $default->id]);
    }

    public function test_a_non_empty_folder_cannot_be_deleted()
    {
        $this->actingAsManager();
        $folder = MediaFolder::factory()->create();
        $folder->addMedia(UploadedFile::fake()->image('keep.jpg'))->toMediaCollection();

        $this->delete(route('admin.media.folders.destroy', $folder))
            ->assertRedirect(route('admin.media.index'));

        $this->assertDatabaseHas('media_folders', ['id' => $folder->id]);
    }

    public function test_an_empty_non_default_folder_can_be_deleted()
    {
        $this->actingAsManager();
        $folder = MediaFolder::factory()->create();

        $this->delete(route('admin.media.folders.destroy', $folder))
            ->assertRedirect(route('admin.media.index'));

        $this->assertDatabaseMissing('media_folders', ['id' => $folder->id]);
    }

    // -- picker (used from inside other, already-gated forms) -----------------

    public function test_the_picker_endpoint_requires_authentication_but_not_media_manage()
    {
        $this->get(route('admin.media.picker'))->assertRedirect(route('admin.login'));

        // Any signed-in admin can browse the picker feed, whatever their
        // permissions -- it's read-only and used from contexts (Pages,
        // Profile) that are already gated on their own terms.
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.media.picker'))->assertOk();
    }

    public function test_the_picker_returns_thumbnail_and_url_for_each_item()
    {
        $manager = $this->actingAsManager();
        MediaFolder::default()->addMedia(UploadedFile::fake()->image('pickable.jpg'))->toMediaCollection();
        $this->actingAs($manager);

        $response = $this->getJson(route('admin.media.picker'))->assertOk();

        $response->assertJsonStructure(['data' => [['id', 'name', 'file_name', 'thumbnail', 'url', 'is_image']]]);
        $this->assertSame('pickable.jpg', $response->json('data.0.file_name'));
        $this->assertTrue($response->json('data.0.is_image'));
    }

    public function test_the_picker_can_be_searched_by_filename()
    {
        $manager = $this->actingAsManager();
        MediaFolder::default()->addMedia(UploadedFile::fake()->image('alpha.jpg'))->toMediaCollection();
        MediaFolder::default()->addMedia(UploadedFile::fake()->image('beta.jpg'))->toMediaCollection();
        $this->actingAs($manager);

        $response = $this->getJson(route('admin.media.picker', ['q' => 'alpha']))->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('alpha.jpg', $response->json('data.0.file_name'));
    }
}
