/* Login: mostrar u ocultar la contraseña. */
(function () {
  'use strict';
  // Toggle de visibilidad de contraseña
  (function () {
    var toggleBtn = document.getElementById('toggle-pw-btn');
    var pwInput = document.getElementById('pw-login');
    var toggleIcon = document.getElementById('toggle-pw-icon');
    if (toggleBtn && pwInput && toggleIcon) {
      toggleBtn.addEventListener('click', function() {
        if (pwInput.type === 'password') {
          pwInput.type = 'text';
          toggleIcon.classList.remove('bi-eye');
          toggleIcon.classList.add('bi-eye-slash');
          toggleBtn.setAttribute('aria-label', 'Ocultar contraseña');
        } else {
          pwInput.type = 'password';
          toggleIcon.classList.remove('bi-eye-slash');
          toggleIcon.classList.add('bi-eye');
          toggleBtn.setAttribute('aria-label', 'Mostrar contraseña');
        }
      });
    }
  
  })();
})();
