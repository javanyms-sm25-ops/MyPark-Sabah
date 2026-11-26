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
    const profileMenu = navigation.querySelector('[data-profile-nav-menu]');
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
      if (!isOpen && profileMenu) profileMenu.open = false;
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
        if (profileMenu) profileMenu.open = false;
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.innerHTML = '☰';
      });
    });
  }

  document.querySelectorAll('[data-profile-nav-menu]').forEach(profileMenu => {
    const trigger = profileMenu.querySelector('summary');
    let openedByHover = false;
    let hoverCloseTimer;

    function closeProfileMenu() {
      clearTimeout(hoverCloseTimer);
      openedByHover = false;
      profileMenu.open = false;
    }

    profileMenu.addEventListener('pointerenter', event => {
      if (event.pointerType !== 'mouse') return;
      clearTimeout(hoverCloseTimer);
      if (!profileMenu.open) {
        openedByHover = true;
        profileMenu.open = true;
      }
    });
    profileMenu.addEventListener('pointerleave', event => {
      if (event.pointerType === 'mouse' && openedByHover) {
        hoverCloseTimer = setTimeout(() => {
          if (!profileMenu.contains(document.activeElement)) closeProfileMenu();
        }, 180);
      }
    });
    trigger.addEventListener('click', event => {
      clearTimeout(hoverCloseTimer);
      if (openedByHover && profileMenu.open && event.detail > 0) event.preventDefault();
      openedByHover = false;
    });
    profileMenu.addEventListener('focusout', event => {
      if (event.relatedTarget && !profileMenu.contains(event.relatedTarget)) closeProfileMenu();
    });
    profileMenu.addEventListener('keydown', event => {
      if (event.key !== 'Escape' || !profileMenu.open) return;
      event.preventDefault();
      closeProfileMenu();
      trigger.focus();
    });
    document.addEventListener('pointerdown', event => {
      if (profileMenu.open && !profileMenu.contains(event.target)) closeProfileMenu();
    });
  });

  // Confirmation prompts
  document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-confirm]');
    if (button && !window.confirm(button.dataset.confirm)) event.preventDefault();
  });

  // Parking location filters
  const lotSearch = document.getElementById('lotSearch');
  const cityFilter = document.getElementById('cityFilter');

  function filterLocations() {
    document.querySelectorAll('.lot-card').forEach(function (card) {
      const matchesName = !lotSearch || card.dataset.name.includes(lotSearch.value.toLowerCase());
      const matchesCity = !cityFilter || !cityFilter.value || card.dataset.city === cityFilter.value;
      card.hidden = !(matchesName && matchesCity);
    });
  }

  [lotSearch, cityFilter].forEach(function (control) {
    if (control) control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', filterLocations);
  });

  const parkingTable = document.querySelector('[data-ajax-table="parking-sessions"]');
  const hasNotificationCenter = Boolean(document.querySelector('[data-notification-menu]'));

  // Parking countdown timers
  const activeParking = document.querySelector('[data-active-parking]');
  const dashboardSessions = Array.from(activeParking?.querySelectorAll('[data-dashboard-parking-session]') ?? []);
  const dashboardLoadedAt = performance.getEntriesByType('navigation')[0]?.responseStart ?? performance.now();
  const dashboardServerTime = Number(activeParking?.dataset.serverTime);
  const dashboardReminder = Number(activeParking?.dataset.reminderSeconds);
  const dashboardEmpty = activeParking?.querySelector('[data-parking-empty]');
  const dashboardMore = activeParking?.querySelector('[data-parking-session-list]');
  const dashboardCount = dashboardMore?.querySelector('[data-parking-session-count]');
  const dashboardAnnouncement = activeParking?.querySelector('[data-parking-announcement]');
  let shownSession = dashboardSessions[0];

  function formatDashboardCountdown(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor(seconds / 60) % 60;
    const remainingSeconds = String(seconds % 60).padStart(2, '0');
    const template = hours ? '{hours} hr {minutes} min {seconds} sec' : '{minutes} min {seconds} sec';
    return translate(template, { hours, minutes, seconds: remainingSeconds });
  }

  function updateDashboardParking() {
    if (!dashboardSessions.length) return;

    // Align with the server's clock even if the driver's device time is wrong.
    const now = dashboardServerTime + Math.floor((performance.now() - dashboardLoadedAt) / 1000);
    const available = dashboardSessions.filter(session => !session.dataset.expires || Number(session.dataset.expires) > now);
    const next = available[0];
    const remaining = next?.dataset.expires ? Number(next.dataset.expires) - now : null;
    if (shownSession !== next) {
      const moveFocus = shownSession?.contains(document.activeElement);
      if (shownSession) shownSession.hidden = true;
      if (next) next.hidden = false;
      shownSession = next;
      dashboardEmpty.hidden = Boolean(next);
      dashboardAnnouncement.textContent = translate('Parking time expired');
      if (moveFocus) (next ?? dashboardEmpty).querySelector('.btn').focus();
    }

    if (available.length < 2 && dashboardMore.contains(document.activeElement)) {
      (next ?? dashboardEmpty).querySelector('.btn').focus();
    }
    dashboardMore.hidden = available.length < 2;
    if (!dashboardMore.hidden) {
      dashboardCount.textContent = translate('{count} active sessions', { count: available.length });
    }
    if (remaining === null) return;

    const soon = remaining <= dashboardReminder;
    if (soon !== next.hasAttribute('data-ending-soon')) {
      next.toggleAttribute('data-ending-soon', soon);
      const label = translate(soon ? 'Parking ends soon' : 'Time remaining');
      next.querySelector('[data-parking-time-label]').textContent = label;
      if (soon) dashboardAnnouncement.textContent = label;
    }
    const countdown = next.querySelector('[data-parking-countdown]');
    const timeText = formatDashboardCountdown(remaining);
    if (countdown.textContent !== timeText) countdown.textContent = timeText;
    countdown.hidden = false;
    const progress = next.querySelector('progress');
    if (progress) progress.value = Math.min(remaining, progress.max);
  }

  function formatRemainingTime(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    return [hours, minutes, remainingSeconds]
      .map(function (value) { return String(value).padStart(2, '0'); })
      .join(':');
  }

  function updateParkingTimers() {
    updateDashboardParking();
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
        policy.textContent = translate('The 3-minute cancellation window has closed.');
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
    if (statusChanged) parkingTable?.dispatchEvent(new Event('mypark:table-refresh'));
  }

  if (parkingTable || dashboardSessions.length || document.querySelector('.parking-timer')) {
    updateParkingTimers();
    window.setInterval(updateParkingTimers, 1000);
  }
  parkingTable?.addEventListener('mypark:table-updated', updateParkingTimers);
  if (activeParking) {
    window.addEventListener('pageshow', event => {
      if (event.persisted) window.location.reload();
    });
  }

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
