/* ===================================================================
 * IMPORT PREVIEW
 * ===================================================================
 * Muestra en el navegador el contenido del archivo elegido ANTES de
 * enviar el formulario de importación: nombre, tamaño, número de filas
 * detectadas y una tabla con las primeras filas (incluida la cabecera).
 *
 * Es 100% frontend: no altera el <input>, ni el <form>, ni el envío.
 * Si el archivo no se puede leer se muestra un aviso y el formulario
 * sigue siendo utilizable (el servidor es quien valida de verdad).
 *
 * Uso en HTML:
 *   <input type="file" name="archivo" accept=".csv,.xlsx" data-import-preview>
 *
 * Atributos opcionales:
 *   data-import-preview-rows="10"       filas de datos a mostrar (def. 10)
 *   data-import-preview-mode="meta"     solo ficha del archivo, sin tabla
 *                                       (para formatos que el navegador no
 *                                        puede leer, p. ej. PDF)
 *   data-import-preview-target="#id"    contenedor donde pintar el panel;
 *                                       por defecto se inserta justo
 *                                       después del input.
 * =================================================================== */

(function () {
    'use strict';

    const DEFAULT_ROWS = 10;

    // Por encima de este tamaño no se intenta parsear el contenido: solo se
    // muestra la ficha del archivo. Las vistas ya avisan del límite de 10 MB.
    const MAX_PARSE_BYTES = 12 * 1024 * 1024;

    // Recorte del texto de cada celda para que la tabla siga siendo legible.
    const MAX_CELL_CHARS = 90;

    const SHEETJS_URL = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';

    // ----- utilidades -----

    function formatBytes(bytes) {
        if (!bytes && bytes !== 0) return '';
        if (bytes < 1024) return bytes + ' B';
        const kb = bytes / 1024;
        if (kb < 1024) return kb.toFixed(1) + ' KB';
        return (kb / 1024).toFixed(2) + ' MB';
    }

    function extensionOf(name) {
        const parts = String(name || '').split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        // Siempre textContent: el contenido viene de un archivo del usuario y
        // no debe interpretarse como HTML.
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    }

    /**
     * Carga SheetJS una sola vez y de forma diferida (solo cuando hace falta
     * leer un Excel). Si la vista ya lo trae cargado, se reutiliza.
     */
    let sheetJsPromise = null;
    function loadSheetJs() {
        if (window.XLSX) return Promise.resolve(window.XLSX);
        if (sheetJsPromise) return sheetJsPromise;

        sheetJsPromise = new Promise(function (resolve, reject) {
            const existing = document.querySelector('script[data-sheetjs]');
            if (existing) {
                existing.addEventListener('load', () => resolve(window.XLSX));
                existing.addEventListener('error', () => reject(new Error('No se pudo cargar el lector de Excel.')));
                return;
            }
            const script = document.createElement('script');
            script.src = SHEETJS_URL;
            script.async = true;
            script.crossOrigin = 'anonymous';
            script.referrerPolicy = 'no-referrer';
            script.setAttribute('data-sheetjs', '1');
            script.onload = function () {
                if (window.XLSX) resolve(window.XLSX);
                else reject(new Error('El lector de Excel se cargó incompleto.'));
            };
            script.onerror = function () {
                reject(new Error('No se pudo cargar el lector de Excel (¿sin conexión?).'));
            };
            document.head.appendChild(script);
        });
        return sheetJsPromise;
    }

    // ----- parseo de CSV -----

    /**
     * Detecta el separador mirando la primera línea fuera de comillas.
     * El backend hace lo mismo (';' si aparece, si no ','); aquí se añade
     * el tabulador porque algunos exports de Excel lo usan.
     */
    function detectDelimiter(text) {
        let inQuotes = false;
        const counts = { ',': 0, ';': 0, '\t': 0 };
        for (let i = 0; i < text.length; i++) {
            const ch = text[i];
            if (ch === '"') {
                inQuotes = !inQuotes;
            } else if (!inQuotes) {
                if (ch === '\n') break;
                if (counts[ch] !== undefined) counts[ch]++;
            }
        }
        if (counts[';'] > 0) return ';';
        if (counts['\t'] > counts[',']) return '\t';
        return ',';
    }

    /**
     * Parser de CSV que respeta comillas dobles, separadores dentro de
     * comillas, saltos de línea embebidos y comillas escapadas ("").
     */
    function parseCsv(text, delimiter) {
        // Quitar BOM: si no, la primera cabecera sale con un carácter raro.
        if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);

        const rows = [];
        let row = [];
        let value = '';
        let inQuotes = false;

        for (let i = 0; i < text.length; i++) {
            const ch = text[i];

            if (inQuotes) {
                if (ch === '"') {
                    if (text[i + 1] === '"') { value += '"'; i++; }
                    else { inQuotes = false; }
                } else {
                    value += ch;
                }
                continue;
            }

            if (ch === '"') {
                inQuotes = true;
            } else if (ch === delimiter) {
                row.push(value);
                value = '';
            } else if (ch === '\n') {
                row.push(value);
                rows.push(row);
                row = [];
                value = '';
            } else if (ch !== '\r') {
                value += ch;
            }
        }
        if (value !== '' || row.length > 0) {
            row.push(value);
            rows.push(row);
        }
        return rows;
    }

    function isEmptyRow(row) {
        return !row || row.every(cell => String(cell === null || cell === undefined ? '' : cell).trim() === '');
    }

    // ----- render -----

    function ensurePanel(input) {
        const targetSel = input.dataset.importPreviewTarget;
        if (targetSel) {
            const target = document.querySelector(targetSel);
            if (target) return target;
        }
        let panel = input.__importPreviewPanel;
        if (panel && panel.isConnected) return panel;

        panel = el('div', 'import-preview mt-3');
        // Después del input, pero fuera de cualquier .input-group para no
        // romper su maquetación.
        const anchor = input.closest('.input-group') || input;
        anchor.parentNode.insertBefore(panel, anchor.nextSibling);
        input.__importPreviewPanel = panel;
        return panel;
    }

    function clearPanel(input) {
        const panel = input.__importPreviewPanel;
        if (panel) panel.innerHTML = '';
        const target = input.dataset.importPreviewTarget
            ? document.querySelector(input.dataset.importPreviewTarget)
            : null;
        if (target) target.innerHTML = '';
    }

    function renderNotice(panel, type, icon, message) {
        panel.innerHTML = '';
        const alert = el('div', 'alert-flat ' + type);
        alert.appendChild(el('i', 'bi ' + icon));
        alert.appendChild(el('div', null, message));
        panel.appendChild(alert);
    }

    function buildHeader(file, badges) {
        const header = el('div', 'card-header d-flex flex-wrap align-items-center gap-2');

        const title = el('span', 'd-inline-flex align-items-center gap-2 me-auto text-truncate');
        title.appendChild(el('i', 'bi bi-file-earmark-text text-primary'));
        const name = el('span', 'text-truncate', file.name);
        name.title = file.name;
        title.appendChild(name);
        header.appendChild(title);

        badges.forEach(function (b) {
            header.appendChild(el('span', 'badge-soft ' + (b.tone || ''), b.text));
        });
        return header;
    }

    /**
     * @param headerRow  fila de cabecera del archivo
     * @param dataRows   [{ n: nº de fila real en el archivo, cells: [...] }]
     */
    function buildTable(headerRow, dataRows, maxRows) {
        const wrap = el('div', 'table-responsive');
        const table = el('table', 'table table-sm align-middle mb-0');

        const bodyRows = dataRows.slice(0, maxRows);

        // Con filas irregulares se usa el ancho máximo para que no se
        // desalineen las columnas.
        let columns = headerRow.length;
        bodyRows.forEach(r => { if (r.cells.length > columns) columns = r.cells.length; });
        if (columns === 0) columns = 1;

        const thead = el('thead');
        const trHead = el('tr');
        trHead.appendChild(el('th', 'text-muted', '#'));
        for (let c = 0; c < columns; c++) {
            const raw = String(headerRow[c] === undefined ? '' : headerRow[c]).trim();
            trHead.appendChild(el('th', null, raw !== '' ? raw : 'Columna ' + (c + 1)));
        }
        thead.appendChild(trHead);
        table.appendChild(thead);

        const tbody = el('tbody');
        bodyRows.forEach(function (row) {
            const tr = el('tr');
            // Nº de fila tal como aparece en el archivo (la 1 es la cabecera),
            // para que coincida con los mensajes de error del importador.
            tr.appendChild(el('td', 'text-muted small', row.n));
            for (let c = 0; c < columns; c++) {
                const cell = row.cells[c];
                let text = cell === undefined || cell === null ? '' : String(cell);
                const full = text;
                if (text.length > MAX_CELL_CHARS) text = text.slice(0, MAX_CELL_CHARS) + '…';
                const td = el('td', null, text);
                if (full !== text) td.title = full;
                tr.appendChild(td);
            }
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        wrap.appendChild(table);

        const footer = el('div', 'card-footer text-muted small');
        if (dataRows.length > bodyRows.length) {
            footer.textContent = 'Mostrando las primeras ' + bodyRows.length +
                ' de ' + dataRows.length + ' filas de datos. El resto se procesa igual al importar.';
        } else {
            footer.textContent = 'Mostrando todas las filas de datos del archivo.';
        }
        return { wrap: wrap, footer: footer };
    }

    function renderPreview(panel, file, rows, maxRows) {
        // Se descartan las filas vacías (el importador también las salta) pero
        // se conserva su número original para poder mostrarlo.
        const dataRows = [];
        rows.slice(1).forEach(function (cells, i) {
            if (!isEmptyRow(cells)) dataRows.push({ n: i + 2, cells: cells });
        });
        const dataRowCount = dataRows.length;

        panel.innerHTML = '';
        const card = el('div', 'card');

        card.appendChild(buildHeader(file, [
            { text: dataRowCount + (dataRowCount === 1 ? ' fila' : ' filas') + ' de datos', tone: 'primary' },
            { text: formatBytes(file.size) }
        ]));

        if (dataRowCount === 0) {
            const body = el('div', 'card-body');
            const alert = el('div', 'alert-flat warning mb-0');
            alert.appendChild(el('i', 'bi bi-exclamation-triangle'));
            alert.appendChild(el('div', null,
                'El archivo no tiene filas de datos (solo la cabecera o está vacío). Revisa el contenido antes de importar.'));
            body.appendChild(alert);
            card.appendChild(body);
            panel.appendChild(card);
            return;
        }

        const built = buildTable(rows[0] || [], dataRows, maxRows);
        const body = el('div', 'card-body p-0');
        body.appendChild(built.wrap);
        card.appendChild(body);
        card.appendChild(built.footer);
        panel.appendChild(card);
    }

    function renderMetaOnly(panel, file, note) {
        panel.innerHTML = '';
        const card = el('div', 'card');
        card.appendChild(buildHeader(file, [{ text: formatBytes(file.size) }]));
        const body = el('div', 'card-body');
        body.appendChild(el('p', 'text-muted small mb-0', note));
        card.appendChild(body);
        panel.appendChild(card);
    }

    // ----- lectura del archivo -----

    function readAsText(file) {
        return new Promise(function (resolve, reject) {
            const reader = new FileReader();
            reader.onload = () => resolve(String(reader.result || ''));
            reader.onerror = () => reject(new Error('No se pudo leer el archivo.'));
            reader.readAsText(file, 'UTF-8');
        });
    }

    function readAsArrayBuffer(file) {
        return new Promise(function (resolve, reject) {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => reject(new Error('No se pudo leer el archivo.'));
            reader.readAsArrayBuffer(file);
        });
    }

    function rowsFromWorkbook(XLSX, buffer) {
        const wb = XLSX.read(new Uint8Array(buffer), { type: 'array' });
        const firstSheetName = wb.SheetNames[0];
        if (!firstSheetName) return [];
        const sheet = wb.Sheets[firstSheetName];
        // blankrows: true mantiene las filas vacías para que el nº de fila que
        // se muestra coincida con el de la hoja de cálculo; se filtran después.
        return XLSX.utils.sheet_to_json(sheet, {
            header: 1,
            raw: false,
            defval: '',
            blankrows: true
        });
    }

    /**
     * Extensiones declaradas en el propio accept del input (única fuente de
     * verdad del markup). Se ignoran las entradas que sean tipos MIME.
     */
    function acceptedExtensions(input) {
        return String(input.getAttribute('accept') || '')
            .split(',')
            .map(part => part.trim().toLowerCase())
            .filter(part => part.startsWith('.') && part.length > 1)
            .map(part => part.slice(1));
    }

    function handleFile(input) {
        const file = input.files && input.files[0];
        if (!file) { clearPanel(input); return; }

        const panel = ensurePanel(input);
        const maxRows = parseInt(input.dataset.importPreviewRows || DEFAULT_ROWS, 10) || DEFAULT_ROWS;
        const mode = input.dataset.importPreviewMode || 'table';
        const ext = extensionOf(file.name);

        // El atributo accept solo filtra el diálogo del sistema: arrastrando el
        // archivo o eligiendo "Todos los archivos" puede colarse una extensión
        // que el servidor va a rechazar. En ese caso se avisa aquí en lugar de
        // pintar una tabla que da la falsa impresión de que el archivo sirve.
        // No se bloquea el envío: la validación buena sigue siendo la del backend.
        const permitidas = acceptedExtensions(input);
        if (permitidas.length > 0 && ext !== '' && permitidas.indexOf(ext) === -1) {
            renderNotice(panel, 'warning', 'bi-exclamation-triangle',
                'El archivo seleccionado es .' + ext + ' y este importador solo admite ' +
                permitidas.map(e => '.' + e).join(' o ') + '. Conviértelo antes de continuar.');
            return;
        }

        if (mode === 'meta') {
            renderMetaOnly(panel, file,
                'Archivo listo para enviar. Su contenido se analiza en el servidor al continuar.');
            return;
        }

        if (file.size === 0) {
            renderNotice(panel, 'warning', 'bi-exclamation-triangle',
                'El archivo está vacío (0 bytes).');
            return;
        }

        if (file.size > MAX_PARSE_BYTES) {
            renderMetaOnly(panel, file,
                'El archivo es demasiado grande para previsualizarlo en el navegador. Puedes continuar: el servidor lo procesará igual.');
            return;
        }

        renderNotice(panel, 'info', 'bi-hourglass-split', 'Leyendo ' + file.name + '…');

        const isCsv = ext === 'csv' || ext === 'txt';
        const isExcel = ext === 'xlsx' || ext === 'xls' || ext === 'xlsm';

        let job;
        if (isCsv) {
            job = readAsText(file).then(function (text) {
                return parseCsv(text, detectDelimiter(text));
            });
        } else if (isExcel) {
            job = Promise.all([loadSheetJs(), readAsArrayBuffer(file)])
                .then(function (results) {
                    return rowsFromWorkbook(results[0], results[1]);
                });
        } else {
            renderMetaOnly(panel, file,
                'No se puede previsualizar este tipo de archivo en el navegador. Puedes continuar: el servidor validará el formato.');
            return;
        }

        job.then(function (rows) {
            if (!rows || rows.length === 0) {
                renderNotice(panel, 'warning', 'bi-exclamation-triangle',
                    'No se detectó ninguna fila en el archivo.');
                return;
            }
            renderPreview(panel, file, rows, maxRows);
        }).catch(function (err) {
            // Nunca se bloquea el formulario: solo se avisa.
            renderNotice(panel, 'warning', 'bi-exclamation-triangle',
                'No se pudo previsualizar el archivo (' + (err && err.message ? err.message : 'error desconocido') +
                '). Puedes continuar: el servidor lo validará al importar.');
        });
    }

    // ----- inicialización -----

    function bind(input) {
        if (input.dataset.importPreviewBound === '1') return;
        input.dataset.importPreviewBound = '1';
        input.addEventListener('change', function () { handleFile(input); });
        // Si el navegador restauró un archivo al volver atrás, pintarlo ya.
        if (input.files && input.files.length > 0) handleFile(input);
    }

    function init(root) {
        (root || document).querySelectorAll('input[type="file"][data-import-preview]').forEach(bind);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init());
    } else {
        init();
    }

    window.ImportPreview = { init: init, bind: bind };
})();
