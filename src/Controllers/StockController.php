<?php

namespace WarehouseStock\Controllers;

use WarehouseStock\Helpers\ResponseHelper;

final class StockController extends BaseController
{
    public function stockIn(): void
    {
        $data = $this->input();
        $itemId = $this->intValue($data, 'item_id');
        $quantity = $this->intValue($data, 'quantity');

        if ($itemId <= 0) {
            ResponseHelper::error('item_id is required');
            return;
        }

        if ($quantity <= 0) {
            ResponseHelper::error('quantity must be > 0');
            return;
        }

        $item = $this->findById('db_items', $itemId);
        if ($item === null) {
            ResponseHelper::error('Item not found', 404);
            return;
        }

        try {
            $this->db->beginTransaction();

            $stockBefore = (int) $item['quantity'];
            $stockAfter = $stockBefore + $quantity;

            $movement = $this->insertMovement([
                ':item_id' => $itemId,
                ':stock_in_transaction_id' => null,
                ':stock_out_transaction_id' => null,
                ':movement_type' => 'IN',
                ':quantity' => $quantity,
                ':stock_before' => $stockBefore,
                ':stock_after' => $stockAfter,
                ':requester_name' => null,
                ':department_id' => null,
                ':purpose' => null,
                ':notes' => $this->stringOrNull($data, 'notes'),
                ':requested_at' => null,
                ':received_at' => $this->stringOrNull($data, 'received_at'),
                ':created_by' => $this->stringOrNull($data, 'created_by'),
            ]);

            $this->updateItemQuantity($itemId, $stockAfter);
            $this->logActivity('STOCK_IN', 'db_stock_movements', (int) $movement['id'], null, $movement, $this->stringOrNull($data, 'created_by'));

            $this->db->commit();

            ResponseHelper::success([
                'item_id' => $itemId,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'received_at' => $movement['received_at'],
                'movement_date' => $movement['movement_date'],
            ], 'Stock in recorded successfully');
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            ResponseHelper::error('Failed to record stock in', 500);
        }
    }

