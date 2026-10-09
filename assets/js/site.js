(function () {
  'use strict';
  const buttons = Array.from(document.querySelectorAll('[data-save-dog]'));
  const panel = document.querySelector('[data-saved-dogs]');
  if (!buttons.length && !panel) return;
  const key = 'perro-saved-dogs:v1';
  const valid = function (slug) { return typeof slug === 'string' && /^[a-z0-9-]{1,512}$/.test(slug); };
  let saved = [], storageOK = true, generation = 0;
  const message = panel ? panel.querySelector('[data-saved-status]') : document.createElement('p');
  if (!panel) { message.className = 'saved-feedback'; message.setAttribute('role', 'status'); buttons[0].after(message); }
  const read = function () {
    try {
      const value = localStorage.getItem(key);
      const parsed = value && value.length <= 53000 ? JSON.parse(value) : [];
      saved = Array.isArray(parsed) ? Array.from(new Set(parsed.filter(valid))).slice(0, 100) : [];
      storageOK = true;
    } catch (_) { saved = []; storageOK = false; }
  };
  const sync = function () {
    buttons.forEach(function (button) {
      button.hidden = false;
      button.setAttribute('aria-pressed', String(saved.includes(button.dataset.saveDog)));
      button.textContent = saved.includes(button.dataset.saveDog) ? 'Quitar de guardados' : 'Guardar aviso';
    });
    if (panel) panel.querySelector('[data-saved-clear]').hidden = !saved.length;
  };
  const persist = function (next) {
    try { localStorage.setItem(key, JSON.stringify(next)); saved = next; storageOK = true; return true; }
    catch (_) { storageOK = false; message.textContent = 'Este navegador no permite guardar la lista. Revisá sus permisos de almacenamiento.'; return false; }
  };
  const refresh = async function () {
    const current = ++generation;
    sync();
    if (panel) {
      const list = panel.querySelector('[data-saved-list]'); list.replaceChildren();
      saved.forEach(function (slug) {
        const row = document.createElement('li'), label = document.createElement('p'), remove = document.createElement('button');
        label.textContent = 'Estado sin confirmar (' + slug + ').';
        remove.type = 'button'; remove.className = 'button button-secondary'; remove.textContent = 'Quitar';
        remove.setAttribute('aria-label', 'Quitar aviso ' + slug + ' de guardados');
        remove.addEventListener('click', function () { if (persist(saved.filter(function (value) { return value !== slug; }))) refresh(); });
        row.append(label, remove); list.append(row);
      });
    }
    if (!storageOK) { message.textContent = 'No pudimos leer los guardados de este navegador. Revisá sus permisos de almacenamiento.'; return; }
    if (!saved.length) { if (panel) message.textContent = 'Todavía no guardaste avisos en este navegador.'; return; }
    if (panel) message.textContent = 'Consultando el estado actual…';
    try {
      const requested = saved.slice(), items = [];
      // Keep even maximum-length saved slugs below web-server request-line limits.
      for (let start = 0; start < requested.length;) {
        if (current !== generation) return;
        const batch = [];
        while (start < requested.length && batch.length < 20) {
          const candidate = batch.concat(requested[start]);
          if (batch.length && ('/guardados/estado?slugs=' + encodeURIComponent(candidate.join(','))).length > 2400) break;
          batch.push(requested[start++]);
        }
        const response = await fetch('/guardados/estado?slugs=' + encodeURIComponent(batch.join(',')), {cache:'no-store', credentials:'omit'});
        if (!response.ok) throw Error('status');
        const payload = await response.json();
        if (current !== generation) return;
        if (!Array.isArray(payload.items) || payload.items.length !== batch.length || batch.some(function (slug) { return !payload.items.some(function (entry) { return entry && entry.slug === slug; }); })) throw Error('items');
        items.push(...payload.items);
      }
      if (!panel) return;
      const list = panel.querySelector('[data-saved-list]');
      list.replaceChildren();
      const types = {adoption:'Adopción', lost:'Perdido', found:'Encontrado'};
      requested.forEach(function (slug) {
        const item = items.find(function (entry) { return entry.slug === slug; });
        if (!item) throw Error('item');
        const row = document.createElement('li');
        const description = document.createElement('p');
        if (item.available === true) {
          const link = document.createElement('a'); link.href = '/perro/' + slug; link.textContent = item.name; description.append(link);
          description.append(document.createTextNode(' · ' + (types[item.type] || 'Aviso') + ' · ' + item.city + (item.status === 'reserved' ? ' · Reservado' : '')));
        } else description.textContent = 'Este aviso ya no está disponible públicamente (' + slug + ').';
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'button button-secondary'; remove.textContent = 'Quitar';
        remove.setAttribute('aria-label', 'Quitar aviso ' + slug + ' de guardados');
        remove.addEventListener('click', function () { if (persist(saved.filter(function (value) { return value !== slug; }))) refresh(); });
        row.append(description, remove); list.append(row);
      });
      message.textContent = saved.length + ' aviso(s) guardado(s). Estado consultado ahora; confirmalo antes de contactar.';
    } catch (_) {
      if (current !== generation) return;
      message.textContent = 'No pudimos confirmar los estados actuales. Tus referencias siguen guardadas; volvé a intentar más tarde.';
    }
  };
  read(); sync();
  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      const slug = button.dataset.saveDog;
      if (!valid(slug)) return;
      const exists = saved.includes(slug);
      if (!exists && saved.length >= 100) { message.textContent = 'Podés guardar hasta 100 avisos. Quitá alguno para agregar otro.'; return; }
      if (persist(exists ? saved.filter(function (value) { return value !== slug; }) : saved.concat(slug))) {
        message.textContent = exists ? 'Aviso quitado de guardados.' : 'Aviso guardado solo en este navegador. No reserva un perro.';
        refresh();
      }
    });
  });
  if (panel) panel.querySelector('[data-saved-clear]').addEventListener('click', function () {
    try { localStorage.removeItem(key); saved = []; storageOK = true; refresh(); }
    catch (_) { message.textContent = 'No pudimos vaciar la lista. Revisá los permisos de almacenamiento del navegador.'; }
  });
  window.addEventListener('storage', function (event) { if (event.key === key || event.key === null) { read(); refresh(); } });
  window.addEventListener('pageshow', function () { read(); refresh(); });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') { read(); refresh(); } });
  refresh();
})();

