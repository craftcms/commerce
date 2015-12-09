/**
 * Class Craft.CommerceOrderTableView
 */
Craft.CommerceOrderTableView = Craft.TableElementIndexView.extend({
	$chartContainer: null,

	afterInit: function() {

		// Add the chart before the table
		this.$chartContainer = $('<svg class="chart"></svg>').prependTo(this.$container);

		// Error
		this.$error = $('<div class="error"/>').prependTo(this.$container);

		// Request orders report
        Craft.postActionRequest('commerce/reports/getOrders', {}, $.proxy(function(response, textStatus)
        {
            if(textStatus == 'success' && typeof(response.error) == 'undefined')
            {
            	// Create chart
                this.chart = new Craft.charts.Area(this.$chartContainer.get(0), this.params, response);
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

		this.base();
	}
});
