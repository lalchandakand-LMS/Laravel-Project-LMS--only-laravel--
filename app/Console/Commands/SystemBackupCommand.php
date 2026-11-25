<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class SystemBackupCommand extends Command
{
    protected $signature = 'system:backup {--keep=14 : Number of dated backups to retain}';
    protected $description = 'Create a private database backup and prune old backup files';

    public function handle(): int
    {
        $connection = config('database.default');
        $directory = storage_path('app/private/system-backups');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error('Could not create private backup directory.');
            return self::FAILURE;
        }
        @chmod($directory, 0700);

        $name = 'aec-'.$connection.'-'.now()->format('Ymd-His').($connection === 'sqlite' ? '.sqlite' : '.sql');
        $target = $directory.'/'.$name;

        try {
            if ($connection === 'sqlite') {
                $source = config('database.connections.sqlite.database');
                if (! is_file($source) || ! copy($source, $target)) {
                    throw new \RuntimeException('SQLite database file could not be copied.');
                }
            } elseif ($connection === 'mysql') {
                $process = new Process([
                    'mysqldump', '--single-transaction', '--quick', '--no-tablespaces',
                    '--host='.config('database.connections.mysql.host'),
                    '--port='.config('database.connections.mysql.port'),
                    '--user='.config('database.connections.mysql.username'),
                    config('database.connections.mysql.database'),
                ], base_path(), ['MYSQL_PWD' => (string) config('database.connections.mysql.password')]);
                $process->setTimeout(600);
                $handle = fopen($target, 'wb');
                if (! $handle) {
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
                if (! $process->isSuccessful() || filesize($target) === 0) {
                    throw new \RuntimeException('mysqldump failed or returned an empty file.');
                }
                chmod($target, 0600);
            } else {
                throw new \RuntimeException('Unsupported database driver: '.$connection);
            }
        } catch (\Throwable $exception) {
            @unlink($target);
            report($exception);
            $this->error('Backup failed: '.$exception->getMessage());
            return self::FAILURE;
        }

        $keep = max(1, min(90, (int) $this->option('keep')));
        $files = glob($directory.'/aec-'.$connection.'-*'.($connection === 'sqlite' ? '.sqlite' : '.sql')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }

        if (DB::getSchemaBuilder()->hasTable('activity_logs')) {
            DB::table('activity_logs')->where('created_at', '<', now()->subDays(180))->delete();
        }

        $this->info('Database backup created: '.$name);
        return self::SUCCESS;
    }
}
