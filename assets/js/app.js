/* MyPark Sabah - browser interactions */
// Password controls are independent and never store or change the entered value.
(function () {
  'use strict';
  const translate = window.MyParkLocale?.text ?? (message => message);


  document.querySelectorAll('.password-field__control').forEach(function (control) {
    const input = control.querySelector('input[type="password"]');
    if (!input) return;

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'password-field__toggle';
    toggle.setAttribute('aria-controls', input.id);
    toggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="password-field__slash" d="M3 3 21 21"/></svg>';

    function updateToggle() {
      const visible = input.type === 'text';
      const label = visible ? translate('Hide password') : translate('Show password');
      toggle.setAttribute('aria-label', label);
      toggle.title = label;
      toggle.classList.toggle('is-password-visible', visible);
    }

    toggle.addEventListener('click', function () {
      const start = input.selectionStart;
      const end = input.selectionEnd;
      input.type = input.type === 'password' ? 'text' : 'password';
      if (start !== null && end !== null) input.setSelectionRange(start, end);
      updateToggle();
    });

    // Reset returns the field to its original masked presentation.
    if (input.form) {
      input.form.addEventListener('reset', function () {
        input.type = 'password';
        updateToggle();
      });
    }
    updateToggle();
    control.append(toggle);
  });
}());

// Dashboard wallet presentation only; no balance values are written to storage.
(function () {
  'use strict';
  const translate = window.MyParkLocale?.text ?? (message => message);


  const wallet = document.querySelector('.dashboard-wallet');
  if (!wallet) return;

  const amount = wallet.querySelector('.dashboard-wallet__amount');
  const toggle = wallet.querySelector('.dashboard-wallet__toggle');
  const storageKey = 'MyPark:wallet-visibility:user:' + wallet.dataset.walletUser;
  let hidden = false;

  try {
    hidden = localStorage.getItem(storageKey) === 'hidden';
  } catch (_) {
    // The toggle still works when browser storage is unavailable.
  }

  function renderBalance() {
    amount.textContent = hidden ? 'RM ••••' : wallet.dataset.walletBalance;
    toggle.setAttribute('aria-label', hidden ? translate('Show wallet balance') : translate('Hide wallet balance'));
    toggle.title = hidden ? translate('Show wallet balance') : translate('Hide wallet balance');
    wallet.classList.toggle('is-balance-visible', !hidden);
  }

  renderBalance();
  toggle.hidden = false;
  toggle.addEventListener('click', function () {
    hidden = !hidden;
    renderBalance();
    try {
      localStorage.setItem(storageKey, hidden ? 'hidden' : 'visible');
    } catch (_) {
      // Keep the current page preference even if persistence is blocked.
    }
  });
}());

