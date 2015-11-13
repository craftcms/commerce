(function($){

if (typeof Craft.Commerce === typeof undefined) {
	Craft.Commerce = {};
}


var elementTypeClass = 'Commerce_Product';

/**
 * Product index class
 */
Craft.Commerce.ProductIndex = Craft.BaseElementIndex.extend({

	productTypes: null,

	$newProductBtnGroup: null,
	$newProductBtn: null,

	canCreateProducts: false,

	afterInit: function() {
		// Find which product types are being shown as sources
		this.productTypes = [];

		for (var i = 0; i < this.$sources.length; i++) {
			var $source = this.$sources.eq(i),
				key = $source.data('key'),
				match = key.match(/^productType:(\d+)$/);

			if (match) {
				this.productTypes.push({
					id: parseInt(match[1]),
					handle: $source.data('handle'),
					name: $source.text(),
					editable: $source.data('editable')
				});

				if (!this.canCreateProducts && $source.data('editable')) {
					this.canCreateProducts = true;
				}
			}
		}

		this.base();
	},

	getDefaultSourceKey: function() {
		// Did they request a specific product type in the URL?
		if (this.settings.context == 'index' && typeof defaultProductTypeHandle != typeof undefined) {
			for (var i = 0; i < this.$sources.length; i++) {
				var $source = $(this.$sources[i]);
				if ($source.data('handle') == defaultProductTypeHandle) {
					return $source.data('key');
				}
			}
		}

		return this.base();
	},

	onSelectSource: function() {
		// Get the handle of the selected source
		var selectedSourceHandle = this.$source.data('handle');

		// Update the New Product button
		// ---------------------------------------------------------------------

		// Remove the old button, if there is one
		if (this.$newProductBtnGroup) {
			this.$newProductBtnGroup.remove();
		}

		// Are they viewing a product type source?
		var selectedProductType;
		if (selectedSourceHandle) {
			for (var i = 0; i < this.productTypes.length; i++) {
				if (this.productTypes[i].handle == selectedSourceHandle) {
					selectedProductType = this.productTypes[i];
					break;
				}
			}
		}

		// Are they allowed to create new products?
		if (this.canCreateProducts) {
			this.$newProductBtnGroup = $('<div class="btngroup submit"/>');
			var $menuBtn;

			// If they are, show a primany "New product" button, and a dropdown of the other product types (if any).
			// Otherwise only show a menu button
			if (selectedProductType) {
				var href = this._getProductTypeTriggerHref(selectedProductType),
					label = (this.settings.context == 'index' ? Craft.t('New product') : Craft.t('New {productType} product', {productType: selectedProductType.name}));
				this.$newProductBtn = $('<a class="btn submit add icon" '+href+'>'+label+'</a>').appendTo(this.$newProductBtnGroup);

				if (this.settings.context != 'index') {
					this.addListener(this.$newProductBtn, 'click', function(ev) {
						this._openCreateProductModal(ev.currentTarget.getAttribute('data-id'));
					});
				}

				if (this.productTypes.length > 1) {
					$menuBtn = $('<div class="btn submit menubtn"></div>').appendTo(this.$newProductBtnGroup);
				}
			} else {
				this.$newProductBtn = $menuBtn = $('<div class="btn submit add icon menubtn">'+Craft.t('New product')+'</div>').appendTo(this.$newProductBtnGroup);
			}

			if ($menuBtn) {
				var menuHtml = '<div class="menu"><ul>';

				for (var i = 0; i < this.productTypes.length; i++) {
					var productType = this.productTypes[i];

					if (this.settings.context == 'index' || productType != selectedProductType) {
						var href = this._getProductTypeTriggerHref(productType),
							label = (this.settings.context == 'index' ? productType.name : Craft.t('New {productType} product', {productType: productType.name}));
						menuHtml += '<li><a '+href+'">'+label+'</a></li>';
					}
				}

				menuHtml += '</ul></div>';

				var $menu = $(menuHtml).appendTo(this.$newProductBtnGroup),
					menuBtn = new Garnish.MenuBtn($menuBtn);

				if (this.settings.context != 'index') {
					menuBtn.on('optionSelect', $.proxy(function(ev) {
						this._openCreateProductModal(ev.option.getAttribute('data-id'));
					}, this));
				}
			}

			this.addButton(this.$newProductBtnGroup);
		}

		// Update the URL if we're on the Products index
		// ---------------------------------------------------------------------

		if (this.settings.context == 'index' && typeof history != typeof undefined) {
			var uri = 'commerce/products';
			if (selectedSourceHandle) {
				uri += '/'+selectedSourceHandle;
			}
			history.replaceState({}, '', Craft.getUrl(uri));
		}

		this.base();
	},

	_getProductTypeTriggerHref: function(productType)
	{
		if (this.settings.context == 'index') {
			return 'href="'+Craft.getUrl('commerce/products/'+productType.handle+'/new')+'"';
		} else {
			return 'data-id="'+productType.id+'"';
		}
	},

	_openCreateProductModal: function(productTypeId)
	{
		if (this.$newProductBtn.hasClass('loading')) {
			return;
		}

		// Find the product type
		var productType;

		for (var i = 0; i < this.productTypes.length; i++) {
			if (this.productTypes[i].id == productTypeId) {
				productType = this.productTypes[i];
				break;
			}
		}

		if (!productType) {
			return;
		}

		this.$newProductBtn.addClass('inactive');
		var newProductBtnText = this.$newProductBtn.text();
		this.$newProductBtn.text(Craft.t('New {productType} product', {productType: productType.name}));

		new Craft.ElementEditor({
			hudTrigger: this.$newProductBtnGroup,
			elementType: elementTypeClass,
			locale: this.locale,
			attributes: {
				typeId: productTypeId
			},
			onBeginLoading: $.proxy(function() {
				this.$newProductBtn.addClass('loading');
			}, this),
			onEndLoading: $.proxy(function() {
				this.$newProductBtn.removeClass('loading');
			}, this),
			onHideHud: $.proxy(function() {
				this.$newProductBtn.removeClass('inactive').text(newProductBtnText);
			}, this),
			onSaveElement: $.proxy(function(response) {
				// Make sure the right product type is selected
				var productTypeSourceKey = 'productType:'+productTypeId;

				if (this.sourceKey != productTypeSourceKey) {
					this.selectSourceByKey(productTypeSourceKey);
				}

				this.selectElementAfterUpdate(response.id);
				this.updateElements();
			}, this)
		});
	}
});

// Register it!
try {
	Craft.registerElementIndexClass(elementTypeClass, Craft.Commerce.ProductIndex);
}
catch(e) {
	// Already registered
}

})(jQuery);
