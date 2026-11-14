/* MyPark Sabah - browser interactions */
// Password controls are independent and never store or change the entered value.
(function () {
  'use strict';

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
      const label = visible ? 'Hide password' : 'Show password';
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
    toggle.setAttribute('aria-label', hidden ? 'Show wallet balance' : 'Hide wallet balance');
    toggle.title = hidden ? 'Show wallet balance' : 'Hide wallet balance';
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

  const root = document.documentElement;

  // Theme
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme) root.dataset.theme = savedTheme;

  const themeButton = document.getElementById('themeBtn');
  if (themeButton) {
    themeButton.addEventListener('click', function () {
      const nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
      root.dataset.theme = nextTheme;
      localStorage.setItem('theme', nextTheme);
    });
  }

  // Responsive navigation and current-page indicator
  const navigation = document.querySelector('header nav');
  const logo = document.querySelector('header .logo');
  if (navigation && logo) {
    const menuButton = document.createElement('button');
    menuButton.type = 'button';
    menuButton.className = 'nav-toggle';
    menuButton.setAttribute('aria-label', 'Open navigation menu');
    menuButton.setAttribute('aria-expanded', 'false');
    menuButton.innerHTML = '☰';
    logo.insertAdjacentElement('afterend', menuButton);

    menuButton.addEventListener('click', function () {
      const isOpen = navigation.classList.toggle('is-open');
      menuButton.setAttribute('aria-expanded', String(isOpen));
      menuButton.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
      menuButton.innerHTML = isOpen ? '×' : '☰';
    });

    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    navigation.querySelectorAll('a[href]').forEach(function (link) {
      const linkPage = link.getAttribute('href').split('#')[0];
      if (linkPage === currentPage || (currentPage === 'index.php' && linkPage === 'dashboard.php')) {
        link.classList.add('nav-active');
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

  // Reservation form
  const bookingForm = document.getElementById('bookForm');
  const selectedLotId = document.getElementById('lot_id');
  const selectedLotLabel = document.getElementById('lotLabel');
  const durationInput = document.getElementById('hours');
  const plateInput = document.getElementById('plate');
  const feeOutput = document.getElementById('fee');
  let hourlyRate = 0;

  document.querySelectorAll('[data-book-lot]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!selectedLotId || !selectedLotLabel) return;
      selectedLotId.value = button.dataset.bookLot;
      hourlyRate = Number(button.dataset.rate);
      selectedLotLabel.textContent = button.dataset.name + ' · RM ' + hourlyRate.toFixed(2) + ' / hour';
      updateEstimatedFee();
      document.getElementById('bookingPanel').scrollIntoView({ behavior: 'smooth' });
    });
  });

  function updateEstimatedFee() {
    if (!feeOutput || !durationInput) return;
    feeOutput.textContent = 'RM ' + (hourlyRate * (Number(durationInput.value) || 0)).toFixed(2);
  }

  if (durationInput) durationInput.addEventListener('input', updateEstimatedFee);

  // Keep the existing hours field as the single value submitted to PHP.
  const durationPicker = document.getElementById('durationPicker');
  const customDuration = document.getElementById('customDuration');
  if (durationPicker && customDuration && durationInput) {
    const choices = durationPicker.querySelectorAll('input[type="radio"]');
    let customHours = durationInput.value;
    let customSelected = false;

    function selectDuration(choice) {
      if (!choice.checked) return;
      if (customSelected) customHours = durationInput.value;
      customSelected = choice.value === 'custom';
      durationInput.value = customSelected ? customHours : choice.value;
      durationInput.readOnly = !customSelected;
      customDuration.hidden = !customSelected;
      updateEstimatedFee();
    }

    choices.forEach(function (choice) {
      choice.addEventListener('change', function () { selectDuration(choice); });
    });
    durationPicker.hidden = false;
    selectDuration(durationPicker.querySelector('input:checked'));
  }

  if (plateInput) {
    plateInput.addEventListener('input', function () {
      plateInput.value = plateInput.value.toUpperCase();
    });
  }

  if (bookingForm && selectedLotId && plateInput && durationInput) {
    bookingForm.addEventListener('submit', function (event) {
      const platePattern = /^[A-Z]{1,3} ?\d{1,4} ?[A-Z]?$/;
      const hours = Number(durationInput.value);
      if (!selectedLotId.value || !platePattern.test(plateInput.value.trim()) || hours < 1 || hours > 24) {
        event.preventDefault();
        window.alert('Choose a location, enter a valid Sabah vehicle plate and select 1–24 hours.');
      }
    });
  }

  // Reservation history search and status filter
  const historySearch = document.getElementById('historySearch');
  const historyStatus = document.getElementById('statusFilter');
  const tablePaginators = new Map();

  function filterHistory() {
    document.querySelectorAll('#historyTable tr[data-status]').forEach(function (row) {
      const matchesStatus = !historyStatus || !historyStatus.value || row.dataset.status === historyStatus.value;
      const matchesText = !historySearch || row.dataset.search.includes(historySearch.value.trim().toLowerCase());
      row.dataset.filterHidden = String(!(matchesStatus && matchesText));
      row.hidden = !(matchesStatus && matchesText);
    });
    const renderHistory = tablePaginators.get(document.getElementById('historyTable'));
    if (renderHistory) renderHistory(true);
  }

  if (historySearch) historySearch.addEventListener('input', filterHistory);
  if (historyStatus) historyStatus.addEventListener('change', filterHistory);

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
    document.querySelectorAll('.parking-timer').forEach(function (timer) {
      const secondsLeft = Math.max(0, Number(timer.dataset.expires) - now);
      timer.textContent = formatRemainingTime(secondsLeft);
      timer.classList.toggle('timer-warning', secondsLeft > 0 && secondsLeft <= 300);
      timer.classList.toggle('timer-expired', secondsLeft === 0);

      if (window.MyParkToast && secondsLeft > 0 && secondsLeft <= 600) {
        const threshold = secondsLeft <= 300 ? '5' : '10';
        if (timer.dataset.toastThreshold !== threshold) {
          timer.dataset.toastThreshold = threshold;
          const row = timer.closest('tr');
          const plate = row ? row.cells[1].textContent.trim() : 'Your vehicle';
          window.MyParkToast.show(plate + ': your parking session ends in ' + threshold + ' minutes or less.', 'warning', {
            key: 'parking:' + plate + ':' + timer.dataset.expires + ':' + threshold,
            details: true
          });
        }
      }

      const label = timer.nextElementSibling;
      if (label && label.classList.contains('timer-label')) {
        label.textContent = secondsLeft === 0 ? 'Parking time expired' :
          (secondsLeft <= 300 ? 'Less than 5 minutes remaining' : 'Time remaining');
      }
    });
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
    controls.innerHTML = '<button type="button" class="page-prev">Previous</button><span class="page-count"></span><button type="button" class="page-next">Next</button>';
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
        ? 'Page ' + page + ' of ' + pages : 'No matching records';
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
  filterHistory();

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
  document.querySelectorAll('[data-wish]').forEach(function (button) {
    const key = 'park-wishlist';
    let savedItems = JSON.parse(localStorage.getItem(key) || '[]');
    if (savedItems.includes(button.dataset.wish)) button.textContent = '♥';

    button.addEventListener('click', function () {
      savedItems = JSON.parse(localStorage.getItem(key) || '[]');
      const id = button.dataset.wish;
      if (savedItems.includes(id)) {
        savedItems = savedItems.filter(function (item) { return item !== id; });
        button.textContent = '♡';
      } else {
        savedItems.push(id);
        button.textContent = '♥';
      }
      localStorage.setItem(key, JSON.stringify(savedItems));
      if (window.MyParkToast) {
        window.MyParkToast.show(savedItems.includes(id) ? 'Parking location added to favourites.' : 'Parking location removed from favourites.', savedItems.includes(id) ? 'success' : 'info');
      }
    });
  });

  // Wallet top-up amount shortcuts
  const topupAmount = document.getElementById('topupAmount');
  document.querySelectorAll('[data-topup]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!topupAmount) return;
      topupAmount.value = button.dataset.topup;
      topupAmount.focus();
      document.querySelectorAll('[data-topup]').forEach(function (choice) {
        choice.classList.toggle('is-selected', choice === button);
      });
    });
  });

  if (topupAmount) {
    topupAmount.addEventListener('input', function () {
      document.querySelectorAll('[data-topup]').forEach(function (choice) {
        choice.classList.toggle('is-selected', choice.dataset.topup === topupAmount.value);
      });
    });
  }

  // Profile image preview and in-browser rotation preview
  const photoInput = document.querySelector('input[type="file"][name="photo"]');
  if (photoInput) {
    photoInput.addEventListener('change', function () {
      const file = photoInput.files[0];
      if (!file) return;
      let preview = document.querySelector('.avatar');
      if (!preview) {
        preview = document.createElement('img');
        preview.className = 'avatar';
        preview.alt = 'Profile photo preview';
        photoInput.parentNode.insertBefore(preview, photoInput);
      }
      preview.src = URL.createObjectURL(file);
    });
  }

  document.querySelectorAll('[data-rotate]').forEach(function (button) {
    button.addEventListener('click', function () {
      const preview = document.querySelector('.avatar');
      if (!preview) return;
      const degrees = (Number(preview.dataset.rotation) || 0) + 90;
      preview.dataset.rotation = String(degrees);
      preview.style.transform = 'rotate(' + degrees + 'deg)';
    });
  });

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
    fetch('api.php')
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
