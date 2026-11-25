<?php

use App\Models\Ebook;
use App\Models\Lesson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Move lesson attachments and e-book documents out of the public web root.
     * Keep database paths stable so existing records continue to resolve.
     */
    public function up(): void
    {
        $this->moveToPrivateStorage(Lesson::query(), 'attachment');
        $this->moveToPrivateStorage(Ebook::query(), 'file');
    }

    public function down(): void
    {
        $this->moveToPublicStorage(Lesson::query(), 'attachment');
        $this->moveToPublicStorage(Ebook::query(), 'file');
    }

    private function moveToPrivateStorage($query, string $column): void
    {
        $query->whereNotNull($column)->orderBy('id')->chunkById(100, function ($records) use ($column) {
            foreach ($records as $record) {
                $path = $record->{$column};
                if (! Storage::disk('local')->exists($path) && Storage::disk('public')->exists($path)) {
                    $stream = Storage::disk('public')->readStream($path);
                    if ($stream === false) {
                        throw new RuntimeException("Unable to read public learning file: {$path}");
                    }

                    $written = Storage::disk('local')->writeStream($path, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    if (! $written) {
                        throw new RuntimeException("Unable to secure learning file: {$path}");
                    }
                }

                if (Storage::disk('local')->exists($path) && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        });
    }

    private function moveToPublicStorage($query, string $column): void
    {
        $query->whereNotNull($column)->orderBy('id')->chunkById(100, function ($records) use ($column) {
            foreach ($records as $record) {
                $path = $record->{$column};
                if (! Storage::disk('public')->exists($path) && Storage::disk('local')->exists($path)) {
                    $stream = Storage::disk('local')->readStream($path);
                    if ($stream !== false) {
                        Storage::disk('public')->writeStream($path, $stream);
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                }
            }
        });
    }
};
