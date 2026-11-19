/* Complete session snapshots; each table owns its independent view state. */
(function () {
  'use strict';
  const translate = window.MyParkLocale?.text ?? (message => message);

  const bootstrap = document.getElementById('operations-data');
  if (!bootstrap) return;
  let data = JSON.parse(bootstrap.textContent);
  const pipeline = window.MyParkTableData, tables = [], pending = new Set();
  let refreshController, refreshSequence = 0;
  let parkingMap;
  const money = v => 'RM ' + Number(v).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const title = v => translate(v.charAt(0).toUpperCase() + v.slice(1));
  const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };
  const btn = (text, fn, cls = 'ops-button') => { const n = el('button', cls, text); n.type = 'button'; n.addEventListener('click', fn); return n; };
  const toast = (text, type) => window.MyParkToast?.show(text, type);
  function icon(search = false) {
    const n = el('span', 'ops-icon'); n.setAttribute('aria-hidden', 'true');
    // Only static markup; all record values are inserted with textContent.
    n.innerHTML = search ? '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8.5" cy="8.5" r="5.5"/><path d="m13 13 4 4"/></svg>' : '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path class="ops-sort-up" d="m4 6 4-4 4 4"/><path class="ops-sort-down" d="m4 10 4 4 4-4"/></svg>';
    return n;
  }
  const dialog = document.getElementById('ops-dialog');
  let dialogResolve, dialogOrigin, dialogFallback;
  dialog.querySelector('[data-dialog-cancel]').addEventListener('click', () => dialog.close('cancel'));
  dialog.querySelector('[data-dialog-confirm]').addEventListener('click', () => dialog.close('confirm'));
  dialog.addEventListener('close', () => {
    if (dialogOrigin?.isConnected) dialogOrigin.focus();
    else if (dialogFallback?.isConnected) dialogFallback.focus();
    dialogResolve?.(dialog.returnValue === 'confirm'); dialogResolve = null;
  });
  function openDialog(heading, content, confirmLabel, origin = document.activeElement) {
    dialogOrigin = origin;
    dialogFallback = origin?.closest('.ops-panel')?.querySelector('.ops-tools .ops-button');
    dialog.querySelector('#ops-dialog-title').textContent = heading;
    dialog.querySelector('[data-dialog-body]').replaceChildren(content);
    const confirm = dialog.querySelector('[data-dialog-confirm]');
    confirm.hidden = !confirmLabel; confirm.textContent = confirmLabel || translate('Confirm');
    dialog.querySelector('[data-dialog-cancel]').textContent = confirmLabel ? translate('Cancel') : translate('Close');
    dialog.returnValue = ''; dialog.showModal(); dialog.querySelector('[data-dialog-cancel]').focus();
    return new Promise(resolve => { dialogResolve = resolve; });
  }
  const columns = {
    accounts: [
      { key: 'name', label: translate('Name'), essential: true, wide: true }, { key: 'email', label: translate('Email'), wide: true },
      { key: 'role_label', label: translate('Role') }, { key: 'status', label: translate('Status'), essential: true }, { key: 'actions', label: translate('Actions'), essential: true }
    ],
    reservations: [
      { key: 'id', label: translate('ID'), essential: true, number: true }, { key: 'user', label: translate('Member'), wide: true },
      { key: 'lot', label: translate('Location'), wide: true }, { key: 'plate', label: translate('Plate'), essential: true },
      { key: 'hours', label: translate('Duration'), number: true }, { key: 'fee', label: translate('Fee'), number: true },
      { key: 'status', label: translate('Status'), essential: true }, { key: 'created_ts', label: translate('Created'), initiallyHidden: true },
      { key: 'actions', label: translate('Actions'), essential: true }
    ]
  };
  class ManagementTable {
    constructor(host, kind) {
      this.host = host; this.kind = kind; this.panel = host.closest('.ops-panel'); this.columns = columns[kind];
      this.heading = kind === 'accounts' ? translate('Account Management') : translate('Reservation Management');
      this.storageKey = 'MyPark:operations:preferences:' + data.actor_id + ':' + kind;
      let prefs = {}; try { prefs = JSON.parse(localStorage.getItem(this.storageKey) || '{}') || {}; } catch (_) { /* Optional storage. */ }
      this.hidden = new Set(Array.isArray(prefs.hidden) ? prefs.hidden.filter(k => this.columns.some(c => c.key === k && !c.essential)) : this.columns.filter(c => c.initiallyHidden).map(c => c.key));
      this.density = prefs.density === 'compact' ? 'compact' : 'comfortable';
      const dated = kind === 'reservations' && data.reservations.some(r => r.created_ts != null);
      this.state = { q: '', status: '', role: '', location: '', from: '', to: '', sort: dated ? 'created_ts' : 'id', dir: kind === 'accounts' ? 'asc' : 'desc', page: 1, size: [10, 25, 50].includes(prefs.size) ? prefs.size : 10 };
      this.build(); this.updateOptions(); this.render();
    }
    save() {
      try { localStorage.setItem(this.storageKey, JSON.stringify({ hidden: [...this.hidden], density: this.density, size: this.state.size })); } catch (_) { /* Never store records or tokens. */ }
    }
    changed() { clearTimeout(this.debounce); this.state.page = 1; this.render(); }
    build() {
      this.host.replaceChildren();
      const toolbar = el('div', 'ops-toolbar'), searchWrap = el('div', 'ops-search');
      this.search = el('input'); this.search.type = 'search'; this.search.placeholder = this.kind === 'accounts' ? translate('Search name or email') : translate('Search member, plate or location'); this.search.setAttribute('aria-label', this.search.placeholder);
      this.search.addEventListener('input', () => { this.state.q = this.search.value; clearTimeout(this.debounce); this.debounce = setTimeout(() => this.changed(), 300); });
      this.clearSearch = btn(translate('Clear'), () => { this.state.q = ''; this.search.value = ''; this.changed(); this.search.focus(); }, 'ops-search-clear');
      this.clearSearch.setAttribute('aria-label', translate('Clear search')); searchWrap.append(icon(true), this.search, this.clearSearch); toolbar.append(searchWrap);
      this.filters = {};
      const filters = this.kind === 'accounts' ? [['role', translate('Role')], ['status', translate('Status')]] : [['status', translate('Status')], ['location', translate('Location')]];
      filters.forEach(([key, label]) => {
        const input = el('select'); input.setAttribute('aria-label', label);
        input.addEventListener('change', () => { this.state[key] = input.value; this.changed(); });
        this.filters[key] = input; toolbar.append(input);
      });
      if (this.kind === 'reservations') {
        this.dateGroup = el('div', 'ops-dates');
        [['from', translate('Created from')], ['to', translate('Created to')]].forEach(([key, text]) => {
          const label = el('label', '', text), input = el('input'); input.type = 'date';
          input.addEventListener('change', () => { this.state[key] = input.value; this.filters.from.max = this.state.to; this.filters.to.min = this.state.from; this.changed(); });
          this.filters[key] = input; label.append(input); this.dateGroup.append(label);
        }); toolbar.append(this.dateGroup);
      }
      const tools = el('div', 'ops-tools'); this.refresh = btn(translate('Refresh'), () => refresh(this));
      const menu = el('details', 'ops-columns'), summary = el('summary', '', translate('Columns')), choices = el('div', 'ops-column-menu'); menu.append(summary);
      this.columns.filter(c => !c.essential).forEach(c => {
        const label = el('label'), input = el('input'); input.type = 'checkbox'; input.checked = !this.hidden.has(c.key);
        input.addEventListener('change', () => { if (input.checked) this.hidden.delete(c.key); else this.hidden.add(c.key); this.save(); this.render(); });
        label.append(input, document.createTextNode(c.label)); choices.append(label);
      }); menu.append(choices);
      menu.addEventListener('keydown', event => { if (event.key === 'Escape') { menu.open = false; summary.focus(); } });
      document.addEventListener('click', event => { if (!menu.contains(event.target)) menu.open = false; });
      const density = el('select'); density.setAttribute('aria-label', translate('Row density'));
      [['comfortable', translate('Comfortable')], ['compact', translate('Compact')]].forEach(([v, t]) => density.add(new Option(t, v))); density.value = this.density;
      density.addEventListener('change', () => { this.density = density.value; this.save(); this.render(); }); tools.append(this.refresh, menu, density);
      if (data.can_export) { this.export = btn(translate('Export filtered results (CSV)'), () => this.exportRows()); tools.append(this.export); }
      this.tags = el('div', 'ops-filter-tags'); this.notice = el('div', 'ops-notice'); this.notice.hidden = true; this.notice.setAttribute('role', 'alert');
      this.loading = el('p', 'ops-loading'); this.loading.hidden = true; this.loading.setAttribute('role', 'status');
      this.scroll = el('div', 'ops-table-scroll'); this.scroll.tabIndex = 0; this.scroll.setAttribute('role', 'region'); this.scroll.setAttribute('aria-label', this.heading + ', scroll for more columns');
      this.table = el('table', 'ops-table'); this.table.setAttribute('aria-label', this.heading);
      this.thead = el('thead'); this.tbody = el('tbody'); this.table.append(this.thead, this.tbody); this.scroll.append(this.table);
      const footer = el('div', 'ops-table-footer'); this.counter = el('p', 'ops-result-count'); this.counter.setAttribute('role', 'status');
      const label = el('label', 'ops-page-size', translate('Rows per page')), size = el('select');
      [10, 25, 50].forEach(n => size.add(new Option(String(n), String(n)))); size.value = String(this.state.size);
      size.addEventListener('change', () => { this.state.size = Number(size.value); this.save(); this.changed(); }); label.append(size);
      this.pager = el('nav', 'ops-pager'); this.pager.setAttribute('aria-label', this.heading + ' pages'); footer.append(this.counter, label, this.pager);
      this.host.append(toolbar, tools, this.tags, this.notice, this.loading, this.scroll, footer);
    }
    updateOptions() {
      const values = this.kind === 'accounts'
        ? { role: Object.entries(data.roles), status: data.account_statuses.map(v => [v, title(v)]) }
        : {
            status: data.reservation_statuses.map(v => [v, title(v)]),
            location: [...new Map([
              ...data.reservations.map(r => [String(r.lot_id), r.lot]),
              ...(data.parking_availability?.locations ?? []).map(lot => [String(lot.id), lot.name])
            ])]
          };
      Object.entries(values).forEach(([key, options]) => {
        const control = this.filters[key], current = this.state[key];
        const emptyOption = { status: translate('All statuses'), role: translate('All roles'), location: translate('All locations') }[key];
        control.replaceChildren(new Option(translate(emptyOption), ''));
        options.forEach(([v, t]) => control.add(new Option(key === 'role' ? translate(t) : t, v)));
        if (current && !options.some(([v]) => v === current)) control.add(new Option(current + ' (unavailable)', current)); control.value = current;
      });
      if (this.dateGroup) this.dateGroup.hidden = !data.reservations.some(r => r.created_ts != null) && !this.state.from && !this.state.to;
    }
    clear() {
      this.state.q = ''; this.search.value = '';
      Object.entries(this.filters).forEach(([key, input]) => { this.state[key] = ''; input.value = ''; if (input.type === 'date') { input.min = ''; input.max = ''; } }); this.changed();
    }
    renderTags() {
      this.tags.replaceChildren();
      const labels = { q: translate('Search'), role: translate('Role'), status: translate('Status'), location: translate('Location'), from: translate('Created from'), to: translate('Created to') };
      Object.keys(labels).forEach(key => {
        if (!this.state[key].trim()) return;
        const control = this.filters[key], value = control?.selectedOptions ? control.selectedOptions[0]?.textContent : this.state[key].trim();
        const chip = btn(labels[key] + ': ' + value + ' ×', () => {
          this.state[key] = ''; if (key === 'q') this.search.value = ''; else control.value = '';
          if (this.filters.from) { this.filters.from.max = this.state.to; this.filters.to.min = this.state.from; }
          this.changed(); this.search.focus();
        }, 'ops-filter-tag'); chip.setAttribute('aria-label', 'Remove ' + labels[key] + ' filter: ' + value); this.tags.append(chip);
      });
      if (this.tags.childElementCount) this.tags.append(btn(translate('Clear filters'), () => { this.clear(); this.search.focus(); }, 'ops-link-button'));
      this.tags.hidden = !this.tags.childElementCount; this.clearSearch.hidden = !this.search.value;
    }
    render() {
      if (this.kind === 'reservations') parkingMap?.setSelection(this.state.location);
      this.panel.dataset.density = this.density;
      const count = data[this.kind].length;
      this.panel.querySelector('[data-total]').textContent = translate('{count} records', { count });
      this.renderTags();
      const visible = this.columns.filter(c => !this.hidden.has(c.key)), header = el('tr');
      visible.forEach(c => {
        const th = el('th', (c.number ? 'ops-number ' : '') + (c.wide ? 'ops-wide' : '')); th.scope = 'col'; th.dataset.column = c.key;
        if (c.key === 'actions') th.textContent = c.label;
        else {
          th.setAttribute('aria-sort', this.state.sort === c.key ? (this.state.dir === 'asc' ? 'ascending' : 'descending') : 'none');
          const nextDirection = this.state.sort === c.key && this.state.dir === 'asc' ? translate('descending') : translate('ascending');
          const sort = btn('', () => {
            this.state.dir = this.state.sort === c.key && this.state.dir === 'asc' ? 'desc' : 'asc'; this.state.sort = c.key; this.state.page = 1; this.render();
            this.thead.querySelector('[data-column="' + c.key + '"] button').focus();
          }, 'ops-sort'); sort.setAttribute('aria-label', translate('Sort by {label}, {direction}', { label: c.label, direction: nextDirection })); sort.append(el('span', 'ops-sort-label', c.label), icon()); th.append(sort);
        } header.append(th);
      }); this.thead.replaceChildren(header);
      this.filtered = pipeline.filterSort(data[this.kind], this.kind, this.state);
      const page = pipeline.paginate(this.filtered, this.state.page, this.state.size); this.state.page = page.page;
      const body = document.createDocumentFragment();
      page.rows.forEach(record => {
        const tr = el('tr'); visible.forEach(c => {
          const td = el('td', c.number ? 'ops-number' : ''); td.dataset.column = c.key;
          if (c.key === 'actions') td.append(this.actions(record));
          else if (c.key === 'status') td.append(el('span', 'ops-badge ops-badge--' + record.status, title(record.status)));
          else { const value = c.key === 'fee' ? money(record.fee) : c.key === 'hours' ? translate('{count} hours', { count: record.hours }) : c.key === 'created_ts' ? record.created_at || '—' : c.key === 'role_label' ? translate(record[c.key]) : record[c.key]; const text = el('span', 'ops-cell-text', value); text.title = String(value); td.append(text); }
          tr.append(td);
        }); body.append(tr);
      });
      if (!page.total) {
        const tr = el('tr'), td = el('td', 'ops-empty'); td.colSpan = visible.length;
        const emptyText = data[this.kind].length
          ? (this.kind === 'reservations' ? translate('No matching reservations') : translate('No matching accounts'))
          : (this.kind === 'reservations' ? translate('No reservations yet') : translate('No accounts yet'));
        td.append(el('strong', '', translate(emptyText)));
        if (data[this.kind].length) td.append(btn(translate('Clear filters'), () => { this.clear(); this.search.focus(); })); tr.append(td); body.append(tr);
      } this.tbody.replaceChildren(body); this.counter.textContent = page.label; this.pager.replaceChildren();
      if (page.pages > 1) {
        const change = n => { this.state.page = n; this.render(); this.pager.querySelector('[aria-current="page"]')?.focus(); };
        const prev = btn(translate('Previous'), () => change(page.page - 1)); prev.disabled = page.page === 1; this.pager.append(prev);
        pipeline.pageNumbers(page.page, page.pages).forEach(n => {
          if (n === '…') { this.pager.append(el('span', 'ops-ellipsis', '…')); return; }
          const button = btn(String(n), () => change(n)); button.setAttribute('aria-label', 'Page ' + n); if (n === page.page) button.setAttribute('aria-current', 'page'); this.pager.append(button);
        }); const next = btn(translate('Next'), () => change(page.page + 1)); next.disabled = page.page === page.pages; this.pager.append(next);
      }
    }
    actions(record) {
      const group = el('div', 'ops-row-actions'), key = this.kind + ':' + record.id;
      const view = btn(translate('View details'), () => this.details(record, view)); view.setAttribute('aria-label', 'View details for ' + (this.kind === 'accounts' ? record.name : 'reservation ' + record.id)); group.append(view);
      if (this.kind === 'accounts' && data.can_manage_accounts) {
        const block = record.status === 'active', lastSuper = record.role === 'superadmin' && data.accounts.filter(u => u.role === 'superadmin' && u.status === 'active').length <= 1;
        const action = btn(pending.has(key) ? translate('Saving…') : block ? translate('Block') : translate('Unblock'), async () => {
          if (block && !await openDialog(translate('Block account?'), el('p', '', 'Block ' + record.name + ' (' + record.email + ')? This account will no longer be able to sign in.'), 'Block account', action)) return;
          mutate(this, record, 'toggle_user', block ? 'blocked' : 'active');
        }); action.dataset.recordAction = record.id;
        action.disabled = pending.has(key) || (block && (record.id === data.actor_id || lastSuper));
        if (block && record.id === data.actor_id) action.title = 'You cannot block your own account.';
        else if (block && lastSuper) action.title = 'The last active Super Admin cannot be blocked.';
        group.append(action);
      }
      if (this.kind === 'reservations' && record.allowed_statuses.length) {
        const form = el('form', 'ops-status-form'), select = el('select'), save = el('button', 'ops-button', pending.has(key) ? translate('Saving…') : translate('Save'));
        select.setAttribute('aria-label', 'New status for reservation ' + record.id); select.required = true; select.add(new Option(translate('Change status…'), ''));
        record.allowed_statuses.forEach(s => select.add(new Option(title(s), s))); save.type = 'submit'; save.dataset.recordAction = record.id;
        select.disabled = save.disabled = pending.has(key);
        form.addEventListener('submit', event => { event.preventDefault(); if (select.value) mutate(this, record, 'status', select.value); }); form.append(select, save); group.append(form);
      } return group;
    }
    details(record, origin) {
      const dl = el('dl', 'ops-details'), fields = this.columns.filter(c => c.key !== 'actions');
      if (this.kind === 'reservations') fields.push({ key: 'city', label: 'City' }, { key: 'slot', label: 'Space' });
      fields.forEach(c => { const value = c.key === 'fee' ? money(record.fee) : c.key === 'created_ts' ? record.created_at : record[c.key]; dl.append(el('dt', '', c.label), el('dd', '', value == null || value === '' ? '—' : value)); });
      openDialog(this.kind === 'accounts' ? translate('Account details') : 'Reservation #' + record.id, dl, null, origin);
    }
    loadingState(value) { this.loading.hidden = !value; this.loading.textContent = value ? translate('Refreshing records…') : ''; this.table.setAttribute('aria-busy', String(value)); this.refresh.disabled = value; }
    error(message, retry) { this.notice.replaceChildren(el('span', '', message)); if (retry) this.notice.append(btn('Retry', retry)); this.notice.hidden = false; }
    async exportRows() {
      this.changed(); this.export.disabled = true; this.export.textContent = translate('Exporting…');
      const params = new URLSearchParams({ table: this.kind }); ['q', 'role', 'status', 'location', 'from', 'to', 'sort', 'dir'].forEach(k => params.set(k, this.state[k]));
      try {
        const response = await fetch('api/export.php?' + params, { credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) throw new Error((await response.text()).slice(0, 240) || translate('Export failed.'));
        if (!response.headers.get('Content-Type')?.includes('text/csv')) throw new Error(translate('Unable to export. Please sign in again.'));
        const url = URL.createObjectURL(await response.blob()), link = el('a'); link.href = url; link.download = 'mypark-' + this.kind + '.csv'; document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
        toast('Filtered ' + this.kind + ' exported.', 'success');
      } catch (error) { this.error(error.message, () => this.exportRows()); }
      finally { this.export.disabled = false; this.export.textContent = translate('Export filtered results (CSV)'); }
    }
  }
  function applySnapshot(next) {
    if (!next || !Array.isArray(next.accounts) || !Array.isArray(next.reservations) || !next.summary || !Number.isInteger(next.revision)) throw new Error(translate('The server returned incomplete table data. Existing records have been kept.'));
    if (next.revision < data.revision) return;
    data = next; tables.forEach(t => { t.updateOptions(); t.render(); });
    parkingMap?.update(data.parking_availability);
    document.querySelectorAll('[data-ops-stat]').forEach(n => { const key = n.dataset.opsStat; n.textContent = key === 'revenue' ? money(data.summary[key]) : data.summary[key]; });
  }
  async function readResponse(response) {
    let result; try { result = await response.json(); } catch (error) {
      if (error.name === 'AbortError') throw error;
      throw new Error(translate('The server returned an unexpected response. Please refresh or sign in again.'));
    }
    if (!response.ok) throw new Error(translate(result?.error || 'The request could not be completed.')); return result;
  }
  async function refresh(table) {
    refreshController?.abort(); const sequence = ++refreshSequence; refreshController = new AbortController();
    setRefreshing(table); table.notice.hidden = true;
    try {
      const response = await fetch('api/operations.php', { credentials: 'same-origin', cache: 'no-store', signal: refreshController.signal });
      const result = await readResponse(response); if (sequence === refreshSequence) applySnapshot(result.data);
    } catch (error) {
      if (sequence === refreshSequence && error.name !== 'AbortError') {
        table.error('Refresh failed. Existing records have been kept. ' + error.message, () => refresh(table));
        parkingMap?.showRefreshError();
      }
    } finally { if (sequence === refreshSequence) setRefreshing(); }
  }
  function setRefreshing(table = null) {
    tables.forEach(item => item.loadingState(item === table));
    parkingMap?.setRefreshing(table !== null);
  }
  async function mutate(table, record, action, status) {
    const key = table.kind + ':' + record.id; if (pending.has(key)) return;
    refreshController?.abort(); ++refreshSequence; setRefreshing(); pending.add(key); table.notice.hidden = true; table.render();
    const payload = new FormData(); Object.entries({ action, id: record.id, status, expected_status: record.status, csrf: data.csrf }).forEach(([k, v]) => payload.set(k, String(v)));
    try {
      const result = await readResponse(await fetch('api/operations.php', { method: 'POST', body: payload, credentials: 'same-origin' })); applySnapshot(result.data); toast(result.message, 'success');
    } catch (error) { table.error('Update failed. ' + error.message + ' Refresh records before retrying the action.', () => refresh(table)); toast(error.message, 'error'); }
    finally {
      pending.delete(key); table.render();
      if (document.activeElement === document.body || !document.activeElement?.isConnected) (table.tbody.querySelector('[data-record-action="' + record.id + '"]') || table.refresh).focus();
    }
  }
  document.querySelectorAll('[data-table-host]').forEach(host => tables.push(new ManagementTable(host, host.dataset.tableHost)));
  const mapPanel = document.getElementById('parking-availability');
  const reservations = tables.find(table => table.kind === 'reservations');
  if (mapPanel && reservations && window.MyParkParkingMap) {
    const locationFilter = reservations.filters.location;
    parkingMap = window.MyParkParkingMap.mount(mapPanel, {
      selectLocation(id) {
        locationFilter.value = id;
        locationFilter.dispatchEvent(new Event('change'));
      },
      refresh: () => refresh(reservations)
    });
    parkingMap.update(data.parking_availability);
  }
}());
