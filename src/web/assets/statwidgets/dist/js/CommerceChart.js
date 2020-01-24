/* globals Craft, Garnish, Chart, deepmerge, $ */
if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.ChartColors
 */
Craft.Commerce.ChartColors = {
    blue: {
        bg: 'rgba(66,153,225, 0.1)',
        border: '#4299E1'
    },
    cyan: {
        bg: 'rgba(56, 190, 201, 0.1)',
        border: 'rgba(56, 190, 201, 0.75)'
    },
    orange: {
        bg: 'rgba(237, 137, 54, 0.1)',
        border: '#ED8936'
    },
    red: {
        bg: 'rgba(245, 101, 101, 0.1)',
        border: '#F56565'
    },
    green: {
        bg: 'rgba(72, 187, 120, 0.1)',
        border: '#48BB78'
    },
    purple: {
        bg: 'rgba(128, 90, 213, 0.1)',
        border: '#805AD5'
    },
    grey: {
        bg: 'rgb(160, 174, 192, 0.1)',
        border: '#A0AEC0'
    },

    gridLines: 'rgba(155, 155, 155, 0.1)',
    text: 'hsl(209, 18%, 30%)',

    bgColors: function() {
        return [
            this.blue.bg,
            this.red.bg,
            this.orange.bg,
            this.green.bg,
            this.purple.bg,
            this.cyan.bg
        ];
    },

    borderColors: function() {
        return [
            this.blue.border,
            this.red.border,
            this.orange.border,
            this.green.border,
            this.purple.border,
            this.cyan.border
        ];
    },

    doughnutColors: function() {
        return [
            this.blue.border,
            this.red.border,
            this.orange.border,
            this.green.border,
            this.purple.border,
            this.grey.border
        ];
    }
};

/**
 * Class Craft.Commerce.Chart
 */
