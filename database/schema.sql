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
    stock_out_transaction_id INTEGER NULL,
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
    FOREIGN KEY (stock_out_transaction_id) REFERENCES db_stock_out_transactions(id),
    FOREIGN KEY (department_id) REFERENCES db_departments(id)
);

CREATE TABLE IF NOT EXISTS db_stock_out_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_no VARCHAR(100) NOT NULL UNIQUE,
    requester_name VARCHAR(150) NOT NULL,
    department_id INTEGER NULL,
    purpose TEXT NULL,
    requested_at DATETIME NULL,
    notes TEXT NULL,
    created_by VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES db_departments(id)
);

CREATE TABLE IF NOT EXISTS db_stock_out_transaction_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    stock_before INTEGER NOT NULL,
    stock_after INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES db_stock_out_transactions(id),
    FOREIGN KEY (item_id) REFERENCES db_items(id)
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
CREATE INDEX IF NOT EXISTS idx_stock_out_transactions_requested_at ON db_stock_out_transactions(requested_at);
CREATE INDEX IF NOT EXISTS idx_stock_out_transactions_department_id ON db_stock_out_transactions(department_id);
CREATE INDEX IF NOT EXISTS idx_stock_out_transaction_items_transaction_id ON db_stock_out_transaction_items(transaction_id);
CREATE INDEX IF NOT EXISTS idx_stock_out_transaction_items_item_id ON db_stock_out_transaction_items(item_id);
