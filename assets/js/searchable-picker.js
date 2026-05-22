/* ===================================================================
 * SEARCHABLE PICKER
 * ===================================================================
 * Convierte cualquier <select> con [data-picker] en un selector
 * con modal de búsqueda en vivo.
 *
 * Uso:
 *   <select name="aprendiz_id" data-picker
 *           data-picker-label="Seleccionar aprendiz"
 *           data-picker-placeholder="Buscar por nombre, documento o ficha...">
 *     <option value="">Selecciona...</option>
 *     <option value="42" data-search="123456 2691234">Juan Pérez — CC 123456 — Ficha #2691234</option>
 *   </select>
 *
 * El atributo `data-search` de cada <option> contiene términos extra
 * (que NO necesariamente están en el texto visible) para buscar.
 *
 * El <select> original queda oculto pero funcional: cuando el usuario
 * elige una opción, su `value` se actualiza y dispara un evento `change`,
 * por lo que los formularios y validaciones existentes siguen funcionando
 * sin modificación alguna del backend.
 * =================================================================== */

(function () {
    'use strict';

    // ----- Helpers -----

    function normalize(s) {
        return (s || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, ''); // remueve acentos
    }

    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function highlight(text, terms) {
        if (!terms.length) return escapeHtml(text);
        const escaped = escapeHtml(text);
        const norm = normalize(text);
        // Reconstruir las posiciones de los matches en el texto original
        // (versión simple: solo el primer término, suficiente para UX)
        const term = terms[0];
        const idx = norm.indexOf(term);
        if (idx === -1) return escaped;
        // Mapear posición normalizada → posición visible
        // (asumimos longitud similar; para acentos puede desviar 1-2 chars pero no rompe el render)
        const before = escaped.slice(0, idx);
        const match  = escaped.slice(idx, idx + term.length);
        const after  = escaped.slice(idx + term.length);
        return before + '<mark>' + match + '</mark>' + after;
    }

    // ----- Construcción del trigger y modal -----

    function buildTrigger(select) {
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'sp-trigger is-empty';
        trigger.setAttribute('aria-haspopup', 'dialog');

        const text = document.createElement('span');
        text.className = 'sp-trigger-text';
        text.textContent = select.dataset.pickerPlaceholder || 'Seleccionar...';

        const icons = document.createElement('span');
        icons.className = 'sp-trigger-icons';

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'sp-trigger-clear';
        clearBtn.innerHTML = '<i class="bi bi-x-circle-fill"></i>';
        clearBtn.title = 'Limpiar selección';
        clearBtn.setAttribute('aria-label', 'Limpiar selección');

        const arrow = document.createElement('span');
        arrow.className = 'sp-trigger-arrow';
        arrow.innerHTML = '<i class="bi bi-chevron-down"></i>';

        icons.appendChild(clearBtn);
        icons.appendChild(arrow);

        trigger.appendChild(text);
        trigger.appendChild(icons);

        return { trigger, text, clearBtn };
    }

    function buildModal(select) {
        const modal = document.createElement('div');
        modal.className = 'sp-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');

        const label = select.dataset.pickerLabel
            || (select.previousElementSibling && select.previousElementSibling.tagName === 'LABEL'
                ? select.previousElementSibling.textContent.trim()
                : 'Seleccionar');

        const placeholder = select.dataset.pickerPlaceholder || 'Buscar...';

        modal.innerHTML = `
            <div class="sp-dialog" role="document">
                <div class="sp-header">
                    <h6>${escapeHtml(label)}</h6>
                    <button type="button" class="sp-close" aria-label="Cerrar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="sp-search-wrap">
                    <i class="bi bi-search sp-search-icon"></i>
                    <input type="text" class="sp-search-input" placeholder="${escapeHtml(placeholder)}" autocomplete="off">
                </div>
                <div class="sp-list" role="listbox"></div>
                <div class="sp-footer">
                    <span><kbd>↑</kbd> <kbd>↓</kbd> navegar · <kbd>Enter</kbd> elegir · <kbd>Esc</kbd> cerrar</span>
                    <span class="sp-count"></span>
                </div>
            </div>
        `;

        return modal;
    }

    // ----- Lógica principal -----

    function enhance(select) {
        if (select.dataset.spInitialized === '1') return;
        select.dataset.spInitialized = '1';

        // Ocultar el select original (manteniéndolo en el DOM para que el form lo envíe)
        select.style.display = 'none';

        // Crear UI
        const { trigger, text, clearBtn } = buildTrigger(select);
        select.parentNode.insertBefore(trigger, select.nextSibling);

        const modal = buildModal(select);
        document.body.appendChild(modal);

        const searchInput = modal.querySelector('.sp-search-input');
        const list        = modal.querySelector('.sp-list');
        const countEl     = modal.querySelector('.sp-count');
        const closeBtn    = modal.querySelector('.sp-close');

        // Extraer opciones del select (incluyendo data-search opcional)
        function getOptions() {
            return Array.from(select.options).map(opt => ({
                value: opt.value,
                label: opt.textContent.trim(),
                searchExtra: opt.dataset.search || '',
                disabled: opt.disabled,
                isPlaceholder: opt.value === '' || opt.value === null,
            }));
        }

        // Sincronizar el texto del trigger con la opción seleccionada
        function syncTriggerLabel() {
            const selectedOpt = select.options[select.selectedIndex];
            if (selectedOpt && selectedOpt.value !== '') {
                text.textContent = selectedOpt.textContent.trim();
                trigger.classList.remove('is-empty');
                trigger.classList.add('has-value');
            } else {
                text.textContent = select.dataset.pickerPlaceholder || 'Seleccionar...';
                trigger.classList.add('is-empty');
                trigger.classList.remove('has-value');
            }
        }

        // Renderizar la lista de resultados
        let focusedIndex = -1;
        let currentResults = [];

        function render(query) {
            const q = normalize(query);
            const terms = q ? [q] : [];
            const options = getOptions();

            currentResults = options.filter(opt => {
                if (opt.isPlaceholder) return false;
                if (!q) return true;
                const hay = normalize(opt.label + ' ' + opt.searchExtra);
                return hay.includes(q);
            });

            list.innerHTML = '';
            focusedIndex = -1;

            if (currentResults.length === 0) {
                list.innerHTML = `
                    <div class="sp-empty">
                        <i class="bi bi-search"></i>
                        ${q ? 'Sin resultados para "' + escapeHtml(query) + '"' : 'No hay opciones disponibles'}
                    </div>
                `;
                countEl.textContent = '';
                return;
            }

            const frag = document.createDocumentFragment();
            currentResults.forEach((opt, i) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'sp-item';
                item.dataset.value = opt.value;
                item.setAttribute('role', 'option');
                if (opt.value === select.value) {
                    item.classList.add('is-selected');
                }

                const main = document.createElement('div');
                main.className = 'sp-item-main';

                // Si el label tiene " — " lo dividimos en título y meta
                const parts = opt.label.split(' — ');
                const title = parts[0];
                const meta  = parts.slice(1).join(' — ');

                const titleEl = document.createElement('div');
                titleEl.className = 'sp-item-title';
                titleEl.innerHTML = highlight(title, terms);
                main.appendChild(titleEl);

                if (meta) {
                    const metaEl = document.createElement('div');
                    metaEl.className = 'sp-item-meta';
                    metaEl.innerHTML = highlight(meta, terms);
                    main.appendChild(metaEl);
                }

                item.appendChild(main);
                frag.appendChild(item);
            });
            list.appendChild(frag);

            countEl.textContent = currentResults.length + (currentResults.length === 1 ? ' resultado' : ' resultados');
        }

        function focusItem(index) {
            const items = list.querySelectorAll('.sp-item');
            items.forEach(it => it.classList.remove('is-focused'));
            if (index >= 0 && index < items.length) {
                items[index].classList.add('is-focused');
                items[index].scrollIntoView({ block: 'nearest' });
                focusedIndex = index;
            }
        }

        function chooseValue(value) {
            select.value = value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncTriggerLabel();
            close();
        }

        function open() {
            // Si estamos dentro de un modal de Bootstrap, mover el picker
            // dentro de ese modal antes de abrirlo. Bootstrap implementa un
            // "focus trap" que captura cualquier elemento fuera del modal
            // activo, lo cual impedía escribir en el input de búsqueda.
            const bsModal = select.closest('.modal');
            if (bsModal && modal.parentNode !== bsModal) {
                bsModal.appendChild(modal);
            } else if (!bsModal && modal.parentNode !== document.body) {
                document.body.appendChild(modal);
            }

            modal.classList.add('is-open');
            trigger.classList.add('is-open');
            searchInput.value = '';
            render('');
            // foco después del paint para que la animación no lo robe
            setTimeout(() => searchInput.focus(), 50);
            // Solo bloqueamos overflow si el picker está en <body>;
            // si está dentro de un modal de Bootstrap, ese ya maneja overflow.
            if (!bsModal) {
                document.body.style.overflow = 'hidden';
            }
        }

        function close() {
            modal.classList.remove('is-open');
            trigger.classList.remove('is-open');
            // Solo restauramos overflow si nosotros lo habíamos cambiado
            if (modal.parentNode === document.body) {
                document.body.style.overflow = '';
            }
            trigger.focus();
        }

        // ----- Eventos -----

        trigger.addEventListener('click', (e) => {
            // Si hicieron click en el botón de limpiar, no abrir
            if (e.target.closest('.sp-trigger-clear')) return;
            open();
        });

        clearBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            chooseValue('');
        });

        closeBtn.addEventListener('click', close);

        modal.addEventListener('click', (e) => {
            if (e.target === modal) close();
        });

        searchInput.addEventListener('input', (e) => {
            render(e.target.value);
        });

        list.addEventListener('click', (e) => {
            const item = e.target.closest('.sp-item');
            if (item) {
                chooseValue(item.dataset.value);
            }
        });

        // Navegación con teclado
        searchInput.addEventListener('keydown', (e) => {
            const items = list.querySelectorAll('.sp-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                focusItem(Math.min(focusedIndex + 1, items.length - 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                focusItem(Math.max(focusedIndex - 1, 0));
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (focusedIndex >= 0 && currentResults[focusedIndex]) {
                    chooseValue(currentResults[focusedIndex].value);
                } else if (currentResults.length === 1) {
                    // Si solo hay un resultado, elegirlo automáticamente
                    chooseValue(currentResults[0].value);
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                close();
            }
        });

        // Si el <select> cambia desde fuera (ej. otro script), sincronizar
        select.addEventListener('change', syncTriggerLabel);

        // Estado inicial
        syncTriggerLabel();
    }

    // ----- Inicialización automática -----

    function init(root) {
        const selects = (root || document).querySelectorAll('select[data-picker]');
        selects.forEach(enhance);
    }

    // Auto-init al cargar y exponer función para inicialización manual
    // (útil para selects dentro de modales que se renderizan después)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init());
    } else {
        init();
    }

    window.SearchablePicker = { init, enhance };

    // Observer para selects agregados dinámicamente
    const observer = new MutationObserver((mutations) => {
        for (const m of mutations) {
            m.addedNodes.forEach(node => {
                if (node.nodeType !== 1) return;
                if (node.matches && node.matches('select[data-picker]')) {
                    enhance(node);
                } else if (node.querySelectorAll) {
                    node.querySelectorAll('select[data-picker]').forEach(enhance);
                }
            });
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();
