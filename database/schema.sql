PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS db_departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    department_name VARCHAR(150) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS db_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_name VARCHAR(150) NOT NULL,
    category_name VARCHAR(150) NULL,
    unit VARCHAR(50) DEFAULT 'pcs',
    quantity INTEGER NOT NULL DEFAULT 0,
    minimum_stock INTEGER DEFAULT 0,
    description TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS db_stock_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    movement_type VARCHAR(20) NOT NULL,
    quantity INTEGER NOT NULL,
    stock_before INTEGER NOT NULL,
    stock_after INTEGER NOT NULL,
    requester_name VARCHAR(150) NULL,
    department_id INTEGER NULL,
    purpose TEXT NULL,
    notes TEXT NULL,
    requested_at DATETIME NULL,
    received_at DATETIME NULL,
    movement_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES db_items(id),
    FOREIGN KEY (department_id) REFERENCES db_departments(id)
);

CREATE TABLE IF NOT EXISTS db_activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_name VARCHAR(100) NULL,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INTEGER NULL,
    old_data TEXT NULL,
    new_data TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_stock_movements_item_id ON db_stock_movements(item_id);
CREATE INDEX IF NOT EXISTS idx_stock_movements_type ON db_stock_movements(movement_type);
CREATE INDEX IF NOT EXISTS idx_stock_movements_department_id ON db_stock_movements(department_id);
CREATE INDEX IF NOT EXISTS idx_stock_movements_movement_date ON db_stock_movements(movement_date);
CREATE INDEX IF NOT EXISTS idx_stock_movements_requested_at ON db_stock_movements(requested_at);
CREATE INDEX IF NOT EXISTS idx_items_category_name ON db_items(category_name);
CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON db_activity_logs(created_at);
