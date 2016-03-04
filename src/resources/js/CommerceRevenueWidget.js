(function($) {


Craft.CommerceRevenueWidget = Garnish.Base.extend(
{
    settings: null,
    data: null,

    $widget: null,
    $body: null,

    init: function(widgetId, settings)
    {
        this.setSettings(settings);

        this.$widget = $('#widget'+widgetId);
        this.$body = this.$widget.find('.body:first');
        this.$infos = $('.infos', this.$body);
        this.$total = $('.total', this.$body);
        this.$chart = $('.chart', this.$body);
        this.$error = $('<div class="error"/>').appendTo(this.$body);


        var dateRange = this.settings.dateRange;

        switch(dateRange)
        {
            case 'd7':
                this.startDate = this.getDateByDays('7');
            break;

            case 'd30':
                this.startDate = this.getDateByDays('30');
            break;

            case 'lastweek':
                this.startDate = this.getDateByDays('14');
                this.endDate = this.getDateByDays('7');
            break;

            case 'lastmonth':
                this.startDate = this.getDateByDays('60');
                this.endDate = this.getDateByDays('30');
            break;
        }

        var requestData = {
            startDate: this.startDate,
            endDate: this.endDate,
            elementType: 'Commerce_Order'
        };

        Craft.postActionRequest('commerce/charts/getRevenueData', requestData, $.proxy(function(response, textStatus)
        {
            if(textStatus == 'success' && typeof(response.error) == 'undefined')
            {
                this.$infos.removeClass('hidden');
                this.$chart.removeClass('hidden');

                if(!this.chart)
                {
                    this.chart = new Craft.charts.Area(this.$chart);
                }

                var chartDataTable = new Craft.charts.DataTable(response.dataTable);

                var chartSettings = {
                    localeDefinition: response.localeDefinition,
                    orientation: response.orientation,
                    formats: response.formats,
                    dataScale: response.scale
                };

                this.chart.draw(chartDataTable, chartSettings);

                this.$total.html(response.totalHtml);

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
                this.$infos.addClass('hidden');
                this.$chart.addClass('hidden');
            }

        }, this));

        this.$widget.data('widget').on('destroy', $.proxy(this, 'destroy'));

        Craft.CommerceRevenueWidget.instances.push(this);
    },

    getDateByDays: function(days)
    {
        var date = new Date();
        date = date.getTime() - (60 * 60 * 24 * days * 1000);
        return new Date(date);
    },

    handleGridRefresh: function()
    {
        this.chart.resize();
    },

    destroy: function()
    {
        Craft.CommerceRevenueWidget.instances.splice($.inArray(this, Craft.CommerceRevenueWidget.instances), 1);
        this.base();
    }
}, {
    instances: []
});


})(jQuery);
