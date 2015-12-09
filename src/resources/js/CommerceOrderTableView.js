/**
 * Class Craft.CommerceOrderTableView
 */
Craft.CommerceOrderTableView = Craft.TableElementIndexView.extend({
	$chartContainer: null,

	afterInit: function() {

		// Add the chart before the table
		this.$chartContainer = $('<svg class="chart"></svg>').prependTo(this.$container);
		this.$error = $('<div class="error"/>').prependTo(this.$container);

        Craft.postActionRequest('commerce/reports/getOrders', {}, $.proxy(function(response, textStatus)
        {
            if(textStatus == 'success' && typeof(response.error) == 'undefined')
            {
                this.chart = new Craft.charts.Area(this.$chartContainer.get(0), this.params, response);
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

		this.base();
	}
});
