// Craft charts

Craft.charts = {};

Craft.charts.Area = Garnish.Base.extend(
{
    chart: null,
    graph: null,
    data: null,

    margin: {
        top: 0,
        bottom: 0,
        left: 0,
        right: 0,
    },

    width: null,
    height: null,

    x: { axis: null, scale: null, enableLines: false, tickFormat: function(d) { var format = d3.time.format("%d/%m"); return format(d); } },
    y: { axis: null, scale: null, enableLines: true, tickFormat: function(d) { return "$" + d; } },

    chartElementsInitialized: false,

    $chart: null,

    init: function(chartElement, params, data)
    {
        this.$chart = d3.select(chartElement);

        this.width = parseInt(this.$chart.style("width")) - (this.margin.left + this.margin.right),
        this.height = parseInt(this.$chart.style("height")) - (this.margin.top + this.margin.bottom);

        this.initChart();
        this.loadData(data);

        d3.select(window).on('resize', $.proxy(function() {
            this.resize();
        }, this));

        this.resize();
    },

    initChart: function()
    {
        this.initScale();
        this.initAxis();
        this.initLines();

        // area
        this.chart = d3.svg.area()
            .x($.proxy(function(d) { return this.x.scale(d.date); }, this))
            .y0(this.height)
            .y1($.proxy(function(d) { return this.y.scale(d.close); }, this));

        // append graph to chart element
        this.graph = this.$chart
                .attr("width", this.width + (this.margin.left + this.margin.right))
                .attr("height", this.height + (this.margin.top + this.margin.bottom))
            .append("g")
                .attr("transform", "translate(" + this.margin.left + "," + this.margin.top + ")");
    },

    initScale: function()
    {
        this.x.scale = d3.time.scale()
            .range([0, this.width]);

        this.y.scale = d3.scale.linear()
            .range([this.height, 0]);
    },

    initAxis: function()
    {
        this.x.axis = d3.svg.axis()
            .scale(this.x.scale)
            .orient("top").tickFormat(this.x.tickFormat);

        this.y.axis = d3.svg.axis()
            .scale(this.y.scale)
            .orient("right").tickFormat(this.y.tickFormat);
    },

    initLines: function()
    {
        this.x.lineAxis = d3.svg.axis()
            .scale(this.x.scale)
            .orient("bottom");

        this.y.lineAxis = d3.svg.axis()
            .scale(this.y.scale)
            .orient("left");
    },

    loadData: function(data)
    {
        this.data = data;

        this.parseData();
        this.drawChart();
    },

    drawChart: function()
    {
        this.scaleDataRange();
        this.initChartElements();

        this.$chart.select('.area')
            .datum(this.data)
            .attr("d", this.chart);

        this.$chart.select(".x.axis") // change the x axis
            .call(this.x.axis);
        this.$chart.select(".y.axis") // change the y axis
            .call(this.y.axis);

        this.resize();
    },


    initChartElements: function()
    {
        if(!this.chartElementsInitialized)
        {
            this.chartElementsInitialized = true;

            if(this.x.enableLines)
            {
                // draw x lines
                this.graph.append("g")
                    .attr("class", "x line")
                    .attr("transform", "translate(0," + this.height + ")");
            }

            if(this.y.enableLines)
            {
                // draw y lines
                this.graph.append("g")
                    .attr("class", "y line");
            }

            // Draw chart
            this.graph.append("path")
                .attr("class", "area");

            // Draw the X axis
            this.graph.append("g")
                .attr("class", "x axis")
                .attr("transform", "translate(0," + this.height + ")");

            // Draw the Y axis
            this.graph.append("g")
                .attr("class", "y axis");
        }
    },

    parseData: function()
    {
        this.data.forEach(function(d) {
            d.date = d3.time.format("%d-%b-%y").parse(d.date);
            d.close = +d.close;
        });
    },

    scaleDataRange: function()
    {
        // Scale the range of the data
        this.x.scale.domain(d3.extent(this.data, function(d) { return d.date; }));
        this.y.scale.domain([0, d3.max(this.data, function(d) { return d.close; })]);
    },

    resize: function()
    {
        this.width = parseInt(this.$chart.style("width")) - (this.margin.left + this.margin.right),
        this.height = parseInt(this.$chart.style("height")) - (this.margin.top + this.margin.bottom);


        // ticks
        this.x.axis.ticks(Math.max(this.width/150, 3));
        this.y.axis.ticks(this.height / 50);
        this.x.lineAxis.ticks(Math.max(this.width/150, 3));
        this.y.lineAxis.ticks(this.height / 50);

        // Update the range of the scale with new width/height
        this.x.scale.range([0, this.width]);
        this.y.scale.range([this.height, 0]);

        // Update the axis with the new scale
        this.graph.select('.x.axis')
            .attr("transform", "translate(0," + this.height + ")")
            .call(this.x.axis);

        this.graph.select('.y.axis')
            .call(this.y.axis);

        // Update lines with new scale
        this.graph.select(".x.line")
            .call(this.x.lineAxis
                .tickSize(-this.height, 0, 0)
                .tickFormat("")
            );

        this.graph.select(".y.line")
            .call(this.y.lineAxis
                .tickSize(-this.width, 0, 0)
                .tickFormat("")
            );

        // Force D3 to recalculate and update the area
        this.graph.selectAll('.area')
            .attr("d", this.chart);


        // hide first & last ticks

        var xTicks = this.graph.selectAll(".x.axis .tick");

        xTicks.filter(function (d, e)
        {
            if(e === 0 || e === (xTicks[0].length - 1))
            {
                this.remove();
            }
        })

        var yTicks = this.graph.selectAll(".y.axis .tick");

        yTicks.filter(function (d, e)
        {
            if(e === 0 || e === (yTicks[0].length - 1))
            {
                this.remove();
            }
        })
    }
});








/**
 * Widget colspan picker class
 */
Craft.DateRangePicker = Garnish.Base.extend(
{
    init: function(container, options)
    {
        this.$container = container;
        this.options = options;

        this.startDate = options.startDate;
        this.endDate = options.endDate;

        this.$dateRangeWrapper = $('<div class="datewrapper"></div>');
        this.$dateRangeWrapper.appendTo(this.$container);

        var dateRangeString =
            (this.startDate.getMonth() + 1)+'/'+this.startDate.getDate()+'/'+this.startDate.getFullYear()+
            '-'+
            (this.endDate.getMonth() + 1)+'/'+this.endDate.getDate()+'/'+this.endDate.getFullYear();

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

                    selectionStarted = false;
                    // cur = -1, prv = -1;

                    this.onAfterSelect();
                }


            }, this),

            onClose: $.proxy(function ( dateText, inst )
            {
                inst.inline = false;
            }, this),

        }, Craft.datepickerOptions));
    },

    onAfterSelect: function()
    {
        if(typeof(this.options.onAfterSelect) == 'function')
        {
            this.options.onAfterSelect();
        }
    },
});