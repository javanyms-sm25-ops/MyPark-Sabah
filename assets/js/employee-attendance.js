(() => {
  document.getElementById('attendance-clear-filters')?.addEventListener('submit', () => {
    document.querySelector('#attendance-report form[data-date-range]').reset();
  });

  const getLocation = () => new Promise((resolve) => {
    let finished = false;
    const finish = (status, coords) => {
      if (finished) return;
      finished = true;
      clearTimeout(timer);
      resolve({ status, coords });
    };
    // The browser's timeout does not include time spent waiting for permission.
    const timer = setTimeout(() => finish('failed'), 12000);
    try {
      if (!navigator.geolocation) {
        finish('failed');
        return;
      }
      navigator.geolocation.getCurrentPosition(
        ({ coords }) => finish('captured', coords),
        ({ code }) => finish(code === 1 ? 'denied' : 'failed'),
        { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 }
      );
    } catch {
      finish('failed');
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

  document.querySelectorAll('[data-attendance-location]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (form.getAttribute('aria-busy') === 'true') return;
      form.setAttribute('aria-busy', 'true');
      form.querySelector('button').disabled = true;
      setStatus(form, form.dataset.locationWorking);
      applyLocation(form, await getLocation());
      form.submit();
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
    let gps;
    let attempt = 0;
    let lastLocationAt = 0;
    const stopCamera = () => {
      camera?.getTracks().forEach((track) => track.stop());
      camera = null;
      video.srcObject = null;
      video.hidden = true;
      take.hidden = true;
    };
    const refreshLocation = () => {
      lastLocationAt = Date.now();
      return getLocation();
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
        // Ask for location only as part of this attendance action.
        gps = refreshLocation();
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
    take.addEventListener('click', async () => {
      if (!video.videoWidth || !video.videoHeight) return;
      const current = attempt;
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
      if ((await gps).status !== 'captured' && current === attempt && !submit.disabled) {
        setStatus(form, form.dataset.photoReady + ' ' + form.dataset.locationWarning);
      }
    });
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form.elements.photo.value || submit.disabled) return;
      form.setAttribute('aria-busy', 'true');
      submit.disabled = true;
      start.disabled = true;
      cancel.disabled = true;
      setStatus(form, form.dataset.submitting);
      const location = Date.now() - lastLocationAt > 60000 ? await refreshLocation() : await gps;
      applyLocation(form, location);
      form.submit();
    });
    window.addEventListener('pagehide', () => {
      attempt++;
      stopCamera();
      start.hidden = false;
      cancel.hidden = true;
      submit.hidden = true;
      preview.hidden = true;
      preview.removeAttribute('src');
      start.disabled = false;
      cancel.disabled = false;
      submit.disabled = false;
      form.elements.photo.value = '';
      form.removeAttribute('aria-busy');
      setStatus(form, '');
    });
  }

  if (location.hash) {
    const section = document.getElementById(location.hash.slice(1));
    if (section instanceof HTMLDetailsElement) section.open = true;
  }
})();
