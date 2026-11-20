/* The server remains the source of truth for the member's saved selection. */
(function () {
  'use strict';
  const translate = window.MyParkLocale?.text ?? (message => message);
  document.querySelectorAll('#my-vehicles form').forEach(vehicleForm => {
    let submitted = false;
    let button;
    let original;
    vehicleForm.addEventListener('submit', event => {
      if (submitted) {
        event.preventDefault();
        return;
      }
      submitted = true;
      button = event.submitter;
      if (button) {
        original = button.textContent;
        button.textContent = translate('Saving vehicle…');
        button.setAttribute('aria-disabled', 'true');
      }
      vehicleForm.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => {
      submitted = false;
      vehicleForm.removeAttribute('aria-busy');
      if (button) {
        button.textContent = original;
        button.removeAttribute('aria-disabled');
      }
    });
  });
  const form = document.getElementById('vehicle-selection-form');
  if (!form || !window.fetch) return;

  const select = form.elements.namedItem('vehicle_id');
  const card = document.getElementById('selected-vehicle');
  const plate = document.getElementById('selected-vehicle-plate');
  const name = document.getElementById('selected-vehicle-name');
  const status = document.getElementById('vehicle-selection-status');
  const error = document.getElementById('vehicle-selection-error');
  const submit = form.querySelector('[data-save-selection]');
  let confirmed = select.value;
  let saving = false;
  submit.hidden = true;

  function render() {
    const option = select.selectedOptions[0];
    plate.textContent = option.dataset.plate;
    name.textContent = option.dataset.name;
    name.hidden = !option.dataset.name;
  }

  async function saveSelection(event) {
    event.preventDefault();
    if (saving || select.value === confirmed) return;
    const requested = select.value;
    saving = true;
    select.disabled = true;
    card.setAttribute('aria-busy', 'true');
    render();
    error.hidden = true;
    status.textContent = translate('Saving vehicle selection…');
    try {
      const body = new URLSearchParams({
        csrf: form.elements.namedItem('csrf').value,
        vehicle_action: 'select',
        vehicle_id: requested
      });
      const response = await fetch(form.dataset.endpoint, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' }, body
      });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || translate('Vehicle selection could not be saved. Please try again.'));
      if (!result.vehicles[requested] || result.selected_id !== requested) throw new Error(translate('Your saved vehicles changed. Refresh the page and choose again.'));
      confirmed = requested;
      const option = select.selectedOptions[0];
      option.dataset.plate = result.vehicles[requested].plate;
      option.dataset.name = result.vehicles[requested].name;
      render();
      status.textContent = result.message;
    } catch (failure) {
      select.value = confirmed;
      render();
      status.textContent = '';
      error.textContent = failure instanceof SyntaxError || failure instanceof TypeError
        ? translate('Vehicle selection could not be saved. Please try again.') : failure.message;
      error.hidden = false;
    } finally {
      saving = false;
      select.disabled = false;
      card.removeAttribute('aria-busy');
    }
  }
  select.addEventListener('change', saveSelection);
  form.addEventListener('submit', saveSelection);
  // A location click waits until the visible selection is safely stored.
  document.querySelectorAll('a[href^="parking-details.php"]').forEach(link => {
    link.addEventListener('click', event => {
      if (!saving) return;
      event.preventDefault();
      status.textContent = translate('Saving vehicle selection…');
    });
  });
}());
