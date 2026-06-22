document.addEventListener('DOMContentLoaded', function() {
    // 1. Obtener los datos inyectados por PHP
    const dataElement = document.getElementById('dashboard-data');
    if (!dataElement) return;
    
    let dashboardData;
    try {
        dashboardData = JSON.parse(dataElement.textContent);
    } catch (e) {
        console.error("Error al decodificar JSON del dashboard:", e);
        return;
    }

    const appUrl = dashboardData.appUrl;
    const css = getComputedStyle(document.documentElement);
    const primaryColor = css.getPropertyValue('--sena-primary').trim() || '#39A900';

    // 2. Colores dinámicos para gráficos en modo claro/oscuro
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.05)';

    // Recargar al cambiar tema en caliente para redibujar correctamente
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
                location.reload();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });

    // 3. Gráfico de Conceptos (Doughnut Chart)
    const chartCanvas = document.getElementById('chartConceptos');
    if (chartCanvas) {
        const ctxPie = chartCanvas.getContext('2d');
        const countA = dashboardData.aprobados;
        const countD = dashboardData.reprobados;
        const countPendiente = dashboardData.pendientes;

        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: ['Aprobados (A)', 'No Aprobados (D)', 'Pendientes'],
                datasets: [{
                    data: [countA, countD, countPendiente],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.15)', // Verde suave
                        'rgba(239, 68, 68, 0.15)', // Rojo suave
                        isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)'
                    ],
                    borderColor: [
                        '#22c55e', // Verde
                        '#ef4444', // Rojo
                        isDark ? '#242f47' : '#e2e8f0'
                    ],
                    borderWidth: 1.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColor,
                            boxWidth: 12,
                            padding: 10,
                            font: { family: "'Inter', sans-serif", size: 11 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleFont: { family: "'Inter', sans-serif" },
                        bodyFont: { family: "'Inter', sans-serif" },
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const total = countA + countD + countPendiente;
                                const val = context.parsed;
                                const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                return ` ${context.label}: ${val} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // 4. Cargar eventos del calendario de forma asíncrona
    const eventsList = document.getElementById('dashboard-events-list');
    if (eventsList) {
        const today = new Date();
        const formatDate = (d) => d.toISOString().split('T')[0];
        
        const start = formatDate(today);
        const next30Days = new Date(today);
        next30Days.setDate(today.getDate() + 30);
        const end = formatDate(next30Days);
        
        const url = `${appUrl}/modules/calendario/api_events.php?start=${start}&end=${end}`;
        
        fetch(url)
            .then(res => res.json())
            .then(events => {
                const loader = document.getElementById('events-loader');
                if (loader) loader.remove();
                
                if (!events || events.length === 0) {
                    eventsList.innerHTML = `
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x d-block mb-2" style="font-size: 2rem; opacity: 0.4;"></i>
                            Sin eventos programados para los próximos 30 días.
                        </div>
                    `;
                    return;
                }
                
                // Ordenar eventos por fecha ascendente
                events.sort((a, b) => new Date(a.start) - new Date(b.start));
                
                // Mostrar un máximo de 5 eventos
                const upcoming = events.slice(0, 5);
                
                let html = '';
                const formatLabel = (dateStr) => {
                    const parts = dateStr.split('-');
                    if (parts.length < 3) return dateStr;
                    const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                    const t = new Date();
                    t.setHours(0,0,0,0);
                    d.setHours(0,0,0,0);
                    
                    const diffTime = d.getTime() - t.getTime();
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    if (diffDays === 0) return 'Hoy';
                    if (diffDays === 1) return 'Mañ.';
                    
                    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                    return `${d.getDate()} ${months[d.getMonth()]}`;
                };

                upcoming.forEach(ev => {
                    const color = ev.color || '#39A900';
                    const targetUrl = ev.url || '#';
                    const title = ev.title || 'Evento';
                    const tipo = (ev.extendedProps && ev.extendedProps.tipo) || 'Académico';
                    const extra = (ev.extendedProps && (ev.extendedProps.ficha || ev.extendedProps.programa || ev.extendedProps.instructor)) || '';
                    
                    html += `
                        <a href="${targetUrl}" class="event-item">
                            <span class="event-badge-dot" style="background-color: ${color};"></span>
                            <div class="event-body">
                                <div class="event-title">${title}</div>
                                <div class="event-desc">${tipo}${extra ? ' · ' + extra : ''}</div>
                            </div>
                            <span class="event-date-badge">${formatLabel(ev.start)}</span>
                        </a>
                    `;
                });
                eventsList.innerHTML = html;
            })
            .catch(err => {
                console.error(err);
                const loader = document.getElementById('events-loader');
                if (loader) loader.remove();
                eventsList.innerHTML = `
                    <div class="text-center py-5 text-danger">
                        <i class="bi bi-exclamation-octagon d-block mb-2" style="font-size: 2rem;"></i>
                        Error al sincronizar eventos.
                    </div>
                `;
            });
    }
});
