/* Booking fields and review. The final price and availability come from PHP. */
(function () {
  'use strict';
  const translate = window.MyParkLocale?.text ?? (message => message);


  function guardSubmission(form, message) {
    if (!form) return;
    const button = form.querySelector('button:not([type="button"])');
    const original = button.textContent;
    let submitted = false;
    form.addEventListener('submit', event => {
      if (submitted) {
        event.preventDefault();
      } else if (!event.defaultPrevented) {
        submitted = true;
        button.disabled = true;
        button.textContent = message;
        form.setAttribute('aria-busy', 'true');
      }
    });
    window.addEventListener('pageshow', () => {
      submitted = false;
      button.disabled = false;
      button.textContent = original;
      form.removeAttribute('aria-busy');
    });
  }

  const reviewDialog = document.getElementById('parkingReview');
  if (reviewDialog) {
    const reviewForm = document.getElementById('startParkingForm');
    const editForm = document.getElementById('editParkingForm');
    guardSubmission(reviewForm, translate('Starting parking…'));
    if (typeof reviewDialog.showModal === 'function') {
      reviewDialog.addEventListener('cancel', event => {
        event.preventDefault();
        if (!reviewForm?.hasAttribute('aria-busy')) editForm.requestSubmit();
      });
      reviewDialog.close();
      reviewDialog.showModal();
    }
  }

  const form = document.getElementById('bookForm');
  if (!form) return;
  const location = document.getElementById('lot_id');
  const bayType = document.getElementById('bay_type');
  const vehicle = document.getElementById('savedVehicle');
  let vehicleControl = vehicle;
  const manual = document.getElementById('manualVehicle');
  const plate = document.getElementById('plate');
  const saveVehicle = manual.querySelector('[name="save_vehicle"]');
  const hours = document.getElementById('hours');
  const durationPicker = document.getElementById('durationPicker');
  const customDuration = document.getElementById('customDuration');
  const fee = document.getElementById('fee');
  const hourlyCents = Number(form.dataset.hourlyCents);
  const dailyCents = Number(form.dataset.dailyCents);
  const summaryError = document.getElementById('bookingError');
  const manage = document.getElementById('manageBookingVehicles');
  const profileLink = document.getElementById('manageVehiclesInProfile');
  const dialog = document.getElementById('bookingVehicleDialog');
  const addForm = document.getElementById('addBookingVehicleForm');
  const fieldErrors = {
    location: document.getElementById('bookingLocationError'),
    bay: document.getElementById('bookingBayError'),
    vehicle: document.getElementById('bookingVehicleError'),
    plate: document.getElementById('bookingPlateError'),
    hours: document.getElementById('bookingHoursError')
  };

  function validHours() {
    const value = hours.valueAsNumber;
    return Number.isInteger(value) && value >= 1 && value <= 24;
  }

  function updateParkingContext() {
    let estimate = translate('Select a location to calculate the fee');
    if (location.value) {
      if (validHours()) {
        const feeCents = bayType?.value === 'oku' ? 0 : Math.min(hours.valueAsNumber * hourlyCents, dailyCents);
        estimate = 'RM ' + (feeCents / 100).toFixed(2);
      } else {
        estimate = translate('Enter a valid duration to calculate the fee');
      }
    }
    if (fee.textContent.trim() !== estimate) fee.textContent = estimate;

    if (!manage) return;
    const url = new URL(manage.href);
    url.searchParams.delete('lot_id');
    url.searchParams.delete('hours');
    url.searchParams.delete('bay_type');
    if (location.value) url.searchParams.set('lot_id', location.value);
    if (validHours()) url.searchParams.set('hours', hours.value);
    if (bayType) url.searchParams.set('bay_type', bayType.value);
    manage.href = profileLink.href = url.href;
    addForm.elements.namedItem('lot_id').value = location.value;
    addForm.elements.namedItem('hours').value = validHours() ? hours.value : '';
    addForm.elements.namedItem('bay_type').value = bayType?.value ?? '';
  }

  function setError(name, input, message = '') {
    const display = fieldErrors[name];
    display.textContent = message;
    display.hidden = !message;
    if (input) {
      if (message) input.setAttribute('aria-invalid', 'true');
      else input.removeAttribute('aria-invalid');
    }
  }

  function updateVehicleFields() {
    const isManual = !vehicle || vehicle.value === 'manual';
    manual.hidden = !isManual;
    manual.disabled = !isManual;
  }

  location.addEventListener('change', () => {
    setError('location', location);
    updateParkingContext();
  });
  bayType?.addEventListener('change', () => {
    setError('bay', bayType);
    updateParkingContext();
  });

  let customHours = hours.value;
  let usingCustom = false;
  function applyDuration(choice) {
    if (!choice) return;
    if (usingCustom) customHours = hours.value;
    usingCustom = choice.value === 'custom';
    hours.value = usingCustom ? customHours : choice.value;
    hours.readOnly = !usingCustom;
    customDuration.hidden = !usingCustom;
    updateParkingContext();
  }
  durationPicker.querySelectorAll('input[type="radio"]').forEach(choice => {
    choice.addEventListener('change', () => {
      if (!choice.checked) return;
      setError('hours', hours);
      applyDuration(choice);
    });
  });
  durationPicker.hidden = false;
  applyDuration(durationPicker.querySelector('input:checked'));
  hours.addEventListener('input', () => {
    setError('hours', hours);
    updateParkingContext();
  });

  vehicle?.addEventListener('change', () => {
    updateVehicleFields();
    if (manual.disabled && saveVehicle) saveVehicle.checked = false;
    setError('vehicle', vehicleControl);
    setError('plate', plate);
  });
  plate.addEventListener('input', () => {
    plate.value = plate.value.toUpperCase();
    setError('plate', plate);
    if (!vehicle) setError('vehicle', null);
  });
  updateVehicleFields();

  form.addEventListener('submit', event => {
    const locationMessage = location.value ? '' : translate('Select a parking location.');
    const vehicleMessage = vehicle && !vehicle.value ? translate('Choose a saved vehicle from your account before reviewing parking.') : '';
    const plateMessage = !manual.disabled && !/^[A-Z]{1,3}\d{1,4}[A-Z]?$/.test(plate.value.toUpperCase().replace(/\s+/g, ''))
      ? translate('Enter a valid plate number, for example SAB 1234.') : '';
    const hoursMessage = validHours() ? '' : translate('Choose a whole number from 1 to 24 hours.');
    setError('location', location, locationMessage);
    setError('vehicle', vehicleControl, vehicleMessage);
    setError('plate', plate, plateMessage);
    setError('hours', hours, hoursMessage);

    if (locationMessage || vehicleMessage || plateMessage || hoursMessage) {
      event.preventDefault();
      summaryError.textContent = translate('Check the highlighted booking details.');
      summaryError.hidden = false;
      const firstInvalid = form.querySelector('[aria-invalid="true"]');
      firstInvalid?.focus();
    }
  });
  if (!reviewDialog) guardSubmission(form, translate('Checking details…'));

  if (dialog && typeof dialog.showModal === 'function') {
    function openAddVehicle() {
      updateParkingContext();
      dialog.showModal();
    }
    manage.addEventListener('click', event => {
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      openAddVehicle();
    });
    dialog.querySelector('[data-close-vehicle-dialog]').addEventListener('click', () => dialog.close());
    if (dialog.hasAttribute('data-reopen')) dialog.showModal();
    guardSubmission(addForm, translate('Saving vehicle…'));

    const picker = document.getElementById('chooseVehicleDialog');
    const trigger = document.getElementById('chooseBookingVehicle');
    const choiceText = trigger.querySelector('#bookingVehicleValue');
    const prompt = picker.querySelector('[data-prompt-for-vehicle]');
    const choices = picker.querySelectorAll('[data-select-vehicle]');
    const placeholder = choiceText.textContent;
    const preferenceKey = picker.dataset.preferenceKey;

    try {
      prompt.checked = localStorage.getItem(preferenceKey) !== 'off';
    } catch (_) { /* The switch still works for this page. */ }

    prompt.addEventListener('change', () => {
      try {
        localStorage.setItem(preferenceKey, prompt.checked ? 'on' : 'off');
      } catch (_) { /* The switch still works for this page. */ }
    });

    function renderVehicleChoice() {
      choiceText.textContent = vehicle.value ? vehicle.selectedOptions[0].textContent.trim() : placeholder;
      choices.forEach(choice => choice.setAttribute('aria-pressed', String(choice.dataset.selectVehicle === vehicle.value)));
    }

    vehicleControl = trigger;
    if (vehicle.hasAttribute('aria-invalid')) {
      trigger.setAttribute('aria-invalid', 'true');
      vehicle.removeAttribute('aria-invalid');
    }
    document.getElementById('bookingVehicleLabel').htmlFor = trigger.id;
    vehicle.hidden = true;
    trigger.hidden = false;
    renderVehicleChoice();
    vehicle.addEventListener('change', renderVehicleChoice);

    function promptForVehicle() {
      if (prompt.checked && location.value && !reviewDialog && !dialog.open && !picker.open) picker.showModal();
    }

    trigger.addEventListener('click', () => picker.showModal());
    picker.querySelector('[data-close-picker]').addEventListener('click', () => picker.close());
    picker.querySelector('[data-add-from-picker]').addEventListener('click', () => {
      picker.close();
      openAddVehicle();
    });
    choices.forEach(choice => {
      choice.addEventListener('click', () => {
        vehicle.value = choice.dataset.selectVehicle;
        vehicle.dispatchEvent(new Event('change', { bubbles: true }));
        picker.close();
        trigger.focus();
      });
    });
    location.addEventListener('change', promptForVehicle);
    if (picker.hasAttribute('data-auto-prompt')) promptForVehicle();
  }

  if (!summaryError.hidden) {
    (form.querySelector('[aria-invalid="true"]') || summaryError).focus();
  } else if (reviewDialog || window.location.hash === '#bookingPanel') {
    const heading = document.getElementById(reviewDialog ? 'parking-review-heading' : 'parking-heading');
    window.addEventListener('pageshow', () => heading.focus({ preventScroll: true }), { once: true });
  }
}());
