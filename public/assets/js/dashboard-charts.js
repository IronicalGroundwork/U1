Chart.defaults.global.defaultFontFamily = "Metropolis, -apple-system, system-ui, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
Chart.defaults.global.defaultFontColor = "#858796";

(() => {
    // Общие конфигурации
    const CHART_COLORS = {
        primary: "rgba(0, 97, 242, 1)",
        secondary: "rgba(227, 19, 191, 1)",
        tertiary: "rgba(255, 221, 0, 1)",
        success: "rgba(0, 172, 105, 1)",
        purple: "rgba(88, 0, 232, 1)"
    };

    const COMMON_OPTIONS = {
        maintainAspectRatio: false,
        layout: {
            padding: { left: 10, right: 25, top: 25, bottom: 0 }
        },
        legend: { display: false },
        tooltips: {
            backgroundColor: "rgb(255,255,255)",
            bodyFontColor: "#858796",
            titleFontColor: "#6e707e",
            titleFontSize: 14,
            borderColor: "#dddfeb",
            borderWidth: 1,
            xPadding: 15,
            yPadding: 15,
            displayColors: false,
            caretPadding: 10
        }
    };

    const AXES_CONFIG = {
        xAxisCommon: {
            gridLines: { display: false, drawBorder: false },
            ticks: { maxTicksLimit: 6 }
        },
        yAxisCommon: {
            ticks: { padding: 10, maxTicksLimit: 5 },
            gridLines: {
                color: "rgb(234, 236, 244)",
                zeroLineColor: "rgb(234, 236, 244)",
                drawBorder: false,
                borderDash: [2],
                zeroLineBorderDash: [2]
            }
        }
    };

    const COLORS_CLASSES = ['text-blue', 'text-green', 'text-purple', 'text-orange'];

    const renderWarehouseList = (warehouses) => {
        const container = document.querySelector('#warehouseChart').closest('.card-body').querySelector('.list-group');
        if (!container || !warehouses) return;

        const total = warehouses.reduce((sum, item) => sum +  parseInt(item.total), 0);
        
        warehouses.forEach((warehouse, index) => {
            const percent = total > 0 ? Math.round((warehouse.total / total) * 100) : 0;
            
            const item = document.createElement('div');
            item.className = 'list-group-item d-flex align-items-center justify-content-between small px-0 py-2';
            
            item.innerHTML = `
                <div class="me-3">
                    <i class="fas fa-circle fa-sm me-1 ${COLORS_CLASSES[index % 4]}"></i>
                    ${warehouse.name}
                </div>
                <div class="fw-500 text-dark">${percent}%</div>
            `;
            
            container.appendChild(item);
        });
    };

    // Инициализация графиков
    const initChart = (chartId, config) => {
        const canvas = document.getElementById(chartId);
        if (!canvas) return;
        
        new Chart(canvas.getContext('2d'), config);
    };

    // Основная функция
    const initCharts = () => {
        if (!window.chartData) return;

        const { movement, warehouse, platform } = window.chartData;

        // Линейный график
        movement && initChart('movementChart', {
            type: 'line',
            data: {
                labels: movement.labels,
                datasets: [{
                    label: 'Перемещений',
                    data: movement.values,
                    lineTension: 0.3,
                    backgroundColor: "rgba(0, 97, 242, 0.05)",
                    borderColor: CHART_COLORS.primary,
                    pointRadius: 3,
                    pointBackgroundColor: CHART_COLORS.primary,
                    pointBorderWidth: 2,
                    pointHoverRadius: 3,
                    pointHitRadius: 10
                }]
            },
            options: {
                ...COMMON_OPTIONS,
                scales: {
                    xAxes: [{ 
                        ...AXES_CONFIG.xAxisCommon,
                        time: { unit: "date" },
                        ticks: { maxTicksLimit: 7 }
                    }],
                    yAxes: [AXES_CONFIG.yAxisCommon]
                },
                tooltips: {
                    ...COMMON_OPTIONS.tooltips,
                    intersect: false,
                    mode: "index"
                }
            }
        });

        // Гистограмма
        platform && initChart('platformChart', {
            type: 'bar',
            data: {
                labels: platform.labels,
                datasets: [{
                    label: 'Количество',
                    data: platform.values,
                    backgroundColor: [CHART_COLORS.primary, CHART_COLORS.secondary, CHART_COLORS.tertiary],
                    hoverBackgroundColor: [
                        "rgba(0, 105, 255, 0.9)",
                        "rgba(227, 19, 191, 0.9)",
                        "rgba(255, 221, 0, 0.9)"
                    ],
                    borderColor: "#4e73df",
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                    maxBarThickness: 25
                }]
            },
            options: {
                ...COMMON_OPTIONS,
                scales: {
                    xAxes: [{
                        ...AXES_CONFIG.xAxisCommon,
                        time: { unit: "month" }
                    }],
                    yAxes: [{
                        ...AXES_CONFIG.yAxisCommon,
                        ticks: { min: 0 }
                    }]
                }
            }
        });

        // Круговая диаграмма
        warehouse && initChart('warehouseChart', {
            type: 'doughnut',
            data: {
                labels: warehouse.labels,
                datasets: [{
                    data: warehouse.values,
                    backgroundColor: [CHART_COLORS.primary, CHART_COLORS.success, CHART_COLORS.purple],
                    hoverBackgroundColor: [
                        "rgba(0, 97, 242, 0.9)",
                        "rgba(0, 172, 105, 0.9)",
                        "rgba(88, 0, 232, 0.9)"
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)"
                }]
            },
            options: {
                ...COMMON_OPTIONS,
                cutoutPercentage: 80
            }
        });

        renderWarehouseList(warehouse.data);
    };

    // Инициализация при готовности DOM
    document.readyState === 'complete' ? initCharts() : window.addEventListener('load', initCharts);
})();