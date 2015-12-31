(function($) {


Craft.CommerceRecentOrdersWidget = Garnish.Base.extend(
{
    params: null,
    data: null,

    $widget: null,
    $body: null,

    init: function(widgetId, params)
    {
        this.params = params;
        this.$widget = $('#widget'+widgetId);
        this.$body = this.$widget.find('.body:first');

        // Add the chart to the body
        this.$chartContainer = $('<div class="chart"/>').prependTo(this.$body);

        // Error
        this.$error = $('<div class="error"/>').prependTo(this.$body);

        // Request orders report
        var requestData = {
            startDate: '-7 days',
            endDate: 'now',
            elementType: 'Commerce_Order'
        };

        Craft.postActionRequest('commerce/reports/getOrders', requestData, $.proxy(function(response, textStatus)
        {
            if(textStatus == 'success' && typeof(response.error) == 'undefined')
            {
                // Create chart
                this.chart = new Craft.charts.Area(this.$chartContainer);

                this.chart.draw(response.report);

                // Resize chart when grid is refreshed
                window.dashboard.grid.on('refreshCols', $.proxy(this, 'handleGridRefresh'));
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

        this.$widget.data('widget').on('destroy', $.proxy(this, 'destroy'));

        Craft.CommerceRecentOrdersWidget.instances.push(this);
    },

    handleGridRefresh: function()
    {
        this.chart.resize();
    },

    destroy: function()
    {
        Craft.CommerceRecentOrdersWidget.instances.splice($.inArray(this, Craft.CommerceRecentOrdersWidget.instances), 1);
        this.base();
    }
}, {
    instances: []
});


})(jQuery);