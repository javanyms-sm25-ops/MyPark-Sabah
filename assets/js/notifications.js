(() => {
  'use strict';

  const menu = document.querySelector('[data-notification-menu]');
  if (!menu) return;

  const translate = window.MyParkLocale.text;
  const trigger = menu.querySelector('summary');
  const badge = menu.querySelector('.notification-badge');
  const preview = menu.querySelector('[data-notification-preview]');
  const inbox = document.querySelector('[data-notification-inbox]');
  const endpoint = inbox ? 'api/notifications.php?all=1' : 'api/notifications.php';
  const rendered = new WeakMap();
  const announced = new Set();
  const announcementPrefix = 'mypark:notice:' + menu.dataset.user + ':';
  let queue = Promise.resolve(false);

  function renderList(target, html) {
    if (!target || rendered.get(target) === html) return;
    const focusedNotice = target.contains(document.activeElement)
      ? document.activeElement.closest('[data-notice]')?.dataset.notice : null;
    target.innerHTML = html;
    rendered.set(target, html);
    if (focusedNotice) target.querySelector('[data-notice="' + CSS.escape(focusedNotice) + '"] a')?.focus({ preventScroll: true });
  }

  function announce(notice) {
    if (document.hidden || announced.has(notice.id)) return;
    announced.add(notice.id);
    const key = announcementPrefix + notice.id;
    try {
      if (sessionStorage.getItem(key)) return;
      sessionStorage.setItem(key, '1');
    } catch (_) {
      // In-memory tracking still prevents repeated announcements on this page.
    }
    window.MyParkToast?.show(notice.message, notice.type === 'payment' ? 'success' : 'warning', {
      key, parkingNotice: true, details: notice.type === 'ending',
    });
  }

  function load(fields) {
    queue = queue.then(async () => {
      const options = { cache: 'no-store', signal: AbortSignal.timeout?.(8000) };
      if (fields) {
        options.method = 'POST';
        options.body = new URLSearchParams({ ...fields, csrf: menu.dataset.csrf });
      }
      const response = await fetch(endpoint, options);
      if (!response.ok) return false;
      const state = await response.json();

      badge.hidden = state.unread === 0;
      trigger.setAttribute('aria-label', state.unread
        ? translate('Parking notifications ({count} unread)', { count: state.unread })
        : translate('Parking notifications'));
      renderList(preview, state.preview);
      renderList(inbox, state.inbox);
      state.live.forEach(announce);
      return true;
    }).catch(() => false);
    return queue;
  }

  function markVisible() {
    if (document.hidden) return;
    const visibleList = inbox || (menu.open ? preview : null);
    if (!visibleList) return;
    const ids = Array.from(visibleList.querySelectorAll('[data-unread]'), item => item.dataset.notice);
    if (ids.length) load({ action: 'read', ids: ids.join(',') });
  }

  function refresh() {
    if (!document.hidden) load().then(ok => { if (ok) markVisible(); });
  }

  menu.addEventListener('toggle', () => {
    if (!menu.open) return;
    const openNavigation = menu.parentElement.querySelector('.nav-toggle[aria-expanded="true"]');
    openNavigation?.click();
    refresh();
  });
  document.addEventListener('click', event => {
    if (event.isTrusted && menu.open && !menu.contains(event.target)) menu.open = false;
  });
  menu.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || !menu.open) return;
    event.preventDefault();
    menu.open = false;
    trigger.focus();
  });
  refresh();
  window.setInterval(refresh, 15000);
  window.addEventListener('pageshow', event => { if (event.persisted) refresh(); });
  document.addEventListener('visibilitychange', refresh);

  const zones = JSON.parse(menu.dataset.zones);
  const geolocation = navigator.geolocation;
  const toggle = document.querySelector('[data-location-alert-toggle]');
  const status = document.querySelector('[data-location-alert-status]');
  const locationKey = 'mypark:zone-alerts:' + menu.dataset.user;
  let locationEnabled = false;
  let currentZone = null;
  let positionWatch = null;
  let enteringZone = false;

  try {
    locationEnabled = sessionStorage.getItem(locationKey) === '1';
    currentZone = Number(sessionStorage.getItem(locationKey + ':current')) || null;
  } catch (_) {
    // The control still works while the current page remains open.
  }

  function updateLocationControl(message = '') {
    if (!toggle) return;
    toggle.textContent = translate(locationEnabled ? 'Disable location alerts' : 'Enable location alerts');
    const translated = message ? translate(message) : '';
    if (status.textContent !== translated) status.textContent = translated;
    status.hidden = !message;
  }

  function rememberZone(id) {
    currentZone = id;
    try {
      if (id === null) sessionStorage.removeItem(locationKey + ':current');
      else sessionStorage.setItem(locationKey + ':current', String(id));
    } catch (_) {}
  }

  function distanceToZone(coords, zone) {
    const radians = degrees => degrees * Math.PI / 180;
    const deltaLat = radians(zone.lat - coords.latitude);
    const deltaLng = radians(zone.lng - coords.longitude);
    const arc = Math.sin(deltaLat / 2) ** 2 + Math.cos(radians(coords.latitude)) *
      Math.cos(radians(zone.lat)) * Math.sin(deltaLng / 2) ** 2;
    return 12742000 * Math.asin(Math.sqrt(Math.min(1, arc)));
  }

  function checkPosition({ coords }) {
    if (!locationEnabled || document.hidden || enteringZone) return;
    const accuracy = coords.accuracy;
    const nearby = zones.map(zone => ({ zone, distance: distanceToZone(coords, zone) }));
    if (!Number.isFinite(accuracy) || accuracy > Math.max(...zones.map(zone => zone.radius))) {
      updateLocationControl('Waiting for an accurate location…');
      return;
    }
    updateLocationControl('Location alerts are on.');
    const previous = nearby.find(item => item.zone.id === currentZone);
    if (previous && previous.distance - accuracy <= previous.zone.radius + 40) return;
    if (currentZone !== null) rememberZone(null);

    const closest = nearby.filter(item => item.distance + accuracy <= item.zone.radius)
      .sort((a, b) => a.distance - b.distance)[0];
    if (!closest) return;
    enteringZone = true;
    load({ action: 'zone', lot_id: closest.zone.id }).then(ok => {
      enteringZone = false;
      if (ok && locationEnabled) rememberZone(closest.zone.id);
      if (ok) markVisible();
    });
  }

  function stopPositionWatch() {
    if (positionWatch === null) return;
    geolocation.clearWatch(positionWatch);
    positionWatch = null;
  }

  function syncPositionWatch() {
    if (!locationEnabled || document.hidden) {
      stopPositionWatch();
      return;
    }
    if (positionWatch !== null) return;
    positionWatch = geolocation.watchPosition(checkPosition, error => {
      if (error.code === 1) {
        setLocationEnabled(false, 'Allow location access in your browser to use these alerts.');
      } else {
        updateLocationControl('Waiting for an accurate location…');
      }
    }, { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 });
  }

  function setLocationEnabled(enabled, message = '') {
    locationEnabled = enabled;
    try { sessionStorage.setItem(locationKey, enabled ? '1' : '0'); } catch (_) {}
    if (!enabled) rememberZone(null);
    updateLocationControl(message);
    syncPositionWatch();
  }

  if (!window.isSecureContext || !geolocation || !zones.length) {
    updateLocationControl(!zones.length ? 'No paid parking locations are mapped yet.' : 'Location alerts require HTTPS or localhost and a browser with location access.');
    return;
  }
  if (toggle) {
    toggle.hidden = false;
    updateLocationControl(locationEnabled ? 'Waiting for an accurate location…' : '');
    toggle.addEventListener('click', () => setLocationEnabled(!locationEnabled,
      locationEnabled ? '' : 'Waiting for an accurate location…'));
  }
  document.addEventListener('visibilitychange', syncPositionWatch);
  window.addEventListener('pagehide', stopPositionWatch);
  window.addEventListener('pageshow', syncPositionWatch);
  syncPositionWatch();
})();