    public function stockInBulk(): void
    {
        $data = $this->input();
        $items = $data['items'] ?? [];

        if (!is_array($items) || count($items) === 0) {
            ResponseHelper::error('items are required');
            return;
        }

        $requestedByItem = [];
        foreach ($items as $line) {
            if (!is_array($line)) {
                ResponseHelper::error('Each item line must be an object');
                return;
            }

            $itemId = (int) ($line['item_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($itemId <= 0 || $quantity <= 0) {
                ResponseHelper::error('Each item must have item_id and quantity > 0');
                return;
            }

            $requestedByItem[$itemId] = ($requestedByItem[$itemId] ?? 0) + $quantity;
        }

        $normalizedItems = [];
        foreach ($requestedByItem as $itemId => $quantity) {
            $item = $this->findById('db_items', (int) $itemId);
            if ($item === null) {
                ResponseHelper::error('Item not found: ' . $itemId, 404);
                return;
            }

            $stockBefore = (int) $item['quantity'];
            $normalizedItems[] = [
                'item' => $item,
                'item_id' => (int) $itemId,
                'quantity' => (int) $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore + (int) $quantity,
            ];
        }

        try {
            $this->db->beginTransaction();

            $transaction = $this->insertStockInTransaction([
                ':transaction_no' => $this->generateTransactionNo('SI'),
                ':source_name' => $this->stringOrNull($data, 'source_name'),
                ':received_at' => $this->stringOrNull($data, 'received_at'),
                ':notes' => $this->stringOrNull($data, 'notes'),
                ':created_by' => $this->stringOrNull($data, 'created_by'),
            ]);

            $movementRows = [];
            $lineRows = [];

            foreach ($normalizedItems as $line) {
                $this->insertStockInTransactionItem([
                    ':transaction_id' => (int) $transaction['id'],
                    ':item_id' => $line['item_id'],
                    ':quantity' => $line['quantity'],
                    ':stock_before' => $line['stock_before'],
                    ':stock_after' => $line['stock_after'],
                ]);

                $lineRows[] = [
                    'item_id' => $line['item_id'],
                    'item_name' => $line['item']['item_name'],
                    'quantity' => $line['quantity'],
                    'stock_before' => $line['stock_before'],
                    'stock_after' => $line['stock_after'],
                ];

                $movement = $this->insertMovement([
                    ':item_id' => $line['item_id'],
                    ':stock_in_transaction_id' => (int) $transaction['id'],
                    ':stock_out_transaction_id' => null,
                    ':movement_type' => 'IN',
                    ':quantity' => $line['quantity'],
                    ':stock_before' => $line['stock_before'],
                    ':stock_after' => $line['stock_after'],
                    ':requester_name' => null,
                    ':department_id' => null,
                    ':purpose' => null,
                    ':notes' => $this->stringOrNull($data, 'notes'),
                    ':requested_at' => null,
                    ':received_at' => $this->stringOrNull($data, 'received_at'),
                    ':created_by' => $this->stringOrNull($data, 'created_by'),
                ]);
                $movementRows[] = $movement;

                $this->updateItemQuantity($line['item_id'], $line['stock_after']);
            }

            $payload = [
                'transaction' => $transaction,
                'items' => $lineRows,
                'movements' => $movementRows,
            ];

            $this->logActivity('STOCK_IN_BULK', 'db_stock_in_transactions', (int) $transaction['id'], null, $payload, $this->stringOrNull($data, 'created_by'));

            $this->db->commit();

            ResponseHelper::success($payload, 'Bulk stock in recorded successfully', 201);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            ResponseHelper::error('Failed to record bulk stock in', 500);
        }
    }

    public function stockOut(): void
    {
        $data = $this->input();
        $itemId = $this->intValue($data, 'item_id');
        $quantity = $this->intValue($data, 'quantity');
        $requesterName = $this->stringOrNull($data, 'requester_name');
        $departmentId = $this->intValue($data, 'department_id');

        if ($itemId <= 0) {
            ResponseHelper::error('item_id is required');
            return;
        }

        if ($quantity <= 0) {
            ResponseHelper::error('quantity must be > 0');
            return;
        }

        if ($requesterName === null) {
            ResponseHelper::error('requester_name is required');
            return;
        }

        $item = $this->findById('db_items', $itemId);
        if ($item === null) {
            ResponseHelper::error('Item not found', 404);
            return;
        }

        if ($departmentId > 0 && $this->findById('db_departments', $departmentId) === null) {
            ResponseHelper::error('Department not found', 404);
            return;
        }

        $stockBefore = (int) $item['quantity'];
        if ($quantity > $stockBefore) {
            ResponseHelper::error('Insufficient stock', 409);
            return;
        }

        try {
            $this->db->beginTransaction();

            $stockAfter = $stockBefore - $quantity;

            $movement = $this->insertMovement([
                ':item_id' => $itemId,
                ':stock_out_transaction_id' => null,
                ':movement_type' => 'OUT',
                ':quantity' => $quantity,
                ':stock_before' => $stockBefore,
                ':stock_after' => $stockAfter,
                ':requester_name' => $requesterName,
                ':department_id' => $departmentId > 0 ? $departmentId : null,
                ':purpose' => $this->stringOrNull($data, 'purpose'),
                ':notes' => $this->stringOrNull($data, 'notes'),
                ':requested_at' => $this->stringOrNull($data, 'requested_at'),
                ':received_at' => null,
                ':created_by' => $this->stringOrNull($data, 'created_by'),
            ]);

            $this->updateItemQuantity($itemId, $stockAfter);
            $this->logActivity('STOCK_OUT', 'db_stock_movements', (int) $movement['id'], null, $movement, $this->stringOrNull($data, 'created_by'));

            $this->db->commit();

            ResponseHelper::success([
                'item_id' => $itemId,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'requested_at' => $movement['requested_at'],
                'movement_date' => $movement['movement_date'],
            ], 'Stock out recorded successfully');
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            ResponseHelper::error('Failed to record stock out', 500);
        }
    }

    public function stockOutBulk(): void
    {
        $data = $this->input();
        $requesterName = $this->stringOrNull($data, 'requester_name');
        $departmentId = $this->intValue($data, 'department_id');
        $items = $data['items'] ?? [];

        if ($requesterName === null) {
            ResponseHelper::error('requester_name is required');
            return;
        }

        if ($departmentId > 0 && $this->findById('db_departments', $departmentId) === null) {
            ResponseHelper::error('Department not found', 404);
            return;
        }

        if (!is_array($items) || count($items) === 0) {
            ResponseHelper::error('items are required');
            return;
        }

        $normalizedItems = [];
        $requestedByItem = [];

        foreach ($items as $line) {
            if (!is_array($line)) {
                ResponseHelper::error('Each item line must be an object');
                return;
            }

            $itemId = (int) ($line['item_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($itemId <= 0 || $quantity <= 0) {
                ResponseHelper::error('Each item must have item_id and quantity > 0');
                return;
            }

            $requestedByItem[$itemId] = ($requestedByItem[$itemId] ?? 0) + $quantity;
        }

        foreach ($requestedByItem as $itemId => $quantity) {
            $item = $this->findById('db_items', (int) $itemId);
            if ($item === null) {
                ResponseHelper::error('Item not found: ' . $itemId, 404);
                return;
            }

            $stockBefore = (int) $item['quantity'];
            if ($quantity > $stockBefore) {
                ResponseHelper::error('Insufficient stock for ' . $item['item_name'], 409);
                return;
            }

            $normalizedItems[] = [
                'item' => $item,
                'item_id' => (int) $itemId,
                'quantity' => (int) $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore - (int) $quantity,
            ];
        }

        try {
            $this->db->beginTransaction();

            $transactionNo = $this->generateTransactionNo();
            $transaction = $this->insertStockOutTransaction([
                ':transaction_no' => $transactionNo,
                ':requester_name' => $requesterName,
                ':department_id' => $departmentId > 0 ? $departmentId : null,
                ':purpose' => $this->stringOrNull($data, 'purpose'),
                ':requested_at' => $this->stringOrNull($data, 'requested_at'),
                ':notes' => $this->stringOrNull($data, 'notes'),
                ':created_by' => $this->stringOrNull($data, 'created_by'),
            ]);

            $movementRows = [];
            $lineRows = [];

            foreach ($normalizedItems as $line) {
                $this->insertStockOutTransactionItem([
                    ':transaction_id' => (int) $transaction['id'],
                    ':item_id' => $line['item_id'],
                    ':quantity' => $line['quantity'],
                    ':stock_before' => $line['stock_before'],
                    ':stock_after' => $line['stock_after'],
                ]);

                $lineRows[] = [
                    'item_id' => $line['item_id'],
                    'item_name' => $line['item']['item_name'],
                    'quantity' => $line['quantity'],
                    'stock_before' => $line['stock_before'],
                    'stock_after' => $line['stock_after'],
                ];

                $movement = $this->insertMovement([
                    ':item_id' => $line['item_id'],
                    ':stock_in_transaction_id' => null,
                    ':stock_out_transaction_id' => (int) $transaction['id'],
                    ':movement_type' => 'OUT',
                    ':quantity' => $line['quantity'],
                    ':stock_before' => $line['stock_before'],
                    ':stock_after' => $line['stock_after'],
                    ':requester_name' => $requesterName,
                    ':department_id' => $departmentId > 0 ? $departmentId : null,
                    ':purpose' => $this->stringOrNull($data, 'purpose'),
                    ':notes' => $this->stringOrNull($data, 'notes'),
                    ':requested_at' => $this->stringOrNull($data, 'requested_at'),
                    ':received_at' => null,
                    ':created_by' => $this->stringOrNull($data, 'created_by'),
                ]);
                $movementRows[] = $movement;

                $this->updateItemQuantity($line['item_id'], $line['stock_after']);
            }

            $payload = [
                'transaction' => $transaction,
                'items' => $lineRows,
                'movements' => $movementRows,
            ];

            $this->logActivity('STOCK_OUT_BULK', 'db_stock_out_transactions', (int) $transaction['id'], null, $payload, $this->stringOrNull($data, 'created_by'));

            $this->db->commit();

            ResponseHelper::success($payload, 'Bulk stock out recorded successfully', 201);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            ResponseHelper::error('Failed to record bulk stock out', 500);
        }
    }

    public function movements(): void
    {
        $where = [];
        $params = [];

        if (isset($_GET['item_id']) && (int) $_GET['item_id'] > 0) {
            $where[] = 'm.item_id = :item_id';
            $params[':item_id'] = (int) $_GET['item_id'];
        }

        if (isset($_GET['type']) && trim((string) $_GET['type']) !== '') {
            $where[] = 'm.movement_type = :type';
            $params[':type'] = strtoupper(trim((string) $_GET['type']));
        }

        if (isset($_GET['department_id']) && (int) $_GET['department_id'] > 0) {
            $where[] = 'm.department_id = :department_id';
            $params[':department_id'] = (int) $_GET['department_id'];
        }

        if (isset($_GET['date_from']) && trim((string) $_GET['date_from']) !== '') {
            $where[] = 'date(m.movement_date) >= date(:date_from)';
            $params[':date_from'] = trim((string) $_GET['date_from']);
        }

        if (isset($_GET['date_to']) && trim((string) $_GET['date_to']) !== '') {
            $where[] = 'date(m.movement_date) <= date(:date_to)';
            $params[':date_to'] = trim((string) $_GET['date_to']);
        }

        $sql = $this->movementSql();
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY m.movement_date DESC, m.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        ResponseHelper::success($stmt->fetchAll());
    }

    public function stockOutTransactions(): void
    {
        $sql = 'SELECT
                    t.id,
                    t.transaction_no,
                    t.requester_name,
                    t.department_id,
                    d.department_name,
                    t.purpose,
                    t.requested_at,
                    t.notes,
                    t.created_by,
                    t.created_at,
                    COUNT(ti.id) AS total_lines,
                    COALESCE(SUM(ti.quantity), 0) AS total_quantity
                FROM db_stock_out_transactions t
                LEFT JOIN db_departments d ON d.id = t.department_id
                LEFT JOIN db_stock_out_transaction_items ti ON ti.transaction_id = t.id
                GROUP BY t.id
                ORDER BY t.created_at DESC, t.id DESC';

        $stmt = $this->db->query($sql);
        ResponseHelper::success($stmt->fetchAll());
    }

    public function stockInTransactions(): void
    {
        $sql = 'SELECT
                    t.id,
                    t.transaction_no,
                    t.source_name,
                    t.received_at,
                    t.notes,
                    t.created_by,
                    t.created_at,
                    COUNT(ti.id) AS total_lines,
                    COALESCE(SUM(ti.quantity), 0) AS total_quantity
                FROM db_stock_in_transactions t
                LEFT JOIN db_stock_in_transaction_items ti ON ti.transaction_id = t.id
                GROUP BY t.id
                ORDER BY t.created_at DESC, t.id DESC';

        $stmt = $this->db->query($sql);
        ResponseHelper::success($stmt->fetchAll());
    }

    private function insertMovement(array $params): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO db_stock_movements
             (item_id, stock_in_transaction_id, stock_out_transaction_id, movement_type, quantity, stock_before, stock_after, requester_name, department_id, purpose, notes, requested_at, received_at, created_by)
             VALUES
             (:item_id, :stock_in_transaction_id, :stock_out_transaction_id, :movement_type, :quantity, :stock_before, :stock_after, :requester_name, :department_id, :purpose, :notes, :requested_at, :received_at, :created_by)'
        );
        $stmt->execute($params);

        $id = (int) $this->db->lastInsertId();
        return $this->findById('db_stock_movements', $id);
    }

