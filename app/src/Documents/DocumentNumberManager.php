<?php

declare(strict_types=1);

namespace Documents;

/**
 * Allocates public order/quote numbers without deriving them from row counts.
 * Existing identifiers are never rewritten; each year/prefix combination owns
 * an independent, transaction-locked sequence.
 */
final class DocumentNumberManager
{
    private const TYPES = ['order', 'quote'];
    private static bool $schemaReady = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) return;
        if (\Database::get()->inTransaction()) {
            throw new \RuntimeException('Document number schema must be prepared before starting a transaction.');
        }
        \Database::query("CREATE TABLE IF NOT EXISTS document_number_sequences (
            document_type VARCHAR(20) NOT NULL,
            year_label VARCHAR(5) NOT NULL,
            prefix VARCHAR(12) NOT NULL,
            next_number BIGINT UNSIGNED NOT NULL DEFAULT 1,
            padding TINYINT UNSIGNED NOT NULL DEFAULT 3,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (document_type, year_label, prefix)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$schemaReady = true;
    }

    public static function defaultYearLabel(?\DateTimeInterface $date = null): string
    {
        $date ??= new \DateTimeImmutable('now', new \DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Kolkata'));
        $year = (int)$date->format('Y');
        $start = (int)$date->format('n') >= 4 ? $year : $year - 1;
        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }

    public static function config(string $type): array
    {
        self::assertType($type);
        $year = strtoupper(trim((string)\Database::setting($type . '_id_year_label', \Database::setting('document_year_label', self::defaultYearLabel()))));
        $prefix = strtoupper(trim((string)\Database::setting($type . '_id_prefix', $type === 'order' ? 'RCS' : 'CQ')));
        $padding = (int)\Database::setting($type . '_id_padding', '3');
        if (!preg_match('/^\d{2}-\d{2}$/', $year)) $year = self::defaultYearLabel();
        if (!preg_match('/^[A-Z0-9]{1,12}$/', $prefix)) $prefix = $type === 'order' ? 'RCS' : 'CQ';
        return ['year_label' => $year, 'prefix' => $prefix, 'padding' => max(2, min(8, $padding))];
    }

    public static function next(string $type, ?int $adminId = null): string
    {
        self::ensureSchema();
        $config = self::config($type);
        $pdo = \Database::get();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();

        try {
            \Database::query(
                "INSERT IGNORE INTO document_number_sequences (document_type,year_label,prefix,next_number,padding,updated_by) VALUES (?,?,?,?,?,?)",
                [$type, $config['year_label'], $config['prefix'], 1, $config['padding'], $adminId]
            );
            $row = \Database::row(
                "SELECT next_number FROM document_number_sequences WHERE document_type=? AND year_label=? AND prefix=? FOR UPDATE",
                [$type, $config['year_label'], $config['prefix']]
            );
            $number = max(1, (int)($row['next_number'] ?? 1));
            do {
                $identifier = $config['year_label'] . $config['prefix'] . str_pad((string)$number, $config['padding'], '0', STR_PAD_LEFT);
                $number++;
            } while (self::exists($type, $identifier));

            \Database::query(
                "UPDATE document_number_sequences SET next_number=?,padding=?,updated_by=? WHERE document_type=? AND year_label=? AND prefix=?",
                [$number, $config['padding'], $adminId, $type, $config['year_label'], $config['prefix']]
            );
            if ($ownsTransaction) $pdo->commit();
            return $identifier;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function preview(string $type): array
    {
        self::ensureSchema();
        $config = self::config($type);
        $row = \Database::row(
            "SELECT next_number FROM document_number_sequences WHERE document_type=? AND year_label=? AND prefix=?",
            [$type, $config['year_label'], $config['prefix']]
        );
        $next = max(1, (int)($row['next_number'] ?? 1));
        while (self::exists($type, $config['year_label'] . $config['prefix'] . str_pad((string)$next, $config['padding'], '0', STR_PAD_LEFT))) $next++;
        return $config + ['next_number' => $next, 'preview' => $config['year_label'] . $config['prefix'] . str_pad((string)$next, $config['padding'], '0', STR_PAD_LEFT)];
    }

    public static function reset(string $type, int $adminId): array
    {
        self::assertType($type);
        self::ensureSchema();
        $config = self::config($type);
        $stem = $config['year_label'] . $config['prefix'];
        $table = $type === 'order' ? 'orders' : 'custom_quote_requests';
        $column = $type === 'order' ? 'order_id' : 'request_code';
        $used = \Database::row("SELECT COUNT(*) AS total FROM {$table} WHERE {$column} LIKE ?", [$stem . '%']);
        if ((int)($used['total'] ?? 0) > 0) {
            return ['ok' => false, 'msg' => 'This year and prefix already have issued IDs. Reset was blocked to prevent duplicate IDs. Change the year or prefix first.'];
        }
        \Database::query(
            "INSERT INTO document_number_sequences (document_type,year_label,prefix,next_number,padding,updated_by) VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE next_number=1,padding=VALUES(padding),updated_by=VALUES(updated_by)",
            [$type, $config['year_label'], $config['prefix'], 1, $config['padding'], $adminId]
        );
        return ['ok' => true, 'msg' => ucfirst($type) . ' sequence reset to 1.'];
    }

    private static function exists(string $type, string $identifier): bool
    {
        return $type === 'order'
            ? (bool)\Database::row("SELECT 1 FROM orders WHERE order_id=? LIMIT 1", [$identifier])
            : (bool)\Database::row("SELECT 1 FROM custom_quote_requests WHERE request_code=? LIMIT 1", [$identifier]);
    }

    private static function assertType(string $type): void
    {
        if (!in_array($type, self::TYPES, true)) throw new \InvalidArgumentException('Unsupported document type.');
    }
}
