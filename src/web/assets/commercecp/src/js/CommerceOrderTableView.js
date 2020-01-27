if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.OrderTableView
 */
Craft.Commerce.OrderTableView = Craft.TableElementIndexView.extend({

    $chartExplorer: null,
    $totalValue: null,
    $chartContainer: null,
    $spinner: null,
    $error: null,
    $chart: null,

    afterInit: function() {
        this.$explorerContainer = $('<div class="chart-container"></div>').prependTo(this.$container);

        this.createChart();

        this.base();
    },

    createChart: function() {
        var $chartExplorer = $('<div class="chart-explorer"></div>').appendTo(this.$explorerContainer);
        var $chartHeader = $('<div class="chart-header"></div>').appendTo($chartExplorer);
        var $total = $('<div class="total"></div>').appendTo($chartHeader);
        var $totalLabel = $('<div class="total-label light">' + Craft.t('commerce', 'Total Revenue') + '</div>').appendTo($total);
        var $totalValueWrapper = $('<div class="total-value-wrapper"></div>').appendTo($total);
        var $totalValue = $('<span class="total-value">&nbsp;</span>').appendTo($totalValueWrapper);

        this.$chartExplorer = $chartExplorer;
        this.$totalValue = $totalValue;
        this.$chartContainer = $('<div class="chart-container"></div>').appendTo($chartExplorer);
        this.$spinner = $('<div class="spinner hidden" />').prependTo($chartHeader);
        this.$error = $('<div class="error"></div>').appendTo(this.$chartContainer);
        this.$chart = $('<div class="chart"></div>').appendTo(this.$chartContainer);

        this.loadChart();
    },

    loadChart: function() {
        var requestData = this.settings.params;

        this.$spinner.removeClass('hidden');
        this.$error.addClass('hidden');
        this.$chart.removeClass('error');

        Craft.postActionRequest('commerce/charts/get-revenue-data', requestData, $.proxy(function(response, textStatus) {
            this.$spinner.addClass('hidden');

            if (textStatus === 'success' && typeof (response.error) === 'undefined') {
                if (!this.chart) {
                    this.chart = new Craft.charts.Area(this.$chart);
                }

                var chartDataTable = new Craft.charts.DataTable(response.dataTable);
                var chartSettings = {
                    formatLocaleDefinition: response.formatLocaleDefinition,
                    orientation: response.orientation,
                    formats: response.formats,
                    dataScale: response.scale
                };

                this.chart.draw(chartDataTable, chartSettings);
                this.$totalValue.html(response.totalHtml);

            } else {
                var msg = Craft.t('commerce', 'An unknown error occurred.');

                if (typeof (response) !== 'undefined' && response && typeof (response.error) !== 'undefined') {
                    msg = response.error;
                }

                this.$error.html(msg);
                this.$error.removeClass('hidden');
                this.$chart.addClass('error');
            }
        }, this));
    }
});