    private function insertStockInTransaction(array $params): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO db_stock_in_transactions
             (transaction_no, source_name, received_at, notes, created_by)
             VALUES
             (:transaction_no, :source_name, :received_at, :notes, :created_by)'
        );
        $stmt->execute($params);

        $id = (int) $this->db->lastInsertId();
        return $this->findById('db_stock_in_transactions', $id);
    }

    private function insertStockInTransactionItem(array $params): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO db_stock_in_transaction_items
             (transaction_id, item_id, quantity, stock_before, stock_after)
             VALUES
             (:transaction_id, :item_id, :quantity, :stock_before, :stock_after)'
        );
        $stmt->execute($params);
    }

    private function insertStockOutTransaction(array $params): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO db_stock_out_transactions
             (transaction_no, requester_name, department_id, purpose, requested_at, notes, created_by)
             VALUES
             (:transaction_no, :requester_name, :department_id, :purpose, :requested_at, :notes, :created_by)'
        );
        $stmt->execute($params);

        $id = (int) $this->db->lastInsertId();
        return $this->findById('db_stock_out_transactions', $id);
    }

    private function insertStockOutTransactionItem(array $params): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO db_stock_out_transaction_items
             (transaction_id, item_id, quantity, stock_before, stock_after)
             VALUES
             (:transaction_id, :item_id, :quantity, :stock_before, :stock_after)'
        );
        $stmt->execute($params);
    }

    private function generateTransactionNo(string $prefix = 'SO'): string
    {
        return $prefix . '-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function updateItemQuantity(int $itemId, int $quantity): void
    {
        $stmt = $this->db->prepare('UPDATE db_items SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute([':quantity' => $quantity, ':id' => $itemId]);
    }

    private function movementSql(): string
    {
        return 'SELECT
                    m.id,
                    m.item_id,
                    m.stock_in_transaction_id,
                    m.stock_out_transaction_id,
                    i.item_name,
                    m.movement_type,
                    m.quantity,
                    m.stock_before,
                    m.stock_after,
                    m.requester_name,
                    m.department_id,
                    d.department_name,
                    m.purpose,
                    m.notes,
                    m.requested_at,
                    m.received_at,
                    m.movement_date,
                    m.created_by,
                    m.created_at
                FROM db_stock_movements m
                INNER JOIN db_items i ON i.id = m.item_id
                LEFT JOIN db_departments d ON d.id = m.department_id';
    }
}