Craft.Commerce.Chart = Garnish.Base.extend({

    /**
     * Default options key'd by chart type
     */
    defaults: {
        general: {
            options: {
                legend: {
                    labels: {
                        boxWidth: 8,
                        usePointStyle: true
                    }
                },
                tooltips: {
                    bodyFontColor: Craft.Commerce.ChartColors.text,
                    backgroundColor: '#fff',
                    borderColor: Craft.Commerce.ChartColors.gridLines,
                    borderWidth: 1,
                    caretPadding: 6,
                    caretSize: 0,
                    mode: 'index',
                    titleFontColor: Craft.Commerce.ChartColors.text,

                    enabled: false,
                    custom: function(tooltipModel) {
                        // Tooltip Element
                        var tooltipEl = document.getElementById('chartjs-tooltip');

                        // Create element on first render
                        if (!tooltipEl) {
                            tooltipEl = document.createElement('div');
                            tooltipEl.id = 'chartjs-tooltip';
                            tooltipEl.innerHTML = '<div class="chartjs-tooltip-container"></div>';
                            document.body.appendChild(tooltipEl);
                        }

                        tooltipEl.classList.add('commerce-widget-chart-tooltip');

                        // Hide if no tooltip
                        if (tooltipModel.opacity === 0) {
                            tooltipEl.style.opacity = 0;
                            return;
                        }

                        // Set caret Position
                        tooltipEl.classList.remove('above', 'below', 'no-transform');
                        if (tooltipModel.yAlign) {
                            tooltipEl.classList.add(tooltipModel.yAlign);
                        } else {
                            tooltipEl.classList.add('no-transform');
                        }

                        function getBody(bodyItem) {
                            return bodyItem.lines;
                        }

                        // Set Text
                        if (tooltipModel.body) {
                            var titleLines = tooltipModel.title || [];
                            var bodyLines = tooltipModel.body.map(getBody);

                            var innerHtml = '<div>';

                            titleLines.forEach(function(title) {
                                innerHtml += '<h3>' + title + '</h3>';
                            });

                            bodyLines.forEach(function(body, i) {
                                if (body.length) {
                                    var bodyParts = body[0].split(': ');
                                    if (bodyParts.length) {
                                        body = bodyParts[(bodyParts.length - 1)];
                                    }
                                }

                                var colors = tooltipModel.labelColors[i];
                                var style = 'background:' + colors.backgroundColor;
                                style += '; border-color:' + colors.borderColor;
                                var span = '<span class="legend-dot" style="' + style + '"></span>';
                                innerHtml += '<div class="commerce-widget-chart-tooltip-items">' + span + '<span>' + body + '</span>' + '</div>';
                            });
                            innerHtml += '</div>';

                            var tableRoot = tooltipEl.querySelector('.chartjs-tooltip-container');
                            tableRoot.innerHTML = innerHtml;
                        }

                        // `this` will be the overall tooltip
                        var position = this._chart.canvas.getBoundingClientRect();

                        // Display, position, and set styles for font
                        tooltipEl.style.opacity = 1;
                        tooltipEl.style.position = 'absolute';
                        tooltipEl.style.left = position.left + window.pageXOffset + tooltipModel.caretX + 'px';
                        tooltipEl.style.top = position.top + window.pageYOffset + tooltipModel.caretY + 'px';
                        tooltipEl.style.fontFamily = tooltipModel._bodyFontFamily;
                        tooltipEl.style.fontSize = tooltipModel.bodyFontSize + 'px';
                        tooltipEl.style.fontStyle = tooltipModel._bodyFontStyle;
                        tooltipEl.style.pointerEvents = 'none';
                    }
                }
            }
        },
        line: {
            options: {
                aspectRatio: 2.5,
                legend: {
                    labels: {
                        boxWidth: 6,
                    }
                },
            },
        },
        doughnut: {
            options: {
                aspectRatio: 1,
                cutoutPercentage: 60,
                legend: {
                    position: 'bottom'
                }
            }
        }
    },

    /**
     * Default dataset options key'd by chart type
     */
    datasetDefaults: {
        general: {

        },
        doughnut: {
            backgroundColor: Craft.Commerce.ChartColors.doughnutColors(),
            borderColor: Craft.Commerce.ChartColors.doughnutColors(),
            borderWidth: 0
        },
        line: {
            borderWidth: 3,
            pointRadius: 2,
            pointHitRadius: 4,
            lineTension: 0
        }
    },

    /**
     * Global defaults
     */
    globalDefaults: {
        defaultFontFamily: "system-ui, BlinkMacSystemFont, -apple-system, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif"
    },

    /**
     * RTL options
     * These are separated from the defaults so they are forced
     */
    rtl: false,
    rtlDefaults: {
        options: {
            legend: {
                rtl: true
            },
            tooltips: {
                rtl: true
            }
        }
    },

    chart: null,

    init: function(id, settings) {
        this.$container = $('#' + id);
        this.rtl = $('body').hasClass('rtl');

        if (this.$container.length && settings.chart) {
            var options = this.getDefaultOptions(settings.chart.type);

            // Merge user defined options with defaults
            options = deepmerge(options, settings.chart);

            options = this.mergeRtlOptions(options);

            if (options.data && options.data.datasets && options.data.datasets.length) {
                options.data.datasets = this.mergeDatasetsDefaults(options.data.datasets, options.type);
            }

            this.renderChart(options);
        }
    },

    getDefaultOptions: function(type) {
        var options = this.defaults.general;

        if (this.defaults[type]) {
            options = deepmerge(options, this.defaults[type]);
        }

        return options;
    },

    mergeDatasetsDefaults: function(datasets, type) {
        if (this.datasetDefaults[type] == undefined) {
            return datasets;
        }

        var mergedDatasets = [];
        var colorsIndex = 0;
        var tmp;

        for (var i = 0; i < datasets.length; i++) {
            tmp = deepmerge(this.datasetDefaults[type], datasets[i]);

            // Loop through colours for line charts
            if (type == 'line') {
                tmp = deepmerge(tmp, {
                    backgroundColor: Craft.Commerce.ChartColors.bgColors()[colorsIndex],
                    borderColor: Craft.Commerce.ChartColors.borderColors()[colorsIndex],
                    pointBackgroundColor: Craft.Commerce.ChartColors.borderColors()[colorsIndex]
                });
            }

            mergedDatasets.push(tmp);

            colorsIndex++;
            if (colorsIndex == Craft.Commerce.ChartColors.bgColors().length) {
                colorsIndex = 0;
            }
        }

        return mergedDatasets;
    },

    mergeRtlOptions: function(options) {
        if (!this.rtl) {
            return options;
        }

        return deepmerge(options, this.rtlDefaults);
    },

    renderChart: function(options) {
        Chart.defaults.global = deepmerge(Chart.defaults.global, this.globalDefaults);

        this.chart = new Chart(this.$container, options);
    }
});