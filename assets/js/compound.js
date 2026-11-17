(function () {
  'use strict';
  const translate = window.MyParkLocale?.text ?? (message => message);


  const form = document.getElementById('compound-lookup');
  const input = document.getElementById('compound-query');
  const button = document.getElementById('compound-scan');
  const dialog = document.getElementById('compound-camera');
  const video = dialog.querySelector('video');
  const status = document.getElementById('compound-scan-status');
  const cameraStatus = document.getElementById('compound-camera-status');
  let scan = null;

  function stop() {
    if (!scan) return;
    clearTimeout(scan.timer);
    scan.stream?.getTracks().forEach((track) => track.stop());
    video.srcObject = null;
    scan = null;
  }

  dialog.addEventListener('close', stop);
  window.addEventListener('pagehide', stop);

  async function detect(active, detector) {
    try {
      const codes = video.readyState >= 2 ? await detector.detect(video) : [];
      if (scan !== active || !dialog.open) return;

      const match = codes.find((code) => /^[a-z0-9][a-z0-9 -]{0,23}$/i.test(code.rawValue.trim()));
      if (match) {
        input.value = match.rawValue.trim();
        stop();
        dialog.close();
        form.requestSubmit();
        return;
      }

      if (codes.length) cameraStatus.textContent = 'That barcode does not contain a compound number. Try again or enter the number manually.';
      active.timer = setTimeout(() => detect(active, detector), 200);
    } catch (_) {
      if (scan !== active) return;
      stop();
      cameraStatus.textContent = 'Could not read the barcode. Close the camera and enter the number manually.';
    }
  }

  button.hidden = false;
  button.addEventListener('click', async function () {
    if (typeof window.BarcodeDetector !== 'function' || !navigator.mediaDevices?.getUserMedia || !dialog.showModal) {
      status.textContent = 'Camera scanning is unavailable here. Enter the compound number or plate above.';
      input.focus();
      return;
    }

    const active = {};
    stop();
    scan = active;
    status.textContent = '';
    cameraStatus.textContent = translate('Requesting camera access…');
    dialog.showModal();

    try {
      const formats = await window.BarcodeDetector.getSupportedFormats();
      if (scan !== active || !dialog.open) return;
      if (!formats.length) throw new Error('No barcode formats supported');
      const detector = new window.BarcodeDetector({ formats });
      const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
      if (scan !== active || !dialog.open) {
        stream.getTracks().forEach((track) => track.stop());
        return;
      }
      active.stream = stream;
      video.srcObject = stream;
      await video.play();
      if (scan !== active || !dialog.open) return;
      cameraStatus.textContent = 'Hold the barcode inside the camera view.';
      detect(active, detector);
    } catch (_) {
      if (scan !== active) return;
      stop();
      cameraStatus.textContent = 'Camera scanning is unavailable. Close the camera and enter the number manually.';
    }
  });
}());
