<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Throwable;

class SystemBackupController extends Controller
{
    private function directory(): string
    {
        $path = storage_path('app/private/system-backups');
        if (! is_dir($path)) {
            mkdir($path, 0700, true);
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
        $process->setTimeout(600);
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

    public function index()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $files = collect(glob($this->directory().'/*') ?: [])
            ->filter(fn ($path) => is_file($path) && preg_match('/^aec-(mysql|sqlite)-[0-9]{8}-[0-9]{6}\\.(sql|sqlite)$/', basename($path)))
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
                    return back()->with('error', 'SQLite database file was not found.');
                }
                if (! copy($source, $target)) {
                    throw new \RuntimeException('Could not copy SQLite database.');
                }
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
        abort_unless(basename($name) === $name && preg_match('/^aec-(mysql|sqlite)-[0-9]{8}-[0-9]{6}\\.(sql|sqlite)$/', $name), 404);
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
        if ($data['backup']->getClientOriginalExtension() !== $extension) {
            return back()->with('error', 'Backup file type does not match the configured database driver.');
        }

        $uploaded = $data['backup']->getRealPath();
        $directory = $this->directory();
        $safetyName = 'aec-'.$connection.'-'.now()->format('Ymd-His').'.'.$extension;
        $safetyPath = $directory.'/'.$safetyName;

        try {
            if ($connection === 'sqlite') {
                $current = config('database.connections.sqlite.database');
                if (! is_file($current) || ! copy($current, $safetyPath)) {
                    throw new \RuntimeException('Could not create pre-restore SQLite safety backup.');
                }
                Artisan::call('down', ['--retry' => 60]);
                DB::disconnect();
                try {
                    if (! copy($uploaded, $current)) {
                        throw new \RuntimeException('Could not replace SQLite database file.');
                    }
                } finally {
                    DB::purge();
                    Artisan::call('up');
                }
            } elseif ($connection === 'mysql') {
                $this->dumpMysqlToFile($safetyPath);

                Artisan::call('down', ['--retry' => 60]);
                try {
                    $process = new Process([
                        'mysql', '--host='.config('database.connections.mysql.host'),
                        '--port='.config('database.connections.mysql.port'),
                        '--user='.config('database.connections.mysql.username'),
                        config('database.connections.mysql.database'),
                    ], base_path(), ['MYSQL_PWD' => (string) config('database.connections.mysql.password')]);
                    $input = fopen($uploaded, 'rb');
                    if ($input === false) {
                        throw new \RuntimeException('Could not read uploaded SQL backup.');
                    }
                    $process->setInput($input);
                    $process->setTimeout(600);
                    $process->run();
                    fclose($input);
                    if (! $process->isSuccessful()) {
                        throw new \RuntimeException('MySQL restore process failed.');
                    }
                } finally {
                    Artisan::call('up');
                }
            } else {
                return back()->with('error', 'This database driver is not supported by restore yet.');
            }
        } catch (Throwable $exception) {
            report($exception);
            if (is_file($safetyPath)) {
                return back()->with('error', 'Restore failed. A pre-restore safety backup was saved as '.$safetyName.'. Verify the database before allowing normal use.');
            }
            return back()->with('error', 'Restore failed before the database was changed. No safety backup could be confirmed.');
        }

        return back()->with('success', 'Database restored. Pre-restore safety backup: '.$safetyName);
    }
}
