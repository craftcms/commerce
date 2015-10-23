(function($){


if (typeof Craft.Commerce === typeof undefined) {
	Craft.Commerce = {};
}


/**
 * Variant Matrix class
 */
Craft.Commerce.VariantMatrix = Garnish.Base.extend(
{
	id: null,
	fieldBodyHtml: null,
	fieldFootHtml: null,
	inputNamePrefix: null,
	inputIdPrefix: null,

	$container: null,
	$variantContainer: null,
	$addVariantBtn: null,

	variantSort: null,
	variantSelect: null,
	totalNewVariants: 0,

	init: function(id, fieldBodyHtml, fieldFootHtml, inputNamePrefix)
	{
		this.id = id
		this.fieldBodyHtml = fieldBodyHtml;
		this.fieldFootHtml = fieldFootHtml;
		this.inputNamePrefix = inputNamePrefix;
		this.inputIdPrefix = Craft.formatInputId(this.inputNamePrefix);

		this.$container = $('#'+this.id);
		this.$variantContainer = this.$container.children('.blocks');
		this.$addVariantBtn = this.$container.children('.btn');

		var $variants = this.$variantContainer.children(),
			collapsedVariants = Craft.Commerce.VariantMatrix.getCollapsedVariantIds();

		this.variantSort = new Garnish.DragSort($variants, {
			handle: '> .actions > .move',
			axis: 'y',
			filter: $.proxy(function()
			{
				// Only return all the selected items if the target item is selected
				if (this.variantSort.$targetItem.hasClass('sel'))
				{
					return this.variantSelect.getSelectedItems();
				}
				else
				{
					return this.variantSort.$targetItem;
				}
			}, this),
			collapseDraggees: true,
			magnetStrength: 4,
			helperLagBase: 1.5,
			helperOpacity: 0.9,
			onSortChange: $.proxy(function() {
				this.variantSelect.resetItemOrder();
			}, this)
		});

		this.variantSelect = new Garnish.Select(this.$variantContainer, $variants, {
			multi: true,
			vertical: true,
			handle: '> .checkbox, > .titlebar',
			checkboxMode: true
		});

		for (var i = 0; i < $variants.length; i++)
		{
			var $variant = $variants.eq(i),
				id = $variant.data('id');

			// Is this a new variant?
			var newMatch = (typeof id == 'string' && id.match(/new(\d+)/));

			if (newMatch && newMatch[1] > this.totalNewVariants)
			{
				this.totalNewVariants = parseInt(newMatch[1]);
			}

			var variant = new Variant(this, $variant);

			if (variant.id && $.inArray(''+variant.id, collapsedVariants) != -1)
			{
				variant.collapse();
			}
		}

		this.addListener(this.$addVariantBtn, 'click', function() {
			this.addVariant();
		});
	},

	addVariant: function($insertBefore)
	{
		this.totalNewVariants++;

		var id = 'new'+this.totalNewVariants;

		var $variant = $(
			'<div class="variant-matrixblock matrixblock" data-id="'+id+'">' +
				'<input type="hidden" name="'+this.inputNamePrefix+'['+id+'][enabled]" value="1"/>' +
				'<div class="titlebar">' +
					'<div class="preview"></div>' +
				'</div>' +
				'<div class="checkbox" title="'+Craft.t('Select')+'"></div>' +
				'<div class="actions">' +
					'<div class="status off" title="'+Craft.t('Disabled')+'"></div>' +
					'<a class="settings icon menubtn" title="'+Craft.t('Actions')+'" role="button"></a> ' +
					'<div class="menu">' +
						'<ul class="padded">' +
							'<li><a data-icon="collapse" data-action="collapse">'+Craft.t('Collapse')+'</a></li>' +
							'<li class="hidden"><a data-icon="expand" data-action="expand">'+Craft.t('Expand')+'</a></li>' +
							'<li><a data-icon="disabled" data-action="disable">'+Craft.t('Disable')+'</a></li>' +
							'<li class="hidden"><a data-icon="enabled" data-action="enable">'+Craft.t('Enable')+'</a></li>' +
						'</ul>' +
						'<hr class="padded"/>' +
						'<ul class="padded">' +
							'<li><a data-icon="+" data-action="add">'+Craft.t('Add variant above')+'</a></li>' +
						'</ul>' +
						'<hr class="padded"/>' +
						'<ul class="padded">' +
							'<li><a data-icon="remove" data-action="delete">'+Craft.t('Delete')+'</a></li>' +
						'</ul>' +
					'</div>' +
					'<a class="move icon" title="'+Craft.t('Reorder')+'" role="button"></a> ' +
				'</div>' +
			'</div>'
		);

		if ($insertBefore)
		{
			$variant.insertBefore($insertBefore);
		}
		else
		{
			$variant.appendTo(this.$variantContainer);
		}

		var $fieldsContainer = $('<div class="fields"/>').appendTo($variant),
			bodyHtml = this.getParsedVariantHtml(this.fieldBodyHtml, id),
			footHtml = this.getParsedVariantHtml(this.fieldFootHtml, id);

		$(bodyHtml).appendTo($fieldsContainer);

		// Animate the variant into position
		$variant.css(this.getHiddenVariantCss($variant)).velocity({
			opacity: 1,
			'margin-bottom': 10
		}, 'fast', $.proxy(function()
		{
			$variant.css('margin-bottom', '');
			Garnish.$bod.append(footHtml);
			Craft.initUiElements($fieldsContainer);
			new Variant(this, $variant);
			this.variantSort.addItems($variant);
			this.variantSelect.addItems($variant);

			Garnish.requestAnimationFrame(function()
			{
				// Scroll to the variant
				Garnish.scrollContainerToElement($variant);
			});
		}, this));
	},

	collapseSelectedVariants: function()
	{
		this.callOnSelectedVariants('collapse');
	},

	expandSelectedVariants: function()
	{
		this.callOnSelectedVariants('expand');
	},

	disableSelectedVariants: function()
	{
		this.callOnSelectedVariants('disable');
	},

	enableSelectedVariants: function()
	{
		this.callOnSelectedVariants('enable');
	},

	deleteSelectedVariants: function()
	{
		this.callOnSelectedVariants('selfDestruct');
	},

	callOnSelectedVariants: function(fn)
	{
		for (var i = 0; i < this.variantSelect.$selectedItems.length; i++)
		{
			this.variantSelect.$selectedItems.eq(i).data('variant')[fn]();
		}
	},

	getHiddenVariantCss: function($variant)
	{
		return {
			opacity: 0,
			marginBottom: -($variant.outerHeight())
		};
	},

	getParsedVariantHtml: function(html, id)
	{
		if (typeof html == 'string')
		{
			return html.replace(/__VARIANT__/g, id);
		}
		else
		{
			return '';
		}
	}
},
{
	collapsedVariantStorageKey: 'Craft-'+Craft.siteUid+'.Commerce.VariantMatrix.collapsedVariants',

	getCollapsedVariantIds: function()
	{
		if (typeof localStorage[Craft.Commerce.VariantMatrix.collapsedVariantStorageKey] == 'string')
		{
			return Craft.filterArray(localStorage[Craft.Commerce.VariantMatrix.collapsedVariantStorageKey].split(','));
		}
		else
		{
			return [];
		}
	},

	setCollapsedVariantIds: function(ids)
	{
		localStorage[Craft.Commerce.VariantMatrix.collapsedVariantStorageKey] = ids.join(',');
	},

	rememberCollapsedVariantId: function(id)
	{
		if (typeof Storage !== 'undefined')
		{
			var collapsedVariants = Craft.Commerce.VariantMatrix.getCollapsedVariantIds();

			if ($.inArray(''+id, collapsedVariants) == -1)
			{
				collapsedVariants.push(id);
				Craft.Commerce.VariantMatrix.setCollapsedVariantIds(collapsedVariants);
			}
		}
	},

	forgetCollapsedVariantId: function(id)
	{
		if (typeof Storage !== 'undefined')
		{
			var collapsedVariants = Craft.Commerce.VariantMatrix.getCollapsedVariantIds(),
				collapsedVariantsIndex = $.inArray(''+id, collapsedVariants);

			if (collapsedVariantsIndex != -1)
			{
				collapsedVariants.splice(collapsedVariantsIndex, 1);
				Craft.Commerce.VariantMatrix.setCollapsedVariantIds(collapsedVariants);
			}
		}
	}
});


var Variant = Garnish.Base.extend(
{
	matrix: null,
	$container: null,
	$titlebar: null,
	$fieldsContainer: null,
	$previewContainer: null,
	$actionMenu: null,
	$collapsedInput: null,

	isNew: null,
	id: null,

	collapsed: false,

	init: function(matrix, $container)
	{
		this.matrix = matrix;
		this.$container = $container;
		this.$titlebar = $container.children('.titlebar');
		this.$previewContainer = this.$titlebar.children('.preview');
		this.$fieldsContainer = $container.children('.fields');

		this.$container.data('variant', this);

		this.id    = this.$container.data('id');
		this.isNew = (!this.id || (typeof this.id == 'string' && this.id.substr(0, 3) == 'new'));

		var $menuBtn = this.$container.find('> .actions > .settings'),
			menuBtn = new Garnish.MenuBtn($menuBtn);

		this.$actionMenu = menuBtn.menu.$container;

		menuBtn.menu.settings.onOptionSelect = $.proxy(this, 'onMenuOptionSelect');

		// Was this variant already collapsed?
		if (Garnish.hasAttr(this.$container, 'data-collapsed'))
		{
			this.collapse();
		}

		this.addListener(this.$titlebar, 'dblclick', function(ev)
		{
			ev.preventDefault();
			this.toggle();
		});
	},

	toggle: function()
	{
		if (this.collapsed)
		{
			this.expand();
		}
		else
		{
			this.collapse(true);
		}
	},

	collapse: function(animate)
	{
		if (this.collapsed)
		{
			return;
		}

		this.$container.addClass('collapsed');

		var previewHtml = '',
			$fields = this.$fieldsContainer.find('> .meta > .field:first-child, > .custom-fields > .field');

		for (var i = 0; i < $fields.length; i++)
		{
			var $field = $($fields[i]),
				$inputs = $field.children('.input').find('select,input[type!="hidden"],textarea,.label'),
				inputPreviewText = '';

			for (var j = 0; j < $inputs.length; j++)
			{
				var $input = $($inputs[j]);

				if ($input.hasClass('label'))
				{
					var $maybeLightswitchContainer = $input.parent().parent();

					if ($maybeLightswitchContainer.hasClass('lightswitch') && (
						($maybeLightswitchContainer.hasClass('on') && $input.hasClass('off')) ||
						(!$maybeLightswitchContainer.hasClass('on') && $input.hasClass('on'))
					))
					{
						continue;
					}

					var value = $input.text();
				}
				else
				{
					var value = Craft.getText(Garnish.getInputPostVal($input));
				}

				if (value instanceof Array)
				{
					value = value.join(', ');
				}

				if (value)
				{
					value = Craft.trim(value);

					if (value)
					{
						if (inputPreviewText)
						{
							inputPreviewText += ', ';
						}

						inputPreviewText += value;
					}
				}
			}

			if (inputPreviewText)
			{
				previewHtml += (previewHtml ? ' <span>|</span> ' : '') + inputPreviewText;
			}
		}

		this.$previewContainer.html(previewHtml);

		this.$fieldsContainer.velocity('stop');
		this.$container.velocity('stop');

		if (animate)
		{
			this.$fieldsContainer.velocity('fadeOut', { duration: 'fast' });
			this.$container.velocity({ height: 30 }, 'fast');
		}
		else
		{
			this.$previewContainer.show();
			this.$fieldsContainer.hide();
			this.$container.css({ height: 30 });
		}

		setTimeout($.proxy(function() {
			this.$actionMenu.find('a[data-action=collapse]:first').parent().addClass('hidden');
			this.$actionMenu.find('a[data-action=expand]:first').parent().removeClass('hidden');
		}, this), 200);

		// Remember that?
		if (!this.isNew)
		{
			Craft.Commerce.VariantMatrix.rememberCollapsedVariantId(this.id);
		}
		else
		{
			if (!this.$collapsedInput)
			{
				this.$collapsedInput = $('<input type="hidden" name="'+this.matrix.inputNamePrefix+'['+this.id+'][collapsed]" value="1"/>').appendTo(this.$container);
			}
			else
			{
				this.$collapsedInput.val('1');
			}
		}

		this.collapsed = true;
	},

	expand: function()
	{
		if (!this.collapsed)
		{
			return;
		}

		this.$container.removeClass('collapsed');

		this.$fieldsContainer.velocity('stop');
		this.$container.velocity('stop');

		var collapsedContainerHeight = this.$container.height();
		this.$container.height('auto');
		this.$fieldsContainer.css('display', 'flex');
		var expandedContainerHeight = this.$container.height();
		this.$container.height(collapsedContainerHeight);
		this.$fieldsContainer.hide().velocity('fadeIn', { duration: 'fast', display: 'flex' });
		this.$container.velocity({ height: expandedContainerHeight }, 'fast', $.proxy(function() {
			this.$previewContainer.html('');
			this.$container.height('auto');
		}, this));

		setTimeout($.proxy(function() {
			this.$actionMenu.find('a[data-action=collapse]:first').parent().removeClass('hidden');
			this.$actionMenu.find('a[data-action=expand]:first').parent().addClass('hidden');
		}, this), 200);

		// Remember that?
		if (!this.isNew && typeof Storage !== 'undefined')
		{
			var collapsedVariants = Craft.Commerce.VariantMatrix.getCollapsedVariantIds(),
				collapsedVariantsIndex = $.inArray(''+this.id, collapsedVariants);

			if (collapsedVariantsIndex != -1)
			{
				collapsedVariants.splice(collapsedVariantsIndex, 1);
				Craft.Commerce.VariantMatrix.setCollapsedVariantIds(collapsedVariants);
			}
		}

		if (!this.isNew)
		{
			Craft.Commerce.VariantMatrix.forgetCollapsedVariantId(this.id);
		}
		else if (this.$collapsedInput)
		{
			this.$collapsedInput.val('');
		}

		this.collapsed = false;
	},

	disable: function()
	{
		this.$container.children('input[name$="[enabled]"]:first').val('');
		this.$container.addClass('disabled');

		setTimeout($.proxy(function() {
			this.$actionMenu.find('a[data-action=disable]:first').parent().addClass('hidden');
			this.$actionMenu.find('a[data-action=enable]:first').parent().removeClass('hidden');
		}, this), 200);

		this.collapse(true);
	},

	enable: function()
	{
		this.$container.children('input[name$="[enabled]"]:first').val('1');
		this.$container.removeClass('disabled');

		setTimeout($.proxy(function() {
			this.$actionMenu.find('a[data-action=disable]:first').parent().removeClass('hidden');
			this.$actionMenu.find('a[data-action=enable]:first').parent().addClass('hidden');
		}, this), 200);
	},

	onMenuOptionSelect: function(option)
	{
		var batchAction = (this.matrix.variantSelect.totalSelected > 1 && this.matrix.variantSelect.isSelected(this.$container)),
			$option = $(option);

		switch ($option.data('action'))
		{
			case 'collapse':
			{
				if (batchAction)
				{
					this.matrix.collapseSelectedVariants();
				}
				else
				{
					this.collapse(true);
				}

				break;
			}

			case 'expand':
			{
				if (batchAction)
				{
					this.matrix.expandSelectedVariants();
				}
				else
				{
					this.expand();
				}

				break;
			}

			case 'disable':
			{
				if (batchAction)
				{
					this.matrix.disableSelectedVariants();
				}
				else
				{
					this.disable();
				}

				break;
			}

			case 'enable':
			{
				if (batchAction)
				{
					this.matrix.enableSelectedVariants();
				}
				else
				{
					this.enable();
					this.expand();
				}

				break;
			}

			case 'add':
			{
				this.matrix.addVariant(this.$container);
				break;
			}

			case 'delete':
			{
				if (batchAction)
				{
					if (confirm(Craft.t('Are you sure you want to delete the selected variants?')))
					{
						this.matrix.deleteSelectedVariants();
					}
				}
				else
				{
					this.selfDestruct();
				}

				break;
			}
		}
	},

	selfDestruct: function()
	{
		this.$container.velocity(this.matrix.getHiddenVariantCss(this.$container), 'fast', $.proxy(function()
		{
			this.$container.remove();
		}, this));
	}
});


})(jQuery);
