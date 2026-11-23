<?php

namespace App\Domain\Admin\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatabaseBackupService
{
    /**
     * Core application tables included in portable snapshots.
     *
     * @var list<string>
     */
    private const TABLES = [
        'categories',
        'products',
        'product_images',
        'product_reviews',
        'campaign_contents',
        'customer_addresses',
        'wishlist_items',
        'orders',
        'order_items',
        'order_status_histories',
        'payments',
        'payment_events',
        'stock_movements',
        'account_audit_logs',
        'admin_audit_logs',
    ];

    /**
     * Create a checksummed (and optionally AES-256 encrypted) backup in private storage.
     *
     * @return array{
     *     filename: string,
     *     encrypted: bool,
     *     checksum_sha256: string,
     *     total_rows: int,
     *     row_counts: array<string, int>,
     *     created_at: string
     * }
     */
    public function createBackup(bool $encrypt = true): array
    {
        $tablesData = [];
        $rowCounts = [];
        $totalRows = 0;

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->orderBy('id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();

            $tablesData[$table] = $rows;
            $count = count($rows);
            $rowCounts[$table] = $count;
            $totalRows += $count;
        }

        $tablesJson = json_encode($tablesData, JSON_THROW_ON_ERROR);
        $checksum = hash('sha256', $tablesJson);
        $createdAt = now()->utc()->toIso8601String();

        $snapshot = [
            'version' => '1.0',
            'created_at' => $createdAt,
            'checksum_sha256' => $checksum,
            'row_counts' => $rowCounts,
            'tables' => $tablesData,
        ];

        $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        $storedContent = $encrypt
            ? json_encode([
                'encrypted' => true,
                'created_at' => $createdAt,
                'ciphertext' => Crypt::encryptString($snapshotJson),
            ], JSON_THROW_ON_ERROR)
            : $snapshotJson;

        $timestamp = now()->utc()->format('Ymd_His_u');
        $extension = $encrypt ? 'json.enc' : 'json';
        $filename = "xhopy_backup_{$timestamp}.{$extension}";

        Storage::disk('local')->put("backups/{$filename}", $storedContent);

        return [
            'filename' => $filename,
            'encrypted' => $encrypt,
            'checksum_sha256' => $checksum,
            'total_rows' => $totalRows,
            'row_counts' => $rowCounts,
            'created_at' => $createdAt,
        ];
    }

    /**
     * Verify a backup's encryption, SHA-256 checksum, row counts, and optionally run a non-destructive transactional restore check.
     *
     * @return array{
     *     verified: bool,
     *     encrypted: bool,
     *     checksum_sha256: string,
     *     total_rows: int,
     *     tables_verified: int,
     *     restore_tested: bool
     * }
     */
    public function verifyBackup(string $filename, bool $runRestoreSimulation = false): array
    {
        $path = 'backups/'.basename($filename);

        if (! Storage::disk('local')->exists($path)) {
            throw new RuntimeException("Backup file [{$filename}] was not found in private storage.");
        }

        $rawContent = (string) Storage::disk('local')->get($path);
        /** @var mixed $outer */
        $outer = json_decode($rawContent, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($outer)) {
            throw new RuntimeException('Backup payload is not a valid JSON object.');
        }

        $isEncrypted = ($outer['encrypted'] ?? false) === true;

        if ($isEncrypted) {
            $ciphertext = $outer['ciphertext'] ?? null;
            if (! is_string($ciphertext) || $ciphertext === '') {
                throw new RuntimeException('Encrypted backup is missing ciphertext.');
            }

            $decryptedJson = Crypt::decryptString($ciphertext);
            /** @var mixed $snapshot */
            $snapshot = json_decode($decryptedJson, true, 512, JSON_THROW_ON_ERROR);
        } else {
            $snapshot = $outer;
        }

        if (! is_array($snapshot) || ! isset($snapshot['tables'], $snapshot['checksum_sha256'], $snapshot['row_counts'])) {
            throw new RuntimeException('Backup snapshot structure is invalid.');
        }

        if (! is_array($snapshot['tables']) || ! is_array($snapshot['row_counts']) || ! is_string($snapshot['checksum_sha256'])) {
            throw new RuntimeException('Backup metadata fields are malformed.');
        }

        $computedChecksum = hash('sha256', json_encode($snapshot['tables'], JSON_THROW_ON_ERROR));

        if (! hash_equals($snapshot['checksum_sha256'], $computedChecksum)) {
            throw new RuntimeException('Backup SHA-256 checksum mismatch: file may be corrupted or tampered.');
        }

        $totalRows = 0;
        $tablesVerified = 0;

        foreach ($snapshot['tables'] as $tableName => $rows) {
            if (! is_string($tableName) || ! is_array($rows)) {
                throw new RuntimeException('Invalid table entry in backup snapshot.');
            }

            $expectedCount = (int) ($snapshot['row_counts'][$tableName] ?? -1);
            if (count($rows) !== $expectedCount) {
                throw new RuntimeException("Row count mismatch in backup table [{$tableName}].");
            }

            $totalRows += count($rows);
            $tablesVerified++;
        }

        $restoreTested = false;

        if ($runRestoreSimulation) {
            DB::beginTransaction();

            try {
                foreach ($snapshot['tables'] as $tableName => $rows) {
                    if (! Schema::hasTable($tableName)) {
                        throw new RuntimeException("Target database is missing required table [{$tableName}].");
                    }

                    $existingColumns = Schema::getColumnListing($tableName);

                    foreach ($rows as $row) {
                        if (! is_array($row)) {
                            throw new RuntimeException("Malformed row in table [{$tableName}].");
                        }

                        foreach (array_keys($row) as $column) {
                            if (! in_array((string) $column, $existingColumns, true)) {
                                throw new RuntimeException("Backup column [{$tableName}.{$column}] does not exist in target schema.");
                            }
                        }
                    }
                }

                $restoreTested = true;
            } finally {
                DB::rollBack();
            }
        }

        return [
            'verified' => true,
            'encrypted' => $isEncrypted,
            'checksum_sha256' => $snapshot['checksum_sha256'],
            'total_rows' => $totalRows,
            'tables_verified' => $tablesVerified,
            'restore_tested' => $restoreTested,
        ];
    }
}
