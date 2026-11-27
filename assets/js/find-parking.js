(() => {
  'use strict';

  const finder = document.querySelector('[data-find-parking]');
  if (!finder) return;

  const translate = window.MyParkLocale?.text ?? (message => message);
  const filters = finder.querySelector('[data-parking-filters]');
  const search = finder.querySelector('#find-parking-query');
  const availableOnly = filters.elements.namedItem('available');
  const okuOnly = filters.elements.namedItem('oku');
  const favourites = window.MyParkFavourites;
  const favouritesOnly = favourites ? filters.elements.namedItem('favourites') : null;
  const okuHelp = finder.querySelector('[data-parking-oku-help]');
  const countLabel = finder.querySelector('[data-parking-result-count]');
  const clear = finder.querySelector('[data-parking-clear]');
  const list = finder.querySelector('.find-parking-list-wrap');
  const locationList = finder.querySelector('#find-parking-list');
  const locationsHeading = finder.querySelector('#find-parking-heading');
  const locate = finder.querySelector('[data-use-location]');
  const locationStatus = finder.querySelector('[data-location-status]');
  const empty = finder.querySelector('[data-parking-empty]');
  const emptyDefault = finder.querySelector('[data-parking-empty-default]');
  const favouritesEmpty = finder.querySelector('[data-favourites-empty]');
  const favouritesNoMatch = finder.querySelector('[data-favourites-no-match]');
  const canvas = finder.querySelector('#find-parking-map');
  const mapPanel = canvas.closest('.find-parking-map-panel');
  const message = finder.querySelector('[data-map-message]');
  const refreshStatus = finder.querySelector('[data-parking-refresh-status]');
  const refreshInterval = 20000;
  // Match the full-width letters, numbers and punctuation supported by the server search.
  const normalize = value => value.replace(/[\uFF01\uFF03-\uFF06\uFF08-\uFF3B\uFF3D-\uFF5D]/g, character =>
    String.fromCharCode(character.charCodeAt(0) - 0xFEE0)).replace(/\u3000/g, ' ').toLowerCase().trim();
  const readLocations = () => Array.from(locationList.querySelectorAll('[data-parking-location]'), card => ({
    card,
    id: card.dataset.parkingLocation,
    availability: card.querySelector('.find-parking-availability'),
    searchText: normalize(card.dataset.search),
    title: card.querySelector('h3').textContent,
    button: card.querySelector('[data-show-on-map]'),
    bookingLink: card.querySelector('[data-select-parking]'),
    hasOku: Number(card.dataset.okuCapacity) > 0,
    regularAvailable: Number(card.dataset.regularAvailable) > 0,
    okuAvailable: Number(card.dataset.okuAvailable) > 0,
    distanceLabel: card.querySelector('[data-parking-distance]'),
    position: card.hasAttribute('data-lat')
      ? { lat: Number(card.dataset.lat), lng: Number(card.dataset.lng) }
      : null,
  }));

  let locations = readLocations();
  let mapped = locations.filter(location => location.position);
  let map, infoWindow, selected, timeout, searchTimeout;
  let userPosition, userMarker;
  let mapFailed = false, mapLoading = false;
  let refreshTimer;
  let refreshing = false;

  function centerUserPosition() {
    if (!map || mapFailed || !userPosition) return;
    if (!userMarker) {
      const dot = document.createElement('span');
      dot.className = 'find-parking-location-dot';
      userMarker = new google.maps.marker.AdvancedMarkerElement({
        map,
        position: userPosition,
        title: translate('Your location'),
        anchorTop: '-50%',
        zIndex: 2,
      });
      userMarker.append(dot);
    } else {
      userMarker.position = userPosition;
    }
    infoWindow.close();
    selectLocation(null);
    map.panTo(userPosition);
    map.setZoom(14);
  }

  function distanceInKm(from, to) {
    const radians = degrees => degrees * Math.PI / 180;
    const dLat = radians(to.lat - from.lat);
    const dLng = radians(to.lng - from.lng);
    const arc = Math.sin(dLat / 2) ** 2 + Math.cos(radians(from.lat)) *
      Math.cos(radians(to.lat)) * Math.sin(dLng / 2) ** 2;
    return 12742 * Math.asin(Math.sqrt(Math.min(1, arc)));
  }

  function sortByDistance() {
    if (!mapped.length) return;
    const format = new Intl.NumberFormat(document.documentElement.lang || 'en', { maximumFractionDigits: 1 });
    const ranked = mapped.map(location => ({ location, distance: distanceInKm(userPosition, location.position) }));
    ranked.sort((first, second) => first.distance - second.distance);
    for (const { location, distance } of ranked) {
      location.distanceLabel.textContent = translate('{distance} km away').replace('{distance}', format.format(distance));
      location.distanceLabel.hidden = false;
      locationList.append(location.card);
    }
    locations.filter(location => !location.position).forEach(location => locationList.append(location.card));
    locationsHeading.textContent = translate('Nearest parking locations');
    list.scrollTop = 0;
  }

  locate.hidden = !mapped.length;
  locate.addEventListener('click', () => {
    if (!window.isSecureContext || !navigator.geolocation) {
      locationStatus.textContent = translate('Location access requires HTTPS or localhost. You can still search for parking.');
      return;
    }
    locate.disabled = true;
    locationStatus.textContent = translate('Finding your location…');
    navigator.geolocation.getCurrentPosition(({ coords }) => {
      locate.disabled = false;
      locationStatus.textContent = '';
      userPosition = { lat: coords.latitude, lng: coords.longitude };
      sortByDistance();
      centerUserPosition();
    }, error => {
      locate.disabled = false;
      locationStatus.textContent = translate(error.code === 1
        ? 'Location access was denied. You can still search for parking.'
        : 'Your location could not be found. Try again or search for parking.');
    }, { timeout: 10000, maximumAge: 30000 });
  });

  function selectLocation(location, pan = true) {
    if (selected) {
      selected.card.classList.remove('is-selected');
      selected.button.setAttribute('aria-pressed', 'false');
      selected.marker.zIndex = null;
    }
    selected = location;
    if (!location) return;

    location.card.classList.add('is-selected');
    location.button.setAttribute('aria-pressed', 'true');
    location.marker.zIndex = 1;
    if (pan) {
      map.panTo(location.position);
      if (map.getZoom() < 14) map.setZoom(14);
    }

    const content = document.createElement('div');
    content.className = 'find-parking-map-info';
    const title = document.createElement('strong');
    title.textContent = location.title;
    const availability = location.availability.cloneNode(true);
    const rate = location.card.querySelector('.find-parking-rate').cloneNode(true);
    const link = location.bookingLink?.cloneNode(true);
    content.append(title, availability, rate);
    if (link) {
      link.removeAttribute('class');
      content.append(link);
    }
    infoWindow.setOptions({ ariaLabel: location.title });
    infoWindow.setContent(content);
    infoWindow.open({ map, anchor: location.marker });
  }

  function updateMarkers(refit = true) {
    if (!map || mapFailed) return;
    const visible = locations.filter(location => location.marker && !location.card.hidden);
    const wasHidden = mapPanel.hidden;
    mapPanel.hidden = !visible.length;
    finder.classList.toggle('has-parking-map', !mapPanel.hidden);
    for (const location of locations) {
      if (location.marker) location.marker.map = location.card.hidden ? null : map;
    }
    if (selected?.card.hidden) {
      infoWindow.close();
      selectLocation(null);
    }
    if (selected || userPosition || !visible.length || (!refit && !wasHidden)) return;

    const first = visible[0].position;
    if (visible.every(({ position }) => position.lat === first.lat && position.lng === first.lng)) {
      map.setCenter(first);
      map.setZoom(14);
      return;
    }
    const bounds = new google.maps.LatLngBounds();
    visible.forEach(({ position }) => bounds.extend(position));
    map.fitBounds(bounds, 42);
  }

  function renderResults(query, refreshingCounts = false) {
    let count = 0;
    for (const location of locations) {
      const baysAvailable = okuOnly.checked ? location.okuAvailable : location.regularAvailable;
      const matches = location.searchText.includes(query) && (!okuOnly.checked || location.hasOku)
        && (!availableOnly.checked || baysAvailable)
        && (!favouritesOnly?.checked || favourites.has(location.id));
      if (!matches && location.card.contains(document.activeElement)) {
        locationsHeading.focus({ preventScroll: true });
      }
      location.card.hidden = !matches;
      if (matches) count++;
      if (location.bookingLink && !refreshingCounts) {
        const bookingUrl = new URL(location.bookingLink.href);
        if (okuOnly.checked) bookingUrl.searchParams.set('bay_type', 'oku');
        else bookingUrl.searchParams.delete('bay_type');
        location.bookingLink.href = bookingUrl.href;
      }
    }
    const countKey = count === 1 ? '{count} location found' : '{count} locations found';
    const countText = translate(countKey, { count });
    if (countLabel.textContent.trim() !== countText) countLabel.textContent = countText;
    empty.hidden = count > 0;
    clear.hidden = !query && !availableOnly.checked && !okuOnly.checked && !favouritesOnly?.checked;
    if (favouritesOnly) {
      const showingFavourites = favouritesOnly.checked;
      const hasSavedLocations = showingFavourites && locations.some(location => favourites.has(location.id));
      emptyDefault.hidden = showingFavourites;
      favouritesEmpty.hidden = !showingFavourites || hasSavedLocations;
      favouritesNoMatch.hidden = !hasSavedLocations;
    }
    if (okuHelp) okuHelp.hidden = !okuOnly.checked;
  }

  function applyFilters() {
    clearTimeout(searchTimeout);
    renderResults(normalize(search.value));
    finder.classList.toggle('is-oku-search', okuOnly.checked);
    list.scrollTop = 0;

    const url = new URL(window.location.href);
    const values = {
      q: search.value.trim(),
      available: availableOnly.checked ? '1' : '',
      oku: okuOnly.checked ? '1' : '',
      favourites: favouritesOnly?.checked ? '1' : '',
    };
    Object.entries(values).forEach(([key, value]) => {
      if (value) url.searchParams.set(key, value);
      else url.searchParams.delete(key);
    });
    if (url.href !== window.location.href) window.history.replaceState(window.history.state, '', url);

    updateMarkers();
    if (selected) {
      const popupLink = infoWindow.getContent()?.querySelector('a');
      if (popupLink) popupLink.href = selected.bookingLink.href;
    }
  }

  function filterAndRefresh() {
    applyFilters();
    refreshAvailability();
  }

  function updateAvailabilityRows(root, bays) {
    root.querySelector('[data-parking-capacity-unknown]').hidden = bays.regular.capacity !== null;
    for (const type of ['regular', 'oku']) {
      const { capacity, available } = bays[type];
      const row = root.querySelector('[data-parking-bay="' + type + '"]');
      row.hidden = capacity === null;
      const status = row.querySelector('[data-bay-status]');
      const state = !capacity ? 'none' : available === 0 ? 'full' : 'available';
      const label = state === 'none' ? translate('Not offered')
        : state === 'full' ? translate('Full') : translate('{count} available', { count: available });
      status.dataset.bayStatus = state;
      if (status.textContent !== label) status.textContent = label;
      if (type === 'oku') row.querySelector('[data-parking-oku-rate]').hidden = !(capacity > 0);
    }
  }

  function setRefreshStatus(label, stale = false) {
    const text = label ? translate(label) : '';
    if (refreshStatus.textContent !== text) refreshStatus.textContent = text;
    refreshStatus.toggleAttribute('data-stale', stale);
  }

  function scheduleRefresh() {
    clearTimeout(refreshTimer);
    if (!document.hidden) refreshTimer = setTimeout(refreshAvailability, refreshInterval);
  }

  async function refreshLocations(signal) {
    const url = new URL(filters.action);
    url.searchParams.set('_locations', '1');
    const response = await fetch(url, { cache: 'no-store', credentials: 'same-origin', signal });
    const version = response.headers.get('X-MyPark-Parking-Locations');
    if (!response.ok || response.redirected || !version) throw new Error('Parking locations unavailable');
    const fragment = new DOMParser().parseFromString(await response.text(), 'text/html');
    const nextList = fragment.querySelector('#find-parking-list');
    if (!nextList) throw new Error('Parking locations missing');

    const scrollTop = list.scrollTop;
    const active = document.activeElement;
    const focusId = active?.closest('[data-parking-location]')?.dataset.parkingLocation;
    const focusControl = ['[data-wish]', '[data-select-parking]', '[data-show-on-map]']
      .find(selector => active?.matches(selector));
    const selectedId = selected?.id;
    if (selected) selectLocation(null);
    infoWindow?.close();
    for (const location of mapped) {
      if (location.marker) location.marker.map = null;
    }

    locationList.replaceChildren(...nextList.children);
    finder.dataset.catalogVersion = version;
    locations = readLocations();
    mapped = locations.filter(location => location.position);
    locate.hidden = !mapped.length;
    emptyDefault.querySelector('h3').textContent = translate(locations.length
      ? 'No matching parking locations.' : 'No parking locations yet.');
    emptyDefault.querySelector('p').hidden = !locations.length;
    favourites?.updateButtons(locationList);
    renderResults(normalize(search.value));
    if (userPosition) sortByDistance();
    if (map && !mapFailed) {
      mapped.forEach(addLocationMarker);
      const nextSelected = mapped.find(location => location.id === selectedId && !location.card.hidden);
      if (nextSelected) selectLocation(nextSelected, false);
      updateMarkers();
    } else {
      loadMap();
    }
    list.scrollTop = scrollTop;
    if (focusId) {
      const card = locations.find(location => location.id === focusId && !location.card.hidden)?.card;
      const target = focusControl && card?.querySelector(focusControl);
      (target && !target.hidden ? target : locationsHeading).focus({ preventScroll: true });
    }
  }

  async function refreshAvailability() {
    if (document.hidden || refreshing) return;
    refreshing = true;
    const controller = new AbortController();
    const deadline = setTimeout(() => controller.abort(), 10000);
    try {
      const response = await fetch('api/availability.php?view=counts', {
        cache: 'no-store',
        credentials: 'same-origin',
        signal: controller.signal,
      });
      if (!response.ok || response.redirected) throw new Error('Availability unavailable');
      const snapshot = await response.json();
      if (typeof snapshot.catalogVersion !== 'string' || !Array.isArray(snapshot.locations)) {
        throw new Error('Availability response is invalid');
      }
      const byId = new Map(snapshot.locations.map(location => [String(location.id), location.bays]));
      if (snapshot.catalogVersion !== finder.dataset.catalogVersion
          || byId.size !== locations.length || locations.some(location => !byId.has(location.id))) {
        await refreshLocations(controller.signal);
        setRefreshStatus('');
        return;
      }

      const validBay = bay => bay && (bay.capacity === null ? bay.available === null
        : Number.isSafeInteger(bay.capacity) && bay.capacity >= 0
          && Number.isSafeInteger(bay.available) && bay.available >= 0 && bay.available <= bay.capacity);
      if (Array.from(byId.values()).some(bays => !validBay(bays?.regular) || !validBay(bays?.oku))) {
        throw new Error('Bay counts are invalid');
      }
      for (const location of locations) {
        const bays = byId.get(location.id);
        location.hasOku = bays.oku.capacity > 0;
        location.regularAvailable = bays.regular.available > 0;
        location.okuAvailable = bays.oku.available > 0;
        updateAvailabilityRows(location.availability, bays);
      }
      renderResults(normalize(search.value), true);
      updateMarkers(false);
      if (selected) {
        const popup = infoWindow.getContent()?.querySelector('.find-parking-availability');
        if (popup) updateAvailabilityRows(popup, byId.get(selected.id));
      }
      setRefreshStatus('');
    } catch (_) {
      setRefreshStatus('Could not refresh availability. Showing the last successful check.', true);
    } finally {
      clearTimeout(deadline);
      refreshing = false;
      scheduleRefresh();
    }
  }

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) clearTimeout(refreshTimer);
    else refreshAvailability();
  });
  window.addEventListener('pageshow', event => {
    if (event.persisted) refreshAvailability();
  });
  window.addEventListener('online', refreshAvailability);
  window.addEventListener('offline', () => {
    setRefreshStatus('Could not refresh availability. Showing the last successful check.', true);
  });
  scheduleRefresh();

  search.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(filterAndRefresh, 250);
  });
  filters.addEventListener('submit', event => {
    event.preventDefault();
    filterAndRefresh();
  });
  [availableOnly, okuOnly].forEach(input => input.addEventListener('change', filterAndRefresh));
  function clearFilters() {
    search.value = '';
    availableOnly.checked = false;
    okuOnly.checked = false;
    if (favouritesOnly) favouritesOnly.checked = false;
    filterAndRefresh();
    search.focus();
  }

  clear.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) return;
    event.preventDefault();
    clearFilters();
  });

  if (favouritesOnly) {
    favouritesOnly.disabled = false;
    finder.querySelector('[data-favourites-filter]').hidden = false;
    favouritesOnly.addEventListener('change', filterAndRefresh);
    finder.querySelector('[data-show-all-parking]').addEventListener('click', clearFilters);
    favourites.onChange(() => {
      if (!favouritesOnly.checked) return;
      renderResults(normalize(search.value));
      updateMarkers(false);
    });
  }
  applyFilters();

  function failMap() {
    clearTimeout(timeout);
    mapFailed = true;
    infoWindow?.close();
    selectLocation(null);
    mapPanel.hidden = true;
    finder.classList.remove('has-parking-map');
    mapped.forEach(location => { location.button.hidden = true; });
    message.textContent = translate('Google Maps is unavailable. Select a location from the list.');
  }

  function addLocationMarker(location) {
    location.marker = new google.maps.marker.AdvancedMarkerElement({
      position: location.position,
      title: location.title,
      gmpClickable: true,
    });
    location.marker.addEventListener('gmp-click', () => {
      selectLocation(location);
      if (list.scrollHeight > list.clientHeight) list.scrollTop = location.card.offsetTop;
    });
    location.button.addEventListener('click', () => {
      selectLocation(location);
      if (window.matchMedia('(max-width: 860px)').matches) {
        mapPanel.scrollIntoView({ block: 'start' });
      }
    });
    location.button.hidden = false;
  }

  function loadMap() {
    if (mapLoading || mapFailed || !mapped.length) return;
    if (!finder.dataset.mapsKey) {
      failMap();
      return;
    }

    mapLoading = true;
    message.textContent = translate('Loading Google Maps…');
    window.gm_authFailure = failMap;
    window.myParkInitFindParking = () => {
      if (mapFailed) return;
      try {
        mapPanel.hidden = false;
        finder.classList.add('has-parking-map');
        map = new google.maps.Map(canvas, {
          center: mapped[0]?.position ?? { lat: 5.4, lng: 117 },
          zoom: 8,
          mapId: finder.dataset.mapId,
          gestureHandling: 'cooperative',
        });
        infoWindow = new google.maps.InfoWindow();
        infoWindow.addListener('closeclick', () => selectLocation(null));
        mapped.forEach(addLocationMarker);
        updateMarkers();
        centerUserPosition();
        message.textContent = '';
        clearTimeout(timeout);
      } catch (_) {
        failMap();
      }
    };

    const params = new URLSearchParams({
      key: finder.dataset.mapsKey,
      v: 'weekly',
      loading: 'async',
      libraries: 'marker',
      callback: 'myParkInitFindParking',
      region: 'MY',
      language: document.documentElement.lang === 'zh-Hans' ? 'zh-CN' : document.documentElement.lang || 'en',
    });
    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://maps.googleapis.com/maps/api/js?' + params;
    script.addEventListener('error', failMap, { once: true });
    timeout = setTimeout(failMap, 15000);
    document.head.append(script);
  }

  loadMap();
})();
