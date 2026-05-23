<?php

namespace WarehouseStock\Database;

use PDO;
use RuntimeException;
use WarehouseStock\Helpers\Env;

final class Connection
{
    private static $pdo;

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $rootPath = dirname(__DIR__, 2);
        $dbPath = Env::get('DB_PATH', 'database/warehouse.sqlite');
        $fullPath = self::isAbsolutePath($dbPath) ? $dbPath : $rootPath . DIRECTORY_SEPARATOR . $dbPath;
        $directory = dirname($fullPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('PDO SQLite driver is not installed. Enable pdo_sqlite or install php-sqlite3.');
        }

        self::$pdo = new PDO('sqlite:' . $fullPath);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->exec('PRAGMA foreign_keys = ON');

        self::migrate(self::$pdo, $rootPath);

        return self::$pdo;
    }

    private static function migrate(PDO $pdo, string $rootPath): void
    {
        $schemaPath = $rootPath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
        if (!is_file($schemaPath)) {
            return;
        }

        $schema = file_get_contents($schemaPath);
        if ($schema !== false) {
            $pdo->exec($schema);
        }

        self::addColumnIfMissing($pdo, 'db_stock_movements', 'stock_in_transaction_id', 'INTEGER NULL');
        self::addColumnIfMissing($pdo, 'db_stock_movements', 'stock_out_transaction_id', 'INTEGER NULL');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_stock_movements_stock_in_transaction_id ON db_stock_movements(stock_in_transaction_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_stock_movements_stock_out_transaction_id ON db_stock_movements(stock_out_transaction_id)');
    }

    private static function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
    {
        $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
        $columns = $stmt === false ? [] : $stmt->fetchAll();

        foreach ($columns as $existingColumn) {
            if (($existingColumn['name'] ?? '') === $column) {
                return;
            }
        }

        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    private static function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || strpos($path, DIRECTORY_SEPARATOR) === 0;
    }
}
