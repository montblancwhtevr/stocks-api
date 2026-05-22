<?php

namespace WarehouseStock\Controllers;

use WarehouseStock\Helpers\ResponseHelper;

final class ItemController extends BaseController
{
    public function index(): void
    {
        $where = [];
        $params = [];

        if (isset($_GET['search']) && trim((string) $_GET['search']) !== '') {
            $where[] = '(item_name LIKE :search OR description LIKE :search)';
            $params[':search'] = '%' . trim((string) $_GET['search']) . '%';
        }

        if (isset($_GET['category_name']) && trim((string) $_GET['category_name']) !== '') {
            $where[] = 'category_name = :category_name';
            $params[':category_name'] = trim((string) $_GET['category_name']);
        }

        $sql = 'SELECT id, item_name, category_name, unit, quantity, minimum_stock, description, created_at, updated_at FROM db_items';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY item_name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        ResponseHelper::success($stmt->fetchAll());
    }

    public function create(): void
    {
        $data = $this->input();
        $errors = $this->validate($data);
        if ($errors !== []) {
            ResponseHelper::error('Validation failed', 422, $errors);
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO db_items (item_name, category_name, unit, quantity, minimum_stock, description)
             VALUES (:item_name, :category_name, :unit, :quantity, :minimum_stock, :description)'
        );
        $stmt->execute($this->params($data));

        $id = (int) $this->db->lastInsertId();
        $item = $this->findById('db_items', $id);
        $this->logActivity('CREATE', 'db_items', $id, null, $item, $data['created_by'] ?? null);

        ResponseHelper::success($item, 'Item created successfully', 201);
    }

    public function update(int $id): void
    {
        $existing = $this->findById('db_items', $id);
        if ($existing === null) {
            ResponseHelper::error('Item not found', 404);
            return;
        }

        $data = array_merge($existing, $this->input());
        $errors = $this->validate($data);
        if ($errors !== []) {
            ResponseHelper::error('Validation failed', 422, $errors);
            return;
        }

        $params = $this->params($data);
        $params[':id'] = $id;

        $stmt = $this->db->prepare(
            'UPDATE db_items
             SET item_name = :item_name,
                 category_name = :category_name,
                 unit = :unit,
                 quantity = :quantity,
                 minimum_stock = :minimum_stock,
                 description = :description,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute($params);

        $updated = $this->findById('db_items', $id);
        $this->logActivity('UPDATE', 'db_items', $id, $existing, $updated, $data['created_by'] ?? null);

        ResponseHelper::success($updated, 'Item updated successfully');
    }

    public function delete(int $id): void
    {
        $existing = $this->findById('db_items', $id);
        if ($existing === null) {
            ResponseHelper::error('Item not found', 404);
            return;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM db_stock_movements WHERE item_id = :id');
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            ResponseHelper::error('Cannot delete item with stock movement history', 409);
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM db_items WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $this->logActivity('DELETE', 'db_items', $id, $existing, null);

        ResponseHelper::success(null, 'Item deleted successfully');
    }

    private function validate(array $data): array
    {
        $errors = [];
        $itemName = trim((string) ($data['item_name'] ?? ''));
        $categoryName = isset($data['category_name']) ? trim((string) $data['category_name']) : null;
        $unit = trim((string) ($data['unit'] ?? 'pcs'));
        $quantity = (int) ($data['quantity'] ?? 0);
        $minimumStock = (int) ($data['minimum_stock'] ?? 0);

        if ($itemName === '') {
            $errors['item_name'] = 'item_name is required';
        } elseif (strlen($itemName) > 150) {
            $errors['item_name'] = 'item_name max length is 150';
        }

        if ($categoryName !== null && strlen($categoryName) > 150) {
            $errors['category_name'] = 'category_name max length is 150';
        }

        if ($unit === '') {
            $errors['unit'] = 'unit cannot be empty';
        } elseif (strlen($unit) > 50) {
            $errors['unit'] = 'unit max length is 50';
        }

        if ($quantity < 0) {
            $errors['quantity'] = 'quantity must be >= 0';
        }

        if ($minimumStock < 0) {
            $errors['minimum_stock'] = 'minimum_stock must be >= 0';
        }

        return $errors;
    }

    private function params(array $data): array
    {
        return [
            ':item_name' => trim((string) $data['item_name']),
            ':category_name' => $this->stringOrNull($data, 'category_name'),
            ':unit' => $this->stringOrNull($data, 'unit') ?? 'pcs',
            ':quantity' => (int) ($data['quantity'] ?? 0),
            ':minimum_stock' => (int) ($data['minimum_stock'] ?? 0),
            ':description' => $this->stringOrNull($data, 'description'),
        ];
    }
}
