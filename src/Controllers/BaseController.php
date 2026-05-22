<?php

namespace WarehouseStock\Controllers;

use PDO;

abstract class BaseController
{
    protected $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    protected function input(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    protected function stringOrNull(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        $value = trim((string) $data[$key]);
        return $value === '' ? null : $value;
    }

    protected function intValue(array $data, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return $default;
        }

        return (int) $data[$key];
    }

    protected function findById(string $table, int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    protected function logActivity(string $action, string $tableName, ?int $recordId, ?array $oldData, ?array $newData, ?string $adminName = null): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO db_activity_logs (admin_name, action, table_name, record_id, old_data, new_data, ip_address, user_agent)
                 VALUES (:admin_name, :action, :table_name, :record_id, :old_data, :new_data, :ip_address, :user_agent)'
            );

            $stmt->execute([
                ':admin_name' => $adminName,
                ':action' => $action,
                ':table_name' => $tableName,
                ':record_id' => $recordId,
                ':old_data' => $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_SLASHES),
                ':new_data' => $newData === null ? null : json_encode($newData, JSON_UNESCAPED_SLASHES),
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            error_log('Activity log failed: ' . $exception->getMessage());
        }
    }
}
