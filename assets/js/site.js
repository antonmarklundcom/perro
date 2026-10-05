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

  const ownerForm = document.querySelector('.submission-form');
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
    ownerForm.addEventListener('submit', function (event) {
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
    let urls = [];
    const update = function () {
      urls.forEach(function (url) { URL.revokeObjectURL(url); }); urls = [];
      previews.replaceChildren();
      const files = Array.from(input.files);
      const kept = input.form.querySelectorAll('[name="keep_photos[]"]:checked').length;
      const error = files.length + kept > 5 ? 'Podés tener hasta cinco fotos en total. Volvé a elegir menos fotos o quitá alguna existente.' : files.some(function (file) { return file.size > 5 * 1024 * 1024; }) ? 'Cada foto debe pesar como máximo 5 MB. Volvé a elegir las fotos.' : files.some(function (file) { return !['image/jpeg', 'image/png', 'image/webp'].includes(file.type); }) ? 'Elegí únicamente fotos JPG, PNG o WebP.' : '';
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
      });
    };
    input.addEventListener('change', update);
    input.form.querySelectorAll('[name="keep_photos[]"]').forEach(function (field) { field.addEventListener('change', update); });
    window.addEventListener('pageshow', update);
    window.addEventListener('pagehide', function () { urls.forEach(function (url) { URL.revokeObjectURL(url); }); urls = []; });
  });
})();
