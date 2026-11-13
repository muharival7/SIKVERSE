(() => {
    const svgNamespace = 'http://www.w3.org/2000/svg';

    document.querySelectorAll('[data-graduation-chart]').forEach((chart) => {
        const source = document.getElementById(chart.dataset.chartId);
        const canvas = chart.querySelector('[data-chart-canvas]');
        const emptyState = chart.querySelector('[data-chart-empty]');

        if (!source || !canvas) {
            return;
        }

        let rows;

        try {
            rows = JSON.parse(source.textContent || '[]');
        } catch {
            rows = [];
        }

        if (!Array.isArray(rows) || rows.length === 0) {
            if (emptyState) {
                emptyState.hidden = false;
            }

            return;
        }

        if (emptyState) {
            emptyState.hidden = true;
        }

        canvas.replaceChildren(chart.dataset.graduationChart === 'line'
            ? createLineChart(rows, chart.dataset.chartId)
            : createComparisonChart(rows));
    });

    function createLineChart(rows, id) {
        const width = 760;
        const height = 300;
        const plot = { left: 52, right: 20, top: 24, bottom: 52 };
        const plotWidth = width - plot.left - plot.right;
        const plotHeight = height - plot.top - plot.bottom;
        const maxValue = niceMaximum(Math.max(...rows.map((row) => number(row.lulus))));
        const svg = createSvg(width, height, `${id}-title`, 'Jumlah peserta yang lulus pada setiap tahun.');
        const definitions = createSvgElement('defs');
        const gradient = createSvgElement('linearGradient', {
            id: `${id}-fill`, x1: '0', x2: '0', y1: '0', y2: '1',
        });

        gradient.append(
            createSvgElement('stop', { offset: '0%', 'stop-color': '#4c9b7d', 'stop-opacity': '.2' }),
            createSvgElement('stop', { offset: '100%', 'stop-color': '#4c9b7d', 'stop-opacity': '0' }),
        );
        definitions.append(gradient);
        svg.append(definitions);

        for (let step = 0; step <= 4; step += 1) {
            const value = Math.round((maxValue / 4) * step);
            const y = plot.top + plotHeight - (value / maxValue) * plotHeight;

            svg.append(createSvgElement('line', {
                x1: plot.left, x2: width - plot.right, y1: y, y2: y, class: 'sd-chart-grid',
            }));
            svg.append(createSvgElement('text', {
                x: plot.left - 10, y: y + 4, class: 'sd-chart-axis-label', 'text-anchor': 'end',
            }, value.toLocaleString('id-ID')));
        }

        const plotted = rows.map((row, index) => {
            const x = rows.length === 1
                ? plot.left + (plotWidth / 2)
                : plot.left + (index / (rows.length - 1)) * plotWidth;
            const total = number(row.lulus);

            return {
                x,
                y: plot.top + plotHeight - (total / maxValue) * plotHeight,
                total,
                year: String(row.tahun ?? ''),
            };
        });
        const path = plotted.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(1)} ${point.y.toFixed(1)}`).join(' ');
        const areaPath = `${path} L ${plotted.at(-1).x.toFixed(1)} ${plot.top + plotHeight} L ${plotted[0].x.toFixed(1)} ${plot.top + plotHeight} Z`;

        svg.append(createSvgElement('path', { d: areaPath, class: 'sd-kalab-line-area' }));
        svg.append(createSvgElement('path', { d: path, class: 'sd-kalab-line' }));

        plotted.forEach((point) => {
            svg.append(createSvgElement('circle', {
                cx: point.x, cy: point.y, r: 4.5, class: 'sd-kalab-line-point',
            }));
            svg.append(createSvgElement('text', {
                x: point.x, y: point.y - 12, class: 'sd-kalab-value-label', 'text-anchor': 'middle',
            }, point.total.toLocaleString('id-ID')));
            svg.append(createSvgElement('text', {
                x: point.x, y: height - 17, class: 'sd-chart-x-label', 'text-anchor': 'middle',
            }, point.year));
        });

        return svg;
    }

    function createComparisonChart(rows) {
        const width = 760;
        const height = 300;
        const plot = { left: 52, right: 20, top: 24, bottom: 52 };
        const plotWidth = width - plot.left - plot.right;
        const plotHeight = height - plot.top - plot.bottom;
        const maxValue = niceMaximum(Math.max(...rows.flatMap((row) => [number(row.lulus), number(row.tidak_lulus)])));
        const groupWidth = plotWidth / rows.length;
        const barWidth = Math.min(30, groupWidth * .24);
        const svg = createSvg(width, height, 'graduation-comparison-title', 'Perbandingan jumlah peserta lulus dan tidak lulus tiap tahun.');

        for (let step = 0; step <= 4; step += 1) {
            const value = Math.round((maxValue / 4) * step);
            const y = plot.top + plotHeight - (value / maxValue) * plotHeight;

            svg.append(createSvgElement('line', {
                x1: plot.left, x2: width - plot.right, y1: y, y2: y, class: 'sd-chart-grid',
            }));
            svg.append(createSvgElement('text', {
                x: plot.left - 10, y: y + 4, class: 'sd-chart-axis-label', 'text-anchor': 'end',
            }, value.toLocaleString('id-ID')));
        }

        rows.forEach((row, index) => {
            const center = plot.left + (groupWidth * index) + (groupWidth / 2);
            const bars = [
                { value: number(row.lulus), offset: -barWidth - 3, className: 'sd-kalab-bar-pass' },
                { value: number(row.tidak_lulus), offset: 3, className: 'sd-kalab-bar-fail' },
            ];

            bars.forEach((bar) => {
                const barHeight = (bar.value / maxValue) * plotHeight;
                const x = center + bar.offset;
                const y = plot.top + plotHeight - barHeight;

                svg.append(createSvgElement('rect', {
                    x, y, width: barWidth, height: barHeight, rx: 3, class: bar.className,
                }));

                if (bar.value > 0) {
                    svg.append(createSvgElement('text', {
                        x: x + (barWidth / 2), y: y - 7, class: 'sd-kalab-value-label', 'text-anchor': 'middle',
                    }, bar.value.toLocaleString('id-ID')));
                }
            });

            svg.append(createSvgElement('text', {
                x: center, y: height - 17, class: 'sd-chart-x-label', 'text-anchor': 'middle',
            }, String(row.tahun ?? '')));
        });

        return svg;
    }

    function createSvg(width, height, titleId, description) {
        const svg = createSvgElement('svg', {
            viewBox: `0 0 ${width} ${height}`,
            role: 'img',
            'aria-labelledby': `${titleId} ${titleId}-description`,
            preserveAspectRatio: 'xMidYMid meet',
        });

        svg.append(
            createSvgElement('title', { id: titleId }, description),
            createSvgElement('desc', { id: `${titleId}-description` }, description),
        );

        return svg;
    }

    function createSvgElement(tagName, attributes = {}, text = '') {
        const element = document.createElementNS(svgNamespace, tagName);

        Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, String(value)));

        if (text) {
            element.textContent = text;
        }

        return element;
    }

    function number(value) {
        const parsed = Number(value);

        return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    }

    function niceMaximum(value) {
        return Math.max(10, Math.ceil(value / 10) * 10);
    }
})();
