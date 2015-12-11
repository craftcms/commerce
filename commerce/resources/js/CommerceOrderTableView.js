/**
 * Class Craft.CommerceOrderTableView
 */
Craft.CommerceOrderTableView = Craft.TableElementIndexView.extend({
	$chartContainer: null,

	afterInit: function()
    {
		// Add the chart before the table
		this.$chartContainer = $('<svg class="chart"></svg>').prependTo(this.$container);

		// Error
		this.$error = $('<div class="error"/>').prependTo(this.$container);

        this.$loadReportBtn = $('<input type="button" value="Load" />');
        this.$loadReportBtn.prependTo(this.$container);
        this.addListener(this.$loadReportBtn, 'click', 'loadReport');

        this.$startDate = $('<input type="text" value="2015-12-02" />');
        this.$startDate.prependTo(this.$container);

        this.loadReport();

		this.base();
	},

    loadReport: function()
    {
        // Request orders report
        Craft.postActionRequest('commerce/reports/getOrders', { startDate: this.$startDate.val() }, $.proxy(function(response, textStatus)
        {
            if(textStatus == 'success' && typeof(response.error) == 'undefined')
            {
                // Create chart
                if(!this.chart)
                {
                    this.chart = new Craft.charts.Area(this.$chartContainer.get(0), this.params, response);
                }
                else
                {
                    this.chart.loadData(response);
                }
            }
            else
            {
                // Error

                var msg = 'An unknown error occured.';

                if(typeof(response) != 'undefined' && response && typeof(response.error) != 'undefined')
                {
                    msg = response.error;
                }

                this.$error.html(msg);
                this.$error.removeClass('hidden');
            }

        }, this));
    }
});
