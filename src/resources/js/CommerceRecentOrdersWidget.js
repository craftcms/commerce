(function($) {


Craft.CommerceRecentOrdersWidget = Garnish.Base.extend(
{
    params: null,

    $widget: null,
    $body: null,

    init: function(widgetId, params)
    {
        console.log('hello widget');
        this.params = params;
        this.$widget = $('#widget'+widgetId);
        this.$body = this.$widget.find('.body:first');

        this.chart = new Craft.charts.Area('#widget'+widgetId+' .chart', this.params);

        this.$widget.data('widget').on('destroy', $.proxy(this, 'destroy'));

        Craft.CommerceRecentOrdersWidget.instances.push(this);
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