/* SENA - Lógica UI compartida */
(function () {
  'use strict';

  // ===== Dark mode =====
  const root = document.documentElement;
  const savedTheme = localStorage.getItem('sena-theme');
  if (savedTheme === 'dark') root.setAttribute('data-theme', 'dark');

  window.toggleTheme = function () {
    const isDark = root.getAttribute('data-theme') === 'dark';
    if (isDark) {
      root.removeAttribute('data-theme');
      localStorage.setItem('sena-theme', 'light');
    } else {
      root.setAttribute('data-theme', 'dark');
      localStorage.setItem('sena-theme', 'dark');
    }
    updateThemeIcons();
  };

  function updateThemeIcons() {
    const isDark = root.getAttribute('data-theme') === 'dark';
    document.querySelectorAll('[data-theme-icon]').forEach(el => {
      el.className = isDark ? 'bi bi-sun' : 'bi bi-moon-stars';
    });
  }

  // ===== Sidebar collapse =====
  window.toggleSidebar = function () {
    const shell = document.querySelector('.app-shell');
    if (!shell) return;
    shell.classList.toggle('collapsed');
    localStorage.setItem('sena-sidebar', shell.classList.contains('collapsed') ? '1' : '0');
  };

  document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('sena-sidebar') === '1') {
      document.querySelector('.app-shell')?.classList.add('collapsed');
    }
    updateThemeIcons();

    // Posicionamiento dinámico de tooltips del sidebar para evitar clipping al hacer scroll
    document.querySelectorAll('.sidebar-link').forEach(link => {
      const span = link.querySelector('span');
      if (!span) return;
      link.addEventListener('mouseenter', () => {
        const rect = link.getBoundingClientRect();
        span.style.top = rect.top + 'px';
      });
    });

    // Password toggle
    document.querySelectorAll('[data-pw-toggle]').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = document.querySelector(btn.getAttribute('data-pw-toggle'));
        if (!target) return;
        const isPw = target.type === 'password';
        target.type = isPw ? 'text' : 'password';
        btn.querySelector('i').className = isPw ? 'bi bi-eye-slash' : 'bi bi-eye';
      });
    });

    // Textarea char counter
    document.querySelectorAll('textarea[data-counter]').forEach(ta => {
      const max = parseInt(ta.getAttribute('maxlength') || '2000', 10);
      const out = document.querySelector(ta.getAttribute('data-counter'));
      const update = () => { if (out) out.textContent = `${ta.value.length} / ${max}`; };
      ta.addEventListener('input', update); update();
    });

    // Concept toggle (eval)
    document.querySelectorAll('.concept-toggle').forEach(group => {
      group.addEventListener('click', e => {
        const btn = e.target.closest('.concept-btn');
        if (!btn) return;
        group.querySelectorAll('.concept-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const planSection = document.getElementById('plan-mejora');
        if (planSection) planSection.style.display = btn.classList.contains('D') ? 'block' : 'none';
      });
    });

    // Dropzone visual feedback
    document.querySelectorAll('.dropzone').forEach(dz => {
      ['dragover', 'dragenter'].forEach(ev =>
        dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('drag'); })
      );
      ['dragleave', 'drop'].forEach(ev =>
        dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('drag'); })
      );
    });

    // Password strength + requirements
    document.querySelectorAll('[data-pw-strength]').forEach(input => {
      input.addEventListener('input', () => {
        const v = input.value;
        const reqs = {
          len: v.length >= 8,
          letter: /[a-zA-Z]/.test(v),
          num: /\d/.test(v),
          upper: /[A-Z]/.test(v),
        };
        document.querySelectorAll('[data-req]').forEach(r => {
          const ok = reqs[r.getAttribute('data-req')];
          r.classList.toggle('ok', ok);
          r.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
        });
        const score = Object.values(reqs).filter(Boolean).length;
        const meter = document.querySelector('.pw-strength');
        if (meter) {
          meter.className = 'pw-strength s' + score;
        }
      });
    });

    // Accordion phases
    document.querySelectorAll('.acc-header').forEach(h => {
      h.addEventListener('click', () => {
        const body = h.nextElementSibling;
        if (!body) return;
        const open = body.style.display !== 'none';
        body.style.display = open ? 'none' : 'block';
        const chev = h.querySelector('[data-chev]');
        if (chev) chev.className = open ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
      });
    });

    // Dynamic role fields (user form)
    const roleSel = document.getElementById('user-role');
    if (roleSel) {
      const update = () => {
        document.getElementById('field-ficha').style.display = roleSel.value === 'aprendiz' ? 'block' : 'none';
        document.getElementById('field-comp').style.display = roleSel.value === 'instructor' ? 'block' : 'none';
      };
      roleSel.addEventListener('change', update); update();
    }
  });
})();
