/* Staff tables request filtered and sorted pages from PHP. */
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
      const focusable = [...sidebar.querySelectorAll('a[href], button:not([disabled]), select:not([disabled])')];
      const first = focusable[0], last = focusable.at(-1);
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  mobile.addEventListener('change', () => setMenu(false, false)); setMenu(false, false);

  const bootstrap = document.getElementById('staff-data');
  if (!bootstrap) return; // Existing My Profile uses the shared sidebar only.
  let data = JSON.parse(bootstrap.textContent);
  const { create: tableRequest, pageLabel } = window.MyParkTableRequest;
  const page = document.getElementById('staff-app').dataset.page;
  const money = cents => 'RM ' + (Number(cents) / 100).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const dateFormat = new Intl.DateTimeFormat(document.documentElement.lang, { timeZone: 'Asia/Kuala_Lumpur', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });
  const dateText = value => { const date = new Date(value); return Number.isNaN(date.getTime()) ? '—' : dateFormat.format(date); };
  const plateKey = value => value.toUpperCase().replace(/[^A-Z0-9]/g, '');
  const tables = [];
  const dialog = document.getElementById('staff-dialog');
  const dialogBody = document.getElementById('staff-dialog-body');
  const closeDialog = document.getElementById('staff-dialog-close');
  let origin, submitting = false;
  closeDialog.addEventListener('click', () => { if (!submitting) dialog.close(); });
  dialog.addEventListener('cancel', event => { if (submitting) event.preventDefault(); });
  dialog.addEventListener('close', () => {
    if (origin?.isConnected) origin.focus();
    else (document.getElementById('staff-new') || document.getElementById('staff-page-title')).focus();
  });
  function openDialog(title, trigger) {
    origin = trigger || document.activeElement;
    document.getElementById('staff-dialog-title').textContent = translate(title);
    dialogBody.replaceChildren(); dialog.showModal(); closeDialog.focus();
  }
  function badge(status, kind = 'fine') {
    const label = statuses(kind)[status] || status;
    return el('span', 'staff-badge staff-badge--' + status, translate(label));
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
    tables.forEach(table => table.load());
    if (page === 'dashboard') renderDashboard();
  }

  const fineColumns = [
        ['code', translate('Fine ID')], ['plate', translate('Vehicle Plate')], ['violation', translate('Violation Type')], ['occurred_ts', translate('Date')],
    ['amount_cents', translate('Amount (RM)'), true], ['status', translate('Status')], ['actions', translate('Actions')]
  ];
  const maintenanceColumns = [['code', translate('Record ID')], ['title', translate('Issue')], ['location', translate('Location')], ['status', translate('Status')], ['updated_at', translate('Updated')], ['actions', translate('Actions')]];
  const parkingColumns = [['id', translate('Booking ID')], ['plate', translate('Vehicle Plate')], ['location', translate('Location')],
    ['hours', translate('Hours'), true], ['created_at', translate('Created')], ['status', translate('Status')]];
  const tabs = {
    fines: { kind: 'fine', label: 'Fine records', columns: fineColumns, sort: 'occurred_ts', empty: 'No fine records yet.' },
    'recent-fines': { kind: 'fine', label: 'Recent fine records', columns: fineColumns, sort: 'created_at', empty: 'No fine records yet.' },
    maintenance: { kind: 'maintenance', label: 'Maintenance records', columns: maintenanceColumns, sort: 'updated_at', empty: 'No maintenance records yet.' },
    'vehicle-fines': { kind: 'fine', label: 'Related fines', columns: fineColumns, sort: 'occurred_ts', empty: 'No fine records found for this vehicle.' },
    'vehicle-bookings': { kind: 'booking', label: 'Parking records', columns: parkingColumns, sort: 'created_at', empty: 'No parking records found for this vehicle.' }
  };
  function statuses(kind) {
    if (kind === 'fine') return data.fine_statuses;
    if (kind === 'maintenance') return data.maintenance_statuses;
    return { active: 'Active', completed: 'Completed', cancelled: 'Cancelled' };
  }
  function displayValue(record, key, kind) {
    if (key === 'amount_cents') return money(record.amount_cents);
    if (key === 'occurred_ts') return dateText(record.occurred_ts * 1000);
    if (kind === 'booking' && key === 'id') return '#' + record.id;
    if (kind === 'booking' && key === 'created_at') return record.created_at || '—';
    if (key === 'updated_at' || key === 'created_at') return dateText(record[key]);
    if (key === 'status') return translate(statuses(kind)[record.status] || record.status);
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
  function createTable(records, { columns, kind, label }, emptyText) {
    const scroll = el('div', 'staff-table-scroll'); scroll.tabIndex = 0; scroll.setAttribute('role', 'region'); scroll.setAttribute('aria-label', translate(label));
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
    constructor(host, key, plate = '', onUpdate) {
      this.key = key; this.config = tabs[key]; this.plate = plate; this.onUpdate = onUpdate;
      const { kind, sort, label: title } = this.config;
      this.state = { q: '', status: '', sort, dir: 'desc', page: 1, size: 10 };
      this.request = tableRequest();
      const tools = el('div', 'staff-table-tools');
      const searchLabel = { fine: 'Search Fine ID or Vehicle Plate', maintenance: 'Search ID, issue or location',
        booking: 'Search parking records' }[kind];
      this.search = el('input'); this.search.type = 'search'; this.search.placeholder = translate(searchLabel);
      this.search.setAttribute('aria-label', this.search.placeholder);
      this.search.addEventListener('input', () => {
        this.state.q = this.search.value;
        this.request.cancel(); this.loadingState(false);
        clearTimeout(this.timer); this.timer = setTimeout(() => this.resetPage(), 300);
      });
      this.status = el('select'); this.status.setAttribute('aria-label', translate('Filter by status')); this.status.add(new Option(translate('All statuses'), ''));
      Object.entries(statuses(kind)).forEach(([value, label]) => this.status.add(new Option(translate(label), value)));
      this.status.addEventListener('change', () => { this.state.status = this.status.value; this.resetPage(); });
      const clear = button(translate('Clear filters'), () => {
        this.state.q = this.state.status = this.search.value = this.status.value = '';
        this.resetPage(); this.search.focus();
      });
      tools.append(this.search, this.status, clear);
      this.output = el('div');
      this.loading = el('p', 'staff-request-status'); this.loading.setAttribute('role', 'status'); this.loading.hidden = true;
      this.error = el('div', 'staff-error-banner'); this.error.hidden = true; this.error.setAttribute('role', 'alert');
      const footer = el('div', 'table-footer staff-table-footer'); this.count = el('span', 'staff-muted'); this.count.setAttribute('role', 'status');
      const label = el('label', 'table-page-size', translate('Rows per page')), select = el('select');
      [10, 25, 50].forEach(n => select.add(new Option(String(n), String(n)))); select.addEventListener('change', () => { this.state.size = Number(select.value); this.resetPage(); }); label.append(select);
      this.pages = el('nav', 'pagination'); this.pages.setAttribute('aria-label', translate(title) + ' · ' + translate('Pagination'));
      footer.append(this.count, this.pages, label); host.append(tools, this.loading, this.error, this.output, footer);
      this.load();
    }
    resetPage() { this.state.page = 1; this.load(); }
    cancel() { clearTimeout(this.timer); this.request.cancel(); }
    loadingState(busy) {
      this.loading.hidden = !busy;
      this.loading.textContent = busy ? translate('Refreshing records…') : '';
      this.output.setAttribute('aria-busy', String(busy));
    }
    load(focus) {
      clearTimeout(this.timer);
      this.error.hidden = true;
      const { page: rowPage, ...state } = this.state;
      const params = new URLSearchParams({ page, table: this.key, ...state, row_page: rowPage });
      if (this.plate) params.set('plate', this.plate);
      this.request.run(async signal => {
        const response = await fetch('api/staff.php?' + params, { credentials: 'same-origin', cache: 'no-store', signal });
        const result = await readResponse(response);
        if (!Array.isArray(result.table?.rows)) throw new Error(translate('The table could not be updated.'));
        return result.table;
      }, paged => {
        const restore = this.output.contains(document.activeElement) || this.pages.contains(document.activeElement);
        this.paged = paged;
        this.state.page = paged.page;
        this.render(paged, state);
        this.onUpdate?.();
        if (restore && focus) {
          const target = focus === 'page' ? this.pages.querySelector('[aria-current="page"]')
            : this.output.querySelector('th[data-column="' + focus + '"] button');
          (target ?? this.search).focus();
        }
      }, failure => {
        this.error.replaceChildren(el('span', '', translate(failure.message)), button(translate('Retry'), () => this.load(focus)));
        this.error.hidden = false;
      }, busy => this.loadingState(busy));
    }
    render(paged, appliedState) {
      const state = this.state;
      const empty = translate(paged.unfiltered ? 'No matching records' : this.config.empty);
      const rendered = createTable(paged.rows, this.config, empty);
      this.config.columns.forEach(([key, label]) => {
        if (key === 'actions') return;
        const th = rendered.querySelector('th[data-column="' + key + '"]');
        if (appliedState.sort === key) th.setAttribute('aria-sort', appliedState.dir === 'asc' ? 'ascending' : 'descending');
        const nextDirection = state.sort === key && state.dir === 'asc' ? translate('descending') : translate('ascending');
        const sort = button('', () => {
          state.dir = state.sort === key && state.dir === 'asc' ? 'desc' : 'asc';
          state.sort = key; state.page = 1; this.load(key);
        }); sort.className = 'staff-sort';
        sort.setAttribute('aria-label', translate('Sort by {label}, {direction}', { label, direction: nextDirection }));
        const arrow = el('span', 'staff-sort-mark'); arrow.setAttribute('aria-hidden', 'true');
        // Fixed geometry keeps both arrows in place when the direction changes.
        arrow.innerHTML = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path class="staff-sort-up" d="m4 6 4-4 4 4"/><path class="staff-sort-down" d="m4 10 4 4 4-4"/></svg>';
        sort.append(el('span', 'staff-sort-label', label), arrow); th.replaceChildren(sort);
      });
      this.output.replaceChildren(rendered); this.count.textContent = pageLabel(paged);
      const count = document.getElementById('staff-record-count');
      if (count) count.textContent = translate('{count} records', { count: paged.unfiltered });
      window.MyParkTableData.renderPager(this.pages, paged, number => {
        state.page = number;
        this.load('page');
      });
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
    const error = document.getElementById(input.id + '-error');
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
  function maintenancePhotoUrl(record) {
    return 'api/maintenance-photo.php?' + new URLSearchParams({ id: record.id, v: record.photo_version });
  }
  function maintenancePhotoError(file) {
    const limit = Number(document.getElementById('staff-app').dataset.photoMaxBytes);
    return file.size > limit ? translate('Choose a maintenance photo up to 5 MB.') : '';
  }
  function maintenancePhotoField(form, fields, record) {
    const input = field(fields, 'photo', translate('Attach photo (optional)'), { type: 'file', required: false });
    input.accept = 'image/jpeg,image/png,image/webp';
    const group = input.parentElement;
    group.classList.add('staff-maintenance-photo');
    const hint = el('p', 'staff-help', translate('JPG, PNG or WebP, up to 5 MB.'));
    hint.id = 'staff-photo-formats';
    input.setAttribute('aria-describedby', input.getAttribute('aria-describedby') + ' ' + hint.id);
    const preview = el('img', 'staff-maintenance-preview');
    preview.alt = translate('Maintenance photo preview');
    const video = el('video', 'staff-maintenance-preview');
    video.muted = true; video.playsInline = true; video.hidden = true;
    video.setAttribute('aria-label', translate('Live camera preview'));
    const status = el('p', 'staff-help');
    status.setAttribute('role', 'status');
    status.hidden = true;
    const savedPhoto = record?.photo_version ? maintenancePhotoUrl(record) : '';
    let camera, captured, objectUrl, attempt = 0;

    function message(text) {
      status.textContent = text ? translate(text) : '';
      status.hidden = !text;
    }
    function renderPreview(file = null) {
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = file ? URL.createObjectURL(file) : '';
      const src = objectUrl || savedPhoto;
      if (src) preview.src = src;
      else preview.removeAttribute('src');
      preview.hidden = !src;
      clear.hidden = !file && !input.files.length;
    }
    function stopCamera() {
      attempt++;
      camera?.getTracks().forEach(track => track.stop());
      camera = null;
      video.srcObject = null;
      video.hidden = true;
      preview.hidden = !preview.hasAttribute('src');
      take.hidden = true;
      cancel.hidden = true;
      open.hidden = false;
    }
    const open = button(translate('Open camera'), async () => {
      if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        message('Camera unavailable. Attach a saved photo instead.');
        return;
      }
      const current = ++attempt;
      open.hidden = true;
      cancel.hidden = false;
      message('Opening the camera…');
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
        if (current !== attempt || !dialog.open) {
          stream.getTracks().forEach(track => track.stop());
          return;
        }
        camera = stream;
        video.srcObject = stream;
        video.hidden = false;
        preview.hidden = true;
        await video.play();
        if (current !== attempt) return;
        take.disabled = false;
        take.hidden = false;
        message('');
        take.focus();
      } catch {
        if (current !== attempt) return;
        stopCamera();
        message('Camera unavailable. Attach a saved photo instead.');
        open.focus();
      }
    });
    const take = button(translate('Take photo'), () => {
      if (!video.videoWidth || !video.videoHeight) return;
      const current = attempt;
      const canvas = document.createElement('canvas');
      const scale = Math.min(1, 1600 / Math.max(video.videoWidth, video.videoHeight));
      canvas.width = Math.round(video.videoWidth * scale);
      canvas.height = Math.round(video.videoHeight * scale);
      canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
      take.disabled = true;
      canvas.toBlob(photo => {
        if (current !== attempt) return;
        stopCamera();
        if (!photo) {
          message('The camera could not take a photo. Try again or attach one.');
          open.focus();
          return;
        }
        captured = photo;
        input.value = '';
        setFieldError(input, '');
        renderPreview(photo);
        message('Photo ready to attach.');
        open.focus();
      }, 'image/jpeg', 0.85);
    }, false);
    const cancel = button(translate('Close camera'), () => { stopCamera(); message(''); open.focus(); });
    const clear = button(translate('Clear new photo'), () => {
      captured = null;
      input.value = '';
      renderPreview();
      setFieldError(input, '');
      message('');
      input.focus();
    });
    stopCamera();
    renderPreview();
    const actions = el('div', 'staff-maintenance-photo-actions');
    actions.append(open, take, cancel, clear);
    group.append(hint, actions, status, video, preview);

    input.addEventListener('change', () => {
      stopCamera();
      captured = null;
      const file = input.files[0];
      const error = file ? maintenancePhotoError(file) : '';
      renderPreview(error ? null : file);
      setFieldError(input, error);
      message(file && !error ? 'Photo ready to attach.' : '');
    });
    form.addEventListener('formdata', event => {
      if (captured) event.formData.set('photo', captured, 'maintenance.jpg');
    });
    form.addEventListener('submit', event => {
      if (!cancel.hidden) {
        event.preventDefault();
        message('Take a photo or close the camera before saving.');
      }
    }, true);
    dialog.addEventListener('close', () => {
      stopCamera();
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      window.removeEventListener('pagehide', stopCamera);
    }, { once: true });
    window.addEventListener('pagehide', stopCamera);
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
      const locations = data.locations.filter(location => !location.archived || location.id === record?.lot_id);
      field(fields, 'title', translate('Issue title'), { value: record?.title, maxLength: 120 });
      field(fields, 'lot_id', translate('Parking location'), { tag: 'select', options: [['', 'Select a location'], ...locations.map(l => [l.id, l.name])], value: record?.lot_id });
      field(fields, 'description', translate('Description'), { tag: 'textarea', maxLength: 2000, value: record?.description });
      field(fields, 'status', translate('Status'), { tag: 'select', options: Object.entries(data.maintenance_statuses), value: record?.status || 'open' });
      maintenancePhotoField(form, fields, record);
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
    if (event.defaultPrevented) return;
    event.preventDefault(); if (submitting) return;
    const inputs = [...form.querySelectorAll('input:not([type="hidden"]),select,textarea')];
    let invalid;
    inputs.forEach(input => { setFieldError(input, input.validity.valid ? '' : input.validationMessage); if (!input.validity.valid && !invalid) invalid = input; });
    if (invalid) { invalid.focus(); return; }
    const photoInput = form.elements.namedItem('photo');
    const photoError = photoInput?.files[0] ? maintenancePhotoError(photoInput.files[0]) : '';
    if (photoError) { setFieldError(photoInput, photoError); photoInput.focus(); return; }
    const payload = new FormData(form); payload.set('csrf', data.csrf);
    const message = form.querySelector('.staff-form-error'); message.hidden = true;
    submitting = true; closeDialog.disabled = true; form.setAttribute('aria-busy', 'true'); form.querySelector('fieldset').disabled = true;
    form.querySelectorAll('button').forEach(b => b.disabled = true); const save = form.querySelector('[type="submit"]'); save.textContent = translate('Saving…');
    try {
      const response = await fetch('api/staff.php?page=' + page, { method: 'POST', body: payload, credentials: 'same-origin' });
      const result = await readResponse(response); applyData(result.data); dialog.close();
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
    dialogBody.append(details);
    if (kind === 'maintenance' && record.photo_version) {
      const photo = el('section', 'staff-maintenance-photo');
      const link = el('a', 'staff-maintenance-photo-link');
      link.href = maintenancePhotoUrl(record); link.target = '_blank'; link.rel = 'noopener noreferrer';
      const image = el('img', 'staff-maintenance-preview');
      image.src = link.href; image.alt = translate('Open maintenance photo in a new tab');
      link.append(image);
      photo.append(el('h3', '', translate('Issue photo')), link);
      dialogBody.append(photo);
    }
    dialogBody.append(el('h3', '', 'Audit history'));
    const history = el('ol', 'staff-audit');
    const tracked = { plate: translate('Plate'), violation: translate('Violation'), amount_cents: translate('Amount'), occurred_at: translate('Date'), notes: translate('Notes'), status: translate('Status'), title: translate('Issue'), location: translate('Location'), description: translate('Description'), payment_reference: translate('Payment reference'), deleted_at: 'Removed at' };
    [...record.audit].reverse().forEach(entry => {
      const actorName = entry.actor_id === 4 && entry.actor_name === 'Demo Member' ? 'Parking Member' : entry.actor_name;
      const reason = entry.action === 'paid' && entry.reason === 'MyPark demo wallet payment' ? 'MyPark wallet payment' : entry.reason;
      const item = el('li'); item.append(el('strong', '', ({ created: 'Created', update: 'Updated', paid: 'Payment recorded', void: 'Voided', delete: 'Removed' })[entry.action] || entry.action), el('p', 'staff-muted', dateText(entry.at) + ' · ' + actorName));
      if (reason) item.append(el('p', '', translate(reason)));
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
  }
  function renderLookup(plate) {
    tables.splice(0).forEach(table => table.cancel());
    const host = document.getElementById('staff-lookup-results'); host.replaceChildren();
    const summary = el('p', 'staff-lookup-summary', plate);
    host.append(summary);
    const updateSummary = () => {
      const [fines, bookings] = tables;
      if (fines?.paged && bookings?.paged) summary.textContent = translate('{plate} · {fines} fine records · {bookings} parking records',
        { plate, fines: fines.paged.unfiltered, bookings: bookings.paged.unfiltered });
    };
    for (const key of ['vehicle-fines', 'vehicle-bookings']) {
      const card = el('section', 'staff-card');
      card.append(el('h2', '', translate(tabs[key].label)));
      host.append(card);
      tables.push(new RecordTable(card, key, plate, updateSummary));
    }
  }
  if (page === 'dashboard') {
    renderDashboard();
    tables.push(new RecordTable(document.getElementById('staff-recent'), 'recent-fines'));
    const leaveForm = document.querySelector('.staff-leave-form');
    if (leaveForm) {
      const reason = leaveForm.elements.namedItem('reason');
      const feedback = document.getElementById('leave-reason-error');
      const submit = leaveForm.querySelector('button[type="submit"]');

      function validateReason(showError) {
        const value = reason.value.trim();
        let error = '';
        if (!value) error = translate('Please enter a reason for your leave request.');
        else if (Array.from(value).length > reason.maxLength) error = translate('Enter a leave reason of 500 characters or fewer.');

        reason.setCustomValidity(error);
        submit.disabled = error !== '';
        const message = showError ? error : '';
        reason.setAttribute('aria-invalid', String(message !== ''));
        if (feedback.textContent !== message) feedback.textContent = message;
      }

      reason.addEventListener('input', () => validateReason(true));
      reason.addEventListener('blur', () => validateReason(true));
      reason.addEventListener('invalid', event => {
        event.preventDefault();
        validateReason(true);
      });
      validateReason(reason.getAttribute('aria-invalid') === 'true');
    }
  }
  if (page === 'fines' || page === 'maintenance') {
    const kind = tabs[page].kind;
    tables.push(new RecordTable(document.getElementById('staff-records'), page));
    const create = document.getElementById('staff-new'); create.addEventListener('click', () => editRecord(kind, null, create));
  }
  if (page === 'vehicles') {
    const form = document.getElementById('staff-lookup'), input = document.getElementById('lookup-plate');
    const error = el('p', 'staff-field-error'); error.id = 'lookup-error'; error.hidden = true; form.append(error); input.setAttribute('aria-describedby', 'lookup-error');
    const lookup = () => {
      const value = input.value.trim();
      if (!/^[A-Za-z0-9][A-Za-z0-9 -]{1,14}$/.test(value)) { error.textContent = translate('Enter a valid vehicle plate.'); error.hidden = false; input.setAttribute('aria-invalid', 'true'); input.focus(); return; }
      error.hidden = true; input.setAttribute('aria-invalid', 'false'); renderLookup(plateKey(value));
    };
    form.addEventListener('submit', event => { event.preventDefault(); lookup(); }); if (input.value.trim()) lookup();
  }
}());
