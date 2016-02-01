/**
 * Class Craft.CommerceOrderTableView
 */
Craft.CommerceOrderTableView = Craft.TableElementIndexView.extend({

    startDate: null,
    endDate: null,

	$chartExplorer: null,

	afterInit: function()
    {
        this.startDate = new Date();
        this.startDate.setDate(this.startDate.getDate() - 7);
        this.endDate = new Date();

        var $chartExplorer = $('<div class="chart-explorer"></div>').prependTo(this.$container),
            $chartHeader = $('<div class="chart-header"></div>').appendTo($chartExplorer),
            $dateRangeContainer = $('<div class="datewrapper" />').appendTo($chartHeader),
            $total = $('<div class="total"><strong>Total Revenue</strong></div>').appendTo($chartHeader),
            $totalCountWrapper = $('<div class="count-wrapper light"></div>').appendTo($total);


        this.$error = $('<div class="error">Example error.</div>').appendTo($chartHeader);
        this.$spinner = $('<div class="spinner hidden" />').appendTo($chartHeader);
        this.$totalCount = $('<span class="count">0</span>').appendTo($totalCountWrapper);
        this.$chartContainer = $('<div class="chart-container"></div>').appendTo($chartExplorer);

        this.$dateRange = $('<input type="text" class="text" />').appendTo($dateRangeContainer);

        this.dateRange = new Craft.DateRangePicker(this.$dateRange, {
            value: 'd7',
            onAfterSelect: $.proxy(this, 'onAfterDateRangeSelect')
        });

        this.loadReport(this.dateRange.startDate, this.dateRange.endDate);

		this.base();
	},

    onAfterDateRangeSelect: function(value, startDate, endDate)
    {
        this.loadReport(startDate, endDate)
    },

    loadReport: function(startDate, endDate)
    {
        var requestData = this.settings.params;

        requestData.startDate = startDate;
        requestData.endDate = endDate;

        this.$spinner.removeClass('hidden');
        this.$error.addClass('hidden');
        this.$chartContainer.removeClass('error');

        Craft.postActionRequest('commerce/reports/getOrders', requestData, $.proxy(function(response, textStatus)
        {
            this.$spinner.addClass('hidden');

            if(textStatus == 'success' && typeof(response.error) == 'undefined')
            {
                if(!this.chart)
                {
                    this.chart = new Craft.charts.Area(this.$chartContainer);
                }

                var chartDataTable = new Craft.charts.DataTable(response.reportDataTable);

                var chartSettings = {
                    currency: response.currencyFormat,
                    dataScale: response.scale
                };

                this.chart.draw(chartDataTable, chartSettings);

                this.$totalCount.html(response.totalHtml);

            }
            else
            {
                var msg = 'An unknown error occured.';

                if(typeof(response) != 'undefined' && response && typeof(response.error) != 'undefined')
                {
                    msg = response.error;
                }

                this.$error.html(msg);
                this.$error.removeClass('hidden');
                this.$chartContainer.addClass('error');
            }

        }, this));
    }
});