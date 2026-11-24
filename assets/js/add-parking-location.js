(() => {
  'use strict';

  const form = document.querySelector('.ops-location-form');
  if (!form) return;

  const translate = window.MyParkLocale?.text ?? (message => message);
  const canvas = form.querySelector('[data-location-map]');
  const status = form.querySelector('[data-location-map-status]');
  const search = form.querySelector('[data-place-search]');
  const searchStatus = search.querySelector('[data-place-search-status]');
  const name = form.elements.name;
  const city = form.elements.city;
  const address = form.elements.address;
  const latitude = form.elements.lat;
  const longitude = form.elements.lng;
  const submit = form.querySelector('button[type="submit"]');
  function readPosition() {
    if (!latitude.value.trim() || !longitude.value.trim()) return null;
    const lat = Number(latitude.value);
    const lng = Number(longitude.value);
    return Math.abs(lat) <= 90 && Math.abs(lng) <= 180 ? { lat, lng } : null;
  }
  let failed = false;
  let timeout;
  let lookupVersion = 0;
  const autofilled = new Map();

  function cityFrom(components) {
    for (const type of ['locality', 'postal_town', 'administrative_area_level_3', 'administrative_area_level_2', 'sublocality']) {
      const component = components?.find(part => part.types.includes(type));
      const cityName = component?.longText ?? component?.long_name;
      if (cityName) return cityName;
    }
    return '';
  }

  function fill(field, value) {
    field.value = value ?? '';
    autofilled.set(field, field.value);
  }

  function clearPreviousAddress() {
    for (const field of [city, address]) {
      if (field.value === autofilled.get(field)) field.value = '';
      autofilled.delete(field);
    }
  }

  function failMap() {
    failed = true;
    clearTimeout(timeout);
    lookupVersion++;
    submit.disabled = !readPosition();
    canvas.hidden = true;
    search.hidden = true;
    status.textContent = translate('Google Maps could not load. Check the Maps configuration and reload this page.');
  }

  if (!form.dataset.mapsKey) {
    failMap();
    return;
  }

  async function mountPlaceSearch(selectPlace) {
    const searchUnavailable = () => {
      searchStatus.textContent = translate('Place search is unavailable. Use the map or enter the details manually.');
    };
    try {
      const { PlaceAutocompleteElement } = await google.maps.importLibrary('places');
      if (failed) return;
      const autocomplete = new PlaceAutocompleteElement({
        includedRegionCodes: ['my'],
        locationBias: { north: 7.6, south: 3.9, east: 119.5, west: 114.9 },
        placeholder: translate('Search by place name'),
        description: translate('Search for an address or landmark in Malaysia'),
      });
      autocomplete.addEventListener('gmp-select', ({ placePrediction }) => {
        searchStatus.textContent = '';
        selectPlace(placePrediction);
      });
      autocomplete.addEventListener('gmp-error', () => {
        searchStatus.textContent = translate('Google Places denied the search. Check that Places API (New) is enabled and allowed for this site’s Maps key. You can still choose a pin on the map.');
      });
      search.querySelector('[data-place-search-input]').append(autocomplete);
      search.hidden = false;
    } catch (_) {
      if (failed) return;
      searchUnavailable();
      search.hidden = false;
    }
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
      let geocoder;

      function updatePin() {
        const point = readPosition();
        if (point) {
          pin.position = point;
          pin.map = map;
          map.panTo(point);
        } else {
          pin.map = null;
        }
        submit.disabled = !point;
        status.textContent = translate(point ? 'Pin placed. Click or tap another spot to move it.' : 'Click or tap the map to place a pin.');
      }

      function setPosition(point) {
        latitude.value = point.lat().toFixed(6);
        longitude.value = point.lng().toFixed(6);
        updatePin();
      }

      async function lookupLocation(getDetails) {
        const version = ++lookupVersion;
        submit.disabled = true;
        status.textContent = translate('Looking up location details…');
        try {
          const details = await getDetails();
          if (version !== lookupVersion) return;
          if (details.position) {
            map.setZoom(15);
            setPosition(details.position);
          }
          if (details.name && (!name.value.trim() || name.value === autofilled.get(name))) fill(name, details.name);
          fill(city, details.city);
          fill(address, details.address);
          status.textContent = translate(city.value && address.value
            ? 'City and address filled. Review them before saving.'
            : 'Enter any missing city or address details.');
        } catch (_) {
          if (version === lookupVersion) status.textContent = translate('Location details are unavailable. Enter the city and address manually.');
        } finally {
          if (version === lookupVersion) submit.disabled = !readPosition();
        }
      }

      map.addListener('click', ({ latLng }) => {
        if (!latLng) return;
        clearPreviousAddress();
        setPosition(latLng);
        lookupLocation(async () => {
          if (!geocoder) {
            const { Geocoder } = await google.maps.importLibrary('geocoding');
            geocoder ??= new Geocoder();
          }
          const { results } = await geocoder.geocode({ location: latLng });
          const result = results[0];
          return { city: cityFrom(result?.address_components), address: result?.formatted_address };
        });
      });

      mountPlaceSearch(placePrediction => lookupLocation(async () => {
        const place = placePrediction.toPlace();
        await place.fetchFields({ fields: ['location', 'formattedAddress', 'addressComponents'] });
        if (!place.location) throw new Error('The selected place has no map position.');
        return {
          position: place.location,
          name: placePrediction.mainText?.text,
          city: cityFrom(place.addressComponents),
          address: place.formattedAddress,
        };
      }));
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
