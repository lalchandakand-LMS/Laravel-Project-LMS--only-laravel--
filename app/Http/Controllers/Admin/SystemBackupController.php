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

    private function replaceSqliteDatabase(string $source, string $target): void
    {
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

        return view('admin.system.backups', compact('files'));
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
}
