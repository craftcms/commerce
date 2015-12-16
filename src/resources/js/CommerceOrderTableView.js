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

        this.$chartExplorer = $('<div class="chart-explorer"></div>').prependTo(this.$container);
        this.$chartHeader = $('<div class="chart-header"></div>').appendTo(this.$chartExplorer);
        this.$error = $('<div class="error">Example error.</div>').appendTo(this.$chartHeader);
        this.$spinner = $('<div class="spinner hidden" />').appendTo(this.$chartHeader);
        this.$chartContainer = $('<div class="chart-container"></div>').appendTo(this.$chartExplorer);

        this.dateRange = new Craft.DateRangePicker(this.$chartHeader, {
            startDate: this.startDate,
            endDate: this.endDate,
            onAfterSelect: $.proxy(this, 'loadReport')
        });

        this.loadReport();

		this.base();
	},

    loadReport: function()
    {
        var requestData = {
            startDate: this.dateRange.startDate,
            endDate: this.dateRange.endDate,
        };

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
                    this.chart = new Craft.charts.Area(this.$chartContainer, this.params, response.report);
                }
                else
                {
                    this.chart.loadData(response.report);
                }
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