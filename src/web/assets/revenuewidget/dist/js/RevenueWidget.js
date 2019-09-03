(function($) {

    if (typeof Craft.Commerce === typeof undefined) {
        Craft.Commerce = {};
    }

    /**
     * Class Craft.Commerce.RevenueWidget
     */
    Craft.Commerce.RevenueWidget = Garnish.Base.extend(
        {
            settings: null,
            data: null,

            $widget: null,
            $body: null,

            init: function(widgetId, settings) {
                this.setSettings(settings);

                this.$widget = $('#widget' + widgetId);
                this.$body = this.$widget.find('.body:first');
                this.$infos = $('.infos', this.$body);
                this.$total = $('.total', this.$body);
                this.$chart = $('.chart', this.$body);
                this.$error = $('<div class="error"/>').appendTo(this.$body);


                var dateRange = this.settings.dateRange;

                switch (dateRange) {
                    case 'd7':
                        this.startDate = Craft.Commerce.RevenueWidget.getDateByDays('7');
                        this.endDate = new Date();
                        break;

                    case 'd30':
                        this.startDate = Craft.Commerce.RevenueWidget.getDateByDays('30');
                        this.endDate = new Date();
                        break;

                    case 'lastweek':
                        this.startDate = Craft.Commerce.RevenueWidget.getDateByDays('14');
                        this.endDate = Craft.Commerce.RevenueWidget.getDateByDays('7');
                        break;

                    case 'lastmonth':
                        this.startDate = Craft.Commerce.RevenueWidget.getDateByDays('60');
                        this.endDate = Craft.Commerce.RevenueWidget.getDateByDays('30');
                        break;
                }

                var requestData = {
                    startDate: Craft.Commerce.RevenueWidget.getDateValue(this.startDate),
                    endDate: Craft.Commerce.RevenueWidget.getDateValue(this.endDate),
                    elementType: '\\craft\\commerce\\elements\\Order'
                };

                Craft.postActionRequest('commerce/charts/get-revenue-data', requestData, $.proxy(function(response, textStatus) {
                    if (textStatus === 'success' && typeof(response.error) === 'undefined') {
                        this.$infos.removeClass('hidden');
                        this.$chart.removeClass('hidden');

                        if (!this.chart) {
                            this.chart = new Craft.charts.Area(this.$chart);
                        }

                        var chartDataTable = new Craft.charts.DataTable(response.dataTable);

                        var chartSettings = {
                            formatLocaleDefinition: response.formatLocaleDefinition,
                            orientation: response.orientation,
                            formats: response.formats,
                            dataScale: response.scale
                        };

                        this.chart.draw(chartDataTable, chartSettings);

                        this.$total.html(response.totalHtml);

                        // Resize chart when grid is refreshed
                        window.dashboard.grid.on('refreshCols', $.proxy(this, 'handleGridRefresh'));
                    }
                    else {
                        // Error

                        var msg = Craft.t('commerce', 'An unknown error occurred.');

                        if (typeof(response) !== 'undefined' && response && typeof(response.error) !== 'undefined') {
                            msg = response.error;
                        }

                        this.$error.html(msg);
                        this.$error.removeClass('hidden');
                        this.$infos.addClass('hidden');
                        this.$chart.addClass('hidden');
                    }

                }, this));

                this.$widget.data('widget').on('destroy', $.proxy(this, 'destroy'));

                Craft.Commerce.RevenueWidget.instances.push(this);
            },

            handleGridRefresh: function() {
                this.chart.resize();
            },

            destroy: function() {
                Craft.Commerce.RevenueWidget.instances.splice($.inArray(this, Craft.Commerce.RevenueWidget.instances), 1);
                this.base();
            }
        }, {
            instances: [],

            getDateByDays: function(days) {
                var date = new Date();
                date = date.getTime() - (60 * 60 * 24 * days * 1000);
                return new Date(date);
            },

            getDateValue: function(date) {
                return date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate();
            }
        });


})(jQuery);
