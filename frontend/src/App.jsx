import React, { useEffect, useMemo, useState } from 'react';
import { API_BASE_URL, apiRequest, clearSession, getStoredSession, loginRequest, saveSession } from './api.js';

const tabs = [
  ['dashboard', 'Dashboard', '▦'],
  ['departments', 'Departments', '▥'],
  ['items', 'Items', '▤'],
  ['stock-in', 'Stock In', '↙'],
  ['stock-out', 'Stock Out', '↗'],
  ['reports', 'Reports', '▧'],
  ['logs', 'Logs', '↺'],
];

const emptyItem = {
  item_name: '',
  category_name: '',
  unit: 'pcs',
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

const emptyStockIn = {
  source_name: '',
  received_at: '',
  notes: '',
  created_by: 'admin',
  items: [{ item_id: '', quantity: 1 }],
};

function toApiDateTime(value) {
  if (!value) {
    return '';
  }

  return value.length === 16 ? `${value.replace('T', ' ')}:00` : value.replace('T', ' ');
}

function fromDateTimeParts(date, hour, minute) {
  if (!date) {
    return '';
  }

  return `${date}T${hour || '00'}:${minute || '00'}`;
}

function toDateTimeParts(value) {
  if (!value) {
    return { date: '', hour: '00', minute: '00' };
  }

  const [date, time = '00:00'] = value.split('T');
  const [hour = '00', minute = '00'] = time.split(':');

  return {
    date,
    hour: hour.padStart(2, '0'),
    minute: minute.padStart(2, '0'),
  };
}

export default function App() {
  const [session, setSession] = useState(getStoredSession);
  const [loginForm, setLoginForm] = useState({ username: '', password: '' });
  const [loginLoading, setLoginLoading] = useState(false);
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
  const [itemFilters, setItemFilters] = useState({ search: '', category: '', lowStockOnly: false });
  const [stockIn, setStockIn] = useState(emptyStockIn);
  const [stockOut, setStockOut] = useState(emptyStockOut);

  const isReady = API_BASE_URL && session.token;
  const lowStockCount = useMemo(
    () => currentStock.filter((item) => Number(item.quantity) <= Number(item.minimum_stock)).length,
    [currentStock]
  );
  const itemCategories = useMemo(
    () => Array.from(new Set(items.map((item) => item.category_name).filter(Boolean))).sort(),
    [items]
  );
  const filteredItems = useMemo(() => {
    const search = itemFilters.search.trim().toLowerCase();

    return items.filter((item) => {
      const matchesSearch =
        search === '' ||
        item.item_name.toLowerCase().includes(search) ||
        (item.description || '').toLowerCase().includes(search);
      const matchesCategory = itemFilters.category === '' || item.category_name === itemFilters.category;
      const matchesLowStock = !itemFilters.lowStockOnly || Number(item.quantity) <= Number(item.minimum_stock);

      return matchesSearch && matchesCategory && matchesLowStock;
    });
  }, [items, itemFilters]);

  useEffect(() => {
    if (isReady) {
      refreshAll();
    }
  }, [isReady]);

  async function handleLogin(event) {
    event.preventDefault();
    setError('');
    setStatus('');
    setLoginLoading(true);

    try {
      const response = await loginRequest(loginForm.username.trim(), loginForm.password);
      const token = response.data?.token;

      if (!token) {
        throw new Error('Login succeeded but no token was returned.');
      }

      saveSession(token, loginForm.username.trim());
      setSession({ token, username: loginForm.username.trim() });
      setLoginForm({ username: '', password: '' });
      setStatus('Login successful');
    } catch (err) {
      setError(err.message);
    } finally {
      setLoginLoading(false);
    }
  }

  function logout() {
    clearSession();
    setSession({ token: '', username: '' });
    setStatus('');
    setError('');
    setDepartments([]);
    setItems([]);
    setMovements([]);
    setLogs([]);
    setCurrentStock([]);
    setStockByDepartment([]);
    setActiveTab('dashboard');
  }

  async function run(action, successMessage = '') {
    setError('');
    setStatus('');
    try {
      await action();
      if (successMessage) {
        setStatus(successMessage);
      }
    } catch (err) {
      if (err.status === 401) {
        clearSession();
        setSession({ token: '', username: '' });
      }
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
      await apiRequest('/api/stock/in-bulk', {
        method: 'POST',
        body: JSON.stringify({
          ...stockIn,
          received_at: toApiDateTime(stockIn.received_at),
          items: stockIn.items.map((line) => ({
            item_id: Number(line.item_id),
            quantity: Number(line.quantity),
          })),
        }),
      });
      setStockIn(emptyStockIn);
      await refreshAll();
    }, 'Bulk stock in recorded');
  }

  async function submitStockOut(event) {
    event.preventDefault();
    await run(async () => {
      await apiRequest('/api/stock/out-bulk', {
        method: 'POST',
        body: JSON.stringify({
          ...stockOut,
          department_id: stockOut.department_id ? Number(stockOut.department_id) : null,
          requested_at: toApiDateTime(stockOut.requested_at),
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
        <div className="brand">
          <h1>Stocks App</h1>
          {/* <p>Warehouse</p> */}
        </div>
        <nav>
          {tabs.map(([key, label, icon]) => (
            <button
              key={key}
              className={activeTab === key ? 'active' : ''}
              disabled={!session.token}
              onClick={() => setActiveTab(key)}
            >
              <span className="nav-icon">{icon}</span>
              <span>{label}</span>
            </button>
          ))}
        </nav>
        {session.token && (
          <div className="sidebar-bottom">
            {/* <button type="button" className="sidebar-cta" onClick={() => setActiveTab('stock-in')}>
              Record Bulk Stock In
            </button> */}
            <div className="admin-chip">
              <span>{session.username ? session.username.slice(0, 2).toUpperCase() : 'AD'}</span>
              <div>
                <strong>{session.username || 'Warehouse Admin'}</strong>
                <small>Signed in</small>
              </div>
            </div>
            <button type="button" className="secondary logout-button" onClick={logout}>
              Logout
            </button>
          </div>
        )}
      </aside>

      <main>
        <header className="topbar" aria-label="Top navigation" />

        {status && <div className="notice success">{status}</div>}
        {error && <div className="notice error">{error}</div>}
        {!API_BASE_URL && <div className="notice error">Frontend API host is not configured.</div>}
        {!session.token && (
          <LoginScreen
            value={loginForm}
            loading={loginLoading}
            onChange={setLoginForm}
            onSubmit={handleLogin}
          />
        )}

        {isReady && activeTab === 'dashboard' && (
          <section>
            <div className="metrics">
              <Metric label="Total Items" value={items.length} note="Master SKUs" />
              <Metric label="Departments" value={departments.length} note="Active units" />
              <Metric label="Low Stock Items" value={lowStockCount} note="Review critical SKUs" danger />
              <Metric label="Total Movements" value={movements.length} note="Audit records" />
            </div>
            <Panel title="Recent Movements">
              <MovementTable rows={movements.slice(0, 8)} />
            </Panel>
          </section>
        )}

        {isReady && activeTab === 'departments' && (
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

        {isReady && activeTab === 'items' && (
          <section className="grid-two wide-right">
            <Panel title="Create Item Master">
              <ItemForm value={itemForm} onChange={setItemForm} onSubmit={createItem} />
            </Panel>
            <Panel title="Items">
              <div className="filters">
                <input
                  value={itemFilters.search}
                  onChange={(event) => setItemFilters({ ...itemFilters, search: event.target.value })}
                  placeholder="Search item"
                />
                <select
                  value={itemFilters.category}
                  onChange={(event) => setItemFilters({ ...itemFilters, category: event.target.value })}
                >
                  <option value="">All categories</option>
                  {itemCategories.map((category) => (
                    <option key={category} value={category}>
                      {category}
                    </option>
                  ))}
                </select>
                <label className="check-filter">
                  <input
                    type="checkbox"
                    checked={itemFilters.lowStockOnly}
                    onChange={(event) => setItemFilters({ ...itemFilters, lowStockOnly: event.target.checked })}
                  />
                  Low stock
                </label>
              </div>
              <Table
                columns={['SKU / Item', 'Category', 'Qty', 'Min. Stock', 'Status', '']}
                rows={filteredItems.map((item) => [
                  <div className="item-cell">
                    <strong>{item.item_name}</strong>
                    {item.description ? <small>{item.description}</small> : null}
                  </div>,
                  item.category_name || '-',
                  <strong>{item.quantity} {item.unit}</strong>,
                  `${item.minimum_stock} ${item.unit}`,
                  <StockStatus item={item} />,
                  <button className="danger" onClick={() => deleteItem(item.id)}>
                    Delete
                  </button>,
                ])}
              />
            </Panel>
          </section>
        )}

        {isReady && activeTab === 'stock-in' && (
          <Panel title="Stock In">
            <StockInForm items={items} value={stockIn} onChange={setStockIn} onSubmit={submitStockIn} />
          </Panel>
        )}

        {isReady && activeTab === 'stock-out' && (
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

        {isReady && activeTab === 'reports' && (
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

        {isReady && activeTab === 'logs' && (
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

function LoginScreen({ value, loading, onChange, onSubmit }) {
  return (
    <section className="login-layout">
      <div className="login-copy">
        <span>Warehouse Access</span>
        <h2>Sign in to manage stock</h2>
        <p>Use your admin credentials to view inventory, record stock movements, and review reports.</p>
      </div>
      <div className="panel login-panel">
        <div className="panel-header">
          <h2>Login</h2>
        </div>
        <form onSubmit={onSubmit}>
          <label>
            Username
            <input
              autoComplete="username"
              value={value.username}
              onChange={(event) => onChange({ ...value, username: event.target.value })}
              placeholder="admin"
            />
          </label>
          <label>
            Password
            <input
              autoComplete="current-password"
              type="password"
              value={value.password}
              onChange={(event) => onChange({ ...value, password: event.target.value })}
              placeholder="Enter password"
            />
          </label>
          <button type="submit" disabled={loading}>
            {loading ? 'Signing in...' : 'Login'}
          </button>
        </form>
      </div>
    </section>
  );
}

function Metric({ label, value, note, danger = false }) {
  return (
    <div className={`metric ${danger ? 'danger-metric' : ''}`}>
      <span>{label}</span>
      <strong>{value}</strong>
      {note ? <small>{note}</small> : null}
    </div>
  );
}

function StockStatus({ item }) {
  const quantity = Number(item.quantity);
  const minimum = Number(item.minimum_stock);
  const isCritical = quantity <= minimum;
  const isLow = !isCritical && quantity <= minimum * 1.5;

  if (isCritical) {
    return <span className="badge critical">Critical</span>;
  }

  if (isLow) {
    return <span className="badge low">Low Stock</span>;
  }

  return <span className="badge stable">Stable</span>;
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
      <label>
        Item Name
        <input value={value.item_name} onChange={(event) => onChange({ ...value, item_name: event.target.value })} placeholder="Laptop Charger" />
      </label>
      <label>
        Category
        <input
          value={value.category_name}
          onChange={(event) => onChange({ ...value, category_name: event.target.value })}
          placeholder="Optional"
        />
      </label>
      <div className="form-row">
        <label>
          Unit
          <input value={value.unit} onChange={(event) => onChange({ ...value, unit: event.target.value })} placeholder="pcs" />
        </label>
        <label>
          Minimum Stock
          <input
            type="number"
            min="0"
            value={value.minimum_stock}
            onChange={(event) => onChange({ ...value, minimum_stock: Number(event.target.value) })}
          />
        </label>
      </div>
      <label>
        Description
        <textarea
          value={value.description}
          onChange={(event) => onChange({ ...value, description: event.target.value })}
          placeholder="Optional notes about this item"
        />
      </label>
      <button type="submit">Create Item Master</button>
    </form>
  );
}

function StockInForm({ items, value, onChange, onSubmit }) {
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
        value={value.source_name}
        onChange={(event) => onChange({ ...value, source_name: event.target.value })}
        placeholder="Source or supplier optional"
      />
      <DateTime24 label="Received At" value={value.received_at} onChange={(received_at) => onChange({ ...value, received_at })} />
      <textarea value={value.notes} onChange={(event) => onChange({ ...value, notes: event.target.value })} placeholder="Notes" />

      <div className="line-items">
        <div className="line-items-header">
          <strong>Received Items</strong>
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

      <button type="submit">Record Bulk Stock In</button>
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
      <DateTime24 label="Requested At" value={value.requested_at} onChange={(requested_at) => onChange({ ...value, requested_at })} />
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

function DateTime24({ label, value, onChange }) {
  const parts = toDateTimeParts(value);
  const hours = Array.from({ length: 24 }, (_, index) => String(index).padStart(2, '0'));
  const minutes = Array.from({ length: 60 }, (_, index) => String(index).padStart(2, '0'));

  function update(nextParts) {
    onChange(fromDateTimeParts(nextParts.date, nextParts.hour, nextParts.minute));
  }

  return (
    <label>
      {label}
      <div className="datetime-24">
        <input
          type="date"
          value={parts.date}
          onChange={(event) => update({ ...parts, date: event.target.value })}
        />
        <select value={parts.hour} onChange={(event) => update({ ...parts, hour: event.target.value })} aria-label={`${label} hour`}>
          {hours.map((hour) => (
            <option key={hour} value={hour}>
              {hour}
            </option>
          ))}
        </select>
        <span>:</span>
        <select value={parts.minute} onChange={(event) => update({ ...parts, minute: event.target.value })} aria-label={`${label} minute`}>
          {minutes.map((minute) => (
            <option key={minute} value={minute}>
              {minute}
            </option>
          ))}
        </select>
      </div>
    </label>
  );
}
