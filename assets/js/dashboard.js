/* Dashboard tables load filtered pages independently of the availability refresh. */
(function () {
  'use strict';
  const translate = window.MyParkLocale?.text ?? (message => message);

  const clock = document.querySelector('[data-ops-clock]');
  if (clock) {
    const time = clock.querySelector('.ops-clock__time');
    const date = clock.querySelector('.ops-clock__date');
    const locale = document.documentElement.lang || 'en-MY';
    const timeZone = 'Asia/Kuala_Lumpur';
    const timeFormat = new Intl.DateTimeFormat(locale, {
      timeZone, hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'
    });
    const dateFormat = new Intl.DateTimeFormat(locale, { timeZone, dateStyle: 'full' });

    const updateClock = () => {
      const now = new Date();
      const dateText = dateFormat.format(now);
      time.textContent = timeFormat.format(now);
      if (date.textContent !== dateText) date.textContent = dateText;
      clock.dateTime = now.toISOString();
    };
    updateClock();
    window.setInterval(updateClock, 1000);
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) updateClock();
    });
  }

  const bootstrap = document.getElementById('operations-data');
  if (!bootstrap) return;
  const fastAvailability = bootstrap.dataset.fastAvailability === 'true';
  let data = JSON.parse(bootstrap.textContent);
  const { create: tableRequest, pageLabel } = window.MyParkTableRequest;
  const tables = [], pending = new Set();
  let refreshController, refreshSequence = 0;
  let parkingMap, availabilityPending = false;
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
    dialogFallback = origin?.closest('.ops-panel')?.querySelector('.ops-search input');
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
      { key: 'name', label: translate('Name'), wide: true }, { key: 'email', label: translate('Email'), wide: true },
      { key: 'role_label', label: translate('Role') }, { key: 'status', label: translate('Status') }, { key: 'actions', label: translate('Actions') }
    ],
    reservations: [
      { key: 'id', label: translate('ID'), number: true }, { key: 'user', label: translate('Member'), wide: true },
      { key: 'lot', label: translate('Location'), wide: true }, { key: 'plate', label: translate('Plate') },
      { key: 'hours', label: translate('Duration'), number: true }, { key: 'fee', label: translate('Fee'), number: true },
      { key: 'status', label: translate('Status') }, { key: 'created_ts', label: translate('Created'), detailOnly: true },
      { key: 'actions', label: translate('Actions') }
    ]
  };
  class ManagementTable {
    constructor(host, kind) {
      this.host = host; this.kind = kind; this.panel = host.closest('.ops-panel'); this.columns = columns[kind];
      this.heading = kind === 'accounts' ? translate('Account Management') : translate('Reservation Management');
      this.storageKey = 'MyPark:operations:preferences:' + data.actor_id + ':' + kind;
      let prefs = {}; try { prefs = JSON.parse(localStorage.getItem(this.storageKey) || '{}') || {}; } catch (_) { /* Optional storage. */ }
      const dated = kind === 'reservations' && data.reservations.some(r => r.created_ts != null);
      this.state = { q: '', status: '', role: '', location: '', from: '', to: '', sort: dated ? 'created_ts' : 'id', dir: kind === 'accounts' ? 'asc' : 'desc', page: 1, size: [10, 25, 50].includes(prefs.size) ? prefs.size : 10 };
      this.request = tableRequest();
      this.build(); this.updateOptions(); this.load();
    }
    save() {
      try { localStorage.setItem(this.storageKey, JSON.stringify({ size: this.state.size })); } catch (_) { /* Optional storage. */ }
    }
    changed() { this.state.page = 1; this.load(); }
    load(focus, background = false) {
      clearTimeout(this.debounce);
      this.debounce = null;
      this.notice.hidden = true;
      const { page, ...state } = this.state;
      const params = new URLSearchParams({ table: this.kind, ...state, row_page: page });
      if (background) params.set('background', '1');
      if (!background) {
        this.renderTags();
        if (this.kind === 'reservations') parkingMap?.setSelection(state.location);
      }
      return this.request.run(async signal => {
        const response = await fetch('api/dashboard.php?' + params, { credentials: 'same-origin', cache: 'no-store', signal });
        const result = await readResponse(response);
        if (!Array.isArray(result.table?.rows)) throw new Error(translate('The table could not be updated.'));
        return result.table;
      }, pageData => {
        if (background && (pending.size || document.querySelector('dialog[open]'))) return;
        this.paged = pageData;
        this.state.page = pageData.page;
        this.appliedSort = state.sort;
        this.appliedDirection = state.dir;
        const active = document.activeElement;
        const inPager = this.pager.contains(active);
        const restoreFocus = this.table.contains(active) || inPager;
        const focusKey = focus ?? (inPager ? 'page' : active?.closest('th')?.dataset.column);
        this.render(background);
        if (restoreFocus && (focus || background)) {
          const target = focusKey === 'page' ? this.pager.querySelector('[aria-current="page"]')
            : this.thead.querySelector('[data-column="' + focusKey + '"] button');
          (target ?? this.search).focus();
        }
      }, error => this.error(error.message, () => this.load(focus)), busy => {
        this.pageBusy = busy;
        if (!background) this.updateLoading();
      });
    }
    build() {
      const toolbar = el('div', 'ops-toolbar'), searchWrap = el('div', 'ops-search');
      this.host.replaceChildren(toolbar);
      this.search = el('input'); this.search.type = 'search'; this.search.placeholder = this.kind === 'accounts' ? translate('Search name or email') : translate('Search member, plate or location'); this.search.setAttribute('aria-label', this.search.placeholder);
      this.search.addEventListener('input', () => {
        this.state.q = this.search.value;
        this.request.cancel(); this.loadingState(false);
        clearTimeout(this.debounce); this.debounce = setTimeout(() => this.changed(), 300);
      });
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
        const updateDates = () => {
          const { from, to } = this.filters;
          this.state.from = from.value;
          this.state.to = to.value;
          from.max = to.value;
          to.min = from.value;
          this.changed();
        };
        [['from', translate('Created from')], ['to', translate('Created to')]].forEach(([key, text]) => {
          const field = el('div'), label = el('label', '', text), input = el('input');
          input.id = 'reservation-created-' + key;
          input.type = 'date';
          label.htmlFor = input.id;
          input.addEventListener('change', updateDates);
          this.filters[key] = input;
          field.append(label, input);
          this.dateGroup.append(field);
        });
        toolbar.append(this.dateGroup);
        this.syncDateRange = window.MyParkDateRange?.enhance(this.filters.from, this.filters.to, updateDates);
      }
      const tools = el('div', 'ops-tools');
      if (data.can_export) { this.export = btn(translate('Export Report'), () => this.exportRows()); tools.append(this.export); }
      tools.hidden = !data.can_export;
      this.tags = el('div', 'ops-filter-tags'); this.notice = el('div', 'ops-notice'); this.notice.hidden = true; this.notice.setAttribute('role', 'alert');
      this.loading = el('p', 'ops-loading'); this.loading.hidden = true; this.loading.setAttribute('role', 'status');
      this.scroll = el('div', 'ops-table-scroll'); this.scroll.tabIndex = 0; this.scroll.setAttribute('role', 'region'); this.scroll.setAttribute('aria-label', this.heading + ', scroll for more columns');
      this.table = el('table', 'ops-table'); this.table.setAttribute('aria-label', this.heading);
      this.thead = el('thead'); this.tbody = el('tbody'); this.table.append(this.thead, this.tbody); this.scroll.append(this.table);
      const footer = el('div', 'table-footer ops-table-footer'); this.counter = el('p', 'ops-result-count'); this.counter.setAttribute('role', 'status');
      const label = el('label', 'table-page-size', translate('Rows per page')), size = el('select');
      [10, 25, 50].forEach(n => size.add(new Option(String(n), String(n)))); size.value = String(this.state.size);
      size.addEventListener('change', () => { this.state.size = Number(size.value); this.save(); this.changed(); }); label.append(size);
      this.pager = el('nav', 'pagination'); this.pager.setAttribute('aria-label', this.heading + ' · ' + translate('Pagination')); footer.append(this.counter, this.pager, label);
      this.host.append(tools, this.tags, this.notice, this.loading, this.scroll, footer);
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
      this.syncDateRange?.();
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
    render(background = false) {
      const page = this.paged;
      if (!page) return;
      const count = data[this.kind].length;
      this.panel.querySelector('[data-total]').textContent = translate('{count} records', { count });
      if (!background) this.renderTags();
      const visible = this.columns.filter(c => !c.detailOnly), header = el('tr');
      visible.forEach(c => {
        const th = el('th', (c.number ? 'ops-number ' : '') + (c.wide ? 'ops-wide' : '')); th.scope = 'col'; th.dataset.column = c.key;
        if (c.key === 'actions') th.textContent = c.label;
        else {
          if (this.appliedSort === c.key) th.setAttribute('aria-sort', this.appliedDirection === 'asc' ? 'ascending' : 'descending');
          const nextDirection = this.state.sort === c.key && this.state.dir === 'asc' ? translate('descending') : translate('ascending');
          const sort = btn('', () => {
            this.state.dir = this.state.sort === c.key && this.state.dir === 'asc' ? 'desc' : 'asc';
            this.state.sort = c.key; this.state.page = 1; this.load(c.key);
          }, 'ops-sort'); sort.setAttribute('aria-label', translate('Sort by {label}, {direction}', { label: c.label, direction: nextDirection })); sort.append(el('span', 'ops-sort-label', c.label), icon()); th.append(sort);
        } header.append(th);
      }); this.thead.replaceChildren(header);
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
      } this.tbody.replaceChildren(body); this.counter.textContent = pageLabel(page);
      window.MyParkTableData.renderPager(this.pager, page, number => {
        this.state.page = number;
        this.load('page');
      });
    }
    actions(record) {
      const group = el('div', 'ops-row-actions'), key = this.kind + ':' + record.id;
      const view = btn(translate('View details'), () => this.details(record, view)); view.setAttribute('aria-label', 'View details for ' + (this.kind === 'accounts' ? record.name : 'reservation ' + record.id)); group.append(view);
      if (this.kind === 'accounts' && data.can_manage_accounts) {
        const block = record.status === 'active', lastSuper = record.role === 'superadmin' && data.accounts.filter(u => u.role === 'superadmin' && u.status === 'active').length <= 1;
        const action = btn(pending.has(key) ? translate('Saving…') : block ? translate('Block') : translate('Unblock'), async () => {
          if (block && !await openDialog(translate('Block account?'), el('p', '', 'Block ' + record.name + ' (' + record.email + ')? This account will no longer be able to sign in.'), 'Block account', action)) return;
          mutateAccount(this, record, block ? 'blocked' : 'active');
        }); action.dataset.recordAction = record.id;
        action.disabled = pending.has(key) || (block && (record.id === data.actor_id || lastSuper));
        if (block && record.id === data.actor_id) action.title = 'You cannot block your own account.';
        else if (block && lastSuper) action.title = 'The last active Super Admin cannot be blocked.';
        group.append(action);
      }
      return group;
    }
    details(record, origin) {
      const dl = el('dl', 'ops-details'), fields = this.columns.filter(c => c.key !== 'actions');
      if (this.kind === 'reservations') fields.push({ key: 'city', label: 'City' }, { key: 'slot', label: 'Space' });
      fields.forEach(c => { const value = c.key === 'fee' ? money(record.fee) : c.key === 'created_ts' ? record.created_at : record[c.key]; dl.append(el('dt', '', c.label), el('dd', '', value == null || value === '' ? '—' : value)); });
      openDialog(this.kind === 'accounts' ? translate('Account details') : 'Reservation #' + record.id, dl, null, origin);
    }
    loadingState(value) { this.pageBusy = value; this.updateLoading(); }
    updateLoading() {
      const busy = Boolean(this.pageBusy || this.snapshotBusy);
      this.loading.hidden = !busy;
      this.loading.textContent = busy ? translate('Refreshing records…') : '';
      this.table.setAttribute('aria-busy', String(busy));
    }
    error(message, retry) { this.notice.replaceChildren(el('span', '', message)); if (retry) this.notice.append(btn('Retry', retry)); this.notice.hidden = false; }
    async exportRows() {
      this.export.disabled = true; this.export.textContent = translate('Exporting…');
      const params = new URLSearchParams({ table: this.kind }); ['q', 'role', 'status', 'location', 'from', 'to', 'sort', 'dir'].forEach(k => params.set(k, this.state[k]));
      try {
        const response = await fetch('api/export.php?' + params, { credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) throw new Error((await response.text()).slice(0, 240) || translate('Export failed.'));
        if (!response.headers.get('Content-Type')?.includes('text/csv')) throw new Error(translate('Unable to export. Please sign in again.'));
        const url = URL.createObjectURL(await response.blob()), link = el('a'); link.href = url; link.download = 'mypark-' + this.kind + '.csv'; document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
        toast('Filtered ' + this.kind + ' exported.', 'success');
      } catch (error) { this.error(error.message, () => this.exportRows()); }
      finally { this.export.disabled = false; this.export.textContent = translate('Export Report'); }
    }
  }
  function applySnapshot(next, background = false) {
    if (!next || !Array.isArray(next.accounts) || !Array.isArray(next.reservations) || !next.summary || !Number.isInteger(next.revision)) throw new Error(translate('The server returned incomplete table data. Existing records have been kept.'));
    if (next.revision < data.revision) return;
    data = next; tables.forEach(t => { t.updateOptions(); t.load(undefined, background); });
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
  function dashboardIsBusy() {
    return document.hidden || pending.size || document.querySelector('dialog[open]')
      || tables.some(table => table.pageBusy || table.debounce);
  }
  async function refresh(table, background = false) {
    if (background && (refreshController || dashboardIsBusy())) return;
    refreshController?.abort(); const sequence = ++refreshSequence; refreshController = new AbortController();
    const controller = refreshController;
    const timeout = window.setTimeout(() => controller.abort(), 8000);
    if (!background) { setRefreshing(table); table.notice.hidden = true; }
    try {
      const url = background ? 'api/dashboard.php?background=1' : 'api/dashboard.php';
      const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
      const result = await readResponse(response);
      if (sequence === refreshSequence && (!background || !dashboardIsBusy())) applySnapshot(result.data, background);
    } catch (error) {
      if (sequence === refreshSequence && error.name !== 'AbortError') {
        table.error('Refresh failed. Existing records have been kept. ' + error.message, () => refresh(table));
        parkingMap?.showRefreshError();
      }
    } finally {
      window.clearTimeout(timeout);
      if (sequence === refreshSequence) {
        refreshController = null;
        if (!background) setRefreshing();
      }
    }
  }

  async function refreshAvailability(reservations) {
    if (document.hidden || refreshController || pending.size || availabilityPending) return;
    const sequence = refreshSequence;
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 8000);
    availabilityPending = true;

    try {
      const response = await fetch('api/availability.php?view=dashboard', {
        credentials: 'same-origin', cache: 'no-store', signal: controller.signal
      });
      const snapshot = await readResponse(response);
      if (sequence !== refreshSequence) return;
      if (!Array.isArray(snapshot.locations) || !Number.isFinite(Date.parse(snapshot.refreshed_at))) {
        throw new Error('Invalid availability snapshot.');
      }
      const options = locations => JSON.stringify(locations.map(lot => [lot.id, lot.name]));
      const locationsChanged = options(snapshot.locations) !== options(data.parking_availability.locations);
      data.parking_availability = snapshot;
      if (locationsChanged) reservations.updateOptions();
      parkingMap.update(snapshot);
    } catch (_) {
      if (sequence === refreshSequence) parkingMap.showRefreshError();
    } finally {
      window.clearTimeout(timeout);
      availabilityPending = false;
    }
  }

  function setRefreshing(table = null) {
    tables.forEach(item => { item.snapshotBusy = item === table; item.updateLoading(); });
    if (table) parkingMap?.clearRefreshError();
  }
  async function mutateAccount(table, record, status) {
    const key = table.kind + ':' + record.id; if (pending.has(key)) return;
    refreshController?.abort(); refreshController = null; ++refreshSequence; setRefreshing(); pending.add(key); table.notice.hidden = true; table.render();
    const payload = new FormData(); Object.entries({ action: 'toggle_user', id: record.id, status, expected_status: record.status, csrf: data.csrf }).forEach(([k, v]) => payload.set(k, String(v)));
    try {
      const result = await readResponse(await fetch('api/dashboard.php', { method: 'POST', body: payload, credentials: 'same-origin' })); applySnapshot(result.data); toast(result.message, 'success');
    } catch (error) { table.error('Update failed. ' + error.message + ' Refresh records before retrying the action.', () => refresh(table)); toast(error.message, 'error'); }
    finally {
      pending.delete(key); table.render();
      if (document.activeElement === document.body || !document.activeElement?.isConnected) (table.tbody.querySelector('[data-record-action="' + record.id + '"]') || table.search).focus();
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
      }
    });
    parkingMap.update(data.parking_availability);
    if (fastAvailability) {
      window.setInterval(() => refreshAvailability(reservations), 20000);
    }
  }
  if (reservations && data.parking_availability) {
    const refreshReservations = () => refresh(reservations, true);
    window.setInterval(refreshReservations, 60000);
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) refreshReservations();
    });
  }
}());
