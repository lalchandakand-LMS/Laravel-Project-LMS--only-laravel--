<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Secure learning documents without risking the only existing copy.
     *
     * Files are copied and verified before the public copy is deleted. Missing
     * files or failed copies stop the migration so deployment can be repaired.
     */
    public function up(): void
    {
        $this->moveToPrivateStorage(\Illuminate\Support\Facades\DB::table('lessons'), 'attachment');
        $this->moveToPrivateStorage(DB::table('ebooks'), 'file');
    }

    /**
     * Restore public copies on rollback. Keep private copies as a safety net.
     */
    public function down(): void
    {
        $this->restorePublicCopies(\Illuminate\Support\Facades\DB::table('lessons'), 'attachment');
        $this->restorePublicCopies(\Illuminate\Support\Facades\DB::table('ebooks'), 'file');
    }

    private function moveToPrivateStorage($query, string $column): void
    {
        $query->whereNotNull($column)->orderBy('id')->chunkById(100, function ($records) use ($column) {
            foreach ($records as $record) {
                $path = $record->{$column};
                $private = Storage::disk('local');
                $public = Storage::disk('public');

                $privateExists = $private->exists($path);
                $publicExists = $public->exists($path);

                if (! $privateExists && ! $publicExists) {
                    throw new RuntimeException("Learning file is missing from both disks: {$path}");
                }

                if (! $privateExists && $publicExists) {
                    $source = $public->readStream($path);
                    if (! is_resource($source)) {
                        throw new RuntimeException("Unable to read public learning file: {$path}");
                    }

                    try {
                        $written = $private->writeStream($path, $source);
                    } finally {
                        fclose($source);
                    }

                    if (! $written || ! $private->exists($path)) {
                        throw new RuntimeException("Unable to copy learning file to private storage: {$path}");
                    }
                }

                // Verify the private copy before removing any publicly accessible copy.
                if ($publicExists && $this->fileHash($private, $path) !== $this->fileHash($public, $path)) {
                    throw new RuntimeException("Learning file verification failed; public copy retained: {$path}");
                }

                if ($publicExists && ! $public->delete($path) && $public->exists($path)) {
                    throw new RuntimeException("Unable to remove public learning file after verification: {$path}");
                }

                if (! $private->exists($path)) {
                    throw new RuntimeException("Private learning file is unavailable after migration: {$path}");
                }
            }
        });
    }

    private function restorePublicCopies($query, string $column): void
    {
        $query->whereNotNull($column)->orderBy('id')->chunkById(100, function ($records) use ($column) {
            foreach ($records as $record) {
                $path = $record->{$column};
                $private = Storage::disk('local');
                $public = Storage::disk('public');

                if (! $private->exists($path) && ! $public->exists($path)) {
                    throw new RuntimeException("Cannot restore missing learning file: {$path}");
                }

                if (! $public->exists($path)) {
                    $source = $private->readStream($path);
                    if (! is_resource($source)) {
                        throw new RuntimeException("Unable to read private learning file for rollback: {$path}");
                    }

                    try {
                        $written = $public->writeStream($path, $source);
                    } finally {
                        fclose($source);
                    }

                    if (! $written || ! $public->exists($path)) {
                        throw new RuntimeException("Unable to restore public learning file: {$path}");
                    }
                }

                if ($private->exists($path) && $this->fileHash($private, $path) !== $this->fileHash($public, $path)) {
                    throw new RuntimeException("Rollback verification failed; both copies retained: {$path}");
                }
            }
        });
    }

    private function fileHash($disk, string $path): string
    {
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read learning file for verification: {$path}");
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);
            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }
};
