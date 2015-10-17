/**
 * Class Craft.CommerceOrderTableView
 */
Craft.CommerceOrderTableView = Craft.TableElementIndexView.extend({
	$chartContainer: null,

	afterInit: function() {
		// Add the chart before the table
		this.$chartContainer = $('<div/>').prependTo(this.$container);

		// temp...
		this.$chartContainer
			.addClass('light')
			.css({
				'margin-bottom': '24px',
				padding: '100px 0',
				background: '#f8f8f8',
				'text-align': 'center'
			})
			.text('chart goes here!');

		this.base();
	}
});
