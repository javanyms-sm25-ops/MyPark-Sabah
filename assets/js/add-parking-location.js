(() => {
  'use strict';

  const form = document.querySelector('.ops-location-form');
  if (!form?.dataset.mapsKey) return;

  const translate = window.MyParkLocale?.text ?? (message => message);
  const canvas = form.querySelector('[data-location-map]');
  const status = form.querySelector('[data-location-map-status]');
  const latitude = form.elements.lat;
  const longitude = form.elements.lng;
  const readPosition = () => latitude.validity.valid && longitude.validity.valid
    ? { lat: latitude.valueAsNumber, lng: longitude.valueAsNumber }
    : null;
  let failed = false;
  let timeout;

  function failMap() {
    failed = true;
    clearTimeout(timeout);
    canvas.hidden = true;
    status.textContent = translate('Google Maps could not load. Enter the coordinates below.');
  }

  window.gm_authFailure = failMap;
  window.myParkInitAddLocation = () => {
    if (failed) return;

    try {
      const position = readPosition();
      canvas.hidden = false;
      const map = new google.maps.Map(canvas, {
        center: position ?? { lat: 5.4, lng: 117 },
        zoom: position ? 15 : 8,
        mapId: form.dataset.mapId,
        gestureHandling: 'cooperative',
        clickableIcons: false,
      });
      const pin = new google.maps.marker.AdvancedMarkerElement({ title: translate('Selected location') });

      function updatePin() {
        const point = readPosition();
        if (point) {
          pin.position = point;
          pin.map = map;
          map.panTo(point);
        } else {
          pin.map = null;
        }
        status.textContent = translate(point ? 'Pin placed. Click or tap another spot to move it.' : 'Click or tap the map to place a pin.');
      }

      map.addListener('click', ({ latLng }) => {
        if (!latLng) return;
        latitude.value = latLng.lat().toFixed(6);
        longitude.value = latLng.lng().toFixed(6);
        updatePin();
      });
      latitude.addEventListener('change', updatePin);
      longitude.addEventListener('change', updatePin);
      updatePin();
      clearTimeout(timeout);
    } catch (_) {
      failMap();
    }
  };

  const params = new URLSearchParams({
    key: form.dataset.mapsKey,
    v: 'weekly',
    loading: 'async',
    libraries: 'marker',
    callback: 'myParkInitAddLocation',
    region: 'MY',
    language: document.documentElement.lang === 'zh-Hans' ? 'zh-CN' : document.documentElement.lang || 'en',
  });
  const script = document.createElement('script');
  script.async = true;
  script.src = 'https://maps.googleapis.com/maps/api/js?' + params;
  script.addEventListener('error', failMap, { once: true });
  status.textContent = translate('Loading Google Maps…');
  timeout = setTimeout(failMap, 15000);
  document.head.append(script);
})();
