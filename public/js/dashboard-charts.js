(() => {
    const svgNamespace = 'http://www.w3.org/2000/svg';

    document.querySelectorAll('[data-attendance-chart]').forEach((chart) => {
        const dataElement = document.getElementById(chart.dataset.trendId);
        const canvas = chart.querySelector('[data-chart-canvas]');
        const emptyState = chart.querySelector('[data-chart-empty]');

        if (!dataElement || !canvas) {
            return;
        }

        let points;

        try {
            points = JSON.parse(dataElement.textContent || '[]');
        } catch {
            points = [];
        }

        if (!Array.isArray(points) || points.length === 0) {
            if (emptyState) {
                emptyState.hidden = false;
            }

            return;
        }

        if (emptyState) {
            emptyState.hidden = true;
        }

        const width = 760;
        const height = chart.dataset.chartSize === 'compact' ? 210 : 280;
        const plot = { left: 48, right: 18, top: 18, bottom: 48 };
        const plotWidth = width - plot.left - plot.right;
        const plotHeight = height - plot.top - plot.bottom;
        const baseline = plot.top + plotHeight;
        const svg = createSvgElement('svg', {
            viewBox: `0 0 ${width} ${height}`,
            role: 'img',
            'aria-labelledby': 'sd-trend-chart-title sd-trend-chart-description',
            preserveAspectRatio: 'xMidYMid meet',
        });

        svg.append(createSvgElement('title', { id: 'sd-trend-chart-title' }, 'Tren kehadiran bulanan'));
        svg.append(createSvgElement(
            'desc',
            { id: 'sd-trend-chart-description' },
            'Grafik garis menunjukkan persentase hadir dari catatan absensi setiap bulan.',
        ));

        const definitions = createSvgElement('defs');
        const gradient = createSvgElement('linearGradient', {
            id: 'sd-trend-fill',
            x1: '0',
            x2: '0',
            y1: '0',
            y2: '1',
        });
        gradient.append(
            createSvgElement('stop', { offset: '0%', 'stop-color': '#a53538', 'stop-opacity': '.2' }),
            createSvgElement('stop', { offset: '100%', 'stop-color': '#a53538', 'stop-opacity': '0' }),
        );
        definitions.append(gradient);
        svg.append(definitions);

        [0, 50, 100].forEach((value) => {
            const y = plot.top + plotHeight - (value / 100) * plotHeight;

            svg.append(createSvgElement('line', {
                x1: plot.left,
                x2: width - plot.right,
                y1: y,
                y2: y,
                class: 'sd-chart-grid',
            }));
            svg.append(createSvgElement(
                'text',
                { x: plot.left - 12, y: y + 4, class: 'sd-chart-axis-label', 'text-anchor': 'end' },
                `${value}%`,
            ));
        });

        const plottedPoints = points.map((point, index) => {
            const value = Math.max(0, Math.min(100, Number(point.persentase) || 0));
            const x = points.length === 1
                ? plot.left + plotWidth / 2
                : plot.left + (index / (points.length - 1)) * plotWidth;
            const y = plot.top + plotHeight - (value / 100) * plotHeight;

            return { ...point, value, x, y };
        });

        const linePath = plottedPoints
            .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(1)} ${point.y.toFixed(1)}`)
            .join(' ');
        const areaPath = `${linePath} L ${plottedPoints.at(-1).x.toFixed(1)} ${baseline} L ${plottedPoints[0].x.toFixed(1)} ${baseline} Z`;

        svg.append(createSvgElement('path', { d: areaPath, class: 'sd-chart-area' }));

        const line = createSvgElement('path', {
            d: linePath,
            class: 'sd-chart-line',
            pathLength: '1',
        });
        svg.append(line);

        plottedPoints.forEach((point) => {
            const marker = createSvgElement('circle', {
                cx: point.x,
                cy: point.y,
                r: 5,
                class: 'sd-chart-point',
                tabindex: '0',
                'aria-label': `${point.label}: ${point.value}% hadir, ${point.hadir} dari ${point.total} catatan`,
            });

            marker.append(createSvgElement(
                'title',
                {},
                `${point.label}: ${point.value}% hadir (${point.hadir}/${point.total})`,
            ));
            svg.append(marker);

            svg.append(createSvgElement(
                'text',
                {
                    x: point.x,
                    y: baseline + 25,
                    class: 'sd-chart-x-label',
                    'text-anchor': 'middle',
                },
                point.label,
            ));
        });

        canvas.replaceChildren(svg);
        requestAnimationFrame(() => line.classList.add('is-visible'));
    });

    function createSvgElement(tagName, attributes = {}, text = '') {
        const element = document.createElementNS(svgNamespace, tagName);

        Object.entries(attributes).forEach(([name, value]) => {
            element.setAttribute(name, String(value));
        });

        if (text) {
            element.textContent = text;
        }

        return element;
    }
})();
