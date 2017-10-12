if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}
Craft.Commerce.ProductsIndex = Craft.BaseElementIndex.extend(
    {
        editableProductTypes: null,
        $newProductsBtnGroup: null,
        $newProductsBtn: null,

        afterInit: function() {
            // Find which of the visible productTypes the user has permission to create new products in
            this.editableProductTypes = [];
            for (var i = 0; i < Craft.Commerce.editableProductsTypes.length; i++) {
                var productType = Craft.Commerce.editableProductsTypes[i];

                if (this.getSourceByKey('productType:' + productType.id)) {
                    this.editableProductTypes.push(productType);
                }
            }

            this.base();
        },

        getDefaultSourceKey: function() {
            // Did they request a specific product productType in the URL?
            if (this.settings.context === 'index' && typeof defaultProductTypeHandle !== 'undefined') {
                for (var i = 0; i < this.$sources.length; i++) {
                    var $source = $(this.$sources[i]);

                    if ($source.data('handle') === defaultProductTypeHandle) {
                        return $source.data('key');
                    }
                }
            }

            return this.base();
        },

        onSelectSource: function() {
            // Get the handle of the selected source
            var selectedSourceHandle = this.$source.data('handle');

            var i, href, label;

            // Update the New Products button
            // ---------------------------------------------------------------------

            if (this.editableProductTypes.length) {
                // Remove the old button, if there is one
                if (this.$newProductsBtnGroup) {
                    this.$newProductsBtnGroup.remove();
                }

                // Determine if they are viewing a productType that they have permission to create products in
                var selectedGroup;

                if (selectedSourceHandle) {
                    for (i = 0; i < this.editableProductTypes.length; i++) {
                        if (this.editableProductTypes[i].handle === selectedSourceHandle) {
                            selectedGroup = this.editableProductTypes[i];
                            break;
                        }
                    }
                }

                this.$newProductsBtnGroup = $('<div class="btngroup submit"/>');
                var $menuBtn;

                // If they are, show a primary "New product" button, and a dropdown of the other productTypes (if any).
                // Otherwise only show a menu button
                if (selectedGroup) {
                    href = this._getGroupTriggerHref(selectedGroup);
                    label = (this.settings.context === 'index' ? Craft.t('app', 'New product') : Craft.t('app', 'New {productType} product', {productType: selectedGroup.name}));
                    this.$newProductsBtn = $('<a class="btn submit add icon" ' + href + '>' + Craft.escapeHtml(label) + '</a>').appendTo(this.$newProductsBtnGroup);

                    if (this.settings.context !== 'index') {
                        this.addListener(this.$newProductsBtn, 'click', function(ev) {
                            this._openCreateProductsModal(ev.currentTarget.getAttribute('data-id'));
                        });
                    }

                    if (this.editableProductTypes.length > 1) {
                        $menuBtn = $('<div class="btn submit menubtn"></div>').appendTo(this.$newProductsBtnGroup);
                    }
                }
                else {
                    this.$newProductsBtn = $menuBtn = $('<div class="btn submit add icon menubtn">' + Craft.t('app', 'New product') + '</div>').appendTo(this.$newProductsBtnGroup);
                }

                if ($menuBtn) {
                    var menuHtml = '<div class="menu"><ul>';

                    for (i = 0; i < this.editableProductTypes.length; i++) {
                        var productType = this.editableProductTypes[i];

                        if (this.settings.context === 'index' || productType !== selectedGroup) {
                            href = this._getGroupTriggerHref(productType);
                            label = (this.settings.context === 'index' ? productType.name : Craft.t('app', 'New {productType} product', {productType: productType.name}));
                            menuHtml += '<li><a ' + href + '">' + Craft.escapeHtml(label) + '</a></li>';
                        }
                    }

                    menuHtml += '</ul></div>';

                    $(menuHtml).appendTo(this.$newProductsBtnGroup);
                    var menuBtn = new Garnish.MenuBtn($menuBtn);

                    if (this.settings.context !== 'index') {
                        menuBtn.on('optionSelect', $.proxy(function(ev) {
                            this._openCreateProductsModal(ev.option.getAttribute('data-id'));
                        }, this));
                    }
                }

                this.addButton(this.$newProductsBtnGroup);
            }

            // Update the URL if we're on the Categories index
            // ---------------------------------------------------------------------

            if (this.settings.context === 'index' && typeof history !== 'undefined') {
                var uri = 'products';

                if (selectedSourceHandle) {
                    uri += '/' + selectedSourceHandle;
                }

                history.replaceState({}, '', Craft.getUrl(uri));
            }

            this.base();
        },

        _getGroupTriggerHref: function(productType) {
            if (this.settings.context === 'index') {
                return 'href="' + Craft.getUrl('commerce/products/' + productType.handle + '/new') + '"';
            }
            else {
                return 'data-id="' + productType.id + '"';
            }
        },

        _openCreateProductsModal: function(productTypeId) {
            if (this.$newProductsBtn.hasClass('loading')) {
                return;
            }

            // Find the productType
            var productType;

            for (var i = 0; i < this.editableProductTypes.length; i++) {
                if (this.editableProductTypes[i].id == productTypeId) {
                    productType = this.editableProductTypes[i];
                    break;
                }
            }

            if (!productType) {
                return;
            }

            this.$newProductsBtn.addClass('inactive');
            var newProductsBtnText = this.$newProductsBtn.text();
            this.$newProductsBtn.text(Craft.t('app', 'New {productType} product', {productType: productType.name}));

            Craft.createElementEditor(this.elementType, {
                hudTrigger: this.$newProductsBtnGroup,
                elementType: 'craft\\elements\\Products',
                siteId: this.siteId,
                attributes: {
                    typeId: productTypeId
                },
                onBeginLoading: $.proxy(function() {
                    this.$newProductsBtn.addClass('loading');
                }, this),
                onEndLoading: $.proxy(function() {
                    this.$newProductsBtn.removeClass('loading');
                }, this),
                onHideHud: $.proxy(function() {
                    this.$newProductsBtn.removeClass('inactive').text(newProductsBtnText);
                }, this),
                onSaveElement: $.proxy(function(response) {
                    // Make sure the right productType is selected
                    var productTypeSourceKey = 'productType:' + productTypeId;

                    if (this.sourceKey !== productTypeSourceKey) {
                        this.selectSourceByKey(productTypeSourceKey);
                    }

                    this.selectElementAfterUpdate(response.id);
                    this.updateElements();
                }, this)
            });
        }
    });

Craft.registerElementIndexClass('craft\\commerce\\elements\\Product', Craft.Commerce.ProductsIndex);
