/* Recuperación de contraseña: mostrar/ocultar, fortaleza y confirmación. */
// Toggle Password Visibility
document.querySelectorAll('[data-pw-toggle]').forEach(btn => {
  btn.addEventListener('click', () => {
    const inp = document.querySelector(btn.dataset.pwToggle);
    const icon = btn.querySelector('i');
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('bi-eye', inp.type === 'password');
    icon.classList.toggle('bi-eye-slash', inp.type !== 'password');
  });
});

// Password Strength Checker
const pwInput = document.getElementById('pw-new');
const pwBar = document.getElementById('pw-bar');
const pwConf = document.getElementById('pw-confirm');

if (pwInput) {
  pwInput.addEventListener('input', () => {
    const v = pwInput.value;
    const r = {
      len: v.length >= 8 && new TextEncoder().encode(v).length <= 72,
      letter: /[A-Za-z]/.test(v),
      num: /[0-9]/.test(v),
      upper: /[A-Z]/.test(v)
    };

    document.querySelectorAll('.pw-req').forEach(el => {
      const ok = r[el.dataset.req];
      el.classList.toggle('ok', ok);
      el.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
    });

    if (pwBar) {
      const score = Object.values(r).filter(Boolean).length;
      pwBar.className = 'pw-strength' + (score ? ` s${score}` : '');
    }
  });
}

if (pwConf && pwInput) {
  pwConf.addEventListener('input', () => {
    const mismatch = pwConf.value && pwConf.value !== pwInput.value;
    pwConf.style.borderColor = mismatch ? 'rgba(239, 68, 68, 0.6)' : '';
    pwConf.style.boxShadow = mismatch ? '0 0 0 3px rgba(239, 68, 68, 0.12)' : '';
  });
}

// Button loading state spinners
['recover-form', 'reset-form'].forEach(id => {
  document.getElementById(id)?.addEventListener('submit', function() {
    const btn = this.querySelector('[type="submit"]');
    if (btn && !btn.disabled) {
      btn.textContent = 'Procesando...';
      btn.classList.add('procesando');
      btn.disabled = true;
    }
  });
});
