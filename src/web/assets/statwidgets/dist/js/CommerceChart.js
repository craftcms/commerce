/* globals Craft, Garnish, Chart, deepmerge */
if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.Chart
 */
Craft.Commerce.ChartColors = {
    blue: {
        bg: 'rgba(152, 211, 236, 0.4)',
        border: 'rgba(152, 211, 236, 0.75)'
    },
    cyan: {
        bg: 'rgba(56, 190, 201, 0.4)',
        border: 'rgba(56, 190, 201, 0.75)'
    },
    pink: {
        bg: 'rgba(232, 81, 158,0.4)',
        border: 'rgba(232, 81, 158, 0.75)'
    },
    red: {
        bg: 'rgba(255, 153, 153, 0.4)',
        border: 'rgba(255, 153, 153, 0.75)'
    },
    teal: {
        bg: 'rgba(39, 171, 131, 0.4)',
        border: 'rgba(39, 171, 131, 0.75)'
    },
    yellow: {
        bg: 'rgba(240, 180, 41, 0.4)',
        border: 'rgba(240, 180, 41, 0.75)'
    },

    gridLines: 'rgba(155, 155, 155, 0.1)',
    text: 'hsl(209, 18%, 30%)',

    bgColors: function() {
        return [
            this.blue.bg,
            this.cyan.bg,
            this.pink.bg,
            this.red.bg,
            this.teal.bg,
            this.yellow.bg
        ];
    },

    borderColors: function() {
        return [
            this.blue.border,
            this.cyan.border,
            this.pink.border,
            this.red.border,
            this.teal.border,
            this.yellow.border
        ];
    }
};

Craft.Commerce.Chart = Garnish.Base.extend({

    defaults: {
        options: {
            legend: {
                labels: {
                    boxWidth: 16
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
            var options = deepmerge(this.defaults, settings.chart);
            options = this.mergeRtlOptions(options);

            this.renderChart(options);
        }
    },

    mergeRtlOptions: function(chart) {
        if (!this.rtl) {
            return chart;
        }

        return deepmerge(chart, this.rtlDefaults);
    },

    renderChart: function(options) {
        Chart.defaults.global.defaultFontFamily = "system-ui, BlinkMacSystemFont, -apple-system, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif";
        this.chart = new Chart(this.$container, options);
    }
});