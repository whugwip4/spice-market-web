(() => {
    const dataNode = document.getElementById('stats-data');

    if (!dataNode || typeof window.Chart === 'undefined') {
        return;
    }

    const specs = JSON.parse(dataNode.textContent || '{}');

    // Категориальная палитра на основе фирменных цветов Spice Market.
    // Порядок проверен валидатором палитр: цвета различимы и при нарушениях цветового зрения.
    const palette = ['#b64926', '#2f6fb0', '#d99a12', '#4f8a3c', '#9a4e8c'];
    const chrome = {
        ink: '#261a14',
        muted: '#756252',
        grid: '#eee6da',
        axis: '#d9ccb9',
        surface: '#ffffff',
    };

    const formats = {
        count: new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }),
        decimal: new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 }),
        money: new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }),
        percent: new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 }),
        ms: new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }),
    };
    const compact = new Intl.NumberFormat('ru-RU', { notation: 'compact', maximumFractionDigits: 1 });

    const formatValue = (unit, value) => {
        if (value === null || value === undefined) {
            return 'нет данных';
        }
        if (unit === 'percent') {
            return `${formats.percent.format(value)} %`;
        }
        if (unit === 'ms') {
            return `${formats.ms.format(value)} мс`;
        }
        if (unit === 'kb') {
            return `${formats.decimal.format(value)} КБ`;
        }
        return (formats[unit] || formats.count).format(value);
    };

    const formatTick = (unit, value) => {
        if (unit === 'percent') {
            return `${formats.percent.format(value)} %`;
        }
        if (unit === 'ms') {
            return `${compact.format(value)} мс`;
        }
        if (unit === 'money') {
            return `${compact.format(value)} ₽`;
        }
        return compact.format(value);
    };

    const alpha = (hex, opacity) => {
        const value = parseInt(hex.slice(1), 16);
        return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${opacity})`;
    };

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 12;
    Chart.defaults.color = chrome.muted;
    Chart.defaults.borderColor = chrome.grid;
    Chart.defaults.animation.duration = 400;

    const tooltip = {
        backgroundColor: chrome.ink,
        titleColor: '#ffffff',
        bodyColor: '#f6ede1',
        padding: 10,
        cornerRadius: 10,
        usePointStyle: true,
        boxPadding: 6,
    };

    const legend = (display, keyStyle) => ({
        display,
        position: 'top',
        align: 'start',
        labels: {
            usePointStyle: true,
            pointStyle: keyStyle,
            boxWidth: 18,
            boxHeight: 8,
            padding: 16,
            color: chrome.ink,
        },
    });

    const valueAxis = (spec) => ({
        beginAtZero: spec.suggested_min === undefined,
        suggestedMin: spec.suggested_min,
        max: spec.max,
        stacked: Boolean(spec.stacked),
        border: { display: false },
        grid: { color: chrome.grid },
        ticks: { maxTicksLimit: 5, callback: (value) => formatTick(spec.unit, value) },
    });

    const categoryAxis = {
        grid: { display: false },
        border: { color: chrome.axis },
        ticks: { autoSkip: true, maxRotation: 0 },
    };

    // Вертикальная линия по выбранной дате: подсказка показывает значения всех рядов в этой точке
    const crosshair = {
        id: 'crosshair',
        afterDatasetsDraw(chart) {
            const active = chart.tooltip ? chart.tooltip.getActiveElements() : [];
            if (!active.length) {
                return;
            }

            const { ctx, chartArea } = chart;
            const x = active[0].element.x;
            ctx.save();
            ctx.strokeStyle = chrome.axis;
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(x, chartArea.top);
            ctx.lineTo(x, chartArea.bottom);
            ctx.stroke();
            ctx.restore();
        },
    };

    // Значение у конца горизонтального столбца (и доля от суммы, если она нужна)
    const barValueLabels = {
        id: 'barValueLabels',
        afterDatasetsDraw(chart, args, options) {
            if (!options.enabled) {
                return;
            }

            const values = chart.data.datasets[0].data;
            // доля считается от суммы всех столбцов, а для воронки – от первого шага
            const total = (options.shareBase === 'first' ? values[0] : values.reduce((sum, value) => sum + value, 0)) || 1;
            const { ctx } = chart;

            ctx.save();
            ctx.fillStyle = chrome.ink;
            ctx.font = `600 12px ${Chart.defaults.font.family}`;
            ctx.textBaseline = 'middle';
            chart.getDatasetMeta(0).data.forEach((bar, index) => {
                let text = formatValue(options.unit, values[index]);
                if (options.share) {
                    text += ` · ${formats.percent.format(values[index] / total * 100)} %`;
                }
                ctx.fillText(text, bar.x + 6, bar.y);
            });
            ctx.restore();
        },
    };

    const lineConfig = (spec) => {
        const single = spec.series.length === 1;

        return {
            type: 'line',
            data: {
                labels: spec.labels,
                datasets: spec.series.map((series, index) => ({
                    label: series.name,
                    data: series.values,
                    borderColor: palette[index],
                    backgroundColor: single ? alpha(palette[index], 0.1) : palette[index],
                    fill: single ? 'origin' : false,
                    borderWidth: 2,
                    tension: 0.3,
                    cubicInterpolationMode: 'monotone',
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBorderWidth: 2,
                    pointHoverBorderColor: chrome.surface,
                    pointHoverBackgroundColor: palette[index],
                    pointHitRadius: 12,
                })),
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: legend(!single, 'line'),
                    tooltip: {
                        ...tooltip,
                        callbacks: {
                            label: (context) => ` ${formatValue(spec.unit, context.parsed.y)}  ${context.dataset.label}`,
                            labelPointStyle: () => ({ pointStyle: 'line', rotation: 0 }),
                        },
                    },
                },
                scales: {
                    x: { ...categoryAxis, ticks: { ...categoryAxis.ticks, maxTicksLimit: 10 } },
                    y: valueAxis(spec),
                },
            },
            plugins: [crosshair],
        };
    };

    const barConfig = (spec, horizontal) => ({
        type: 'bar',
        data: {
            labels: spec.labels,
            datasets: spec.series.map((series, index) => ({
                label: series.name,
                data: series.values,
                backgroundColor: palette[index],
                hoverBackgroundColor: alpha(palette[index], 0.78),
                // в составном столбце части разделены белой полосой, скругляется только верх
                borderRadius: spec.stacked ? 0 : 4,
                borderSkipped: 'start',
                borderWidth: spec.stacked ? { top: 2 } : 0,
                borderColor: chrome.surface,
                maxBarThickness: 24,
                categoryPercentage: 0.8,
                barPercentage: 0.9,
            })),
        },
        options: {
            indexAxis: horizontal ? 'y' : 'x',
            maintainAspectRatio: false,
            layout: { padding: { right: horizontal ? (spec.show_share ? 96 : 56) : 0 } },
            interaction: { mode: 'nearest', axis: horizontal ? 'y' : 'x', intersect: false },
            plugins: {
                legend: legend(spec.series.length > 1, 'rect'),
                tooltip: {
                    ...tooltip,
                    callbacks: {
                        label: (context) => ` ${formatValue(spec.unit, horizontal ? context.parsed.x : context.parsed.y)}  ${context.dataset.label}`,
                    },
                },
                barValueLabels: { enabled: horizontal, share: Boolean(spec.show_share), shareBase: spec.share_base || 'sum', unit: spec.unit },
            },
            scales: horizontal
                ? {
                    x: valueAxis(spec),
                    y: { ...categoryAxis, ticks: { color: chrome.ink, autoSkip: false } },
                }
                : {
                    x: { ...categoryAxis, stacked: Boolean(spec.stacked), ticks: { ...categoryAxis.ticks, maxTicksLimit: spec.labels.length <= 24 ? 24 : 16 } },
                    y: valueAxis(spec),
                },
        },
        plugins: [barValueLabels],
    });

    const doughnutConfig = (spec, container) => {
        const values = spec.series[0].values;
        const total = values.reduce((sum, value) => sum + value, 0) || 1;
        const share = (value) => `${formats.percent.format(value / total * 100)} %`;
        const generateLabels = Chart.overrides.doughnut.plugins.legend.labels.generateLabels;

        return {
            type: 'doughnut',
            data: {
                labels: spec.labels,
                datasets: [{
                    label: spec.series[0].name,
                    data: values,
                    backgroundColor: palette.slice(0, values.length),
                    borderColor: chrome.surface,
                    borderWidth: 2,
                    hoverOffset: 6,
                }],
            },
            options: {
                maintainAspectRatio: false,
                cutout: '62%',
                layout: { padding: 6 },
                plugins: {
                    legend: {
                        position: container.clientWidth > 440 ? 'right' : 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'rect',
                            padding: 14,
                            color: chrome.ink,
                            generateLabels: (chart) => generateLabels(chart).map((item) => ({
                                ...item,
                                text: `${item.text} – ${share(values[item.index])}`,
                            })),
                        },
                    },
                    tooltip: {
                        ...tooltip,
                        callbacks: {
                            label: (context) => ` ${formatValue(spec.unit, context.parsed)} (${share(context.parsed)})  ${context.label}`,
                        },
                    },
                },
            },
        };
    };

    const builders = {
        line: (spec) => lineConfig(spec),
        bar: (spec) => barConfig(spec, false),
        hbar: (spec) => barConfig(spec, true),
        doughnut: (spec, container) => doughnutConfig(spec, container),
    };

    // Табличное представление каждого графика: те же данные, доступные без наведения мыши
    const renderTable = (container, table) => {
        const tableElement = document.createElement('table');
        const headRow = tableElement.createTHead().insertRow();

        table.columns.forEach((column) => {
            const cell = document.createElement('th');
            cell.textContent = column;
            headRow.appendChild(cell);
        });

        const body = tableElement.createTBody();
        table.rows.forEach((row) => {
            const tableRow = body.insertRow();
            row.forEach((value) => {
                tableRow.insertCell().textContent = value;
            });
        });

        container.replaceChildren(tableElement);
    };

    const isEmpty = (spec) => !spec.series.length || spec.series.every((series) => series.values.every((value) => !value));

    window.statsCharts = {};

    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        const key = canvas.dataset.chart;
        const spec = specs[key];
        const box = canvas.parentElement;

        if (!spec || !builders[spec.type]) {
            return;
        }

        const tableContainer = document.querySelector(`[data-chart-table="${key}"]`);
        if (tableContainer && spec.table) {
            renderTable(tableContainer, spec.table);
        }

        if (isEmpty(spec)) {
            const empty = document.createElement('div');
            empty.className = 'chart-empty';
            empty.textContent = 'Нет данных за выбранный период';
            box.replaceChildren(empty);
            return;
        }

        window.statsCharts[key] = new Chart(canvas, builders[spec.type](spec, box));
    });
})();
