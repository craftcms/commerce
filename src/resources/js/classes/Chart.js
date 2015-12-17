/**
 * Craft Charts
 */

Craft.charts = {};


/**
 * Class Craft.charts.BaseChart
 */
Craft.charts.BaseChart = Garnish.Base.extend(
{
    $container: null,
    $chart: null,

    margin: { top: 0, right: 0, bottom: 0, left: 0 },
    data: null,
    chartClass: 'chart',

    width: null,
    height: null,
    x: null,
    y: null,
    xAxis: null,
    yAxis: null,
    svg: null,

    init: function(container)
    {
        this.$container = container;
        this.$chart = $('<div class="'+this.chartClass+'" />').appendTo(this.$container);

        d3.select(window).on('resize', $.proxy(function() {
            this.resize();
        }, this));

    },

    resize: function()
    {
        // only redraw if data is set

        if(this.data)
        {
            this.draw();
        }
    },
});


/**
 * Class Craft.charts.Column
 */
Craft.charts.Column = Craft.charts.BaseChart.extend(
{
    chartClass: 'chart column',

    draw: function(data)
    {
        if(typeof(data) != 'undefined')
        {
            this.data = data;

            this.data.forEach(function(d) {
                d.date = d3.time.format("%d-%b-%y").parse(d.date);
                d.close = +d.close;
            });
        }

        this.$chart.html('');

        this.width = this.$chart.width() - this.margin.left - this.margin.right;
        this.height = this.$chart.height() - this.margin.top - this.margin.bottom;

        this.x = d3.scale.ordinal().rangeRoundBands([0, this.width], .05);
        this.y = d3.scale.linear().range([this.height, 0]);

        this.xAxis = d3.svg.axis()
            .scale(this.x)
            .orient("bottom")
            .tickFormat(d3.time.format("%Y-%m"));

        this.yAxis = d3.svg.axis()
            .scale(this.y)
            .orient("left")
            .ticks(10);

        this.svg = d3.select(this.$chart.get(0)).append("svg")
                .attr("width", this.width + this.margin.left + this.margin.right)
                .attr("height", this.height + this.margin.top + this.margin.bottom)
            .append("g")
                .attr("transform", "translate(" + this.margin.left + "," + this.margin.top + ")");

        this.x.domain(this.data.map(function(d) { return d.date; }));
        this.y.domain([0, d3.max(this.data, function(d) { return d.close; })]);


        this.svg.append("g")
            .attr("class", "x axis")
            .attr("transform", "translate(0," + this.height + ")")
            .call(this.xAxis);

        this.svg.append("g")
                .attr("class", "y axis")
                .call(this.yAxis)
            .append("text")
                .attr("transform", "rotate(-90)")
                .attr("y", 6)
                .attr("dy", ".71em")
                .style("text-anchor", "end")
                .text("Frequency");

        this.svg.selectAll(".bar")
                .data(this.data)
            .enter().append("rect")
                .attr("class", "bar")
                .attr("x", $.proxy(function(d) { return this.x(d.date); }, this))
                .attr("width", this.x.rangeBand())
                .attr("y", $.proxy(function(d) { return this.y(d.close); }, this))
                .attr("height", $.proxy(function(d) { return this.height - this.y(d.close); }, this));

    }
});

/**
 * Class Craft.charts.Area
 */
Craft.charts.Area = Craft.charts.BaseChart.extend(
{
    chartClass: 'chart area',

    xTickFormat: function(d) { var format = d3.time.format("%d/%m"); return format(d); },
    yTickFormat: function(d) { return "$" + d; },

    draw: function(data)
    {
        if(typeof(data) != 'undefined')
        {
            this.data = data;

            this.data.forEach(function(d) {
                d.date = d3.time.format("%d-%b-%y").parse(d.date);
                d.close = +d.close;
            });
        }

        this.$chart.html('');

        this.width = this.$chart.width() - this.margin.left - this.margin.right;
        this.height = this.$chart.height() - this.margin.top - this.margin.bottom;

        this.x = d3.time.scale().range([0, this.width]);
        this.y = d3.scale.linear().range([this.height, 0]);

        this.xAxis = d3.svg.axis()
            .scale(this.x)
            .orient("top")
            .tickFormat(this.xTickFormat)
            .ticks(Math.max(this.width/150, 3));

        this.yAxis = d3.svg.axis()
            .scale(this.y)
            .orient("right")
            .tickFormat(this.yTickFormat)
            .ticks(this.height / 50);

        // area
        this.area = d3.svg.area()
            .x($.proxy(function(d) { return this.x(d.date); }, this))
            .y0(this.height)
            .y1($.proxy(function(d) { return this.y(d.close); }, this));

        // append graph to chart element
        this.svg = d3.select(this.$chart.get(0)).append("svg")
                .attr("width", this.width + (this.margin.left + this.margin.right))
                .attr("height", this.height + (this.margin.top + this.margin.bottom))
            .append("g")
                .attr("transform", "translate(" + this.margin.left + "," + this.margin.top + ")");

        this.x.domain(d3.extent(this.data, function(d) { return d.date; }));
        this.y.domain([0, d3.max(this.data, function(d) { return d.close; })]);


        // Draw chart
        this.svg.append("path")
            .datum(this.data)
            .attr("class", "area")
            .attr("d", this.area);

        // Draw the X axis
        this.svg.append("g")
            .attr("class", "x axis")
            .attr("transform", "translate(0," + this.height + ")")
            .call(this.xAxis);

        // Draw the Y axis
        this.svg.append("g")
            .attr("class", "y axis")
            .call(this.yAxis);

    }
});
