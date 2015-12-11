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

        this.$loadReportBtn = $('<input type="button" value="Update Chart" />');
        this.$loadReportBtn.prependTo(this.$container);
        this.addListener(this.$loadReportBtn, 'click', 'loadReport');

        this.$chartControls = $('<div class="chart-controls"></div>');
        this.$chartControls.prependTo(this.$container);

        this.$startDateWrapper = $('<div class="datewrapper"></div>');
        this.$startDateWrapper.appendTo(this.$chartControls);

        this.$startDate = $('<input type="text" class="text" size="10" autocomplete="off" value="2015-12-02" />');
        this.$startDate.appendTo(this.$startDateWrapper);
        this.$startDate.datepicker($.extend({
            defaultDate: new Date(2015, 11, 2)
        }, Craft.datepickerOptions));

        this.$endDateWrapper = $('<div class="datewrapper"></div>');
        this.$endDateWrapper.appendTo(this.$chartControls);

        this.$endDate = $('<input type="text" class="text" size="10" autocomplete="off" value="2015-12-10" />');
        this.$endDate.appendTo(this.$endDateWrapper);
        this.$endDate.datepicker($.extend({
            defaultDate: new Date(2015, 11, 10)
        }, Craft.datepickerOptions));

        this.loadReport();

		this.base();
	},

    loadReport: function()
    {
        var requestData = {
            startDate: this.$startDate.val(),
            endDate: this.$endDate.val(),
        };

        // Request orders report
        Craft.postActionRequest('commerce/reports/getOrders', requestData, $.proxy(function(response, textStatus)
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
