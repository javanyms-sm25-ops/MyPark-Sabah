/* Parking and Find Parking share the saved locations in this browser. */
(() => {
  'use strict';

  const storageKey = 'park-wishlist';
  const buttons = document.querySelectorAll('[data-wish]');
  const listeners = [];
  let saved = readSaved();
  let temporary = false;

  function readSaved() {
    try {
      const items = JSON.parse(window.localStorage.getItem(storageKey) ?? '[]');
      return new Set(Array.isArray(items) ? items.filter(id => typeof id === 'string' && /^[1-9]\d*$/.test(id)) : []);
    } catch (_) {
      return new Set();
    }
  }

  function update() {
    for (const button of buttons) {
      const pressed = saved.has(button.dataset.wish);
      button.textContent = pressed ? '♥' : '♡';
      button.setAttribute('aria-pressed', String(pressed));
    }
    listeners.forEach(listener => listener());
  }

  function sync() {
    if (temporary) return;
    const latest = readSaved();
    if (latest.size === saved.size && [...latest].every(id => saved.has(id))) return;
    saved = latest;
    update();
  }

  for (const button of buttons) {
    button.addEventListener('click', () => {
      sync();
      const id = button.dataset.wish;
      const added = !saved.has(id);
      if (added) saved.add(id);
      else saved.delete(id);
      try {
        window.localStorage.setItem(storageKey, JSON.stringify([...saved]));
        temporary = false;
      } catch (_) {
        temporary = true;
      }
      update();
      const message = temporary ? 'Favourites could only be updated on this page.'
        : added ? 'Parking location added to favourites.' : 'Parking location removed from favourites.';
      window.MyParkToast?.show(message, added && !temporary ? 'success' : 'info');
    });
    button.hidden = false;
  }

  window.MyParkFavourites = {
    has: id => saved.has(id),
    onChange: listener => listeners.push(listener),
  };
  update();
  window.addEventListener('storage', event => {
    if (event.key === storageKey || event.key === null) sync();
  });
  window.addEventListener('pageshow', event => {
    if (event.persisted) sync();
  });
  document.querySelectorAll('[data-view-favourites]').forEach(link => { link.hidden = false; });
})();
