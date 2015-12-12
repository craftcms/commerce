/**
 * Class Craft.CommerceOrderTableView
 */
Craft.CommerceOrderTableView = Craft.TableElementIndexView.extend({

    startDate: null,
    endDate: null,

	$chartContainer: null,

	afterInit: function()
    {
        this.startDate = new Date();
        this.startDate.setDate(this.startDate.getDate() - 7);
        this.endDate = new Date();

		// Add the chart before the table
		this.$chartContainer = $('<svg class="chart"></svg>').prependTo(this.$container);

		// Error
		this.$error = $('<div class="error"/>').prependTo(this.$container);

        this.$chartControls = $('<div class="chart-controls"></div>');
        this.$chartControls.prependTo(this.$container);

        this.$dateRangeWrapper = $('<div class="datewrapper"></div>');
        this.$dateRangeWrapper.appendTo(this.$chartControls);

        var dateRangeString =
            this.startDate.getMonth()+'/'+this.startDate.getDay()+'/'+this.startDate.getFullYear()+
            '-'+
            this.endDate.getMonth()+'/'+this.endDate.getDay()+'/'+this.endDate.getFullYear();

        this.$dateRange = $('<input type="text" class="text" size="20" autocomplete="off" value="'+dateRangeString+'" />');
        this.$dateRange.appendTo(this.$dateRangeWrapper);


        // var cur = -1, prv = -1;

        var cur = this.startDate.getTime(), prv = this.endDate.getTime();
        var selectionStarted = false;

        this.$dateRange.datepicker($.extend({

            // defaultDate: new Date(2015, 11, 2),

            beforeShowDay: function ( date )
            {
                return [true, ( (date.getTime() >= Math.min(prv, cur) && date.getTime() <= Math.max(prv, cur)) ? 'date-range-selected' : '')];
            },

            onSelect: $.proxy(function ( dateText, inst )
            {
                inst.inline = true;

                prv = cur;
                cur = (new Date(inst.selectedYear, inst.selectedMonth, inst.selectedDay)).getTime();

                if ( prv == -1 || prv == cur || !selectionStarted)
                {
                    selectionStarted = true;
                    prv = cur;
                    this.$dateRange.val( dateText );
                }
                else
                {
                    this.startDate = $.datepicker.formatDate( 'mm/dd/yy', new Date(Math.min(prv,cur)), {} );
                    this.endDate = $.datepicker.formatDate( 'mm/dd/yy', new Date(Math.max(prv,cur)), {} );
                    this.$dateRange.val( this.startDate+' - '+this.endDate );
                    this.$dateRange.datepicker('hide');
                    this.loadReport();
                    selectionStarted = false;
                    // cur = -1, prv = -1;
                }


            }, this),

            onClose: $.proxy(function ( dateText, inst )
            {
                inst.inline = false;
            }, this),

        }, Craft.datepickerOptions));

        this.loadReport();

		this.base();
	},

    loadReport: function()
    {
        var requestData = {
            startDate: this.startDate,
            endDate: this.endDate,
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
