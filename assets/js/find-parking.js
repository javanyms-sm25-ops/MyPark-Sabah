(() => {
  'use strict';

  const finder = document.querySelector('[data-find-parking]');
  if (!finder) return;

  const translate = window.MyParkLocale?.text ?? (message => message);
  const search = finder.querySelector('#find-parking-query');
  const list = finder.querySelector('.find-parking-list-wrap');
  const locationList = finder.querySelector('#find-parking-list');
  const locationsHeading = finder.querySelector('#find-parking-heading');
  const locate = finder.querySelector('[data-use-location]');
  const locationStatus = finder.querySelector('[data-location-status]');
  const empty = finder.querySelector('[data-parking-empty]');
  const canvas = finder.querySelector('#find-parking-map');
  const message = finder.querySelector('[data-map-message]');
  const normalize = value => value.normalize('NFKC').toLocaleLowerCase().trim();
  const locations = Array.from(finder.querySelectorAll('[data-parking-location]'), card => ({
    card,
    searchText: normalize(card.dataset.search),
    title: card.querySelector('h3').textContent,
    button: card.querySelector('[data-show-on-map]'),
    distanceLabel: card.querySelector('[data-parking-distance]'),
    position: card.hasAttribute('data-lat')
      ? { lat: Number(card.dataset.lat), lng: Number(card.dataset.lng) }
      : null,
  }));

  let map, infoWindow, selected, timeout;
  let userPosition, userMarker;
  let mapFailed = false;
  const mapped = locations.filter(location => location.position);

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

  locate.hidden = false;
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

  function selectLocation(location) {
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
    map.panTo(location.position);
    if (map.getZoom() < 14) map.setZoom(14);

    const content = document.createElement('div');
    content.className = 'find-parking-map-info';
    const title = document.createElement('strong');
    title.textContent = location.title;
    const rate = location.card.querySelector('.find-parking-rate').cloneNode(true);
    const link = location.card.querySelector('a').cloneNode(true);
    link.removeAttribute('class');
    content.append(title, rate, link);
    infoWindow.setOptions({ ariaLabel: location.title });
    infoWindow.setContent(content);
    infoWindow.open({ map, anchor: location.marker, shouldFocus: false });
  }

  function updateMarkers() {
    if (!map || mapFailed) return;
    const visible = locations.filter(location => location.marker && !location.card.hidden);
    for (const location of locations) {
      if (location.marker) location.marker.map = location.card.hidden ? null : map;
    }
    if (selected?.card.hidden) {
      infoWindow.close();
      selectLocation(null);
    }
    if (selected || userPosition || !visible.length) return;

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

  search.disabled = false;
  search.addEventListener('input', () => {
    const query = normalize(search.value);
    for (const location of locations) {
      location.card.hidden = !location.searchText.includes(query);
    }
    empty.hidden = locations.some(location => !location.card.hidden);
    updateMarkers();
  });

  if (!finder.dataset.mapsKey) {
    message.textContent = translate('Google Maps is unavailable. Select a location from the list.');
    return;
  }

  function failMap() {
    clearTimeout(timeout);
    mapFailed = true;
    infoWindow?.close();
    selectLocation(null);
    canvas.hidden = true;
    mapped.forEach(location => { location.button.hidden = true; });
    message.textContent = translate('Google Maps is unavailable. Select a location from the list.');
  }

  message.textContent = translate('Loading Google Maps…');
  window.gm_authFailure = failMap;
  window.myParkInitFindParking = () => {
    if (mapFailed) return;
    try {
      canvas.hidden = false;
      map = new google.maps.Map(canvas, {
        center: mapped[0]?.position ?? { lat: 5.4, lng: 117 },
        zoom: 8,
        mapId: finder.dataset.mapId,
        gestureHandling: 'cooperative',
      });
      infoWindow = new google.maps.InfoWindow();
      infoWindow.addListener('closeclick', () => selectLocation(null));
      for (const location of mapped) {
        location.marker = new google.maps.marker.AdvancedMarkerElement({
          position: location.position,
          title: location.title,
          gmpClickable: true,
        });
        location.marker.addEventListener('gmp-click', () => {
          selectLocation(location);
          list.scrollTop = location.card.offsetTop;
        });
        location.button.addEventListener('click', () => {
          selectLocation(location);
          if (window.matchMedia('(max-width: 760px)').matches) {
            canvas.parentElement.scrollIntoView({ block: 'start' });
          }
        });
        location.button.hidden = false;
      }
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
})();
