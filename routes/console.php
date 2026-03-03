<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use App\Support\VisitReminders;
use App\Support\Audit;
use App\Support\Dsar;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reminders:send', function () {
    VisitReminders::run();
})->purpose('Send upcoming visit reminders via push');

Artisan::command('audit:prune {--days=}', function () {
    $daysOpt = $this->option('days');
    $days = is_numeric($daysOpt) ? (int) $daysOpt : (int) env('AUDIT_LOG_RETENTION_DAYS', 365);
    if ($days <= 0) {
        $this->warn('AUDIT_LOG_RETENTION_DAYS is <= 0, nothing to prune.');
        return 0;
    }
    $deleted = Audit::prune($days);
    $this->info("Pruned {$deleted} audit logs older than {$days} days.");
    return 0;
})->purpose('Prune audit logs by retention policy');

Artisan::command('dsar:prune {--days=}', function () {
    $daysOpt = $this->option('days');
    $days = is_numeric($daysOpt) ? (int) $daysOpt : (int) env('DSAR_EXPORT_RETENTION_DAYS', 14);
    if ($days <= 0) {
        $this->warn('DSAR_EXPORT_RETENTION_DAYS is <= 0, nothing to prune.');
        return 0;
    }
    $pruned = Dsar::pruneExports($days);
    $this->info("Pruned {$pruned} DSAR export payload(s) older than {$days} days.");
    return 0;
})->purpose('Prune stored DSAR exports by retention policy');

Artisan::command('backup:verify-mysql {--file=}', function () {
    $backupDir = (string) env('BACKUP_DIR', '/backups/mysql');
    $fileOpt = $this->option('file');
    $file = is_string($fileOpt) && $fileOpt !== '' ? $fileOpt : null;

    if ($file === null) {
        $candidates = glob(rtrim($backupDir, '/').DIRECTORY_SEPARATOR.'*.sql.gz') ?: [];
        rsort($candidates);
        $file = $candidates[0] ?? null;
    }

    if ($file === null || !is_file($file)) {
        $message = 'MySQL restore check skipped: backup file not found.';
        Log::channel('backup')->warning($message, ['file' => $file, 'backup_dir' => $backupDir]);
        $this->warn($message);
        return 1;
    }

    $host = (string) env('BACKUP_VERIFY_DB_HOST', env('DB_HOST', 'mysql'));
    $port = (int) env('BACKUP_VERIFY_DB_PORT', env('DB_PORT', 3306));
    $user = (string) env('BACKUP_VERIFY_DB_USER', env('DB_USERNAME', 'root'));
    $pass = (string) env('BACKUP_VERIFY_DB_PASSWORD', env('DB_PASSWORD', ''));
    $verifyDb = 'restore_check_'.date('Ymd_His').'_'.bin2hex(random_bytes(2));

    $mysqlBase = 'mysql -h '.escapeshellarg($host).' -P '.$port.' -u'.escapeshellarg($user);
    if ($pass !== '') {
        $mysqlBase .= ' -p'.escapeshellarg($pass);
    }

    $run = function (string $cmd): array {
        $output = [];
        $exit = 0;
        exec($cmd.' 2>&1', $output, $exit);
        return [$exit, trim(implode("\n", $output))];
    };

    [$createExit, $createOut] = $run($mysqlBase.' -e '.escapeshellarg("CREATE DATABASE `{$verifyDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"));
    if ($createExit !== 0) {
        Log::channel('backup')->error('MySQL restore check FAILED at DB create.', [
            'file' => $file,
            'verify_db' => $verifyDb,
            'error' => $createOut,
        ]);
        $this->error('Restore check failed on CREATE DATABASE.');
        return 1;
    }

    try {
        [$importExit, $importOut] = $run('gunzip -c '.escapeshellarg($file).' | '.$mysqlBase.' '.escapeshellarg($verifyDb));
        if ($importExit !== 0) {
            Log::channel('backup')->error('MySQL restore check FAILED at import.', [
                'file' => $file,
                'verify_db' => $verifyDb,
                'error' => $importOut,
            ]);
            $this->error('Restore check failed on import.');
            return 1;
        }

        config([
            'database.connections.restore_check' => [
                'driver' => 'mysql',
                'host' => $host,
                'port' => $port,
                'database' => $verifyDb,
                'username' => $user,
                'password' => $pass,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ],
        ]);

        $conn = DB::connection('restore_check');
        $tableCount = count($conn->select('SHOW TABLES'));

        if ($tableCount === 0) {
            Log::channel('backup')->error('MySQL restore check FAILED: restored DB has no tables.', [
                'file' => $file,
                'verify_db' => $verifyDb,
            ]);
            $this->error('Restore check failed: no tables after restore.');
            return 1;
        }

        $criticalTables = ['users', 'clients', 'services', 'visits', 'audit_logs', 'dsar_operations'];
        $counts = [];
        foreach ($criticalTables as $table) {
            if (Schema::connection('restore_check')->hasTable($table)) {
                $counts[$table] = $conn->table($table)->count();
            }
        }

        Log::channel('backup')->info('MySQL restore check PASS.', [
            'file' => $file,
            'verify_db' => $verifyDb,
            'table_count' => $tableCount,
            'sample_counts' => $counts,
            'checked_at' => now()->toDateTimeString(),
        ]);

        $this->info('Restore check PASS.');
        return 0;
    } catch (\Throwable $e) {
        Log::channel('backup')->error('MySQL restore check FAILED with exception.', [
            'file' => $file,
            'verify_db' => $verifyDb,
            'exception' => $e->getMessage(),
        ]);
        $this->error('Restore check failed with exception.');
        return 1;
    } finally {
        DB::purge('restore_check');
        [$dropExit, $dropOut] = $run($mysqlBase.' -e '.escapeshellarg("DROP DATABASE IF EXISTS `{$verifyDb}`"));
        if ($dropExit !== 0) {
            Log::channel('backup')->warning('MySQL restore check cleanup warning: failed to drop temp DB.', [
                'verify_db' => $verifyDb,
                'error' => $dropOut,
            ]);
        }
    }
})->purpose('Restore latest MySQL backup into temp DB and verify it');

Schedule::command('reminders:send')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('audit:prune')
    ->dailyAt('03:20')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('dsar:prune')
    ->dailyAt('03:35')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('backup:verify-mysql')
    ->dailyAt('04:10')
    ->withoutOverlapping()
    ->runInBackground();
