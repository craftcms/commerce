/**
 * Class Craft.CommerceOrderTableView
 */
Craft.CommerceOrderTableView = Craft.TableElementIndexView.extend({

    startDate: null,
    endDate: null,

	$chartContainer: null,

	afterInit: function()
    {
        this.startDate = new Date();
        this.startDate.setDate(this.startDate.getDate() - 7);
        this.endDate = new Date();

		this.$chartContainer = $('<svg class="chart"></svg>').prependTo(this.$container);
		this.$error = $('<div class="error"/>').prependTo(this.$container);
        this.$chartControls = $('<div class="chart-controls"></div>');
        this.$chartControls.prependTo(this.$container);

        this.dateRange = new Craft.DateRangePicker(this.$chartControls, {
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

        Craft.postActionRequest('commerce/reports/getOrders', requestData, $.proxy(function(response, textStatus)
        {

            if(textStatus == 'success' && typeof(response.error) == 'undefined')
            {
                if(!this.chart)
                {
                    this.chart = new Craft.charts.Area(this.$chart, this.params, response.report);
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
            }

        }, this));
    }
});