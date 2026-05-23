import React, { useEffect, useMemo, useState } from 'react';
import { apiRequest, clearToken, getSettings, saveSettings } from './api.js';

const tabs = [
  ['dashboard', 'Dashboard'],
  ['departments', 'Departments'],
  ['items', 'Items'],
  ['stock-in', 'Stock In'],
  ['stock-out', 'Stock Out'],
  ['reports', 'Reports'],
  ['logs', 'Logs'],
];

const emptyItem = {
  item_name: '',
  category_name: '',
  unit: 'pcs',
  quantity: 0,
  minimum_stock: 0,
  description: '',
  created_by: 'admin',
};

const emptyStockOut = {
  requester_name: '',
  department_id: '',
  purpose: '',
  requested_at: '',
  notes: '',
  created_by: 'admin',
  items: [{ item_id: '', quantity: 1 }],
};

export default function App() {
  const [settings, setSettings] = useState(getSettings);
  const [activeTab, setActiveTab] = useState('dashboard');
  const [status, setStatus] = useState('');
  const [error, setError] = useState('');
  const [departments, setDepartments] = useState([]);
  const [items, setItems] = useState([]);
  const [movements, setMovements] = useState([]);
  const [logs, setLogs] = useState([]);
  const [currentStock, setCurrentStock] = useState([]);
  const [stockByDepartment, setStockByDepartment] = useState([]);
  const [departmentName, setDepartmentName] = useState('');
  const [itemForm, setItemForm] = useState(emptyItem);
  const [stockIn, setStockIn] = useState({ item_id: '', quantity: 1, received_at: '', notes: '', created_by: 'admin' });
  const [stockOut, setStockOut] = useState(emptyStockOut);

  const isReady = settings.apiBaseUrl && settings.token;
  const lowStockCount = useMemo(
    () => currentStock.filter((item) => Number(item.quantity) <= Number(item.minimum_stock)).length,
    [currentStock]
  );

  useEffect(() => {
    if (isReady) {
      refreshAll();
    }
  }, []);

  async function run(action, successMessage = '') {
    setError('');
    setStatus('');
    try {
      await action();
      if (successMessage) {
        setStatus(successMessage);
      }
    } catch (err) {
      setError(err.message);
    }
  }

  async function refreshAll() {
    await run(async () => {
      const [departmentsRes, itemsRes, stockRes, movementsRes, logsRes, byDepartmentRes] = await Promise.all([
        apiRequest('/api/departments'),
        apiRequest('/api/items'),
        apiRequest('/api/reports/current-stock'),
        apiRequest('/api/stock/movements'),
        apiRequest('/api/activity-logs'),
        apiRequest('/api/reports/stock-out-by-department'),
      ]);
      setDepartments(departmentsRes.data || []);
      setItems(itemsRes.data || []);
      setCurrentStock(stockRes.data || []);
      setMovements(movementsRes.data || []);
      setLogs(logsRes.data || []);
      setStockByDepartment(byDepartmentRes.data || []);
    });
  }

  function handleSaveSettings(event) {
    event.preventDefault();
    saveSettings(settings);
    setStatus('Settings saved');
    refreshAll();
  }

  function logout() {
    clearToken();
    setSettings(getSettings());
    setStatus('Token removed');
  }

  async function createDepartment(event) {
    event.preventDefault();
    await run(async () => {
      await apiRequest('/api/departments', {
        method: 'POST',
        body: JSON.stringify({ department_name: departmentName, created_by: 'admin' }),
      });
      setDepartmentName('');
      await refreshAll();
    }, 'Department created');
  }

  async function deleteDepartment(id) {
    await run(async () => {
      await apiRequest(`/api/departments/${id}`, { method: 'DELETE' });
      await refreshAll();
    }, 'Department deleted');
  }

  async function createItem(event) {
    event.preventDefault();
    await run(async () => {
      await apiRequest('/api/items', { method: 'POST', body: JSON.stringify(itemForm) });
      setItemForm(emptyItem);
      await refreshAll();
    }, 'Item created');
  }

  async function deleteItem(id) {
    await run(async () => {
      await apiRequest(`/api/items/${id}`, { method: 'DELETE' });
      await refreshAll();
    }, 'Item deleted');
  }

  async function submitStockIn(event) {
    event.preventDefault();
    await run(async () => {
      await apiRequest('/api/stock/in', {
        method: 'POST',
        body: JSON.stringify({ ...stockIn, item_id: Number(stockIn.item_id), quantity: Number(stockIn.quantity) }),
      });
      setStockIn({ item_id: '', quantity: 1, received_at: '', notes: '', created_by: 'admin' });
      await refreshAll();
    }, 'Stock in recorded');
  }

  async function submitStockOut(event) {
    event.preventDefault();
    await run(async () => {
      await apiRequest('/api/stock/out-bulk', {
        method: 'POST',
        body: JSON.stringify({
          ...stockOut,
          department_id: stockOut.department_id ? Number(stockOut.department_id) : null,
          items: stockOut.items.map((line) => ({
            item_id: Number(line.item_id),
            quantity: Number(line.quantity),
          })),
        }),
      });
      setStockOut(emptyStockOut);
      await refreshAll();
    }, 'Bulk stock out recorded');
  }

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div>
          <p className="eyebrow">Warehouse</p>
          <h1>Stock Admin</h1>
        </div>
        <nav>
          {tabs.map(([key, label]) => (
            <button key={key} className={activeTab === key ? 'active' : ''} onClick={() => setActiveTab(key)}>
              {label}
            </button>
          ))}
        </nav>
      </aside>

      <main>
        <header className="topbar">
          <form className="settings" onSubmit={handleSaveSettings}>
            <label>
              API
              <input
                value={settings.apiBaseUrl}
                onChange={(event) => setSettings({ ...settings, apiBaseUrl: event.target.value })}
                placeholder="https://api.hiada.my.id"
              />
            </label>
            <label>
              Token
              <input
                type="password"
                value={settings.token}
                onChange={(event) => setSettings({ ...settings, token: event.target.value })}
                placeholder="Bearer token"
              />
            </label>
            <button type="submit">Save</button>
            <button type="button" className="secondary" onClick={logout}>
              Clear
            </button>
          </form>
        </header>

        {status && <div className="notice success">{status}</div>}
        {error && <div className="notice error">{error}</div>}
        {!isReady && <div className="notice">Set the API host and token to load data.</div>}

        {activeTab === 'dashboard' && (
          <section>
            <div className="metrics">
              <Metric label="Items" value={items.length} />
              <Metric label="Departments" value={departments.length} />
              <Metric label="Low Stock" value={lowStockCount} />
              <Metric label="Movements" value={movements.length} />
            </div>
            <Panel title="Recent Movements">
              <MovementTable rows={movements.slice(0, 8)} />
            </Panel>
          </section>
        )}

        {activeTab === 'departments' && (
          <section className="grid-two">
            <Panel title="Create Department">
              <form onSubmit={createDepartment}>
                <input value={departmentName} onChange={(event) => setDepartmentName(event.target.value)} placeholder="Department name" />
                <button type="submit">Create</button>
              </form>
            </Panel>
            <Panel title="Departments">
              <Table
                columns={['Name', 'Created', '']}
                rows={departments.map((department) => [
                  department.department_name,
                  department.created_at,
                  <button className="danger" onClick={() => deleteDepartment(department.id)}>
                    Delete
                  </button>,
                ])}
              />
            </Panel>
          </section>
        )}

        {activeTab === 'items' && (
          <section className="grid-two wide-right">
            <Panel title="Create Item">
              <ItemForm value={itemForm} onChange={setItemForm} onSubmit={createItem} />
            </Panel>
            <Panel title="Items">
              <Table
                columns={['Item', 'Category', 'Unit', 'Qty', 'Min', '']}
                rows={items.map((item) => [
                  item.item_name,
                  item.category_name || '-',
                  item.unit,
                  item.quantity,
                  item.minimum_stock,
                  <button className="danger" onClick={() => deleteItem(item.id)}>
                    Delete
                  </button>,
                ])}
              />
            </Panel>
          </section>
        )}

        {activeTab === 'stock-in' && (
          <Panel title="Stock In">
            <StockInForm items={items} value={stockIn} onChange={setStockIn} onSubmit={submitStockIn} />
          </Panel>
        )}

        {activeTab === 'stock-out' && (
          <Panel title="Stock Out">
            <StockOutForm
              items={items}
              departments={departments}
              value={stockOut}
              onChange={setStockOut}
              onSubmit={submitStockOut}
            />
          </Panel>
        )}

        {activeTab === 'reports' && (
          <section>
            <Panel title="Current Stock">
              <Table
                columns={['Item', 'Category', 'Unit', 'Qty', 'Minimum']}
                rows={currentStock.map((item) => [item.item_name, item.category_name || '-', item.unit, item.quantity, item.minimum_stock])}
              />
            </Panel>
            <Panel title="Stock Out By Department">
              <Table
                columns={['Department', 'Total Out']}
                rows={stockByDepartment.map((row) => [row.department_name, row.total_items_out])}
              />
            </Panel>
            <Panel title="Movement History">
              <MovementTable rows={movements} />
            </Panel>
          </section>
        )}

        {activeTab === 'logs' && (
          <Panel title="Activity Logs">
            <Table
              columns={['Time', 'Admin', 'Action', 'Table', 'Record']}
              rows={logs.map((log) => [log.created_at, log.admin_name || '-', log.action, log.table_name, log.record_id || '-'])}
            />
          </Panel>
        )}
      </main>
    </div>
  );
}

