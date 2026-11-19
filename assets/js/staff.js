/* Staff-only interface. Records and mutations remain in the PHP session store. */
(function () {
  'use strict';
  const translate = window.MyParkLocale?.text ?? (message => message);

  const body = document.body;
  if (!body.classList.contains('staff-portal')) return;
  const el = (tag, cls, text) => {
    const item = document.createElement(tag);
    if (cls) item.className = cls;
    if (text != null) item.textContent = text;
    return item;
  };
  function button(text, action, secondary = true) {
    const item = el('button', 'staff-button' + (secondary ? ' staff-secondary' : ''), text);
    item.type = 'button'; item.addEventListener('click', action); return item;
  }
  const sidebar = document.getElementById('staff-sidebar');
  const menu = document.querySelector('.staff-menu-toggle');
  const backdrop = document.getElementById('staff-backdrop');
  const mobile = window.matchMedia('(max-width: 900px)');
  function setMenu(open, restore = true) {
    open = mobile.matches && open;
    body.classList.toggle('staff-menu-open', open);
    menu.setAttribute('aria-expanded', String(open));
    sidebar.inert = mobile.matches && !open;
    backdrop.hidden = !open;
    if (open) sidebar.querySelector('button').focus();
    else if (restore && mobile.matches) menu.focus();
  }
  menu.addEventListener('click', () => setMenu(!body.classList.contains('staff-menu-open')));
  sidebar.querySelector('.staff-sidebar-close').addEventListener('click', () => setMenu(false));
  backdrop.addEventListener('click', () => setMenu(false));
  document.addEventListener('keydown', event => {
    if (!body.classList.contains('staff-menu-open')) return;
    if (event.key === 'Escape') { event.preventDefault(); setMenu(false); }
    if (event.key === 'Tab') {
      const focusable = [...sidebar.querySelectorAll('a,button')].filter(n => !n.disabled);
      const first = focusable[0], last = focusable.at(-1);
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  mobile.addEventListener('change', () => setMenu(false, false)); setMenu(false, false);

  const bootstrap = document.getElementById('staff-data');
  if (!bootstrap) return; // Existing My Profile uses the shared sidebar only.
  let data = JSON.parse(bootstrap.textContent);
  const page = document.getElementById('staff-app').dataset.page;
  const money = cents => 'RM ' + (Number(cents) / 100).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const dateFormat = new Intl.DateTimeFormat(document.documentElement.lang, { timeZone: 'Asia/Kuala_Lumpur', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });
  const dateText = value => { const date = new Date(value); return Number.isNaN(date.getTime()) ? '—' : dateFormat.format(date); };
  const plateKey = value => value.toUpperCase().replace(/[^A-Z0-9]/g, '');
  const collator = new Intl.Collator('en', { numeric: true, sensitivity: 'base' });
  const refreshButton = document.getElementById('staff-refresh');
  const pageError = document.getElementById('staff-page-error');
  const requestStatus = document.getElementById('staff-request-status');
  let table, lookupPlate = '', refreshController, sequence = 0;
  const dialog = document.getElementById('staff-dialog');
  const dialogBody = document.getElementById('staff-dialog-body');
  const closeDialog = document.getElementById('staff-dialog-close');
  let origin, submitting = false;
  closeDialog.addEventListener('click', () => { if (!submitting) dialog.close(); });
  dialog.addEventListener('cancel', event => { if (submitting) event.preventDefault(); });
  dialog.addEventListener('close', () => {
    if (origin?.isConnected) origin.focus();
    else (document.getElementById('staff-new') || refreshButton).focus();
  });
  function openDialog(title, trigger) {
    origin = trigger || document.activeElement;
    document.getElementById('staff-dialog-title').textContent = translate(title);
    dialogBody.replaceChildren(); dialog.showModal(); closeDialog.focus();
  }
  function badge(status, kind = 'fine') {
    const label = (kind === 'fine' ? data.fine_statuses : data.maintenance_statuses)[status] || status;
    return el('span', 'staff-badge staff-badge--' + status, translate(label));
  }
  function showPageError(text) {
    pageError.querySelector('span').textContent = text; pageError.hidden = false;
  }
  async function readResponse(response) {
    let result;
    try { result = await response.json(); }
    catch (error) {
      if (error.name === 'AbortError') throw error;
      throw new Error(translate('Unexpected server response. Refresh the page or sign in again.'));
    }
    if (!response.ok) {
      const error = new Error(result.error || 'Unable to complete this request.');
      error.fields = result.fields || {}; throw error;
    }
    return result;
  }
  function applyData(next) {
    if (!next || !Array.isArray(next.fines) || !Array.isArray(next.maintenance) || !next.summary || !Number.isInteger(next.revision)) throw new Error(translate('Incomplete response. Existing records have been kept.'));
    if (next.revision < data.revision) return;
    data = next;
    if (table) table.render();
    if (page === 'dashboard') renderDashboard();
    if (page === 'vehicles' && lookupPlate) renderLookup();
  }
  async function refresh() {
    refreshController?.abort(); refreshController = new AbortController();
    const current = ++sequence;
    refreshButton.disabled = true; requestStatus.hidden = false; requestStatus.textContent = translate('Refreshing records…'); pageError.hidden = true;
    try {
      const response = await fetch('api/staff.php?page=' + page, { credentials: 'same-origin', cache: 'no-store', signal: refreshController.signal });
      const result = await readResponse(response); if (current === sequence) applyData(result.data);
    } catch (error) {
      if (current === sequence && error.name !== 'AbortError') showPageError(translate('Refresh failed. {message}', { message: translate(error.message) }));
    } finally { if (current === sequence) { refreshButton.disabled = false; requestStatus.hidden = true; } }
  }
  refreshButton.addEventListener('click', refresh); document.getElementById('staff-retry').addEventListener('click', refresh);

  const fineColumns = [
        ['code', translate('Fine ID')], ['plate', translate('Vehicle Plate')], ['violation', translate('Violation Type')], ['occurred_ts', translate('Date')],
    ['amount_cents', translate('Amount (RM)'), true], ['status', translate('Status')], ['actions', translate('Actions')]
  ];
  const maintenanceColumns = [['code', translate('Record ID')], ['title', translate('Issue')], ['location', translate('Location')], ['status', translate('Status')], ['updated_at', translate('Updated')], ['actions', translate('Actions')]];
  function displayValue(record, key, kind) {
    if (key === 'amount_cents') return money(record.amount_cents);
    if (key === 'occurred_ts') return dateText(record.occurred_ts * 1000);
    if (key === 'updated_at' || key === 'created_at') return dateText(record[key]);
    if (key === 'status') return (kind === 'fine' ? data.fine_statuses : data.maintenance_statuses)[record.status];
    return record[key] ?? '—';
  }
  function rowActions(record, kind) {
    const group = el('div', 'staff-row-actions');
    const view = button(translate('View'), () => viewRecord(record, kind, view)); group.append(view);
    if (!record.can_manage) return group;
    if (kind === 'maintenance' || record.status === 'unpaid') {
      const edit = button(translate('Edit'), () => editRecord(kind, record, edit)); group.append(edit);
    }
    if (kind === 'fine' && record.status === 'unpaid') {
      const paid = button(translate('Record payment'), () => confirmRecord('fine_paid', record, paid)); group.append(paid);
    }
    if (kind === 'maintenance' || record.status !== 'void') {
      const action = kind === 'fine' ? 'fine_void' : 'maintenance_delete';
      const remove = button(kind === 'fine' ? translate('Void') : translate('Delete'), () => confirmRecord(action, record, remove)); remove.classList.add('staff-danger'); group.append(remove);
    }
    return group;
  }
  function createTable(records, columns, kind, emptyText) {
    const scroll = el('div', 'staff-table-scroll'); scroll.tabIndex = 0; scroll.setAttribute('role', 'region'); scroll.setAttribute('aria-label', kind === 'fine' ? translate('Fine records') : translate('Maintenance records'));
    const table = el('table', 'staff-table'); const head = el('thead'), header = el('tr'), tbody = el('tbody');
    columns.forEach(([key, label, number]) => { const th = el('th', number ? 'staff-number' : '', label); th.scope = 'col'; th.dataset.column = key; header.append(th); }); head.append(header);
    records.forEach(record => {
      const tr = el('tr'); tr.dataset.id = record.id;
      columns.forEach(([key, , number]) => {
        const td = el('td', number ? 'staff-number' : ''); td.dataset.column = key;
        if (key === 'actions') td.append(rowActions(record, kind));
        else if (key === 'status') td.append(badge(record.status, kind));
        else { const value = displayValue(record, key, kind); const text = el('span', 'staff-cell-text', value); text.title = String(value); td.append(text); }
        tr.append(td);
      }); tbody.append(tr);
    });
    if (!records.length) { const tr = el('tr'), td = el('td', 'staff-empty', emptyText); td.colSpan = columns.length; tr.append(td); tbody.append(tr); }
    table.append(head, tbody); scroll.append(table); return scroll;
  }

  class RecordTable {
    constructor(host, kind) {
      this.host = host; this.kind = kind; this.key = kind === 'fine' ? 'fines' : 'maintenance'; this.columns = kind === 'fine' ? fineColumns : maintenanceColumns;
      this.state = { query: '', status: '', sort: kind === 'fine' ? 'occurred_ts' : 'updated_at', direction: -1, page: 1, size: 10 };
      const tools = el('div', 'staff-table-tools');
      this.search = el('input'); this.search.type = 'search'; this.search.placeholder = kind === 'fine' ? translate('Search Fine ID or Vehicle Plate') : translate('Search ID, issue or location'); this.search.setAttribute('aria-label', this.search.placeholder);
      this.search.addEventListener('input', () => { this.state.query = this.search.value; clearTimeout(this.timer); this.timer = setTimeout(() => this.resetPage(), 300); });
      this.status = el('select'); this.status.setAttribute('aria-label', translate('Filter by status')); this.status.add(new Option(translate('All statuses'), ''));
      Object.entries(kind === 'fine' ? data.fine_statuses : data.maintenance_statuses).forEach(([value, label]) => this.status.add(new Option(translate(label), value)));
      this.status.addEventListener('change', () => { this.state.status = this.status.value; this.resetPage(); });
      const clear = button(translate('Clear filters'), () => { this.state.query = ''; this.state.status = ''; this.search.value = ''; this.status.value = ''; this.resetPage(); this.search.focus(); });
      tools.append(this.search, this.status, clear); this.output = el('div');
      const footer = el('div', 'staff-table-footer'); this.count = el('span', 'staff-muted'); this.count.setAttribute('role', 'status');
      const label = el('label', 'staff-page-size', translate('Rows per page')), select = el('select');
      [10, 25, 50].forEach(n => select.add(new Option(String(n), String(n)))); select.addEventListener('change', () => { this.state.size = Number(select.value); this.resetPage(); }); label.append(select);
      this.pages = el('nav', 'staff-pagination'); this.pages.setAttribute('aria-label', this.key + ' pages'); footer.append(this.count, label, this.pages); host.append(tools, this.output, footer); this.render();
    }
    resetPage() { clearTimeout(this.timer); this.state.page = 1; this.render(); }
    render() {
      const state = this.state, query = state.query.trim();
      const rows = data[this.key].filter(record => {
        if (state.status && record.status !== state.status) return false;
        if (!query) return true;
        return this.kind === 'fine' ? (record.code.replace(/-/g, '').includes(plateKey(query)) || record.plate_key.includes(plateKey(query))) && !!plateKey(query)
          : [record.code, record.title, record.location].join(' ').toLowerCase().includes(query.toLowerCase());
      }).sort((a, b) => {
        let compare;
        if (['amount_cents', 'occurred_ts'].includes(state.sort)) compare = a[state.sort] - b[state.sort];
        else if (state.sort === 'updated_at') compare = Date.parse(a.updated_at) - Date.parse(b.updated_at);
        else compare = collator.compare(displayValue(a, state.sort, this.kind), displayValue(b, state.sort, this.kind));
        return compare ? compare * state.direction : a.id - b.id;
      });
      const paged = window.MyParkTableData.paginate(rows, state.page, state.size); state.page = paged.page;
      const empty = data[this.key].length
        ? translate(this.kind === 'fine' ? 'No matching fine records.' : 'No matching maintenance records.')
        : translate(this.kind === 'fine' ? 'No fine records yet.' : 'No maintenance records yet.');
      const rendered = createTable(paged.rows, this.columns, this.kind, empty);
      this.columns.forEach(([key, label]) => {
        if (key === 'actions') return;
        const th = rendered.querySelector('th[data-column="' + key + '"]');
        th.setAttribute('aria-sort', state.sort === key ? state.direction === 1 ? 'ascending' : 'descending' : 'none');
        const nextDirection = state.sort === key && state.direction === 1 ? translate('descending') : translate('ascending');
        const sort = button('', () => {
          state.direction = state.sort === key && state.direction === 1 ? -1 : 1; state.sort = key; state.page = 1; this.render();
          this.output.querySelector('th[data-column="' + key + '"] button').focus();
        }); sort.className = 'staff-sort';
        sort.setAttribute('aria-label', translate('Sort by {label}, {direction}', { label, direction: nextDirection }));
        const arrow = el('span', 'staff-sort-mark'); arrow.setAttribute('aria-hidden', 'true');
        // Fixed geometry keeps both arrows in place when the direction changes.
        arrow.innerHTML = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path class="staff-sort-up" d="m4 6 4-4 4 4"/><path class="staff-sort-down" d="m4 10 4 4 4-4"/></svg>';
        sort.append(el('span', 'staff-sort-label', label), arrow); th.replaceChildren(sort);
      });
      this.output.replaceChildren(rendered); this.count.textContent = paged.label;
      document.getElementById('staff-record-count').textContent = translate('{count} records', { count: data[this.key].length });
      this.pages.replaceChildren();
      if (paged.pages > 1) {
        const go = n => { state.page = n; this.render(); this.pages.querySelector('[aria-current="page"]')?.focus(); };
        const prev = button(translate('Previous'), () => go(state.page - 1)); prev.disabled = state.page === 1; this.pages.append(prev);
        window.MyParkTableData.pageNumbers(state.page, paged.pages).forEach(n => {
          if (n === '…') { this.pages.append(el('span', '', n)); return; }
          const item = button(String(n), () => go(n)); if (n === state.page) item.setAttribute('aria-current', 'page'); item.setAttribute('aria-label', 'Page ' + n); this.pages.append(item);
        }); const next = button(translate('Next'), () => go(state.page + 1)); next.disabled = state.page === paged.pages; this.pages.append(next);
      }
    }
  }

  function formBase(action, record) {
    const form = el('form', 'staff-record-form'); form.noValidate = true;
    const message = el('p', 'staff-form-error'); message.hidden = true; message.setAttribute('role', 'alert'); message.tabIndex = -1;
    const fields = el('fieldset'); fields.className = 'staff-form-fields'; form.append(message, fields);
    const hidden = (name, value) => { const input = el('input'); input.type = 'hidden'; input.name = name; input.value = value; fields.append(input); };
    hidden('action', action);
    if (record) { hidden('id', record.id); hidden('version', record.version); }
    else hidden('request_id', [...crypto.getRandomValues(new Uint8Array(16))].map(n => n.toString(16).padStart(2, '0')).join(''));
    form.addEventListener('submit', event => submitForm(event, form));
    dialogBody.append(form); return { form, fields, message };
  }
  function field(container, name, labelText, options = {}) {
    const group = el('div', 'staff-field'); const label = el('label', '', labelText); label.htmlFor = 'staff-field-' + name;
    const input = el(options.tag || 'input'); input.id = label.htmlFor; input.name = name;
    if (options.tag === 'select') (options.options || []).forEach(([value, text]) => input.add(new Option(name === 'lot_id' && value !== '' ? text : translate(text), String(value))));
    else if (!options.tag) input.type = options.type || 'text';
    input.required = options.required !== false;
    ['maxLength', 'min', 'max', 'step', 'placeholder'].forEach(key => { if (options[key] != null) input[key] = options[key]; });
    input.value = options.value ?? '';
    const error = el('p', 'staff-field-error'); error.id = input.id + '-error'; error.hidden = true;
    input.setAttribute('aria-describedby', error.id); input.setAttribute('aria-invalid', 'false');
    input.addEventListener('input', () => { if (input.getAttribute('aria-invalid') === 'true') setFieldError(input, input.validity.valid ? '' : input.validationMessage); });
    group.append(label, input, error); container.append(group); return input;
  }
  function setFieldError(input, text) {
    input.setAttribute('aria-invalid', text ? 'true' : 'false');
    const error = document.getElementById(input.getAttribute('aria-describedby'));
    if (error) { error.textContent = text; error.hidden = !text; }
  }
  function formFooter(form, label) {
    const actions = el('div', 'staff-form-actions'); const cancel = button(translate('Cancel'), () => { if (!submitting) dialog.close(); });
    const save = el('button', 'staff-button', label); save.type = 'submit'; save.dataset.label = label; actions.append(cancel, save); form.append(actions);
  }
  function localNow() {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Kuala_Lumpur', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date()).map(p => [p.type, p.value]));
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
  }
  function editRecord(kind, record, trigger) {
    if (kind === 'fine' && record && record.status !== 'unpaid') return;
    openDialog(record ? translate('Edit {code}', { code: record.code }) : translate(kind === 'fine' ? 'New Fine' : 'New maintenance record'), trigger);
    const { form, fields } = formBase(kind + (record ? '_update' : '_create'), record);
    if (kind === 'fine') {
      field(fields, 'plate', translate('Vehicle plate'), { value: record?.plate, maxLength: 15, placeholder: 'e.g. SAB 1234' });
      field(fields, 'violation', 'Violation type', { value: record?.violation, maxLength: 100, placeholder: translate('Describe the parking violation') });
      field(fields, 'occurred_at', translate('Date and time (Malaysia)'), { type: 'datetime-local', value: record?.occurred_at || localNow(), max: localNow() });
      field(fields, 'amount', translate('Amount (RM)'), { type: 'number', min: '0.01', max: '99999.99', step: '0.01', value: record ? (record.amount_cents / 100).toFixed(2) : '' });
      field(fields, 'notes', translate('Notes (optional)'), { tag: 'textarea', maxLength: 2000, required: false, value: record?.notes });
      if (record) field(fields, 'reason', translate('Reason for correction'), { tag: 'textarea', maxLength: 500 });
      form.append(el('p', 'staff-help', translate('Private parking prototype only. This creates an unpaid record; it is not a government-issued fine.')));
    } else {
      field(fields, 'title', translate('Issue title'), { value: record?.title, maxLength: 120 });
      field(fields, 'lot_id', translate('Parking location'), { tag: 'select', options: [['', 'Select a location'], ...data.locations.map(l => [l.id, l.name])], value: record?.lot_id });
      field(fields, 'description', translate('Description'), { tag: 'textarea', maxLength: 2000, value: record?.description });
      field(fields, 'status', translate('Status'), { tag: 'select', options: Object.entries(data.maintenance_statuses), value: record?.status || 'open' });
    }
    formFooter(form, record ? translate('Save changes') : kind === 'fine' ? translate('Create fine record') : translate('Create record'));
    fields.querySelector('input:not([type="hidden"]),select,textarea')?.focus();
  }
  function confirmRecord(action, record, trigger) {
    const payment = action === 'fine_paid', voiding = action === 'fine_void';
    openDialog(translate(payment ? 'Record received payment · {code}' : voiding ? 'Void fine · {code}' : 'Remove maintenance · {code}', { code: record.code }), trigger);
    const { form, fields } = formBase(action, record);
    const confirmation = payment
      ? 'Record a received payment of {amount} for {plate}? No payment will be collected by this website.'
      : voiding ? 'Void {code} for {plate}? The original amount, payment evidence and audit history will be retained. Voiding does not process a refund.'
      : 'Remove {code}: {title}? It will leave the active list; its audit history will be retained.';
    fields.append(el('p', 'staff-confirm-text', translate(confirmation, { amount: money(record.amount_cents), plate: record.plate, code: record.code, title: record.title })));
    if (payment) {
      field(fields, 'payment_reference', translate('Payment reference'), { maxLength: 120 });
      const input = field(fields, 'confirmed', translate('I confirm this payment has already been received.'), { type: 'checkbox', value: '1' }); input.parentElement.classList.add('staff-checkbox');
    } else field(fields, 'reason', voiding ? translate('Reason for voiding') : translate('Reason for removal'), { tag: 'textarea', maxLength: 500 });
    formFooter(form, payment ? translate('Confirm received payment') : voiding ? translate('Void fine') : translate('Remove record'));
  }
  async function submitForm(event, form) {
    event.preventDefault(); if (submitting) return;
    const inputs = [...form.querySelectorAll('input:not([type="hidden"]),select,textarea')];
    let invalid;
    inputs.forEach(input => { setFieldError(input, input.validity.valid ? '' : input.validationMessage); if (!input.validity.valid && !invalid) invalid = input; });
    if (invalid) { invalid.focus(); return; }
    refreshController?.abort(); ++sequence; refreshButton.disabled = false; requestStatus.hidden = true;
    const payload = new FormData(form); payload.set('csrf', data.csrf);
    const message = form.querySelector('.staff-form-error'); message.hidden = true;
    submitting = true; closeDialog.disabled = true; form.setAttribute('aria-busy', 'true'); form.querySelector('fieldset').disabled = true;
    form.querySelectorAll('button').forEach(b => b.disabled = true); const save = form.querySelector('[type="submit"]'); save.textContent = translate('Saving…');
    try {
      const response = await fetch('api/staff.php?page=' + page, { method: 'POST', body: payload, credentials: 'same-origin' });
      const result = await readResponse(response); applyData(result.data); dialog.close(); pageError.hidden = true;
      window.MyParkToast?.show(result.message, 'success');
    } catch (error) {
      message.textContent = error.fields ? translate(error.message) : translate('The update could not be confirmed. Refresh records before retrying. {message}', { message: translate(error.message) }); message.hidden = false;
      let first;
      Object.entries(error.fields || {}).forEach(([name, text]) => { const input = form.elements.namedItem(name); if (input) { setFieldError(input, text); first ||= input; } });
      // Restore controls before moving focus to a server-rejected field.
      form.querySelector('fieldset').disabled = false;
      (first || message).focus();
    } finally {
      submitting = false; closeDialog.disabled = false; form.setAttribute('aria-busy', 'false'); form.querySelector('fieldset').disabled = false;
      form.querySelectorAll('button').forEach(b => b.disabled = false); save.textContent = save.dataset.label;
    }
  }
  function viewRecord(record, kind, trigger) {
    openDialog(translate('{code} · Details', { code: record.code }), trigger);
    const details = el('dl', 'staff-detail-grid');
    const fields = kind === 'fine' ? [['plate', translate('Vehicle plate')], ['violation', 'Violation type'], ['occurred_ts', translate('Date')], ['amount_cents', translate('Amount')], ['status', translate('Status')], ['notes', translate('Notes')], ['payment_reference', translate('Payment reference')], ['void_reason', translate('Void reason')]]
      : [['title', translate('Issue')], ['location', translate('Location')], ['description', translate('Description')], ['status', translate('Status')]];
    fields.push(['created_by_name', translate('Created by')], ['created_at', translate('Created')], ['updated_at', translate('Updated')]);
    fields.forEach(([key, label]) => details.append(el('dt', '', label), el('dd', '', displayValue(record, key, kind) || '—')));
    if (kind === 'fine' && record.paid_at) details.append(el('dt', '', 'Paid amount retained'), el('dd', '', money(record.paid_amount_cents)), el('dt', '', 'Payment recorded'), el('dd', '', dateText(record.paid_at)));
    dialogBody.append(details, el('h3', '', 'Audit history'));
    const history = el('ol', 'staff-audit');
    const tracked = { plate: translate('Plate'), violation: translate('Violation'), amount_cents: translate('Amount'), occurred_at: translate('Date'), notes: translate('Notes'), status: translate('Status'), title: translate('Issue'), location: translate('Location'), description: translate('Description'), payment_reference: translate('Payment reference'), deleted_at: 'Removed at' };
    [...record.audit].reverse().forEach(entry => {
      const item = el('li'); item.append(el('strong', '', ({ created: 'Created', update: 'Updated', paid: 'Payment recorded', void: 'Voided', delete: 'Removed' })[entry.action] || entry.action), el('p', 'staff-muted', dateText(entry.at) + ' · ' + entry.actor_name));
      if (entry.reason) item.append(el('p', '', entry.reason));
      if (entry.before) {
        const changes = el('ul');
        Object.entries(tracked).forEach(([key, label]) => {
          if (entry.before[key] === entry.after[key]) return;
          const format = v => key === 'amount_cents' ? money(v) : (v || '—');
          changes.append(el('li', '', label + ': ' + format(entry.before[key]) + ' → ' + format(entry.after[key])));
        }); item.append(changes);
      }
      history.append(item);
    }); dialogBody.append(history);
  }
  function renderDashboard() {
    document.querySelectorAll('[data-summary]').forEach(n => { const key = n.dataset.summary; n.textContent = key === 'outstanding_cents' ? money(data.summary[key]) : data.summary[key]; });
    const recent = [...data.fines].sort((a, b) => Date.parse(b.created_at) - Date.parse(a.created_at) || b.id - a.id).slice(0, 5);
    document.getElementById('staff-recent').replaceChildren(createTable(recent, fineColumns, 'fine', translate('No fine records yet.')));
  }
  function renderLookup() {
    const host = document.getElementById('staff-lookup-results'); host.replaceChildren();
    const fines = data.fines.filter(r => r.plate_key === lookupPlate), bookings = data.bookings.filter(r => r.plate_key === lookupPlate);
    host.append(el('p', 'staff-lookup-summary', translate('{plate} · {fines} fine records · {bookings} parking records', { plate: lookupPlate, fines: fines.length, bookings: bookings.length })));
    const fineCard = el('section', 'staff-card'); fineCard.append(el('h2', '', 'Related fines'), createTable(fines, fineColumns, 'fine', 'No fine records found for this vehicle.')); host.append(fineCard);
    const parkingCard = el('section', 'staff-card'); parkingCard.append(el('h2', '', 'Parking records'));
    const rows = bookings.map(r => ({ ...r, code: '#' + r.id, date: r.created_at }));
    const scroll = createTable(rows, [['code', translate('Booking ID')], ['plate', 'Vehicle Plate'], ['location', translate('Location')], ['hours', translate('Hours'), true], ['date', translate('Created')]], 'maintenance', translate('No parking records found for this vehicle.')); parkingCard.append(scroll); host.append(parkingCard);
  }
  if (page === 'dashboard') renderDashboard();
  if (page === 'fines' || page === 'maintenance') {
    const kind = page === 'fines' ? 'fine' : 'maintenance'; table = new RecordTable(document.getElementById('staff-records'), kind);
    const create = document.getElementById('staff-new'); create.addEventListener('click', () => editRecord(kind, null, create));
  }
  if (page === 'vehicles') {
    const form = document.getElementById('staff-lookup'), input = document.getElementById('lookup-plate');
    const error = el('p', 'staff-field-error'); error.id = 'lookup-error'; error.hidden = true; form.append(error); input.setAttribute('aria-describedby', 'lookup-error');
    const lookup = () => {
      const value = input.value.trim();
      if (!/^[A-Za-z0-9][A-Za-z0-9 -]{1,14}$/.test(value)) { error.textContent = translate('Enter a valid vehicle plate.'); error.hidden = false; input.setAttribute('aria-invalid', 'true'); input.focus(); return; }
      error.hidden = true; input.setAttribute('aria-invalid', 'false'); lookupPlate = plateKey(value); renderLookup();
    };
    form.addEventListener('submit', event => { event.preventDefault(); lookup(); }); if (input.value.trim()) lookup();
  }
}());
