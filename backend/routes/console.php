<?php

use App\Domain\Admin\Services\DatabaseBackupService;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Inventory\Services\InventoryService;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('xhopy:user-role {email} {role=admin}', function (string $email, string $role) {
    $targetRole = UserRole::tryFrom(strtolower(trim($role)));

    if ($targetRole === null) {
        $this->error('Invalid role. Allowed roles: customer, staff, admin.');

        return 1;
    }

    $user = User::query()->where('email', strtolower(trim($email)))->first();

    if ($user === null) {
        $this->error("No user found with email [{$email}].");

        return 1;
    }

    $user->assignRole($targetRole);

    $this->info("Assigned role [{$targetRole->value}] to {$user->name} ({$user->email}).");

    return 0;
})->purpose('Assign a platform role (customer, staff, admin) to an existing user account');

Artisan::command('xhopy:backup {--unencrypted : Store plain JSON snapshot without AES-256 encryption}', function (DatabaseBackupService $backupService) {
    $encrypt = ! (bool) $this->option('unencrypted');
    $result = $backupService->createBackup($encrypt);

    $this->info("Backup created: {$result['filename']}");
    $this->line('Encrypted: '.($result['encrypted'] ? 'yes' : 'no'));
    $this->line("SHA-256: {$result['checksum_sha256']}");
    $this->line("Total rows: {$result['total_rows']}");

    return 0;
})->purpose('Create an integrity-checked (and by default AES-256 encrypted) database snapshot in private storage');

Artisan::command('xhopy:backup-verify {file : Backup filename in private storage} {--restore-test : Run non-destructive transactional restore simulation}', function (string $file, DatabaseBackupService $backupService) {
    try {
        $result = $backupService->verifyBackup($file, (bool) $this->option('restore-test'));
    } catch (Throwable $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $this->info("Backup verified: {$file}");
    $this->line("Tables verified: {$result['tables_verified']} ({$result['total_rows']} rows)");
    $this->line('Restore simulation: '.($result['restore_tested'] ? 'passed' : 'skipped'));

    return 0;
})->purpose('Verify a database snapshot checksum, encryption, and optional transactional restore integrity');

Artisan::command('xhopy:launch-check {--reconcile : Reconcile unrecorded initial stock baseline before auditing}', function (InventoryService $inventoryService) {
    $appKeySet = is_string(config('app.key')) && trim((string) config('app.key')) !== '';
    $httpOnlyCookies = (bool) config('session.http_only', false);
    $sameSitePolicy = in_array(config('session.same_site'), ['lax', 'strict'], true);
    $coreTablesReady = Schema::hasTable('products')
        && Schema::hasTable('orders')
        && Schema::hasTable('payments')
        && Schema::hasTable('stock_movements')
        && Schema::hasTable('admin_audit_logs');

    if ($coreTablesReady && (bool) $this->option('reconcile')) {
        $reconciled = $inventoryService->reconcileAll();
        $this->line("Reconciled {$reconciled} product inventory ledger baseline(s).");
    }

    $discrepancyCount = $coreTablesReady ? $inventoryService->countDiscrepancies() : 0;
    $inventoryConsistent = $coreTablesReady && $discrepancyCount === 0;

    $checks = [
        ['APP_KEY encryption key configured', $appKeySet ? 'PASS' : 'FAIL'],
        ['Session HttpOnly cookies enabled', $httpOnlyCookies ? 'PASS' : 'FAIL'],
        ['Session SameSite policy (lax/strict)', $sameSitePolicy ? 'PASS' : 'FAIL'],
        ['Core commerce migrations applied', $coreTablesReady ? 'PASS' : 'FAIL'],
        ['Inventory ledger consistency (0 discrepancies)', $inventoryConsistent ? 'PASS' : 'FAIL'],
    ];

    $this->table(['Check', 'Status'], $checks);

    $allPassed = $appKeySet && $httpOnlyCookies && $sameSitePolicy && $coreTablesReady && $inventoryConsistent;

    if (! $allPassed) {
        $this->error('One or more launch readiness checks failed.');

        return 1;
    }

    $this->info('All XHopy launch readiness checks passed.');

    return 0;
})->purpose('Run operational, security, migration, and inventory ledger readiness checks');
