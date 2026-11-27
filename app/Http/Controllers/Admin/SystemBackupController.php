<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class SystemBackupController extends Controller
{
    private function directory(): string
    {
        $path = storage_path('app/private/system-backups');
        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new \RuntimeException('Could not create the private backup directory.');
        }
        @chmod($path, 0700);

        return $path;
    }

    private function dumpMysqlToFile(string $target): void
    {
        $process = new Process([
            'mysqldump', '--single-transaction', '--quick', '--no-tablespaces',
            '--host='.config('database.connections.mysql.host'),
            '--port='.config('database.connections.mysql.port'),
            '--user='.config('database.connections.mysql.username'),
            config('database.connections.mysql.database'),
        ], base_path(), ['MYSQL_PWD' => (string) config('database.connections.mysql.password')]);
        $process->setTimeout(900);
        $handle = fopen($target, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Could not create backup file.');
        }

        try {
            $process->run(function ($type, $buffer) use ($handle) {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                }
            });
        } finally {
            fclose($handle);
        }

        if (! $process->isSuccessful() || ! is_file($target) || filesize($target) === 0) {
            @unlink($target);
            throw new \RuntimeException('mysqldump failed or returned an empty backup.');
        }

        chmod($target, 0600);
    }

    private function restoreMysqlFromFile(string $source): void
    {
        $process = new Process([
            'mysql',
            '--host='.config('database.connections.mysql.host'),
            '--port='.config('database.connections.mysql.port'),
            '--user='.config('database.connections.mysql.username'),
            config('database.connections.mysql.database'),
        ], base_path(), ['MYSQL_PWD' => (string) config('database.connections.mysql.password')]);
        $process->setTimeout(900);
        $input = fopen($source, 'rb');
        if ($input === false) {
            throw new \RuntimeException('Could not read SQL backup file.');
        }

        try {
            $process->setInput($input);
            $process->run();
        } finally {
            fclose($input);
        }

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('MySQL import failed.');
        }
    }

    private function checkpointSqlite(): void
    {
        DB::connection('sqlite')->statement('PRAGMA wal_checkpoint(FULL)');
    }

    private function verifySqliteBackupFile(string $source): void
    {
        try {
            $pdo = new \PDO('sqlite:'.$source);
            $integrity = $pdo->query('PRAGMA quick_check')->fetchColumn();
            if ($integrity !== 'ok') {
                throw new \RuntimeException('The SQLite backup failed its integrity check.');
            }

            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN);
            foreach (['migrations', 'users', 'courses', 'orders'] as $table) {
                if (! in_array($table, $tables, true)) {
                    throw new \RuntimeException('The SQLite backup is missing required LMS table: '.$table);
                }
            }
        } catch (Throwable $exception) {
            if ($exception instanceof \RuntimeException) {
                throw $exception;
            }

            throw new \RuntimeException('The uploaded SQLite backup is invalid or unreadable.', 0, $exception);
        }
    }

    private function replaceSqliteDatabase(string $source, string $target): void
    {
        // Validate before disconnecting or replacing the live database.
        $this->verifySqliteBackupFile($source);
        DB::disconnect('sqlite');
        $temporary = dirname($target).'/.'.basename($target).'.restore-'.Str::random(8);

        if (! copy($source, $temporary)) {
            @unlink($temporary);
            throw new \RuntimeException('Could not stage the SQLite database file.');
        }

        @chmod($temporary, 0600);
        @unlink($target.'-wal');
        @unlink($target.'-shm');

        if (! rename($temporary, $target)) {
            @unlink($temporary);
            throw new \RuntimeException('Could not atomically replace the SQLite database.');
        }

        DB::purge('sqlite');
        DB::connection('sqlite')->getPdo();
        DB::connection('sqlite')->select('select 1');
        $this->verifyDatabase();
    }

    private function verifyDatabase(): void
    {
        DB::purge();
        DB::connection()->getPdo();
        DB::select('select 1');

        foreach (['migrations', 'users', 'courses', 'orders'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException('Restored database is missing required LMS table: '.$table);
            }
        }
    }

    public function index()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $files = collect(glob($this->directory().'/*') ?: [])
            ->filter(fn ($path) => is_file($path) && preg_match('/^aec-(mysql|sqlite)-[0-9]{8}-[0-9]{6}\.(sql|sqlite)$/', basename($path)))
            ->map(fn ($path) => ['name' => basename($path), 'size' => filesize($path), 'date' => filemtime($path)])
            ->sortByDesc('date')->values();

        $mediaFiles = collect(glob(base_path('storage/backups/*')) ?: [])
            ->filter(fn ($path) => is_file($path) && ! is_link($path) && preg_match('/^(uploads|private)-[0-9]{8}-[0-9]{6}\\.tar\\.gz$/', basename($path)))
            ->map(fn ($path) => ['name' => basename($path), 'kind' => str_starts_with(basename($path), 'uploads-') ? 'Public uploads' : 'Private uploads', 'size' => filesize($path), 'date' => filemtime($path)])
            ->sortByDesc('date')->values();

        return view('admin.system.backups', compact('files', 'mediaFiles'));
    }

    public function create()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $connection = config('database.default');
        $stamp = now()->format('Ymd-His');
        $directory = $this->directory();
        $name = 'aec-'.$connection.'-'.$stamp.($connection === 'sqlite' ? '.sqlite' : '.sql');
        $target = $directory.'/'.$name;

        try {
            if ($connection === 'sqlite') {
                $source = config('database.connections.sqlite.database');
                if (! is_file($source)) {
                    return back()->with('error', 'SQLite database file was not found. In-memory databases cannot be backed up from this panel.');
                }
                $this->checkpointSqlite();
                if (! copy($source, $target)) {
                    throw new \RuntimeException('Could not copy SQLite database.');
                }
                chmod($target, 0600);
            } elseif ($connection === 'mysql') {
                $this->dumpMysqlToFile($target);
            } else {
                return back()->with('error', 'This database driver is not supported by the backup manager yet.');
            }
        } catch (Throwable $exception) {
            report($exception);
            @unlink($target);
            return back()->with('error', 'Backup failed. Check server logs and database backup permissions.');
        }

        return back()->with('success', 'Safety backup created: '.$name);
    }

    public function download(string $name)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_unless(basename($name) === $name && preg_match('/^aec-(mysql|sqlite)-[0-9]{8}-[0-9]{6}\.(sql|sqlite)$/', $name), 404);
        $path = $this->directory().'/'.$name;
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }

    public function restore(Request $request)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $data = $request->validate([
            'backup' => ['required', 'file', 'max:262144'],
            'confirmation' => ['required', 'in:RESTORE'],
        ]);

        $connection = config('database.default');
        $extension = $connection === 'sqlite' ? 'sqlite' : 'sql';
        if (strtolower($data['backup']->getClientOriginalExtension()) !== $extension) {
            return back()->with('error', 'Backup file type does not match the configured database driver.');
        }

        $uploaded = $data['backup']->getRealPath();
        $directory = $this->directory();
        $safetyName = 'aec-'.$connection.'-'.now()->format('Ymd-His').'.'.$extension;
        $safetyPath = $directory.'/'.$safetyName;
        $maintenance = false;
        $snapshotReady = false;

        try {
            if (! in_array($connection, ['sqlite', 'mysql'], true)) {
                return back()->with('error', 'This database driver is not supported by restore yet.');
            }

            if (Artisan::call('down', ['--retry' => 60]) !== 0) {
                throw new \RuntimeException('Could not enable maintenance mode.');
            }
            $maintenance = true;

            // Freeze application writes before taking the rollback snapshot.
            if ($connection === 'sqlite') {
                $current = config('database.connections.sqlite.database');
                if (! is_file($current)) {
                    throw new \RuntimeException('The configured SQLite database file is unavailable.');
                }
                $this->checkpointSqlite();
                if (! copy($current, $safetyPath)) {
                    throw new \RuntimeException('Could not create pre-restore SQLite safety backup.');
                }
                chmod($safetyPath, 0600);
            } else {
                $this->dumpMysqlToFile($safetyPath);
            }
            $snapshotReady = true;

            if ($connection === 'sqlite') {
                $this->replaceSqliteDatabase($uploaded, $current);
            } else {
                DB::disconnect();
                $this->restoreMysqlFromFile($uploaded);
                $this->verifyDatabase();
            }

            if (Artisan::call('up') !== 0) {
                throw new \RuntimeException('Database restore completed but the site could not leave maintenance mode.');
            }
            $maintenance = false;
        } catch (Throwable $exception) {
            report($exception);

            if ($maintenance && $snapshotReady) {
                try {
                    if ($connection === 'sqlite') {
                        $this->replaceSqliteDatabase($safetyPath, $current);
                    } else {
                        DB::disconnect();
                        $this->restoreMysqlFromFile($safetyPath);
                        $this->verifyDatabase();
                    }

                    if (Artisan::call('up') !== 0) {
                        throw new \RuntimeException('Could not bring the site online after the original database was restored.');
                    }
                    $maintenance = false;

                    return back()->with('error', 'Restore failed. The original database was automatically restored from safety backup '.$safetyName.'.');
                } catch (Throwable $rollbackException) {
                    report($rollbackException);
                    return back()->with('error', 'Restore and automatic rollback both failed. The site is intentionally left in maintenance mode. Safety backup: '.$safetyName.'. Contact hosting support before bringing it online.');
                }
            }

            if ($maintenance && ! $snapshotReady) {
                @unlink($safetyPath);
                if (Artisan::call('up') === 0) {
                    $maintenance = false;
                    return back()->with('error', 'Restore stopped before a safety snapshot was complete. The live database was not changed and the site is back online.');
                }
                return back()->with('error', 'Restore stopped before changing data, but the site could not leave maintenance mode. Contact hosting support.');
            }

            if ($maintenance) {
                return back()->with('error', 'Restore failed. The site remains in maintenance mode. Safety backup: '.$safetyName.'.');
            }

            @unlink($safetyPath);
            return back()->with('error', 'Restore failed before the live database was changed. No data was replaced.');
        }

        return back()->with('success', 'Database restored and verified. Pre-restore safety backup: '.$safetyName);
    }

    /**
     * Restore one of the media archives created by backup.sh.
     *
     * Archives are validated before extraction: only regular files/directories
     * under the expected public/ or private/ prefix are accepted. Symlinks,
     * hard links, absolute paths and parent traversal are rejected.
     */
    public function restoreMedia(Request $request)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'regex:/^(uploads|private)-[0-9]{8}-[0-9]{6}\.tar\.gz$/'],
            'confirmation' => ['required', 'in:RESTORE MEDIA'],
        ]);

        $name = $data['name'];
        $source = base_path('storage/backups/'.$name);
        abort_unless(is_file($source) && ! is_link($source), 404);

        $kind = str_starts_with($name, 'uploads-') ? 'public' : 'private';
        $storageRoot = storage_path('app');
        $target = $storageRoot.'/'.$kind;
        $stamp = now()->format('Ymd-His');
        $safetyName = 'aec-media-'.$kind.'-'.$stamp.'.tar.gz';
        $safetyPath = $this->directory().'/'.$safetyName;
        $temporary = $this->directory().'/.media-restore-'.Str::random(12);
        $stagedRoot = $temporary.'/'.$kind;

        try {
            $this->validateMediaArchive($source, $kind);

            // Keep a private rollback archive before changing any live media.
            $tar = new Process(
                ['tar', '--exclude=private/system-backups', '-czf', $safetyPath, '-C', $storageRoot, $kind],
                base_path()
            );
            $tar->setTimeout(900);
            $tar->run();
            if (! $tar->isSuccessful() || ! is_file($safetyPath) || filesize($safetyPath) === 0) {
                throw new \RuntimeException('Could not create a pre-restore media safety backup.');
            }
            chmod($safetyPath, 0600);

            if (! mkdir($temporary, 0700, true) && ! is_dir($temporary)) {
                throw new \RuntimeException('Could not create a temporary restore directory.');
            }

            $extract = new Process(
                ['tar', '--no-same-owner', '--no-same-permissions', '-xzf', $source, '-C', $temporary],
                base_path()
            );
            $extract->setTimeout(900);
            $extract->run();
            if (! $extract->isSuccessful() || ! is_dir($stagedRoot)) {
                throw new \RuntimeException('The media archive could not be extracted.');
            }

            $this->copyRestoredMedia($stagedRoot, $target, $kind === 'private');
            return back()->with('success', ucfirst($kind).' media restored from '.$name.'. Existing matching paths were overwritten; unrelated files were kept. Safety backup: '.$safetyName);
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', 'Media restore failed. No archive contents were accepted unless validation completed. Check server logs before retrying.');
        } finally {
            if (is_dir($temporary)) {
                $this->removeRestoreDirectory($temporary);
            }
        }
    }

    private function validateMediaArchive(string $archive, string $expectedRoot): void
    {
        $listing = new Process(['tar', '-tzf', $archive], base_path());
        $listing->setTimeout(120);
        $listing->run();
        if (! $listing->isSuccessful()) {
            throw new \RuntimeException('The selected media archive is damaged or unreadable.');
        }

        $entries = preg_split('/\r?\n/', trim($listing->getOutput())) ?: [];
        if ($entries === [] || count($entries) > 100000) {
            throw new \RuntimeException('The media archive is empty or contains too many entries.');
        }

        foreach ($entries as $entry) {
            $path = str_starts_with($entry, './') ? substr($entry, 2) : $entry;
            $parts = explode('/', rtrim($path, '/'));
            if ($path === '' || str_starts_with($path, '/') || str_contains($path, chr(92)) ||
                in_array('..', $parts, true) || $parts[0] !== $expectedRoot) {
                throw new \RuntimeException('The archive contains an unsafe or unexpected path.');
            }
        }

        // Reject symbolic and hard links before extraction; tar -tv starts
        // regular files with "-" and directories with "d".
        $verbose = new Process(['tar', '-tvzf', $archive], base_path());
        $verbose->setTimeout(120);
        $verbose->run();
        if (! $verbose->isSuccessful()) {
            throw new \RuntimeException('Could not inspect media archive entries.');
        }
        foreach (preg_split('/\r?\n/', trim($verbose->getOutput())) ?: [] as $line) {
            if ($line !== '' && ! in_array($line[0], ['-', 'd'], true)) {
                throw new \RuntimeException('Links and special files are not allowed in media backups.');
            }
        }
    }

    private function copyRestoredMedia(string $source, string $target, bool $preserveSystemBackups): void
    {
        if (is_link($target)) {
            throw new \RuntimeException('Media destination cannot be a symbolic link.');
        }
        if (! is_dir($target) && ! mkdir($target, 0750, true) && ! is_dir($target)) {
            throw new \RuntimeException('Could not access the media destination directory.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            if ($preserveSystemBackups && ($relative === 'system-backups' || str_starts_with($relative, 'system-backups/'))) {
                continue;
            }
            if ($item->isLink()) {
                throw new \RuntimeException('Symbolic links are not allowed in restored media.');
            }

            $destination = $target.'/'.$relative;
            $cursor = $target;
            foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
                $cursor .= DIRECTORY_SEPARATOR.$segment;
                if (is_link($cursor)) {
                    throw new \RuntimeException('A media destination path is a symbolic link.');
                }
            }
            if ($item->isDir()) {
                if (! is_dir($destination) && ! mkdir($destination, 0750, true) && ! is_dir($destination)) {
                    throw new \RuntimeException('Could not create a media directory.');
                }
                continue;
            }
            if (! $item->isFile()) {
                throw new \RuntimeException('Only regular files can be restored.');
            }

            $parent = dirname($destination);
            if (! is_dir($parent) && ! mkdir($parent, 0750, true) && ! is_dir($parent)) {
                throw new \RuntimeException('Could not create a media destination directory.');
            }
            if (! copy($item->getPathname(), $destination)) {
                throw new \RuntimeException('A media file could not be restored.');
            }
            @chmod($destination, 0640);
        }
    }

    private function removeRestoreDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }

}
