(() => {
  const builder = document.querySelector('[data-report-builder]');
  if (builder) {
    const type = builder.elements.report_type;
    const employee = builder.elements.employee;
    const exception = builder.elements.exception;
    const end = builder.elements.to;
    const update = () => {
      employee.required = type.value === 'individual';
      exception.disabled = type.value !== 'exceptions';
      exception.closest('label').hidden = exception.disabled;
      end.disabled = ['daily', 'weekly', 'monthly'].includes(type.value);
      end.closest('label').hidden = end.disabled;
    };
    type.addEventListener('change', update);
    update();
  }

  const preferredAccuracyMetres = 25;

  const getLocation = (requiredAccuracy, signal, showRefining) => new Promise((resolve) => {
    const geolocation = window.isSecureContext ? navigator.geolocation : null;
    if (!geolocation) {
      resolve({ status: 'failed', message: 'locationUnavailable' });
      return;
    }

    const targetAccuracy = Math.min(preferredAccuracyMetres, requiredAccuracy);
    let best;
    let watchId;
    let refineTimer;
    let finished = false;

    const finish = (result) => {
      if (finished) return;
      finished = true;
      if (watchId !== undefined) geolocation.clearWatch(watchId);
      clearTimeout(deadline);
      clearTimeout(refineTimer);
      signal.removeEventListener('abort', abort);
      resolve(result);
    };
    const bestReading = () => finish(best
      ? { status: 'captured', coords: best }
      : { status: 'failed', message: 'locationFailed' });
    const abort = () => finish({ status: 'skipped' });

    // Stop after a fixed window even if the permission prompt stays open.
    const deadline = setTimeout(bestReading, 40000);
    signal.addEventListener('abort', abort, { once: true });
    try {
      watchId = geolocation.watchPosition(({ coords }) => {
        if (finished || !Number.isFinite(coords.accuracy) || coords.accuracy < 0 || coords.accuracy > 1000000
            || !Number.isFinite(coords.latitude) || Math.abs(coords.latitude) > 90
            || !Number.isFinite(coords.longitude) || Math.abs(coords.longitude) > 180) return;
        const previousAccuracy = best?.accuracy ?? Infinity;
        if (coords.accuracy <= previousAccuracy) {
          best = coords;
          if (best.accuracy <= targetAccuracy) {
            bestReading();
            return;
          }
          if (best.accuracy < previousAccuracy) showRefining(best.accuracy);
        }
        // Give the device a little longer after the first reading.
        refineTimer ??= setTimeout(bestReading, 12000);
      }, ({ code }) => {
        if (code === 1) finish({ status: 'denied', message: 'locationDenied' });
      }, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
    } catch {
      bestReading();
    }
  });

  const applyLocation = (form, { status, coords }) => {
    form.elements.gps_status.value = status;
    form.elements.latitude.value = coords?.latitude ?? '';
    form.elements.longitude.value = coords?.longitude ?? '';
    form.elements.accuracy.value = coords?.accuracy ?? '';
  };

  const setStatus = (form, message) => {
    const status = form.querySelector('[data-capture-status]');
    status.hidden = !message;
    status.textContent = message;
  };

  const locationCapture = document.querySelector('[data-attendance-location-messages]');
  locationCapture?.querySelectorAll('[data-attendance-location]').forEach((form) => {
    const submit = form.querySelector('button[type="submit"]');
    const review = form.querySelector('[data-submit-without-location]');
    const cameraControls = form.querySelectorAll('[data-start-camera], [data-cancel-camera]');
    const submitLabel = submit.textContent;
    const messages = locationCapture.dataset;
    const requiredAccuracy = Number(form.dataset.locationAccuracy);
    let busy = false;
    let locationRequest;
    const accuracyStatus = (template, accuracy) => template
      .replace('{accuracy}', String(Math.ceil(accuracy)))
      .replace('{limit}', String(requiredAccuracy));

    const setBusy = (value) => {
      busy = value;
      submit.disabled = value;
      review.disabled = value;
      cameraControls.forEach(button => { button.disabled = value; });
    };
    const reset = () => {
      locationRequest?.abort();
      locationRequest = null;
      setBusy(false);
      applyLocation(form, { status: 'skipped' });
      submit.textContent = submitLabel;
      review.hidden = true;
    };
    const save = () => {
      setBusy(true);
      setStatus(form, messages.submitting);
      form.submit();
    };

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (busy || (form.elements.photo && !form.elements.photo.value)) return;
      const request = new AbortController();
      locationRequest = request;
      review.hidden = true;
      setBusy(true);
      setStatus(form, messages.locationWorking);
      const location = await getLocation(requiredAccuracy, request.signal,
        accuracy => setStatus(form, accuracyStatus(messages.locationRefining, accuracy)));
      if (request.signal.aborted) return;
      locationRequest = null;
      applyLocation(form, location);
      const captured = location.status === 'captured';
      if (captured && location.coords.accuracy <= requiredAccuracy) {
        save();
        return;
      }

      setBusy(false);
      submit.textContent = messages.retryLocation;
      review.hidden = false;
      setStatus(form, captured ? accuracyStatus(messages.locationApproximate, location.coords.accuracy) : messages[location.message]);
      submit.focus();
    });

    review.addEventListener('click', () => {
      if (!busy && !review.hidden && form.reportValidity()) save();
    });
    cameraControls.forEach(button => button.addEventListener('click', reset));
    window.addEventListener('pagehide', () => {
      reset();
      setStatus(form, '');
    });
  });

  const form = document.querySelector('[data-attendance-camera]');
  if (form) {
    const start = form.querySelector('[data-start-camera]');
    const video = form.querySelector('[data-attendance-video]');
    const preview = form.querySelector('[data-attendance-preview]');
    const take = form.querySelector('[data-take-photo]');
    const cancel = form.querySelector('[data-cancel-camera]');
    const submit = form.querySelector('[data-submit-photo]');
    let camera;
    let attempt = 0;
    const stopCamera = () => {
      camera?.getTracks().forEach((track) => track.stop());
      camera = null;
      video.srcObject = null;
      video.hidden = true;
      take.hidden = true;
    };
    start.hidden = false;
    start.addEventListener('click', async () => {
      const current = ++attempt;
      start.hidden = true;
      cancel.hidden = false;
      submit.hidden = true;
      preview.hidden = true;
      form.elements.photo.value = '';
      setStatus(form, form.dataset.cameraWorking);
      try {
        if (!navigator.mediaDevices?.getUserMedia) throw new Error('Camera unavailable');
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
        if (current !== attempt) {
          stream.getTracks().forEach((track) => track.stop());
          return;
        }
        camera = stream;
        video.srcObject = camera;
        video.hidden = false;
        await video.play();
        if (current !== attempt) return;
        take.hidden = false;
        take.focus();
        setStatus(form, '');
      } catch {
        if (current !== attempt) return;
        stopCamera();
        start.hidden = false;
        cancel.hidden = true;
        setStatus(form, form.dataset.cameraFailed);
        start.focus();
      }
    });
    cancel.addEventListener('click', () => {
      attempt++;
      stopCamera();
      start.hidden = false;
      preview.hidden = true;
      preview.removeAttribute('src');
      submit.hidden = true;
      cancel.hidden = true;
      form.elements.photo.value = '';
      setStatus(form, '');
      start.focus();
    });
    take.addEventListener('click', () => {
      if (!video.videoWidth || !video.videoHeight) return;
      const canvas = document.createElement('canvas');
      const scale = Math.min(1, 1200 / Math.max(video.videoWidth, video.videoHeight));
      canvas.width = Math.round(video.videoWidth * scale);
      canvas.height = Math.round(video.videoHeight * scale);
      canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
      form.elements.photo.value = canvas.toDataURL('image/jpeg', 0.78);
      preview.src = form.elements.photo.value;
      stopCamera();
      preview.hidden = false;
      submit.hidden = false;
      start.textContent = start.dataset.retake;
      start.hidden = false;
      submit.focus();
      setStatus(form, form.dataset.photoReady);
    });
    window.addEventListener('pagehide', () => {
      attempt++;
      stopCamera();
      start.hidden = false;
      cancel.hidden = true;
      submit.hidden = true;
      preview.hidden = true;
      preview.removeAttribute('src');
      form.elements.photo.value = '';
    });
  }

  if (location.hash) {
    const section = document.getElementById(location.hash.slice(1));
    if (section instanceof HTMLDetailsElement) section.open = true;
  }
})();