(function () {
  'use strict';
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('#site-nav');
  if (toggle && nav) {
    document.documentElement.classList.add('nav-enhanced');
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
      ownerForm.querySelectorAll('[name="adoption_requirements"], [name="compatibility"], [name="reason"]').forEach(function (field) {
        field.closest('label').hidden = needed; field.disabled = needed;
      });
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

  document.querySelectorAll('.photo-editor').forEach(function (form) {
    const original = form.querySelector('.photo-editor-original');
    const canvas = form.querySelector('.photo-editor-canvas');
    const ctx = canvas.getContext('2d');
    if (!ctx) return; // The ordinary rotation select and submit remain usable.
    const rotation = form.elements.rotation;
    const names = ['crop_x', 'crop_y', 'crop_width', 'crop_height'];
    const fields = names.map(function (name) { return form.elements[name]; });
    const status = form.querySelector('.photo-editor-status');
    let ready = false, drag = null;
    const clearCrop = function () { fields.forEach(function (field) { field.value = ''; field.setCustomValidity(''); }); };
    const selection = function () {
      if (fields.every(function (field) { return field.value === ''; })) return null;
      if (fields.some(function (field) { return !/^\d+$/.test(field.value); })) return false;
      const values = fields.map(function (field) { return Number(field.value); });
      const [x, y, width, height] = values;
      return values.every(Number.isSafeInteger) && width > 0 && height > 0 && x + width <= canvas.width && y + height <= canvas.height ? {x, y, width, height} : false;
    };
    const draw = function () {
      if (!ready) return;
      const angle = Number(rotation.value);
      canvas.width = angle % 180 ? original.naturalHeight : original.naturalWidth;
      canvas.height = angle % 180 ? original.naturalWidth : original.naturalHeight;
      form.elements.preview_width.value = canvas.width; form.elements.preview_height.value = canvas.height;
      ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.save(); ctx.translate(canvas.width / 2, canvas.height / 2); ctx.rotate(angle * Math.PI / 180);
      ctx.drawImage(original, -original.naturalWidth / 2, -original.naturalHeight / 2); ctx.restore();
      const crop = selection();
      fields.forEach(function (field) { field.setCustomValidity(''); });
      if (crop === false) { fields[0].setCustomValidity('Completá los cuatro campos con un recorte dentro de la foto girada.'); status.textContent = 'Revisá las coordenadas: el recorte tiene que quedar dentro de la foto.'; return; }
      if (crop) {
        ctx.fillStyle = 'rgba(0,0,0,.42)';
        ctx.fillRect(0, 0, canvas.width, crop.y); ctx.fillRect(0, crop.y + crop.height, canvas.width, canvas.height - crop.y - crop.height);
        ctx.fillRect(0, crop.y, crop.x, crop.height); ctx.fillRect(crop.x + crop.width, crop.y, canvas.width - crop.x - crop.width, crop.height);
        ctx.strokeStyle = '#fff'; ctx.lineWidth = Math.max(2, canvas.width / 300); ctx.strokeRect(crop.x, crop.y, crop.width, crop.height);
        status.textContent = 'Recorte: ' + crop.width + ' × ' + crop.height + ' px. Se aplica al guardar la foto.';
      } else status.textContent = 'Foto girada: ' + canvas.width + ' × ' + canvas.height + ' px. Arrastrá para recortar o guardá solo el giro.';
    };
    const start = function () {
      if (!original.naturalWidth || original.naturalWidth * original.naturalHeight > 8000000) return;
      ready = true; original.hidden = true; canvas.hidden = false; form.querySelector('.photo-editor-buttons').hidden = false; draw();
    };
    original.addEventListener('load', start);
    if (original.complete) start();
    rotation.addEventListener('change', function () { clearCrop(); draw(); });
    form.querySelectorAll('[data-photo-turn]').forEach(function (button) { button.addEventListener('click', function () { rotation.value = String((Number(rotation.value) + Number(button.dataset.photoTurn) + 360) % 360); clearCrop(); draw(); }); });
    form.querySelector('[data-photo-reset]').addEventListener('click', function () { clearCrop(); draw(); });
    fields.forEach(function (field) { field.addEventListener('input', draw); });
    const point = function (event) {
      const rect = canvas.getBoundingClientRect();
      return {x:Math.max(0, Math.min(canvas.width, Math.round((event.clientX - rect.left) * canvas.width / rect.width))), y:Math.max(0, Math.min(canvas.height, Math.round((event.clientY - rect.top) * canvas.height / rect.height)))};
    };
    canvas.addEventListener('pointerdown', function (event) {
      if (!ready || !event.isPrimary || event.button !== 0) return;
      drag = {id:event.pointerId, start:point(event), previous:fields.map(function (field) { return field.value; }), clientX:event.clientX, clientY:event.clientY};
      canvas.setPointerCapture(event.pointerId); event.preventDefault();
    });
    canvas.addEventListener('pointermove', function (event) {
      if (!drag || drag.id !== event.pointerId) return;
      const end = point(event), x = Math.min(drag.start.x, end.x), y = Math.min(drag.start.y, end.y);
      [x, y, Math.max(1, Math.abs(drag.start.x - end.x)), Math.max(1, Math.abs(drag.start.y - end.y))].forEach(function (value, i) { fields[i].value = String(value); }); draw();
    });
    const finish = function (event) {
      if (!drag || drag.id !== event.pointerId) return;
      if (event.type === 'pointercancel' || Math.max(Math.abs(event.clientX - drag.clientX), Math.abs(event.clientY - drag.clientY)) < 8) fields.forEach(function (field, i) { field.value = drag.previous[i]; });
      drag = null; draw();
    };
    canvas.addEventListener('pointerup', finish); canvas.addEventListener('pointercancel', finish);
    form.addEventListener('submit', function (event) { if (drag) { event.preventDefault(); return; } draw(); if (!form.reportValidity()) event.preventDefault(); });
  });

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

(function () {
  'use strict';
  document.querySelectorAll('[data-native-share]').forEach(function (button) {
    if (!navigator.share) return;
    button.hidden = false;
    button.addEventListener('click', async function () {
      try { await navigator.share({title: button.dataset.shareTitle, url: button.dataset.nativeShare}); } catch (_) { /* cancellation leaves copy links available */ }
    });
  });
  document.querySelectorAll('form[action="/admin/logout"]').forEach(function (form) {
    form.addEventListener('submit', function () { try { Object.keys(sessionStorage).filter(function (item) { return item.startsWith('perro-admin-draft:'); }).forEach(function (item) { sessionStorage.removeItem(item); }); } catch (_) {} });
  });
  const editor = document.querySelector('[data-admin-draft]');
  if (!editor) return;
  // A form control named "dataset" shadows HTMLFormElement.dataset.
  const key = 'perro-admin-draft:' + editor.getAttribute('data-admin-draft') + ':' + editor.elements.namedItem('dataset').value + ':' + editor.elements.namedItem('id').value;
  if (editor.getAttribute('data-admin-saved') === '1') { try { sessionStorage.removeItem(key); } catch (_) {} }
  const skip = new Set(['csrf_token', 'dataset', 'id', 'review_confirm', 'photo_authorized', 'hide_name', 'hide_whatsapp']);
  const fields = Array.from(editor.elements).filter(function (field) { return field.name && !skip.has(field.name) && !['file', 'password', 'submit', 'button'].includes(field.type); });
  const status = document.createElement('p'); status.className = 'notice'; status.setAttribute('role', 'status');
  status.textContent = 'El texto del borrador se guarda en esta pestaña por hasta 4 horas. Las fotos nuevas deben seleccionarse nuevamente. Cerrá la sesión en equipos compartidos.';
  editor.prepend(status);
  let draft;
  try { draft = JSON.parse(sessionStorage.getItem(key)); } catch (_) {}
  if (draft && Date.now() - draft.at > 4 * 3600000) { sessionStorage.removeItem(key); draft = null; }
  if (draft) {
    const restore = document.createElement('button'); restore.type = 'button'; restore.className = 'button button-secondary'; restore.textContent = 'Recuperar borrador de esta pestaña';
    const discard = document.createElement('button'); discard.type = 'button'; discard.className = 'button button-secondary'; discard.textContent = 'Descartar borrador';
    status.append(document.createElement('br'), restore, discard);
    restore.addEventListener('click', function () {
      fields.forEach(function (field, i) { if (!draft.values[i] || draft.values[i].name !== field.name) return; if (['checkbox', 'radio'].includes(field.type)) field.checked = draft.values[i].checked; else field.value = draft.values[i].value; });
      status.textContent = 'Borrador recuperado. Si la ficha cambió, el servidor pedirá que recargues y revises sus datos. Seleccioná las fotos nuevas nuevamente.';
    });
    discard.addEventListener('click', function () { sessionStorage.removeItem(key); status.textContent = 'Borrador descartado. Se muestran los datos actuales.'; });
  }
  editor.addEventListener('input', function () {
    try { sessionStorage.setItem(key, JSON.stringify({at: Date.now(), values: fields.map(function (field) { return {name: field.name, value: field.value, checked: field.checked}; })})); } catch (_) { status.textContent = 'Este navegador no pudo guardar el borrador. Guardá los cambios antes de salir.'; }
  });
})();
