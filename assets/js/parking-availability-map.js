/* The map and the location list share the Operations snapshot and Location filter. */
(function () {
  'use strict';

  function mount(section, { selectLocation }) {
    const translate = window.MyParkLocale?.text ?? (message => message);
    const canvas = section.querySelector('[data-parking-map]');
    const layout = canvas.parentElement;
    const list = section.querySelector('[data-availability-locations]');
    const search = section.querySelector('[data-availability-search]');
    const searchStatus = section.querySelector('[data-availability-search-status]');
    const mapMessage = section.querySelector('[data-maps-message]');
    const refreshError = section.querySelector('[data-availability-error]');
    const markers = new Map();
    const buttons = new Map();
    let locations = new Map();
    let visibleLocations = new Map();
    let selectedId = '', openId = '', positionsKey = '';
    let maps, map, infoWindow, loadTimeout;
    let mapState = 'idle', renderedCatalog = '';

    function element(tag, className, text) {
      const node = document.createElement(tag);
      if (className) node.className = className;
      if (text != null) node.textContent = text;
      return node;
    }

    function details(location) {
      const content = element('span', 'ops-availability-details');
      content.append(element('strong', 'ops-availability-name', location.name));
      if (location.city) content.append(element('span', 'ops-availability-city', location.city));
      for (const [type, label] of [['regular', 'Regular parking'], ['oku', 'OKU parking']]) {
        const bay = location.bays[type];
        const group = element('span', 'ops-availability-bay');
        const counts = element('span', 'ops-availability-counts');
        [
          ['Capacity', bay.capacity],
          ['Occupied bays', bay.occupied],
          ['Available bays', bay.available]
        ].forEach(([key, value]) => {
          const stat = element('span');
          stat.append(element('strong', '', value ?? '—'), element('span', '', translate(key)));
          counts.append(stat);
        });
        group.append(element('strong', 'ops-availability-bay-label', translate(label)), counts);
        content.append(group);
      }
      if (location.capacity === null) {
        content.append(element('span', 'ops-availability-note', translate('Capacity unavailable')));
      }
      if (!location.position) {
        content.append(element('span', 'ops-availability-note', translate('Map position unavailable')));
      }
      return content;
    }

    function closeInfoWindow() {
      openId = '';
      infoWindow?.close();
    }

    function choose(id) {
      selectLocation(id);
      const marker = markers.get(id);
      if (!marker || mapState !== 'ready') return;
      openId = id;
      infoWindow.setOptions({ ariaLabel: locations.get(id).name });
      infoWindow.setContent(details(locations.get(id)));
      infoWindow.open({ map, anchor: marker, shouldFocus: false });
    }

    function renderLocations(query) {
      const focusedId = document.activeElement?.dataset.availabilityLocation;
      buttons.clear();
      const items = [];
      for (const [id, location] of visibleLocations) {
        const item = element('li');
        const button = element('button', 'ops-availability-location');
        button.type = 'button';
        button.dataset.availabilityLocation = id;
        button.setAttribute('aria-pressed', String(id === selectedId));
        button.setAttribute('aria-controls', 'reservation-management');
        button.addEventListener('click', () => choose(id));
        button.append(details(location));
        item.append(button);
        items.push(item);
        buttons.set(id, button);
      }
      if (!items.length) {
        const message = query ? 'No matching parking locations.' : 'No parking locations yet.';
        items.push(element('li', 'ops-availability-note', translate(message)));
      }
      list.replaceChildren(...items);
      if (focusedId) (buttons.get(focusedId) ?? search).focus({ preventScroll: true });
      const count = visibleLocations.size;
      const status = query ? translate(count === 1 ? '{count} location found' : '{count} locations found', { count }) : '';
      if (searchStatus.textContent !== status) searchStatus.textContent = status;
    }

    function setSelection(id) {
      if (id === selectedId) return;
      selectedId = id;
      for (const [locationId, button] of buttons) button.setAttribute('aria-pressed', String(locationId === id));
      for (const [locationId, marker] of markers) marker.zIndex = locationId === id ? 2 : 1;
      if (openId && openId !== id) closeInfoWindow();
    }

    function updateMarkers() {
      for (const [id, marker] of markers) {
        if (visibleLocations.get(id)?.position) continue;
        if (openId === id) closeInfoWindow();
        marker.map = null;
        markers.delete(id);
      }

      const plotted = [...visibleLocations.values()].filter(location => location.position);
      const nextPositions = JSON.stringify(plotted.map(location => [location.id, location.position]));
      for (const location of plotted) {
        const id = String(location.id);
        if (markers.has(id)) {
          const marker = markers.get(id);
          if (nextPositions !== positionsKey) marker.position = location.position;
          marker.title = location.name;
          continue;
        }
        const marker = new maps.marker.AdvancedMarkerElement({
          map,
          position: location.position,
          title: location.name,
          gmpClickable: true,
          zIndex: id === selectedId ? 2 : 1
        });
        marker.addEventListener('gmp-click', () => choose(id));
        markers.set(id, marker);
      }
      if (nextPositions !== positionsKey) {
        if (plotted.length === 1) {
          map.setCenter(plotted[0].position);
          map.setZoom(14);
        } else if (plotted.length > 1) {
          const bounds = new maps.LatLngBounds();
          plotted.forEach(location => bounds.extend(location.position));
          if (bounds.getNorthEast().equals(bounds.getSouthWest())) {
            map.setCenter(plotted[0].position);
            map.setZoom(14);
          } else {
            map.fitBounds(bounds, 48);
          }
        }
        positionsKey = nextPositions;
      }
      if (openId) infoWindow.setContent(details(locations.get(openId)));
    }

    function setMapVisible(visible) {
      canvas.hidden = !visible;
      layout.classList.toggle('has-map', visible);
    }

    function showMapMessage(message) {
      mapMessage.textContent = message ? translate(message) : '';
      mapMessage.hidden = !message;
    }

    function loadMap(center) {
      mapState = 'loading';
      showMapMessage('Loading Google Maps…');
      const fail = () => {
        clearTimeout(loadTimeout);
        mapState = 'failed';
        closeInfoWindow();
        setMapVisible(false);
        showMapMessage('Google Maps could not load. Use the location list below.');
      };
      window.gm_authFailure = fail;
      window.myParkInitParkingMap = () => {
        if (mapState !== 'loading') return;
        clearTimeout(loadTimeout);
        try {
          maps = window.google.maps;
          setMapVisible(true);
          map = new maps.Map(canvas, {
            center, zoom: 14, mapId: section.dataset.mapsId, gestureHandling: 'cooperative'
          });
          infoWindow = new maps.InfoWindow();
          infoWindow.addListener('closeclick', () => { openId = ''; });
          mapState = 'ready';
          renderMap();
        } catch (_) {
          fail();
        }
      };
      const script = element('script');
      script.async = true;
      const lang = document.documentElement.lang;
      const params = new URLSearchParams({
        key: section.dataset.mapsKey, v: 'weekly', loading: 'async', libraries: 'marker',
        callback: 'myParkInitParkingMap', auth_referrer_policy: 'origin',
        language: lang === 'zh-Hans' ? 'zh-CN' : lang || 'en', region: 'MY'
      });
      script.src = 'https://maps.googleapis.com/maps/api/js?' + params;
      script.addEventListener('error', fail, { once: true });
      loadTimeout = setTimeout(fail, 15000);
      document.head.append(script);
    }

    function renderMap() {
      if (section.dataset.mapEnabled !== 'true') return;
      const first = [...locations.values()].find(location => location.position);
      if (!first) {
        if (mapState === 'ready') updateMarkers();
        setMapVisible(false);
        showMapMessage(locations.size ? 'No parking locations have map coordinates.' : '');
      } else if (!section.dataset.mapsKey) {
        showMapMessage('Google Maps needs an API key. Use the location list below.');
      } else if (mapState === 'idle') {
        loadMap(first.position);
      } else if (mapState === 'ready') {
        setMapVisible(true);
        showMapMessage('');
        updateMarkers();
      }
    }

    function render() {
      const normalize = text => text.normalize('NFKC').toLocaleLowerCase().trim();
      const query = normalize(search.value);
      visibleLocations = query ? new Map([...locations].filter(([, location]) =>
        location.capacity > 0 && normalize(location.name + ' ' + location.city).includes(query)
      )) : locations;
      renderLocations(query);
      renderMap();
    }

    search.closest('label').hidden = false;
    search.addEventListener('input', () => {
      list.scrollTop = 0;
      render();
    });

    function update(snapshot) {
      section.hidden = !snapshot;
      if (!snapshot) {
        closeInfoWindow();
        return;
      }
      refreshError.hidden = true;

      const catalog = JSON.stringify(snapshot.locations);
      if (catalog === renderedCatalog) return;
      renderedCatalog = catalog;
      locations = new Map(snapshot.locations.map(location => [String(location.id), location]));
      render();
    }

    return {
      update,
      setSelection,
      showRefreshError() {
        if (!refreshError.hidden) return;
        refreshError.textContent = translate('Refresh failed. Showing the last known parking availability.');
        refreshError.hidden = false;
      }
    };
  }

  window.MyParkParkingMap = { mount };
}());
