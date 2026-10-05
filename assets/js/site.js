(function () {
  'use strict';
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('#site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      const open = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', String(!open));
      nav.classList.toggle('open', !open);
    });
  }

  const fileInput = document.querySelector('input[type="file"][multiple]');
  if (fileInput) {
    fileInput.addEventListener('change', function () {
      if (fileInput.files.length > 5) {
        fileInput.value = '';
        window.alert('Podés subir hasta cinco fotos.');
      }
    });
  }
})();