function Metric({ label, value }) {
  return (
    <div className="metric">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

function Panel({ title, children }) {
  return (
    <div className="panel">
      <div className="panel-header">
        <h2>{title}</h2>
      </div>
      {children}
    </div>
  );
}

function Table({ columns, rows }) {
  if (rows.length === 0) {
    return <p className="empty">No data yet.</p>;
  }

  return (
    <div className="table-wrap">
      <table>
        <thead>
          <tr>
            {columns.map((column) => (
              <th key={column}>{column}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            <tr key={index}>
              {row.map((cell, cellIndex) => (
                <td key={cellIndex}>{cell}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function MovementTable({ rows }) {
  return (
    <Table
      columns={['Date', 'Item', 'Type', 'Qty', 'Before', 'After', 'Requester']}
      rows={rows.map((movement) => [
        movement.movement_date,
        movement.item_name,
        movement.movement_type,
        movement.quantity,
        movement.stock_before,
        movement.stock_after,
        movement.requester_name || '-',
      ])}
    />
  );
}

function ItemForm({ value, onChange, onSubmit }) {
  return (
    <form onSubmit={onSubmit}>
      <input value={value.item_name} onChange={(event) => onChange({ ...value, item_name: event.target.value })} placeholder="Item name" />
      <input
        value={value.category_name}
        onChange={(event) => onChange({ ...value, category_name: event.target.value })}
        placeholder="Category optional"
      />
      <div className="form-row">
        <input value={value.unit} onChange={(event) => onChange({ ...value, unit: event.target.value })} placeholder="Unit" />
        <input
          type="number"
          min="0"
          value={value.quantity}
          onChange={(event) => onChange({ ...value, quantity: Number(event.target.value) })}
          placeholder="Initial quantity"
        />
      </div>
      <input
        type="number"
        min="0"
        value={value.minimum_stock}
        onChange={(event) => onChange({ ...value, minimum_stock: Number(event.target.value) })}
        placeholder="Minimum stock"
      />
      <textarea
        value={value.description}
        onChange={(event) => onChange({ ...value, description: event.target.value })}
        placeholder="Description"
      />
      <button type="submit">Create Item</button>
    </form>
  );
}

function StockInForm({ items, value, onChange, onSubmit }) {
  return (
    <form className="narrow-form" onSubmit={onSubmit}>
      <SelectItem items={items} value={value.item_id} onChange={(item_id) => onChange({ ...value, item_id })} />
      <input type="number" min="1" value={value.quantity} onChange={(event) => onChange({ ...value, quantity: event.target.value })} />
      <input value={value.received_at} onChange={(event) => onChange({ ...value, received_at: event.target.value })} placeholder="YYYY-MM-DD HH:MM:SS" />
      <textarea value={value.notes} onChange={(event) => onChange({ ...value, notes: event.target.value })} placeholder="Notes" />
      <button type="submit">Record Stock In</button>
    </form>
  );
}

function StockOutForm({ items, departments, value, onChange, onSubmit }) {
  function updateLine(index, changes) {
    onChange({
      ...value,
      items: value.items.map((line, lineIndex) => (lineIndex === index ? { ...line, ...changes } : line)),
    });
  }

  function addLine() {
    onChange({
      ...value,
      items: [...value.items, { item_id: '', quantity: 1 }],
    });
  }

  function removeLine(index) {
    if (value.items.length === 1) {
      return;
    }

    onChange({
      ...value,
      items: value.items.filter((_, lineIndex) => lineIndex !== index),
    });
  }

  return (
    <form onSubmit={onSubmit}>
      <input
        value={value.requester_name}
        onChange={(event) => onChange({ ...value, requester_name: event.target.value })}
        placeholder="Requester name"
      />
      <select value={value.department_id} onChange={(event) => onChange({ ...value, department_id: event.target.value })}>
        <option value="">No department</option>
        {departments.map((department) => (
          <option key={department.id} value={department.id}>
            {department.department_name}
          </option>
        ))}
      </select>
      <input
        value={value.requested_at}
        onChange={(event) => onChange({ ...value, requested_at: event.target.value })}
        placeholder="YYYY-MM-DD HH:MM:SS"
      />
      <textarea value={value.purpose} onChange={(event) => onChange({ ...value, purpose: event.target.value })} placeholder="Purpose" />
      <textarea value={value.notes} onChange={(event) => onChange({ ...value, notes: event.target.value })} placeholder="Notes" />

      <div className="line-items">
        <div className="line-items-header">
          <strong>Requested Items</strong>
          <button type="button" className="secondary" onClick={addLine}>
            Add Item
          </button>
        </div>
        {value.items.map((line, index) => (
          <div className="line-item" key={index}>
            <SelectItem items={items} value={line.item_id} onChange={(item_id) => updateLine(index, { item_id })} />
            <input
              type="number"
              min="1"
              value={line.quantity}
              onChange={(event) => updateLine(index, { quantity: event.target.value })}
            />
            <button type="button" className="danger" onClick={() => removeLine(index)} disabled={value.items.length === 1}>
              Remove
            </button>
          </div>
        ))}
      </div>

      <button type="submit">Record Bulk Stock Out</button>
    </form>
  );
}

function SelectItem({ items, value, onChange }) {
  return (
    <select value={value} onChange={(event) => onChange(event.target.value)}>
      <option value="">Select item</option>
      {items.map((item) => (
        <option key={item.id} value={item.id}>
          {item.item_name} ({item.quantity} {item.unit})
        </option>
      ))}
    </select>
  );
}
