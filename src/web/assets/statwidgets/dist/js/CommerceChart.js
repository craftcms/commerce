/* globals Craft, Garnish, Chart */
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
    text: 'hsl(209, 18%, 30%)'
};

Craft.Commerce.Chart = Garnish.Base.extend({

    options: {
      tooltips: {
          bodyFontColor: Craft.Commerce.ChartColors.text,
          backgroundColor: '#fff',
          borderColor: Craft.Commerce.ChartColors.gridLines,
          borderWidth: 1,
          titleFontColor: Craft.Commerce.ChartColors.text
      }
    },
    rtl: false,
    chart: null,

    init: function(id, settings) {
        this.$container = $('#' + id);
        this.rtl = $('body').hasClass('rtl');

        if (this.$container.length && settings.chart) {
            var options = this.mergeDefaults(settings.chart);
            options = this.mergeRtlOptions(options);

            this.renderChart(options);
        }
    },

    mergeDefaults: function(chart) {
        var options = chart.options;
        chart.options = Object.assign({}, this.options, options);

        return chart;
    },

    mergeRtlOptions: function(chart) {
        if (!this.rtl) {
            return chart;
        }

        var options = Object.assign({}, chart.options);

        if (options.legend) {
            options.legend = Object.assign({}, options.legend, { rtl: true });
        } else {
            options.legend = { rtl: true };
        }

        if (options.tooltips) {
            options.tooltips = Object.assign({}, options.tooltips, { rtl: true });
        } else {
            options.tooltips = { rtl: true };
        }

        return Object.assign({}, chart, { options: options });
    },

    renderChart: function(options) {
        this.chart = new Chart(this.$container, options);
    }
});