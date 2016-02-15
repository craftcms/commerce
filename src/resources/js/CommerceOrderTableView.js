/**
 * Class Craft.CommerceOrderTableView
 */
Craft.CommerceOrderTableView = Craft.TableElementIndexView.extend({

    chartToggleState: null,
    dateRangeState: null,

    startDate: null,
    endDate: null,

	$chartExplorer: null,

	afterInit: function()
    {
        this.chartToggleState = Craft.getLocalStorage('CommerceOrdersIndex.chartToggleState', false);
        this.dateRangeState = Craft.getLocalStorage('CommerceOrdersIndex.dateRangeState', 'd7');

        this.startDate = new Date();
        this.startDate.setDate(this.startDate.getDate() - 7);
        this.endDate = new Date();

        var $viewBtns = $('.viewbtns');
        $viewBtns.removeClass('hidden');

        this.$explorerContainer = $('<div class="chart-explorer-container"></div>').prependTo(this.$container);

        if($('.chart-toggle', $viewBtns).length == 0)
        {
            var $chartToggleContainer = $('<div class="chart-toggle-container"></div>').appendTo($viewBtns);
            var $chartToggle = $('<a class="btn chart-toggle" data-icon="area"></a>').appendTo($chartToggleContainer);
        }
        else
        {
            var $chartToggleContainer = $('.chart-toggle-container', $viewBtns);
            var $chartToggle = $('.chart-toggle', $chartToggleContainer);
        }

        this.addListener($chartToggle, 'click', 'toggleChartExplorer');

        if(this.chartToggleState)
        {
            $chartToggle.trigger('click');
        }

		this.base();
	},

    toggleChartExplorer: function(ev)
    {
        var $chartToggle = $(ev.currentTarget);

        if(this.$chartExplorer)
        {
            this.$chartExplorer.toggleClass('hidden');
        }
        else
        {
            this.createChartExplorer();
        }

        this.chartToggleState = false;

        if(!this.$chartExplorer.hasClass('hidden'))
        {
            this.chartToggleState = true;
        }

        if(this.chartToggleState == true)
        {
            $chartToggle.addClass('active');
        }
        else
        {
            $chartToggle.removeClass('active');
        }

        Craft.setLocalStorage('CommerceOrdersIndex.chartToggleState', this.chartToggleState);
    },

    createChartExplorer: function()
    {
        var $chartExplorer = $('<div class="chart-explorer"></div>').appendTo(this.$explorerContainer),
            $chartHeader = $('<div class="chart-header"></div>').appendTo($chartExplorer),
            $dateRangeContainer = $('<div class="datewrapper" />').appendTo($chartHeader),
            $total = $('<div class="total"></div>').appendTo($chartHeader),
            $totalLabel = $('<div class="total-label light">Total Revenue</div>').appendTo($total),
            $totalValueWrapper = $('<div class="total-value-wrapper"></div>').appendTo($total);
            $totalValue = $('<span class="total-value">0</span>').appendTo($totalValueWrapper);

        this.$chartExplorer = $chartExplorer;
        this.$totalValue = $totalValue;
        this.$chartContainer = $('<div class="chart-container"></div>').appendTo($chartExplorer);
        this.$spinner = $('<div class="spinner hidden" />').appendTo(this.$chartContainer);
        this.$error = $('<div class="error"></div>').appendTo(this.$chartContainer);
        this.$chart = $('<div class="chart"></div>').appendTo(this.$chartContainer);
        this.$dateRange = $('<input type="text" class="text" />').appendTo($dateRangeContainer);

        var customRangeStartDate = Craft.getLocalStorage('CommerceOrdersIndex.customRangeStartDate');
        var customRangeEndDate = Craft.getLocalStorage('CommerceOrdersIndex.customRangeEndDate');

        this.dateRange = new Craft.DateRangePicker(this.$dateRange, {
            value: this.dateRangeState,
            onAfterSelect: $.proxy(this, 'onAfterDateRangeSelect'),
            customRangeStartDate: customRangeStartDate,
            customRangeEndDate: customRangeEndDate,
        });

        this.loadReport(this.dateRange.getStartDate(), this.dateRange.getEndDate());
    },

    onAfterDateRangeSelect: function(value, startDate, endDate, customRangeStartDate, customRangeEndDate)
    {
        Craft.setLocalStorage('CommerceOrdersIndex.dateRangeState', value);
        Craft.setLocalStorage('CommerceOrdersIndex.customRangeStartDate', customRangeStartDate);
        Craft.setLocalStorage('CommerceOrdersIndex.customRangeEndDate', customRangeEndDate);

        this.loadReport(startDate, endDate)
    },

    loadReport: function(startDate, endDate)
    {
        var requestData = this.settings.params;

        requestData.startDate = startDate;
        requestData.endDate = endDate;

        this.$spinner.removeClass('hidden');
        this.$error.addClass('hidden');
        this.$chart.removeClass('error');

        Craft.postActionRequest('commerce/reports/getRevenueReport', requestData, $.proxy(function(response, textStatus)
        {
            this.$spinner.addClass('hidden');

            if(textStatus == 'success' && typeof(response.error) == 'undefined')
            {
                this.chart = new Craft.charts.Chart({
                    bindto: this.$chart.get(0),
                    data: {
                        rows: response.report,
                        x: response.report[0][0]
                    },
                    orientation: response.orientation,
                }, Craft.Commerce.getChartOptions(response.localeDefinition, response.scale));

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