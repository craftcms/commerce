/**
 * Class Craft.CommerceOrderTableView
 */
Craft.CommerceOrderTableView = Craft.TableElementIndexView.extend({

    startDate: null,
    endDate: null,

	$chartExplorer: null,

	afterInit: function()
    {
        this.$explorerContainer = $('<div class="chart-explorer-container"></div>').prependTo(this.$container);

        this.createChartExplorer();

		this.base();
	},

    createChartExplorer: function()
    {
        // start date
        this.startDate = Craft.getLocalStorage('CommerceOrdersIndex.startDate');

        if(this.startDate)
        {
            this.startDate = new Date(this.startDate);
        }
        else
        {
            var date = new Date();
            date = date.getTime() - (60 * 60 * 24 * 7 * 1000);
            this.startDate = new Date(date);
        }

        // end date
        this.endDate = Craft.getLocalStorage('CommerceOrdersIndex.endDate');

        if(this.endDate)
        {
            this.endDate = new Date(this.endDate);
        }
        else
        {
            this.endDate = new Date();
        }

        // chart explorer
        var $chartExplorer = $('<div class="chart-explorer"></div>').appendTo(this.$explorerContainer),
            $chartHeader = $('<div class="chart-header"></div>').appendTo($chartExplorer),
            $dateRange = $('<div class="date-range" />').appendTo($chartHeader),
            $startDateContainer = $('<div class="datewrapper"></div>').appendTo($dateRange),
            $to = $('<span class="to light">to</span>').appendTo($dateRange),
            $endDateContainer = $('<div class="datewrapper"></div>').appendTo($dateRange),
            $total = $('<div class="total"></div>').appendTo($chartHeader),
            $totalLabel = $('<div class="total-label light">'+Craft.t('Total Revenue')+'</div>').appendTo($total),
            $totalValueWrapper = $('<div class="total-value-wrapper"></div>').appendTo($total);
            $totalValue = $('<span class="total-value">&nbsp;</span>').appendTo($totalValueWrapper);

        this.$chartExplorer = $chartExplorer;
        this.$totalValue = $totalValue;
        this.$chartContainer = $('<div class="chart-container"></div>').appendTo($chartExplorer);
        this.$spinner = $('<div class="spinner hidden" />').prependTo($chartHeader);
        this.$error = $('<div class="error"></div>').appendTo(this.$chartContainer);
        this.$chart = $('<div class="chart"></div>').appendTo(this.$chartContainer);

        this.$startDate = $('<input type="text" value="'+Craft.formatDate(this.startDate)+'" class="text" size="20" autocomplete="off" />').appendTo($startDateContainer);
        this.$endDate = $('<input type="text" value="'+Craft.formatDate(this.endDate)+'" class="text" size="20" autocomplete="off" />').appendTo($endDateContainer);

        this.$startDate.datepicker($.extend({
            onSelect: $.proxy(function(dateText, inst)
            {
                var selectedDate = new Date(inst.currentYear, inst.currentMonth, inst.currentDay);

                if(selectedDate.getTime() > this.endDate.getTime())
                {
                    // if selectedDate > endDate, set endDate at selectedDate plus 7 days
                    var _endDate = selectedDate.getTime() + (60 * 60 * 24 * 7 * 1000);
                    _endDate = new Date(_endDate);
                    this.endDate = _endDate;
                    this.$endDate.val(Craft.formatDate(this.endDate));
                    Craft.setLocalStorage('CommerceOrdersIndex.endDate', this.endDate);
                }

                this.startDate = new Date(inst.currentYear, inst.currentMonth, inst.currentDay);
                this.loadReport(this.$startDate.val(), this.$endDate.val());

                Craft.setLocalStorage('CommerceOrdersIndex.startDate', this.startDate);
            }, this)
        }, Craft.datepickerOptions));

        this.$endDate.datepicker($.extend({
            onSelect: $.proxy(function(dateText, inst)
            {
                var selectedDate = new Date(inst.currentYear, inst.currentMonth, inst.currentDay);

                if(selectedDate.getTime() < this.startDate.getTime())
                {
                    // if selectedDate < startDate, set startDate at selectedDate minus 7 days
                    var _startDate = selectedDate.getTime() - (60 * 60 * 24 * 7 * 1000);
                    _startDate = new Date(_startDate);
                    this.startDate = _startDate;
                    this.$startDate.val(Craft.formatDate(this.startDate));
                    Craft.setLocalStorage('CommerceOrdersIndex.startDate', this.startDate);
                }

                this.endDate = new Date(inst.currentYear, inst.currentMonth, inst.currentDay);
                this.loadReport(this.$startDate.val(), this.$endDate.val());

                Craft.setLocalStorage('CommerceOrdersIndex.endDate', this.endDate);
            }, this)
        }, Craft.datepickerOptions));

        this.loadReport(this.$startDate.val(), this.$endDate.val());
    },

    loadReport: function(startDate, endDate)
    {
        var requestData = this.settings.params;

        requestData.startDate = startDate;
        requestData.endDate = endDate;

        this.$spinner.removeClass('hidden');
        this.$error.addClass('hidden');
        this.$chart.removeClass('error');


        Craft.postActionRequest('commerce/charts/getRevenueReport', requestData, $.proxy(function(response, textStatus)
        {
            this.$spinner.addClass('hidden');

            if(textStatus == 'success' && typeof(response.error) == 'undefined')
            {
                if(!this.chart)
                {
                    this.chart = new Craft.charts.Area(this.$chart);
                }

                var chartDataTable = new Craft.charts.DataTable(response.report);

                var chartSettings = {
                    localeDefinition: response.localeDefinition,
                    orientation: response.orientation,
                    formats: response.formats,
                    dataScale: response.scale
                };

                this.chart.draw(chartDataTable, chartSettings);

                this.$totalValue.html(response.totalHtml);
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
                this.$chart.addClass('error');
            }
        }, this));
    }
});