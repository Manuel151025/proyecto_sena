/**
 * Evaluaciones Import Client Logic
 */

function toggleDetails(id) {
    const row = document.getElementById(id);
    const icon = document.getElementById('icon-' + id);
    if (!row || !icon) return;
    
    if (row.style.display === 'none') {
        row.style.display = '';
        icon.style.transform = 'rotate(90deg)';
        icon.classList.add('text-primary');
    } else {
        row.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
        icon.classList.remove('text-primary');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Filtrado en la tabla de detalles
    const detailSearch = document.getElementById('detailSearchInput');
    if (detailSearch) {
        detailSearch.addEventListener('keyup', function() {
            const value = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#detailsTable tbody tr:not([id^="details-"])');
            rows.forEach(row => {
                const doc = row.cells[0]?.textContent.toLowerCase();
                const name = row.cells[1]?.textContent.toLowerCase();
                const detailsId = row.getAttribute('data-details-id');
                const detailsRow = document.getElementById(detailsId);
                const icon = document.getElementById('icon-' + detailsId);
                
                if (doc.includes(value) || name.includes(value)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                    if (detailsRow) {
                        detailsRow.style.display = 'none';
                    }
                    if (icon) {
                        icon.style.transform = 'rotate(0deg)';
                        icon.classList.remove('text-primary');
                    }
                }
            });
        });
    }

    // 2. Subida de archivo vía AJAX
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('excelFileInput');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Selecciona un archivo primero.');
                return;
            }
            
            // Validar extensión
            const ext = file.name.split('.').pop().toLowerCase();
            if (ext !== 'xls') {
                alert('El archivo debe tener extensión .xls');
                return;
            }
            
            const progressDiv = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('uploadProgressBar');
            const statusText = document.getElementById('uploadStatusText');
            const submitBtn = document.getElementById('submitBtn');
            const errorsDiv = document.getElementById('ajaxErrors');
            const errorsContent = document.getElementById('ajaxErrorContent');
            
            // Ocultar errores previos
            if (errorsDiv) errorsDiv.style.display = 'none';
            
            // Mostrar progreso
            if (progressDiv) progressDiv.style.display = 'block';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
            }
            if (statusText) statusText.textContent = 'Leyendo archivo...';
            if (progressBar) progressBar.style.width = '10%';
            
            // Leer el archivo con FileReader (esto bypassa bloqueos del OS)
            const reader = new FileReader();
            
            reader.onload = function(event) {
                if (statusText) statusText.textContent = 'Enviando al servidor...';
                if (progressBar) progressBar.style.width = '40%';
                
                // Obtener el base64 sin el prefijo "data:..."
                const base64Data = event.target.result.split(',')[1];
                
                // Enviar via fetch como POST con datos base64
                const formData = new FormData();
                formData.append('file_data', base64Data);
                formData.append('file_name', file.name);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (statusText) statusText.textContent = 'Procesando importación...';
                    if (progressBar) progressBar.style.width = '80%';
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (progressBar) progressBar.style.width = '100%';
                        
                        if (data.success) {
                            if (statusText) statusText.textContent = '¡Importación completada!';
                            if (progressBar) {
                                progressBar.classList.remove('progress-bar-animated');
                                progressBar.classList.add('bg-success');
                            }
                            // Recargar la página para mostrar el resumen
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            // Mostrar errores
                            if (progressDiv) progressDiv.style.display = 'none';
                            if (errorsDiv && errorsContent) {
                                errorsDiv.style.display = 'flex';
                                errorsContent.innerHTML = data.errors.join('<br>');
                            }
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i>Iniciar Carga Masiva';
                            }
                        }
                    } catch(err) {
                        // La respuesta no es JSON válido.
                        if (progressDiv) progressDiv.style.display = 'none';
                        if (errorsDiv && errorsContent) {
                            errorsDiv.style.display = 'flex';
                            errorsContent.innerHTML = '<strong>Error del servidor al procesar la respuesta:</strong><br>' + 
                                '<div style="text-align: left; max-height: 250px; overflow-y: auto; font-family: monospace; font-size: 0.75rem; background: rgba(0,0,0,0.05); padding: 10px; border-radius: 6px; margin-top: 8px; white-space: pre-wrap; word-break: break-all;">' + 
                                text.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
                        }
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i>Iniciar Carga Masiva';
                        }
                    }
                })
                .catch(error => {
                    if (progressDiv) progressDiv.style.display = 'none';
                    if (errorsDiv && errorsContent) {
                        errorsDiv.style.display = 'flex';
                        errorsContent.innerHTML = 'Error de conexión: ' + error.message + '<br>Intenta de nuevo.';
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i>Iniciar Carga Masiva';
                    }
                });
            };
            
            reader.onerror = function() {
                if (progressDiv) progressDiv.style.display = 'none';
                if (errorsDiv && errorsContent) {
                    errorsDiv.style.display = 'flex';
                    errorsContent.innerHTML = 'Error: No se pudo leer el archivo. Intenta con una copia del archivo.';
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i>Iniciar Carga Masiva';
                }
            };
            
            // Leer como base64 (DataURL)
            reader.readAsDataURL(file);
        });
    }
});
