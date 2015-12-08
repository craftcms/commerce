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
        this.$error = $('.error', this.$widget);

        Craft.postActionRequest('commerce/reports/getOrders', {}, $.proxy(function(response, textStatus)
        {
            if(textStatus == 'success' && typeof(response.error) == 'undefined')
            {
                this.chart = new Craft.charts.Area('#widget'+widgetId+' .chart', this.params, response);

                window.dashboard.grid.on('refreshCols', $.proxy(this, 'handleGridRefresh'));
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