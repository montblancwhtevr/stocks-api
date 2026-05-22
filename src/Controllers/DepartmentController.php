<?php

namespace WarehouseStock\Controllers;

use WarehouseStock\Helpers\ResponseHelper;

final class DepartmentController extends BaseController
{
    public function index(): void
    {
        $stmt = $this->db->query('SELECT id, department_name, created_at, updated_at FROM db_departments ORDER BY department_name ASC');
        ResponseHelper::success($stmt->fetchAll());
    }

    public function create(): void
    {
        $data = $this->input();
        $name = $this->stringOrNull($data, 'department_name');

        if ($name === null) {
            ResponseHelper::error('department_name is required');
            return;
        }

        if (strlen($name) > 150) {
            ResponseHelper::error('department_name max length is 150');
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO db_departments (department_name) VALUES (:department_name)');
        $stmt->execute([':department_name' => $name]);

        $id = (int) $this->db->lastInsertId();
        $department = $this->findById('db_departments', $id);
        $this->logActivity('CREATE', 'db_departments', $id, null, $department, $data['created_by'] ?? null);

        ResponseHelper::success($department, 'Department created successfully', 201);
    }

    public function update(int $id): void
    {
        $existing = $this->findById('db_departments', $id);
        if ($existing === null) {
            ResponseHelper::error('Department not found', 404);
            return;
        }

        $data = $this->input();
        $name = $this->stringOrNull($data, 'department_name');

        if ($name === null) {
            ResponseHelper::error('department_name is required');
            return;
        }

        if (strlen($name) > 150) {
            ResponseHelper::error('department_name max length is 150');
            return;
        }

        $stmt = $this->db->prepare('UPDATE db_departments SET department_name = :department_name, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute([':department_name' => $name, ':id' => $id]);

        $updated = $this->findById('db_departments', $id);
        $this->logActivity('UPDATE', 'db_departments', $id, $existing, $updated, $data['created_by'] ?? null);

        ResponseHelper::success($updated, 'Department updated successfully');
    }

    public function delete(int $id): void
    {
        $existing = $this->findById('db_departments', $id);
        if ($existing === null) {
            ResponseHelper::error('Department not found', 404);
            return;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM db_stock_movements WHERE department_id = :id');
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            ResponseHelper::error('Cannot delete department with stock movement history', 409);
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM db_departments WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $this->logActivity('DELETE', 'db_departments', $id, $existing, null);

        ResponseHelper::success(null, 'Department deleted successfully');
    }
}
