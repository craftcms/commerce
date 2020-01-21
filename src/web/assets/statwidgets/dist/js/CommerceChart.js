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
                    titleFontColor: Craft.Commerce.ChartColors.text
                }
            }
        },
        line: {
            options: {
                aspectRatio: 2.5
            }
        },
        doughnut: {
            options: {
                aspectRatio: 1,
                cutoutPercentage: 60,
                legend: {
                    position: 'bottom'
                },
                rotation: 10
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
            backgroundColor: Craft.Commerce.ChartColors.borderColors(),
            borderColor: Craft.Commerce.ChartColors.borderColors(),
            borderWidth: 0
        },
        line: {
            borderWidth: 2,
            pointRadius: 0,
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