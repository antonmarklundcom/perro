(function () {
  'use strict';
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('#site-nav');
  if (toggle && nav) {
    const setOpen = function (open) {
      toggle.setAttribute('aria-expanded', String(open));
      toggle.querySelector('.sr-only').textContent = open ? 'Cerrar menú' : 'Abrir menú';
      nav.classList.toggle('open', open);
    };
    toggle.addEventListener('click', function () { setOpen(toggle.getAttribute('aria-expanded') !== 'true'); });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') { setOpen(false); toggle.focus(); }
    });
    document.addEventListener('click', function (event) {
      if (!nav.contains(event.target) && !toggle.contains(event.target)) setOpen(false);
    });
    nav.addEventListener('click', function (event) { if (event.target.closest('a')) setOpen(false); });
    window.matchMedia('(min-width: 901px)').addEventListener('change', function () { setOpen(false); });
  }

  const ownerForm = document.querySelector('form[action="/enviar-perro"]');
  const filterDisclosure = document.querySelector('.filter-disclosure');
  if (filterDisclosure && window.matchMedia('(max-width: 760px)').matches) filterDisclosure.open = false;
  if (ownerForm) {
    const type = ownerForm.querySelector('[name="listing_type"]');
    const incident = ownerForm.querySelector('.incident-fields');
    const alias = ownerForm.querySelector('[name="public_display_name"]');
    const syncType = function () {
      if (!incident) return;
      const needed = type.value === 'lost' || type.value === 'found';
      incident.hidden = !needed;
      incident.querySelectorAll('input').forEach(function (field) { field.disabled = !needed; field.required = needed; });
    };
    const syncAlias = function () {
      const publicName = ownerForm.querySelector('[name="name_visibility"]:checked').value === 'public';
      alias.closest('label').hidden = !publicName;
      alias.disabled = !publicName;
      alias.required = publicName;
    };
    type.addEventListener('change', syncType);
    ownerForm.querySelectorAll('[name="name_visibility"]').forEach(function (field) { field.addEventListener('change', syncAlias); });
    syncType(); syncAlias();
    const submit = ownerForm.querySelector('button[type="submit"]');
    const steps = Array.from(ownerForm.children).filter(function (node) { return node.tagName === 'FIELDSET'; });
    const progress = document.createElement('div'); progress.className = 'form-progress';
    const status = document.createElement('p'); status.setAttribute('role', 'status');
    const full = document.createElement('button'); full.type = 'button'; full.className = 'text-button'; full.textContent = 'Ver formulario completo';
    progress.append(status, full); steps[0].before(progress);
    const controls = document.createElement('div'); controls.className = 'wizard-controls';
    const back = document.createElement('button'); back.type = 'button'; back.className = 'button button-secondary'; back.textContent = '← Atrás';
    const next = document.createElement('button'); next.type = 'button'; next.className = 'button'; next.textContent = 'Continuar →';
    submit.before(controls); controls.append(back, next, submit);
    const summary = document.createElement('div'); summary.className = 'notice submission-summary'; steps.at(-1).append(summary);
    let current = 0, expanded = ownerForm.dataset.retry === '1';
    ownerForm.noValidate = true;
    const show = function (index, focus) {
      current = index;
      steps.forEach(function (step, i) { step.hidden = !expanded && i !== index; });
      status.textContent = expanded ? 'Formulario completo · 4 secciones' : 'Paso ' + (index + 1) + ' de 4 · ' + steps[index].querySelector('legend').textContent.replace(/^\d\.\s*/, '');
      full.textContent = expanded ? 'Ver paso a paso' : 'Ver formulario completo';
      back.hidden = expanded || index === 0; next.hidden = expanded || index === steps.length - 1;
      submit.hidden = !expanded && index !== steps.length - 1;
      const field = function (name) { return ownerForm.elements.namedItem(name).value; };
      summary.textContent = 'Se enviará para revisión: ' + (field('name') || 'Nombre pendiente') + ' · ' + (field('city') || 'Ciudad pendiente') + '. Tu nombre ' + (field('name_visibility') === 'public' ? 'se mostrará con el alias que elegiste' : 'permanece privado') + ' y tu WhatsApp ' + (ownerForm.elements.public_whatsapp.checked ? 'se mostrará con tu autorización' : 'permanece privado') + '. No se publica automáticamente.';
      if (focus) { const legend = steps[index].querySelector('legend'); legend.tabIndex = -1; legend.focus(); legend.scrollIntoView({block:'start'}); }
    };
    const reveal = function (field) {
      const index = steps.findIndex(function (step) { return step.contains(field); });
      if (index !== -1) show(index, false);
      let ancestor = field.parentElement;
      while (ancestor && ancestor !== ownerForm) { if (ancestor.tagName === 'DETAILS') ancestor.open = true; ancestor = ancestor.parentElement; }
    };
    const validSteps = function (through) {
      for (const field of Array.from(ownerForm.elements)) {
        const index = steps.findIndex(function (step) { return step.contains(field); });
        if (index < 0 || index > through || field.disabled || !field.willValidate) continue;
        if (!field.checkValidity()) { reveal(field); field.reportValidity(); return false; }
      }
      return true;
    };
    next.addEventListener('click', function () { if (validSteps(current)) show(Math.min(current + 1, steps.length - 1), true); });
    back.addEventListener('click', function () { show(Math.max(0, current - 1), true); });
    full.addEventListener('click', function () { expanded = !expanded; show(current, false); });
    document.querySelectorAll('.form-jump a').forEach(function (link, index) {
      link.addEventListener('click', function (event) { event.preventDefault(); if (index <= current || validSteps(index - 1)) show(index, true); });
    });
    const description = ownerForm.elements.description;
    const counter = document.createElement('span'); counter.className = 'field-help'; counter.id = 'description-help';
    description.setAttribute('aria-describedby', counter.id); description.closest('label').after(counter);
    const count = function () { const length = Array.from(description.value).length; counter.textContent = length + '/3000 caracteres · mínimo 40. Contá lo que sabés; podés aclarar lo que no se sabe.'; };
    description.addEventListener('input', count); count(); show(0, false);
    ownerForm.addEventListener('input', function () { if (current === steps.length - 1 || expanded) show(current, false); });
    ownerForm.addEventListener('submit', function (event) {
      if (ownerForm.dataset.photoBusy === '1') { event.preventDefault(); return; }
      // Pressing Enter in an earlier section advances instead of submitting an incomplete notice.
      if (!expanded && current !== steps.length - 1) { event.preventDefault(); if (validSteps(current)) show(Math.min(current + 1, steps.length - 1), true); return; }
      if (!validSteps(steps.length - 1)) { event.preventDefault(); return; }
      if (!event.defaultPrevented) { submit.disabled = true; submit.textContent = 'Enviando ficha…'; }
    });
    window.addEventListener('pageshow', function () { submit.disabled = false; submit.textContent = 'Enviar ficha para revisión'; });
  }

  document.querySelectorAll('input[type="file"][multiple]').forEach(function (input) {
    const message = document.createElement('span');
    message.className = 'field-help'; message.setAttribute('aria-live', 'polite');
    const id = 'photo-help-' + Math.random().toString(36).slice(2);
    message.id = id; input.setAttribute('aria-describedby', id);
    const previews = document.createElement('div'); previews.className = 'photo-preview';
    input.closest('label').after(message, previews);
    let urls = [], generation = 0;
    const optimize = async function (file) {
      if (!window.createImageBitmap || !window.DataTransfer || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 20 * 1024 * 1024) return file;
      const bitmap = await createImageBitmap(file);
      try {
        if (bitmap.width <= 1800 && bitmap.height <= 1800 && file.size <= 5 * 1024 * 1024) return file;
        const scale = Math.min(1, 1800 / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas'); canvas.width = Math.max(1, Math.round(bitmap.width * scale)); canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        const ctx = canvas.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height); ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise(function (resolve) { canvas.toBlob(resolve, 'image/jpeg', .86); });
        if (!blob) return file;
        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', {type:'image/jpeg', lastModified:file.lastModified});
      } finally { bitmap.close(); }
    };
    const update = async function () {
      const ticket = ++generation;
      urls.forEach(function (url) { URL.revokeObjectURL(url); }); urls = [];
      previews.replaceChildren();
      let files = Array.from(input.files);
      const kept = input.form.querySelectorAll('[name="keep_photos[]"]:checked').length;
      input.form.dataset.photoBusy = files.length ? '1' : '0';
      input.setCustomValidity(files.length ? 'Esperá mientras preparamos las fotos.' : '');
      message.textContent = files.length ? 'Preparando fotos para el envío…' : 'Todavía no elegiste fotos.';
      if (files.length + kept <= 5) {
        // Process sequentially to limit memory on mobile; keep server-side validation authoritative.
        const optimized = [];
        for (const file of files) { try { optimized.push(await optimize(file)); } catch (_) { optimized.push(file); } }
        if (ticket !== generation) return;
        if (window.DataTransfer) { try { const transfer = new DataTransfer(); optimized.forEach(function (file) { transfer.items.add(file); }); input.files = transfer.files; files = Array.from(input.files); } catch (_) { /* Original selection remains available. */ } }
      }
      if (ticket !== generation) return;
      input.form.dataset.photoBusy = '0';
      const error = files.length + kept > 5 ? 'Podés tener hasta cinco fotos en total. Volvé a elegir menos fotos o quitá alguna existente.' : files.some(function (file) { return file.size > 5 * 1024 * 1024; }) ? 'Cada foto debe pesar como máximo 5 MB después de prepararla. Elegí fotos más pequeñas.' : files.some(function (file) { return !['image/jpeg', 'image/png', 'image/webp'].includes(file.type); }) ? 'Elegí fotos JPG, PNG o WebP. Si tu teléfono usa HEIC, exportá la foto como JPG.' : '';
      input.setCustomValidity(error);
      message.textContent = error || (files.length ? files.length + ' foto(s) seleccionada(s). Se enviarán al guardar la ficha.' : 'Todavía no elegiste fotos.');
      message.classList.toggle('field-error', !!error);
      if (error) return;
      files.forEach(function (file, index) {
        const figure = document.createElement('figure');
        const img = document.createElement('img');
        const caption = document.createElement('figcaption');
        const url = URL.createObjectURL(file); urls.push(url);
        img.src = url; img.alt = 'Vista previa de la foto ' + (index + 1);
        caption.textContent = 'Foto ' + (index + 1);
        figure.append(img, caption); previews.append(figure);
        if (window.DataTransfer) { const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'text-button'; remove.textContent = 'Quitar foto ' + (index + 1); remove.addEventListener('click', function () { const transfer = new DataTransfer(); Array.from(input.files).forEach(function (file, i) { if (i !== index) transfer.items.add(file); }); input.files = transfer.files; update(); }); figure.append(remove); }
      });
    };
    input.addEventListener('change', update);
    input.form.querySelectorAll('[name="keep_photos[]"]').forEach(function (field) { field.addEventListener('change', update); });
    window.addEventListener('pageshow', update);
    window.addEventListener('pagehide', function () { urls.forEach(function (url) { URL.revokeObjectURL(url); }); urls = []; });
  });

  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', async function () {
      const status = button.parentElement.querySelector('.copy-status');
      try { await navigator.clipboard.writeText(button.dataset.copy); if (status) status.textContent = 'Copiado.'; }
      catch (_) { if (status) status.textContent = 'Copiá este texto: ' + button.dataset.copy; }
    });
  });
})();
