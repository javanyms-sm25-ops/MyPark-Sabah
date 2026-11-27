/* Parking and Find Parking share the saved locations in this browser. */
(() => {
  'use strict';

  const storageKey = 'park-wishlist';
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

  function updateButtons(root = document) {
    for (const button of root.querySelectorAll('[data-wish]')) {
      const pressed = saved.has(button.dataset.wish);
      button.textContent = pressed ? '♥' : '♡';
      button.setAttribute('aria-pressed', String(pressed));
      button.hidden = false;
    }
  }

  function update() {
    updateButtons();
    listeners.forEach(listener => listener());
  }

  function sync() {
    if (temporary) return;
    const latest = readSaved();
    if (latest.size === saved.size && [...latest].every(id => saved.has(id))) return;
    saved = latest;
    update();
  }

  document.addEventListener('click', event => {
    const button = event.target.closest('button[data-wish]');
    if (!button) return;
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

  window.MyParkFavourites = {
    has: id => saved.has(id),
    onChange: listener => listeners.push(listener),
    updateButtons,
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
