(function () {
    const datos = Restaurante.datos('datos-dashboard') || {};
    const etiquetas = datos.etiquetas || [];
    const totales = (datos.totales || []).map(Number);
    const promedio = Number(datos.promedio || 0);
    const lienzo = document.getElementById('grafico-ganancias');

    const colones = valor => '₡' + Number(valor).toLocaleString('es-CR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    const colonesCorto = valor => {
        const numero = Number(valor);
        if (numero >= 1000000) return '₡' + (numero / 1000000).toLocaleString('es-CR', { maximumFractionDigits: 1 }) + ' M';
        if (numero >= 1000) return '₡' + (numero / 1000).toLocaleString('es-CR', { maximumFractionDigits: 0 }) + ' mil';
        return '₡' + numero.toLocaleString('es-CR');
    };

    document.querySelectorAll('.plano__mesa').forEach((mesa, indice) => {
        mesa.style.setProperty('--orden-mesa', Math.min(indice, 30));
    });

    let grafico = null;

    function colorBarras(contexto) {
        const ultimo = contexto.dataIndex === totales.length - 1;
        return ultimo ? '#234C3B' : '#C9D8CE';
    }

    function configuracion(tipo) {
        const esLinea = tipo === 'line';

        return {
            type: tipo,
            data: {
                labels: etiquetas,
                datasets: [
                    {
                        label: 'Ventas',
                        data: totales,
                        order: 2,
                        backgroundColor: esLinea ? 'rgba(35, 76, 59, 0.10)' : colorBarras,
                        hoverBackgroundColor: esLinea ? 'rgba(35, 76, 59, 0.16)' : '#536B45',
                        borderColor: '#234C3B',
                        borderWidth: esLinea ? 2.5 : 0,
                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 46,
                        fill: esLinea,
                        tension: 0.35,
                        cubicInterpolationMode: 'monotone',
                        pointRadius: esLinea ? 3.5 : 0,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#234C3B',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'Promedio',
                        data: totales.map(() => promedio),
                        type: 'line',
                        order: 1,
                        borderColor: '#C5A56A',
                        borderWidth: 1.5,
                        borderDash: [6, 5],
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 900, easing: 'easeOutQuart' },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#183729',
                        padding: 12,
                        cornerRadius: 10,
                        titleFont: { weight: '700' },
                        displayColors: false,
                        callbacks: {
                            label: item => item.datasetIndex === 0 ? 'Ventas: ' + colones(item.parsed.y) : 'Promedio: ' + colones(item.parsed.y)
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { color: '#6b6b6b', font: { size: 12, weight: '600' } }
                    },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: '#efefef' },
                        ticks: { color: '#9e9e9e', font: { size: 11 }, maxTicksLimit: 5, callback: colonesCorto }
                    }
                }
            }
        };
    }

    if (lienzo && window.Chart) {
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Restaurante.alEntrar(() => {
            grafico = new Chart(lienzo, configuracion('bar'));
        });
    }

    document.querySelectorAll('.dash-grafico__boton').forEach(boton => {
        boton.addEventListener('click', () => {
            document.querySelectorAll('.dash-grafico__boton').forEach(otro => {
                const activo = otro === boton;
                otro.classList.toggle('activo', activo);
                otro.setAttribute('aria-pressed', activo ? 'true' : 'false');
            });

            if (!grafico) return;
            grafico.destroy();
            grafico = new Chart(lienzo, configuracion(boton.dataset.tipo));
        });
    });

    const buscador = document.getElementById('buscar-pedido');
    const filtro = document.getElementById('filtrar-estado');
    const lista = document.getElementById('lista-pedidos');
    const contador = document.getElementById('contador-pedidos');
    const sinResultados = document.getElementById('sin-resultados');

    if (!buscador || !filtro || !lista) return;

    const filas = Array.from(lista.querySelectorAll('tr[data-estado]'));

    function filtrarPedidos() {
        const texto = buscador.value.trim().toLowerCase();
        const estado = filtro.value;
        let visibles = 0;

        filas.forEach(fila => {
            const coincide = (!texto || fila.dataset.busqueda.includes(texto)) && (estado === 'todos' || fila.dataset.estado === estado);
            fila.hidden = !coincide;
            if (coincide) visibles++;
        });

        if (sinResultados) sinResultados.hidden = visibles > 0 || filas.length === 0;
        if (contador) contador.textContent = 'Mostrando ' + visibles + ' de ' + filas.length + ' pedidos';
    }

    buscador.addEventListener('input', filtrarPedidos);
    filtro.addEventListener('change', filtrarPedidos);
})();
