<?php

namespace WarehouseStock\Controllers;

use WarehouseStock\Helpers\ResponseHelper;

final class ReportController extends BaseController
{
    public function currentStock(): void
    {
        $where = [];
        $params = [];

        if (isset($_GET['category_name']) && trim((string) $_GET['category_name']) !== '') {
            $where[] = 'category_name = :category_name';
            $params[':category_name'] = trim((string) $_GET['category_name']);
        }

        if (isset($_GET['low_stock']) && (string) $_GET['low_stock'] === '1') {
            $where[] = 'quantity <= minimum_stock';
        }

        $sql = 'SELECT id, item_name, category_name, unit, quantity, minimum_stock, description FROM db_items';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY item_name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        ResponseHelper::success($stmt->fetchAll());
    }

    public function stockMovement(): void
    {
        $where = [];
        $params = [];

        if (isset($_GET['date_from']) && trim((string) $_GET['date_from']) !== '') {
            $where[] = 'date(m.movement_date) >= date(:date_from)';
            $params[':date_from'] = trim((string) $_GET['date_from']);
        }

        if (isset($_GET['date_to']) && trim((string) $_GET['date_to']) !== '') {
            $where[] = 'date(m.movement_date) <= date(:date_to)';
            $params[':date_to'] = trim((string) $_GET['date_to']);
        }

        if (isset($_GET['type']) && trim((string) $_GET['type']) !== '') {
            $where[] = 'm.movement_type = :type';
            $params[':type'] = strtoupper(trim((string) $_GET['type']));
        }

        $sql = 'SELECT
                    m.id,
                    m.stock_in_transaction_id,
                    m.stock_out_transaction_id,
                    i.item_name,
                    m.movement_type,
                    m.quantity,
                    m.stock_before,
                    m.stock_after,
                    m.requester_name,
                    d.department_name,
                    m.purpose,
                    m.requested_at,
                    m.received_at,
                    m.movement_date
                FROM db_stock_movements m
                INNER JOIN db_items i ON i.id = m.item_id
                LEFT JOIN db_departments d ON d.id = m.department_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY m.movement_date DESC, m.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        ResponseHelper::success($stmt->fetchAll());
    }

    public function stockOutByDepartment(): void
    {
        $stmt = $this->db->query(
            'SELECT
                COALESCE(d.department_name, "No Department") AS department_name,
                SUM(m.quantity) AS total_items_out
             FROM db_stock_movements m
             LEFT JOIN db_departments d ON d.id = m.department_id
             WHERE m.movement_type = "OUT"
             GROUP BY d.id, d.department_name
             ORDER BY total_items_out DESC'
        );

        ResponseHelper::success($stmt->fetchAll());
    }

    public function activityLogs(): void
    {
        $stmt = $this->db->query(
            'SELECT id, admin_name, action, table_name, record_id, old_data, new_data, ip_address, user_agent, created_at
             FROM db_activity_logs
             ORDER BY created_at DESC, id DESC
             LIMIT 200'
        );

        ResponseHelper::success($stmt->fetchAll());
    }
}
