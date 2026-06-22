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

    // 2. Función reutilizable para crear Sparklines premium e interactivos
    function createSparkline(id, labels, data, color, fillGradStart, isPercentage = false) {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 45);
        grad.addColorStop(0, fillGradStart);
        grad.addColorStop(1, 'rgba(0, 0, 0, 0)');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels.length > 0 ? labels : ['Sin datos'],
                datasets: [{
                    data: data.length > 0 ? data : [0],
                    borderColor: color,
                    backgroundColor: grad,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: color,
                    pointBorderWidth: 1.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleFont: { size: 10, family: "'Inter', sans-serif", weight: '600' },
                        bodyFont: { size: 10, family: "'Inter', sans-serif" },
                        padding: 6,
                        cornerRadius: 6,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                const val = context.parsed.y;
                                return isPercentage ? ` Progreso: ${val}%` : ` Cantidad: ${val}`;
                            }
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: { display: false, beginAtZero: true }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    // Inicializar sparklines con datos reales consultados de BD
    createSparkline(
        'sparkFichas', 
        ['Planeación', 'Inducción', 'Ejecución', 'Cierre'],
        dashboardData.fichasEstados,
        primaryColor,
        'rgba(57, 169, 0, 0.25)'
    );

    createSparkline(
        'sparkAprendices', 
        ['Matriculados', 'Suspendidos', 'Desertados', 'Egresados'],
        dashboardData.aprendicesEstados,
        '#3B82F6',
        'rgba(59, 130, 246, 0.25)'
    );

    createSparkline(
        'sparkInstructores', 
        ['Activos', 'Inactivos', 'Bloqueados'],
        dashboardData.instructoresEstados,
        '#8B5CF6',
        'rgba(139, 92, 246, 0.25)'
    );

    createSparkline(
        'sparkRetencion', 
        dashboardData.fichasCumplimientoLabels,
        dashboardData.fichasCumplimientoData,
        '#F59E0B',
        'rgba(245, 158, 11, 0.25)',
        true
    );

    // 3. Plugin personalizado para efecto "Glow" (Neón) en líneas
    const neonGlowPlugin = {
        id: 'neonGlow',
        beforeDatasetsDraw: (chart) => {
            const ctx = chart.ctx;
            chart.data.datasets.forEach((dataset, i) => {
                if (dataset.type === 'line' && chart.getDatasetMeta(i).hidden === false) {
                    ctx.save();
                    ctx.shadowColor = dataset.borderColor;
                    ctx.shadowBlur = 15;
                    ctx.shadowOffsetX = 0;
                    ctx.shadowOffsetY = 4;
                }
            });
        },
        afterDatasetsDraw: (chart) => {
            chart.ctx.restore();
        }
    };
    Chart.register(neonGlowPlugin);

    // 4. Chart Principal: Analítica Avanzada (Mixed Chart)
    const chartProgCanvas = document.getElementById('chartProg');
    if (chartProgCanvas) {
        const ctxProg = chartProgCanvas.getContext('2d');
        
        // Gradientes para Volumen (Barras)
        const gradBar = ctxProg.createLinearGradient(0, 0, 0, 400);
        gradBar.addColorStop(0, 'rgba(59, 130, 246, 0.4)'); // Azul suave
        gradBar.addColorStop(1, 'rgba(59, 130, 246, 0)');
        
        // Gradientes para Cumplimiento (Línea)
        const gradLine = ctxProg.createLinearGradient(0, 0, 0, 400);
        gradLine.addColorStop(0, 'rgba(57, 169, 0, 0.5)'); // Verde SENA
        gradLine.addColorStop(1, 'rgba(57, 169, 0, 0)');

        const labels = dashboardData.programasLabels;
        const dataPromedio = dashboardData.programasPromedio;
        const dataVolumen = dashboardData.programasVolumen;
        
        // Simulación de "Meta Institucional" para análisis comparativo
        const dataMeta = Array(labels.length).fill(80);

        new Chart(ctxProg, {
            type: 'line',
            data: {
                labels: labels.length > 0 ? labels : ['Sin datos'],
                datasets: [
                    {
                        type: 'line',
                        label: 'Meta Institucional',
                        data: dataMeta,
                        borderColor: 'rgba(239, 68, 68, 0.6)', // Rojo suave
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        pointRadius: 0,
                        tension: 0,
                        yAxisID: 'y'
                    },
                    {
                        type: 'line',
                        label: 'Cumplimiento Promedio (%)',
                        data: dataPromedio.length > 0 ? dataPromedio : [0],
                        backgroundColor: gradLine,
                        borderColor: primaryColor,
                        borderWidth: 4,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: primaryColor,
                        pointBorderWidth: 3,
                        pointRadius: 5,
                        pointHoverRadius: 8,
                        pointHoverBorderWidth: 4,
                        yAxisID: 'y'
                    },
                    {
                        type: 'bar',
                        label: 'Volumen de Aprendices',
                        data: dataVolumen.length > 0 ? dataVolumen : [0],
                        backgroundColor: gradBar,
                        borderColor: 'rgba(59, 130, 246, 0.8)',
                        borderWidth: { top: 2, right: 0, bottom: 0, left: 0 },
                        borderRadius: { topLeft: 6, topRight: 6 },
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { 
                        display: true, 
                        position: 'top', 
                        labels: { usePointStyle: true, boxWidth: 8, font: { weight: '600' } } 
                    },
                    tooltip: { 
                        backgroundColor: 'rgba(15, 23, 42, 0.9)', 
                        titleFont: { size: 14, family: "'Inter', sans-serif" }, 
                        bodyFont: { size: 13, family: "'Inter', sans-serif" }, 
                        padding: 16, 
                        cornerRadius: 12,
                        usePointStyle: true,
                        boxPadding: 6,
                        callbacks: {
                            afterBody: function(context) {
                                const index = context[0].dataIndex;
                                const minVal = dashboardData.programasMin[index];
                                const maxVal = dashboardData.programasMax[index];
                                if (minVal !== undefined && maxVal !== undefined) {
                                    return `\nDispersión:\nMax: ${maxVal}%\nMin: ${minVal}%`;
                                }
                            }
                        }
                    }
                },
                scales: { 
                    y: { 
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true, 
                        max: 100, 
                        title: { display: true, text: 'Cumplimiento (%)', font: { weight: 'bold', size: window.innerWidth < 576 ? 10 : 12 } },
                        ticks: { font: { size: window.innerWidth < 576 ? 9 : 11 } },
                        grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false } 
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: { display: true, text: 'N° Aprendices', font: { weight: 'bold', size: window.innerWidth < 576 ? 10 : 12 }, color: '#3B82F6' },
                        grid: { drawOnChartArea: false, drawBorder: false },
                        ticks: { color: '#3B82F6', font: { size: window.innerWidth < 576 ? 9 : 11 } }
                    },
                    x: { 
                        grid: { display: false, drawBorder: false },
                        ticks: { 
                            font: { 
                                weight: '500',
                                size: window.innerWidth < 576 ? 9 : 11
                            },
                            autoSkip: true,
                            maxTicksLimit: window.innerWidth < 576 ? 4 : 10,
                            maxRotation: 45,
                            minRotation: window.innerWidth < 576 ? 45 : 0
                        }
                    }
                },
                interaction: { intersect: false, mode: 'index' },
                animation: {
                    tension: {
                        duration: 1000,
                        easing: 'linear',
                        from: 1,
                        to: 0.4,
                        loop: false
                    }
                }
            }
        });
    }

    // 5. Chart de estados de fichas (Doughnut)
    const chartPieCanvas = document.getElementById('chartPie');
    if (chartPieCanvas) {
        new Chart(chartPieCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Ejecución', 'Inducción', 'Planeación', 'Cierre'],
                datasets: [{
                    data: [
                        dashboardData.fichasEstados[2], // ejecucion
                        dashboardData.fichasEstados[1], // induccion
                        dashboardData.fichasEstados[0], // planeacion
                        dashboardData.fichasEstados[3]  // cierre
                    ],
                    backgroundColor: [primaryColor, '#3B82F6', '#F59E0B', '#8B5CF6'],
                    borderWidth: 0,
                    hoverOffset: 10
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
                            padding: window.innerWidth < 576 ? 10 : 20, 
                            usePointStyle: true, 
                            pointStyle: 'circle',
                            font: { size: window.innerWidth < 576 ? 10 : 12 }
                        } 
                    },
                    tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', padding: 12, cornerRadius: 8 }
                } 
            }
        });
    }

    // 6. Gráficos de deserción (Solo si existen los elementos en el DOM)
    const chartDesercionCanvas = document.getElementById('chartDesercionRate');
    if (chartDesercionCanvas && dashboardData.statsProgramas.length > 0) {
        const desercionRates = dashboardData.statsProgramas.map(p => {
            const matriculados = parseInt(p.matriculados || 0);
            const desertados = parseInt(p.desertados || 0);
            const total = matriculados + desertados;
            return total > 0 ? Math.round((desertados / total) * 100) : 0;
        });

        new Chart(chartDesercionCanvas, {
            type: 'bar',
            data: {
                labels: dashboardData.radarLabels,
                datasets: [{
                    label: 'Tasa de Deserción (%)',
                    data: desercionRates,
                    backgroundColor: 'rgba(239, 68, 68, 0.75)',
                    borderColor: 'rgb(239, 68, 68)',
                    borderWidth: 1,
                    borderRadius: 4,
                    barThickness: 14
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) { return value + '%'; },
                            font: { family: "'Inter', sans-serif", size: window.innerWidth < 576 ? 9 : 11 }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            font: { family: "'Inter', sans-serif", weight: '500', size: window.innerWidth < 576 ? 9 : 11 }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleFont: { family: "'Inter', sans-serif" },
                        bodyFont: { family: "'Inter', sans-serif" },
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            label: function(context) {
                                return ` Tasa de Deserción: ${context.parsed.x}%`;
                            }
                        }
                    }
                }
            }
        });
    }

    const chartRetencionCanvas = document.getElementById('chartRetencion');
    if (chartRetencionCanvas && dashboardData.statsProgramas.length > 0) {
        const dataMatriculados = dashboardData.statsProgramas.map(p => parseInt(p.matriculados || 0));
        const dataDesertados = dashboardData.statsProgramas.map(p => parseInt(p.desertados || 0));

        new Chart(chartRetencionCanvas, {
            type: 'bar',
            data: {
                labels: dashboardData.radarLabels,
                datasets: [
                    {
                        label: 'Matriculados',
                        data: dataMatriculados,
                        backgroundColor: primaryColor,
                        borderRadius: { topLeft: 4, topRight: 4, bottomLeft: 4, bottomRight: 4 },
                        borderSkipped: false
                    },
                    {
                        label: 'Desertados',
                        data: dataDesertados,
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                        borderRadius: { topLeft: 4, topRight: 4, bottomLeft: 4, bottomRight: 4 },
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { 
                        stacked: true, 
                        grid: { display: false },
                        ticks: { 
                            font: { family: "'Inter', sans-serif", size: window.innerWidth < 576 ? 9 : 11 },
                            autoSkip: true,
                            maxTicksLimit: window.innerWidth < 576 ? 4 : 10,
                            maxRotation: 45,
                            minRotation: window.innerWidth < 576 ? 45 : 0
                        }
                    },
                    y: { 
                        stacked: true, 
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { 
                            font: { family: "'Inter', sans-serif", size: window.innerWidth < 576 ? 9 : 11 }
                        }
                    }
                },
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            boxWidth: 12, 
                            font: { size: window.innerWidth < 576 ? 10 : 12 } 
                        } 
                    },
                    tooltip: { mode: 'index', backgroundColor: 'rgba(0,0,0,0.8)' }
                }
            }
        });
    }

    // 7. Cargar eventos del calendario de forma asíncrona
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