(function () {
  'use strict';
  const translate = window.MyParkLocale?.text ?? (message => message);


  function readStorage(key) { try { return localStorage.getItem(key); } catch (_) { return null; } }
  function writeStorage(key, value) { try { localStorage.setItem(key, value); } catch (_) { /* Page controls remain usable without storage. */ } }

  // Responsive navigation and current-page indicator
  const navigation = document.getElementById('site-navigation');
  const logo = document.querySelector('.site-header .logo');
  if (navigation && logo) {
    const menuButton = document.createElement('button');
    menuButton.type = 'button';
    menuButton.className = 'nav-toggle';
    menuButton.setAttribute('aria-label', translate('Open navigation menu'));
    menuButton.setAttribute('aria-expanded', 'false');
    menuButton.setAttribute('aria-controls', navigation.id);
    menuButton.innerHTML = '☰';
    logo.insertAdjacentElement('afterend', menuButton);

    menuButton.addEventListener('click', function () {
      const isOpen = navigation.classList.toggle('is-open');
      menuButton.setAttribute('aria-expanded', String(isOpen));
      menuButton.setAttribute('aria-label', isOpen ? translate('Close navigation menu') : translate('Open navigation menu'));
      menuButton.innerHTML = isOpen ? '×' : '☰';
    });

    const pagePath = pathname => pathname.replace(/\/index\.php$/, '/').replace(/\/$/, '');
    const currentPage = pagePath(window.location.pathname);
    navigation.querySelectorAll('a[href]').forEach(function (link) {
      const destination = new URL(link.href);
      if (destination.origin === window.location.origin &&
          pagePath(destination.pathname) === currentPage) {
        link.classList.add('nav-active');
        link.setAttribute('aria-current', 'page');
      }
      link.addEventListener('click', function () {
        navigation.classList.remove('is-open');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.innerHTML = '☰';
      });
    });
  }

  // Confirmation prompts
  document.querySelectorAll('[data-confirm]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      if (!window.confirm(button.dataset.confirm)) event.preventDefault();
    });
  });

  // Parking location filters
  const lotSearch = document.getElementById('lotSearch');
  const cityFilter = document.getElementById('cityFilter');
  const priceFilter = document.getElementById('priceFilter');

  function filterLocations() {
    document.querySelectorAll('.lot-card').forEach(function (card) {
      const matchesName = !lotSearch || card.dataset.name.includes(lotSearch.value.toLowerCase());
      const matchesCity = !cityFilter || !cityFilter.value || card.dataset.city === cityFilter.value;
      const rate = Number(card.dataset.rate);
      const matchesPrice = !priceFilter || !priceFilter.value ||
        (priceFilter.value === 'low' ? rate < 2.5 : rate >= 2.5);
      card.hidden = !(matchesName && matchesCity && matchesPrice);
    });
  }

  [lotSearch, cityFilter, priceFilter].forEach(function (control) {
    if (control) control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', filterLocations);
  });

  const parkingTable = document.getElementById('parkingSessionsTable');
  const parkingSearch = document.getElementById('parkingSessionSearch');
  const parkingStatus = document.getElementById('parkingSessionStatus');
  const parkingRows = Array.from(parkingTable?.querySelectorAll('tbody tr[data-status]') ?? []);
  const noParkingMatches = parkingTable?.querySelector('[data-no-parking-matches]');
  const tablePaginators = new Map();

  function filterParkingSessions(resetPage = true) {
    if (!parkingTable) return;
    const query = parkingSearch.value.trim().toLowerCase();
    const status = parkingStatus.value;
    let matchingCount = 0;
    parkingRows.forEach(function (row) {
      const rowStatus = row.dataset.status;
      const searchText = [row.dataset.search, rowStatus, translate(rowStatus)].join(' ').toLowerCase();
      const matches = (!status || rowStatus === status) && searchText.includes(query);
      row.dataset.filterHidden = String(!matches);
      row.hidden = !matches;
      if (matches) matchingCount++;
    });
    noParkingMatches.hidden = matchingCount > 0 || tablePaginators.has(parkingTable);
    tablePaginators.get(parkingTable)?.(resetPage);
  }

  parkingSearch?.addEventListener('input', () => filterParkingSessions());
  parkingStatus?.addEventListener('change', () => filterParkingSessions());

  const hasNotificationCenter = Boolean(document.querySelector('[data-notification-menu]'));

  // Parking countdown timers
  function formatRemainingTime(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    return [hours, minutes, remainingSeconds]
      .map(function (value) { return String(value).padStart(2, '0'); })
      .join(':');
  }

  function updateParkingTimers() {
    const now = Math.floor(Date.now() / 1000);
    let statusChanged = false;
    document.querySelectorAll('.parking-timer').forEach(function (timer) {
      const secondsLeft = Math.max(0, Number(timer.dataset.expires) - now);
      const parkingRow = timer.closest('[data-parking-session][data-status="active"]');
      if (secondsLeft === 0 && parkingRow) {
        parkingRow.dataset.status = 'completed';
        const badge = parkingRow.querySelector('.tag');
        badge.classList.replace('active', 'completed');
        badge.textContent = translate('Completed');
        parkingRow.querySelectorAll('[data-active-parking-action]').forEach(form => {
          if (form.contains(document.activeElement)) parkingRow.querySelector('.btn')?.focus();
          form.remove();
        });
        parkingRow.querySelector('[data-cancellation-policy]')?.remove();
        timer.nextElementSibling?.remove();
        timer.remove();
        statusChanged = true;
        return;
      }

      const cancel = parkingRow?.querySelector('[data-cancel-deadline]');
      if (cancel && !cancel.disabled && now >= Number(cancel.dataset.cancelDeadline)) {
        if (document.activeElement === cancel) parkingRow.querySelector('.btn')?.focus();
        cancel.disabled = true;
        cancel.removeAttribute('title');
        const policy = parkingRow.querySelector('[data-cancellation-policy]');
        policy.textContent = translate('Cancellation is closed. More than 5 minutes must remain.');
        policy.classList.remove('validation-sr-only');
      }
      timer.textContent = formatRemainingTime(secondsLeft);
      timer.classList.toggle('timer-warning', secondsLeft > 0 && secondsLeft <= 300);
      timer.classList.toggle('timer-expired', secondsLeft === 0);

      if (window.MyParkToast && secondsLeft > 0 && secondsLeft <= 600 && (!hasNotificationCenter || secondsLeft > 300)) {
        const threshold = secondsLeft <= 300 ? '5' : '10';
        if (timer.dataset.toastThreshold !== threshold) {
          timer.dataset.toastThreshold = threshold;
          const plate = parkingRow?.dataset.plate || translate('Your vehicle');
          window.MyParkToast.show(plate + ': your parking session ends in ' + threshold + ' minutes or less.', 'warning', {
            key: 'parking:' + plate + ':' + timer.dataset.expires + ':' + threshold,
            details: true
          });
        }
      }

      const label = timer.nextElementSibling;
      if (label && label.classList.contains('timer-label')) {
        label.textContent = secondsLeft === 0 ? translate('Parking time expired') :
          (secondsLeft <= 300 ? translate('Less than 5 minutes remaining') : translate('Time remaining'));
      }
    });
    if (statusChanged) filterParkingSessions(false);
  }

  if (document.querySelector('.parking-timer')) {
    updateParkingTimers();
    window.setInterval(updateParkingTimers, 1000);
  }

  // Generic client-side pagination: show 10 table rows per page.
  document.querySelectorAll('table[data-paginated="true"]').forEach(function (table) {
    const rows = Array.from(table.querySelectorAll('tbody tr[data-status]'));
    const perPage = 10;
    if (rows.length <= perPage) return;

    let page = 1;
    let pages = 1;
    const controls = document.createElement('div');
    controls.className = 'pagination';
    controls.innerHTML = '<button type="button" class="page-prev"></button><span class="page-count"></span><button type="button" class="page-next"></button>';
    controls.querySelector('.page-prev').textContent = translate('Previous');
    controls.querySelector('.page-next').textContent = translate('Next');
    table.parentNode.insertAdjacentElement('afterend', controls);

    function showPage(resetPage = false) {
      const matchingRows = rows.filter(function (row) {
        return row.dataset.filterHidden !== 'true';
      });
      pages = Math.max(1, Math.ceil(matchingRows.length / perPage));
      page = resetPage ? 1 : Math.min(page, pages);
      const visibleRows = new Set(matchingRows.slice((page - 1) * perPage, page * perPage));
      rows.forEach(function (row) {
        row.dataset.pageHidden = String(!visibleRows.has(row));
        row.hidden = !visibleRows.has(row);
      });
      controls.querySelector('.page-count').textContent = matchingRows.length
        ? (window.MyParkLocale?.text('Page {page} of {pages}', { page, pages }) ?? 'Page ' + page + ' of ' + pages)
        : translate('No matching records');
      controls.querySelector('.page-prev').disabled = page === 1;
      controls.querySelector('.page-next').disabled = page === pages;
    }

    controls.querySelector('.page-prev').addEventListener('click', function () {
      if (page > 1) { page -= 1; showPage(); }
    });
    controls.querySelector('.page-next').addEventListener('click', function () {
      if (page < pages) { page += 1; showPage(); }
    });
    tablePaginators.set(table, showPage);
    showPage();
  });
  filterParkingSessions();

  // Operations table search and status filter
  const operationsSearch = document.getElementById('opsSearch');
  const operationsStatus = document.getElementById('opsStatus');

  function filterOperations() {
    document.querySelectorAll('#opsTable tr[data-status]').forEach(function (row) {
      const matchesStatus = !operationsStatus || !operationsStatus.value || row.dataset.status === operationsStatus.value;
      const matchesText = !operationsSearch || row.dataset.search.includes(operationsSearch.value.toLowerCase());
      row.hidden = !(matchesStatus && matchesText);
    });
  }

  if (operationsSearch) operationsSearch.addEventListener('input', filterOperations);
  if (operationsStatus) operationsStatus.addEventListener('change', filterOperations);

  // Wishlist (browser-local prototype)
  function readWishlist(key) {
    try {
      const items = JSON.parse(readStorage(key) || '[]');
      return Array.isArray(items) ? items.filter(item => typeof item === 'string') : [];
    } catch (_) { return []; }
  }
  document.querySelectorAll('[data-wish]').forEach(function (button) {
    const key = 'park-wishlist';
    let savedItems = readWishlist(key);
    if (savedItems.includes(button.dataset.wish)) button.textContent = '♥';

    button.addEventListener('click', function () {
      if (readStorage(key) !== null) savedItems = readWishlist(key);
      const id = button.dataset.wish;
      if (savedItems.includes(id)) {
        savedItems = savedItems.filter(function (item) { return item !== id; });
        button.textContent = '♡';
      } else {
        savedItems.push(id);
        button.textContent = '♥';
      }
      writeStorage(key, JSON.stringify(savedItems));
      if (window.MyParkToast) {
        window.MyParkToast.show(savedItems.includes(id) ? 'Parking location added to favourites.' : 'Parking location removed from favourites.', savedItems.includes(id) ? 'success' : 'info');
      }
    });
  });

  const topupAmount = document.getElementById('topupAmount');
  if (topupAmount) {
    const topupForm = topupAmount.form;
    const feedback = document.getElementById('topupAmountFeedback');
    const presets = topupForm.querySelectorAll('[data-topup]');

    function validateTopupAmount() {
      const amount = topupAmount.value;
      const valid = topupAmount.validity.valid && /^[1-9][0-9]{0,2}(?:\.[0-9]{1,2})?$/.test(amount);
      const message = valid ? '' : feedback.dataset.message;
      if (feedback.textContent !== message) feedback.textContent = message;
      if (valid) topupAmount.removeAttribute('aria-invalid');
      else topupAmount.setAttribute('aria-invalid', 'true');
      presets.forEach(function (preset) {
        preset.classList.toggle('is-selected', valid && Number(preset.dataset.topup) === Number(amount));
      });
      return valid;
    }

    presets.forEach(function (preset) {
      preset.addEventListener('click', function () {
        topupAmount.value = preset.dataset.topup;
        validateTopupAmount();
        topupAmount.focus();
      });
    });
    topupAmount.addEventListener('input', validateTopupAmount);
    topupForm.addEventListener('submit', function (event) {
      if (validateTopupAmount()) return;
      event.preventDefault();
      topupAmount.focus();
    });
    topupForm.noValidate = true;
  }

  const photoDrop = document.querySelector('[data-photo-drop]');
  if (photoDrop) {
    const photoInput = photoDrop.querySelector('input[type="file"]');
    const preview = photoDrop.querySelector('.avatar');
    const feedback = photoDrop.querySelector('.field-feedback');
    const savedPhoto = preview.getAttribute('src');
    let previewUrl = null;
    let rotation = 0;

    function validatePhoto(files) {
      if (files.length > 1) return 'Choose one photo at a time.';
      const file = files[0];
      if (!file) return '';
      if (file.size > 2000000) return 'Photo must be under 2 MB.';
      const allowedType = file.type
        ? photoInput.accept.split(',').includes(file.type)
        : /\.(jpe?g|png|webp)$/i.test(file.name);
      return allowedType && file.size > 0 ? '' : 'Use a valid JPG, PNG or WebP image.';
    }

    photoInput.addEventListener('change', function () {
      const error = validatePhoto(photoInput.files);
      if (error) photoInput.value = '';
      feedback.textContent = translate(error);

      const file = photoInput.files[0];
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = file ? URL.createObjectURL(file) : null;
      const source = previewUrl || savedPhoto;
      if (source) preview.src = source;
      else preview.removeAttribute('src');
      preview.hidden = !source;
      rotation = 0;
      preview.style.transform = '';
    });

    photoDrop.addEventListener('dragover', function (event) {
      if (!event.dataTransfer?.types.includes('Files')) return;
      event.preventDefault();
      event.dataTransfer.dropEffect = 'copy';
      photoDrop.classList.add('is-dragover');
    });
    photoDrop.addEventListener('dragleave', function (event) {
      if (!photoDrop.contains(event.relatedTarget)) photoDrop.classList.remove('is-dragover');
    });
    photoDrop.addEventListener('drop', function (event) {
      event.preventDefault();
      photoDrop.classList.remove('is-dragover');
      const files = event.dataTransfer?.files;
      if (!files?.length) return;
      const error = validatePhoto(files);
      if (error) {
        feedback.textContent = translate(error);
        return;
      }
      photoInput.files = files;
      photoInput.dispatchEvent(new Event('change', { bubbles: true }));
    });

    photoInput.form.querySelector('[data-rotate]').addEventListener('click', function () {
      if (preview.hidden) return;
      rotation += 90;
      preview.style.transform = 'rotate(' + rotation + 'deg)';
    });
  }

  // QR ticket
  const qrContainer = document.getElementById('qr');
  if (qrContainer && window.QRCode) {
    new QRCode(qrContainer, { text: qrContainer.dataset.text, width: 160, height: 160 });
  }

  // Live availability refresh for the legacy slot-grid view, if present.
  const slotGrid = document.getElementById('grid');
  if (!slotGrid) return;

  const slotSearch = document.getElementById('search');
  const zoneFilter = document.getElementById('zoneSel');
  const availableCount = document.getElementById('avail');
  const totalSlots = slotGrid.querySelectorAll('.slot').length;

  function filterSlots() {
    const query = slotSearch.value.toUpperCase();
    const zone = zoneFilter.value;
    slotGrid.querySelectorAll('.slot').forEach(function (slot) {
      slot.hidden = !((!zone || slot.dataset.zone === zone) && slot.dataset.code.includes(query));
    });
  }

  function refreshAvailability() {
    fetch('api/availability.php')
      .then(function (response) { return response.json(); })
      .then(function (slots) {
        slots.forEach(function (item) {
          const slot = slotGrid.querySelector('[data-id="' + item.id + '"]');
          if (!slot) return;
          const occupied = Number(item.busy) > 0;
          slot.classList.toggle('free', !occupied);
          slot.classList.toggle('busy', occupied);
          slot.disabled = occupied;
        });
        if (availableCount) {
          availableCount.textContent = slotGrid.querySelectorAll('.slot.free').length + ' of ' + totalSlots + ' slots available';
        }
      })
      .catch(function () { /* Keep the last displayed availability when offline. */ });
  }

  if (slotSearch) slotSearch.addEventListener('input', filterSlots);
  if (zoneFilter) zoneFilter.addEventListener('change', filterSlots);
  refreshAvailability();
  window.setInterval(refreshAvailability, 5000);
}());
