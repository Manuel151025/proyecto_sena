/* SENA - Lógica UI compartida */
(function () {
  'use strict';

  // ===== Dark mode =====
  // data-bs-theme va en paralelo a data-theme para que los componentes de
  // Bootstrap sigan el mismo modo (el header lo aplica antes del primer
  // pintado; aquí solo se mantiene sincronizado al alternar).
  const root = document.documentElement;
  const savedTheme = localStorage.getItem('sena-theme');
  if (savedTheme === 'dark') {
    root.setAttribute('data-theme', 'dark');
    root.setAttribute('data-bs-theme', 'dark');
  }

  window.toggleTheme = function () {
    const isDark = root.getAttribute('data-theme') === 'dark';
    if (isDark) {
      root.removeAttribute('data-theme');
      root.removeAttribute('data-bs-theme');
      localStorage.setItem('sena-theme', 'light');
    } else {
      root.setAttribute('data-theme', 'dark');
      root.setAttribute('data-bs-theme', 'dark');
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

  // ===== Sidebar =====
  // Escritorio y móvil son dos comportamientos distintos y NO comparten
  // estado:
  //   · Escritorio: rail estrecho con expansión por :hover. La clase
  //     .collapsed se conserva (con su valor en localStorage) para el
  //     colapso manual.
  //   · Móvil: panel deslizable, cerrado en cada carga. Usa .sidebar-open
  //     y NO se persiste; si se persistiera, al recargar el menú aparecería
  //     abierto tapando el contenido (que era justo el fallo).
  // La media query debe coincidir con la de theme.css (@media max-width:768px).
  const mobileQuery = window.matchMedia('(max-width: 768px)');
  const isMobile = () => mobileQuery.matches;
  const getShell = () => document.querySelector('.app-shell');

  window.toggleSidebar = function () {
    const shell = getShell();
    if (!shell) return;

    if (isMobile()) {
      shell.classList.toggle('sidebar-open');
      return; // estado transitorio: no se guarda
    }

    shell.classList.toggle('collapsed');
    localStorage.setItem('sena-sidebar', shell.classList.contains('collapsed') ? '1' : '0');
  };

  window.closeSidebar = function () {
    getShell()?.classList.remove('sidebar-open');
  };

  document.addEventListener('DOMContentLoaded', () => {
    const shell = getShell();

    // El estado guardado solo aplica en escritorio. En móvil se arranca
    // siempre cerrado, sin importar lo que hubiera en localStorage.
    if (shell && !isMobile() && localStorage.getItem('sena-sidebar') === '1') {
      shell.classList.add('collapsed');
    }

    if (shell) {
      // Tocar el velo cierra el menú
      document.querySelector('.sidebar-overlay')
        ?.addEventListener('click', window.closeSidebar);

      // Al elegir una opción del menú, cerrar antes de navegar para que no
      // quede el panel encima durante la carga de la página siguiente.
      // Solo enlaces reales: los grupos con submenú son <div class="sidebar-link">
      // y en móvil se muestran desplegados, así que no deben cerrar nada.
      document.querySelector('.sidebar')?.addEventListener('click', (e) => {
        if (isMobile() && e.target.closest('a[href]')) {
          window.closeSidebar();
        }
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') window.closeSidebar();
      });

      // Al pasar a escritorio, descartar el estado de móvil para que no
      // quede colgando si luego se vuelve a reducir la ventana.
      const handleBreakpointChange = () => {
        if (!isMobile()) window.closeSidebar();
      };
      if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', handleBreakpointChange);
      } else if (typeof mobileQuery.addListener === 'function') {
        mobileQuery.addListener(handleBreakpointChange); // Safari antiguo
      }
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
