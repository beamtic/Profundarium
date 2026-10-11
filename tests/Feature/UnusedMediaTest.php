<?php

namespace Tests\Feature;

use App\Media\ImageReferences;
use App\Media\MediaException;
use App\Media\NoteReader;
use App\Media\UnusedMedia;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class UnusedMediaTest extends TestCase
{
    public function test_rejected_image_reference_is_logged_and_cleanup_remains_blocked(): void
    {
        $url = 'http://notes.example.test:invalid/uploads/image.png';
        \Illuminate\Support\Facades\Log::shouldReceive('warning')->once()->with(
            'Media scan could not parse an image reference.',
            ['note_id' => '1', 'note_url' => 'https://notes.example.test/s/test-slug', 'reference' => $url],
        );
        $this->image('keep.png');
        $this->note('<img src="' . $url . '">');
        DB::connection('hedgedoc')->table('Notes')->where('id', 1)->update(['shortid' => 'test-slug']);
        $this->artisan('media:cleanup', ['--force' => true])
            ->doesntExpectOutputToContain($url)->assertFailed();
        $this->assertFileExists($this->uploads . '/keep.png');
    }

    private string $uploads;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploads = sys_get_temp_dir() . '/profundarium-media-' . bin2hex(random_bytes(8));
        mkdir($this->uploads);
        config([
            'services.hedgedoc.public_url' => 'https://notes.example.test',
            'services.hedgedoc.uploads_path' => $this->uploads,
            'database.connections.hedgedoc' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('hedgedoc');
        Schema::connection('hedgedoc')->create('Notes', function ($table): void {
            $table->id();
            $table->string('shortid')->nullable();
            $table->string('alias')->nullable();
            $table->text('content')->nullable();
            $table->string('permission')->default('private');
            $table->timestamp('deletedAt')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('hedgedoc');
        (new Filesystem())->deleteDirectory($this->uploads);
        parent::tearDown();
    }

    private function image(string $path): void
    {
        (new Filesystem())->ensureDirectoryExists(dirname($this->uploads . '/' . $path));
        file_put_contents($this->uploads . '/' . $path, 'isolated image fixture');
    }

    private function note(string $content, string $permission = 'private'): void
    {
        DB::connection('hedgedoc')->table('Notes')->insert(compact('content', 'permission'));
    }

    public function test_all_permissions_duplicates_and_soft_deleted_notes_are_scanned(): void
    {
        foreach (['public.png', 'private.png', 'deleted.png', 'unused.png', 'nested/unused.webp'] as $path) {
            $this->image($path);
        }
        $this->note('![public](/uploads/public.png)', 'freely');
        $this->note('![private](/uploads/private.png)');
        $this->note('![same](/uploads/public.png)');
        DB::connection('hedgedoc')->table('Notes')->insert([
            'content' => '![deleted](/uploads/deleted.png)', 'deletedAt' => '2026-01-01',
        ]);
        file_put_contents($this->uploads . '/other.txt', 'not an image');
        $scan = app(UnusedMedia::class)->scan();
        $this->assertSame(4, $scan->notes);
        $this->assertSame(5, $scan->images);
        $this->assertSame(['nested/unused.webp', 'unused.png'], array_keys($scan->unused));
        $this->assertSame(44, $scan->bytes());
    }

    public function test_markdown_html_srcset_encoded_and_sized_images(): void
    {
        $paths = ['inline.png', 'ref.png', 'collapsed.png', 'shortcut.png', 'html.png', 'one.png', 'two.png', 'nested/a b#.png', 'size.png', 'relative.png', 'comma,name.png'];
        foreach ($paths as $path) {
            $this->image($path);
        }
        $this->note(<<<'MD'
![inline](https://notes.example.test/uploads/inline.png?download=1#fragment)
![reference][asset]

[asset]: /uploads/ref.png "title"

![collapsed][]

[collapsed]: /uploads/collapsed.png

![shortcut]

[shortcut]: /uploads/shortcut.png

<img src="//notes.example.test/uploads/html.png">
<picture><source srcset="/uploads/one.png 1x, /uploads/two.png 2x"></picture>

![encoded](uploads/nested/a%20b%23.png?x=1#other)
![sized](/uploads/size.png =380x240)
![relative](./uploads/relative.png)
<img srcset="/uploads/comma,name.png 1x">
MD);
        $this->assertSame([], app(UnusedMedia::class)->scan()->unused);
    }

    public function test_external_hosts_and_code_examples_do_not_reference_local_uploads(): void
    {
        $this->image('unused.png');
        $this->note(<<<'MD'
![external](https://evil.example/uploads/unused.png)
![lookalike](https://notes.example.test.evil.test/uploads/unused.png)
`![code](/uploads/unused.png)`
```
<img src="/uploads/unused.png">
```
MD);
        $this->assertCount(1, app(UnusedMedia::class)->scan()->unused);
    }

    public function test_base_path_and_traversal_normalization(): void
    {
        $extractor = app(ImageReferences::class);
        $base = 'https://notes.example.test/wiki';
        $this->assertSame('nested/image.png', $extractor->normalize('uploads/nested/image.png', $base));
        $this->assertSame('image.png', $extractor->normalize('/wiki/uploads/nested/../image.png', $base));
        $this->assertSame('image.png', $extractor->normalize('http://notes.example.test:3001/wiki/uploads/image.png?x=1#fragment', $base));
        $this->assertNull($extractor->normalize('/wiki/uploads/%2e%2e/secret.png', $base));
        $this->assertNull($extractor->normalize('/uploads/image.png', $base));
        $this->expectException(MediaException::class);
        $extractor->normalize('/wiki/uploads/%00.png', $base);
    }

    public function test_symbolic_file_and_directory_links_abort_cleanup(): void
    {
        $this->image('keep.png');
        symlink($this->uploads . '/keep.png', $this->uploads . '/link.png');
        $this->artisan('media:cleanup', ['--force' => true])->assertFailed();
        $this->assertFileExists($this->uploads . '/keep.png');
        unlink($this->uploads . '/link.png');
        mkdir($this->uploads . '/directory');
        symlink($this->uploads . '/directory', $this->uploads . '/linked-directory');
        $this->artisan('media:cleanup', ['--force' => true])->assertFailed();
        unlink($this->uploads . '/linked-directory');
    }

    public function test_missing_uploads_database_and_parser_errors_never_delete(): void
    {
        $this->image('keep.png');
        config(['services.hedgedoc.uploads_path' => $this->uploads . '/missing']);
        $this->artisan('media:cleanup', ['--force' => true])->assertFailed();
        config(['services.hedgedoc.uploads_path' => $this->uploads]);
        $reader = $this->mock(NoteReader::class);
        $reader->shouldReceive('each')->once()->andThrow(new RuntimeException('PRIVATE CONTENT'));
        $this->artisan('media:cleanup', ['--force' => true])
            ->doesntExpectOutputToContain('PRIVATE CONTENT')->assertFailed();
        $this->app->forgetInstance(NoteReader::class);
        $this->note("invalid UTF-8 \xff");
        $this->artisan('media:cleanup', ['--force' => true])->assertFailed();
        $this->assertFileExists($this->uploads . '/keep.png');
    }

    public function test_unused_command_displays_public_urls_counts_and_space(): void
    {
        $this->image('a b.png');
        $this->note('A private note with no images.');
        $this->artisan('media:unused')
            ->expectsOutput('Notes scanned: 1')
            ->expectsOutput('Images scanned: 1')
            ->expectsOutput('Unused images: 1')
            ->expectsOutput('Reclaimable: 22 B')
            ->expectsOutput('https://notes.example.test/uploads/a%20b.png')
            ->assertSuccessful();
    }

    public function test_confirmation_cancellation_and_permanent_deletion(): void
    {
        $this->image('unused.png');
        $this->image('used.png');
        $this->note('![keep](/uploads/used.png)');
        $this->artisan('media:cleanup')->expectsConfirmation('Permanently delete these images?', 'no')->assertSuccessful();
        $this->assertFileExists($this->uploads . '/unused.png');
        $this->artisan('media:cleanup')->expectsConfirmation('Permanently delete these images?', 'yes')->assertSuccessful();
        $this->assertFileDoesNotExist($this->uploads . '/unused.png');
        $this->assertFileExists($this->uploads . '/used.png');
    }

    public function test_noninteractive_requires_force(): void
    {
        $this->image('unused.png');
        $this->artisan('media:cleanup', ['--no-interaction' => true])->assertFailed();
        $this->assertFileExists($this->uploads . '/unused.png');
        $this->artisan('media:cleanup', ['--no-interaction' => true, '--force' => true])->assertSuccessful();
        $this->assertFileDoesNotExist($this->uploads . '/unused.png');
    }

    public function test_references_and_file_changes_after_confirmation_are_not_deleted(): void
    {
        $this->image('now-used.png');
        $this->image('changed.png');
        $media = app(UnusedMedia::class);
        $scan = $media->scan();
        $this->note('![new reference](/uploads/now-used.png)');
        file_put_contents($this->uploads . '/changed.png', 'changed data');
        $this->assertSame(['changed.png' => false, 'now-used.png' => false], $media->delete($scan));
        $this->assertFileExists($this->uploads . '/now-used.png');
        $this->assertFileExists($this->uploads . '/changed.png');
    }

    public function test_deletion_failure_is_reported(): void
    {
        $this->image('unused.png');
        $files = \Mockery::mock(Filesystem::class)->makePartial();
        $files->shouldReceive('delete')->once()->andReturn(false);
        $this->app->instance(UnusedMedia::class, new UnusedMedia(new NoteReader(), new ImageReferences(), $files));
        $this->artisan('media:cleanup', ['--force' => true])
            ->expectsOutput('Deleted: 0; failed or skipped: 1.')->assertFailed();
        $this->assertFileExists($this->uploads . '/unused.png');
    }

    public function test_actual_database_failure_aborts_without_deleting(): void
    {
        $this->image('keep.png');
        Schema::connection('hedgedoc')->drop('Notes');
        $this->artisan('media:cleanup', ['--force' => true])->assertFailed();
        $this->assertFileExists($this->uploads . '/keep.png');
    }

    public function test_failure_after_processing_some_notes_aborts_without_deleting(): void
    {
        $this->image('keep.png');
        $this->app->instance(NoteReader::class, new class extends NoteReader {
            public function each(callable $consume): int
            {
                $consume('![keep](/uploads/keep.png)');
                throw new RuntimeException('private database failure');
            }
        });
        $this->artisan('media:cleanup', ['--force' => true])->assertFailed();
        $this->assertFileExists($this->uploads . '/keep.png');
    }

    public function test_root_symlink_and_traversing_root_are_rejected(): void
    {
        $this->image('keep.png');
        $link = $this->uploads . '-link';
        symlink($this->uploads, $link);
        try {
            config(['services.hedgedoc.uploads_path' => $link]);
            $this->artisan('media:cleanup', ['--force' => true])->assertFailed();
        } finally {
            unlink($link);
        }
        config(['services.hedgedoc.uploads_path' => $this->uploads . '/../' . basename($this->uploads)]);
        $this->artisan('media:cleanup', ['--force' => true])->assertFailed();
        $this->assertFileExists($this->uploads . '/keep.png');
    }

    public function test_filesystem_inspection_failure_aborts_without_deleting(): void
    {
        $this->image('keep.png');
        $files = \Mockery::mock(Filesystem::class)->makePartial();
        $files->shouldReceive('size')->once()->andThrow(new RuntimeException('filesystem read failure'));
        $this->app->instance(UnusedMedia::class, new UnusedMedia(new NoteReader(), new ImageReferences(), $files));
        $this->artisan('media:cleanup', ['--force' => true])->assertFailed();
        $this->assertFileExists($this->uploads . '/keep.png');
    }

    public function test_post_confirmation_scan_failure_prevents_all_deletion(): void
    {
        $this->image('keep.png');
        $media = app(UnusedMedia::class);
        $scan = $media->scan();
        Schema::connection('hedgedoc')->drop('Notes');
        try {
            $media->delete($scan);
            $this->fail('Expected the fresh database scan to fail.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertFileExists($this->uploads . '/keep.png');
        }
    }
}
