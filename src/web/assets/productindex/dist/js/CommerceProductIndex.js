/** global: Craft */
/** global: Garnish */
/**
 * Product index class
 */
Craft.Commerce.ProductIndex = Craft.BaseElementIndex.extend(
    {
        editableProductTypes: null,
        $newProductBtnProductType: null,
        $newProductBtn: null,

        init: function(elementType, $container, settings) {
            this.on('selectSource', $.proxy(this, 'updateButton'));
            this.on('selectSite', $.proxy(this, 'updateButton'));
            this.base(elementType, $container, settings);
        },

        afterInit: function() {
            // Find which of the visible productTypes the user has permission to create new products in
            this.editableProductTypes = [];

            for (var i = 0; i < Craft.Commerce.editableProductTypes.length; i++) {
                var productType = Craft.Commerce.editableProductTypes[i];

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

        updateButton: function() {
            if (!this.$source) {
                return;
            }

            // Get the handle of the selected source
            var selectedSourceHandle = this.$source.data('handle');

            var i, href, label;

            // Update the New Product button
            // ---------------------------------------------------------------------

            if (this.editableProductTypes.length) {
                // Remove the old button, if there is one
                if (this.$newProductBtnProductType) {
                    this.$newProductBtnProductType.remove();
                }

                // Determine if they are viewing a productType that they have permission to create products in
                var selectedProductType;

                if (selectedSourceHandle) {
                    for (i = 0; i < this.editableProductTypes.length; i++) {
                        if (this.editableProductTypes[i].handle === selectedSourceHandle) {
                            selectedProductType = this.editableProductTypes[i];
                            break;
                        }
                    }
                }

                this.$newProductBtnProductType = $('<div class="btngroup submit"/>');
                var $menuBtn;

                // If they are, show a primary "New product" button, and a dropdown of the other productTypes (if any).
                // Otherwise only show a menu button
                if (selectedProductType) {
                    href = this._getProductTypeTriggerHref(selectedProductType);
                    label = (this.settings.context === 'index' ? Craft.t('app', 'New product') : Craft.t('app', 'New {productType} product', {productType: selectedProductType.name}));
                    this.$newProductBtn = $('<a class="btn submit add icon" ' + href + '>' + Craft.escapeHtml(label) + '</a>').appendTo(this.$newProductBtnProductType);

                    if (this.settings.context !== 'index') {
                        this.addListener(this.$newProductBtn, 'click', function(ev) {
                            this._openCreateProductModal(ev.currentTarget.getAttribute('data-id'));
                        });
                    }

                    if (this.editableProductTypes.length > 1) {
                        $menuBtn = $('<div class="btn submit menubtn"></div>').appendTo(this.$newProductBtnProductType);
                    }
                }
                else {
                    this.$newProductBtn = $menuBtn = $('<div class="btn submit add icon menubtn">' + Craft.t('app', 'New product') + '</div>').appendTo(this.$newProductBtnProductType);
                }

                if ($menuBtn) {
                    var menuHtml = '<div class="menu"><ul>';

                    for (i = 0; i < this.editableProductTypes.length; i++) {
                        var productType = this.editableProductTypes[i];

                        if (this.settings.context === 'index' || productType !== selectedProductType) {
                            href = this._getProductTypeTriggerHref(productType);
                            label = (this.settings.context === 'index' ? productType.name : Craft.t('app', 'New {productType} product', {productType: productType.name}));
                            menuHtml += '<li><a ' + href + '">' + Craft.escapeHtml(label) + '</a></li>';
                        }
                    }

                    menuHtml += '</ul></div>';

                    $(menuHtml).appendTo(this.$newProductBtnProductType);
                    var menuBtn = new Garnish.MenuBtn($menuBtn);

                    if (this.settings.context !== 'index') {
                        menuBtn.on('optionSelect', $.proxy(function(ev) {
                            this._openCreateProductModal(ev.option.getAttribute('data-id'));
                        }, this));
                    }
                }

                this.addButton(this.$newProductBtnProductType);
            }

            // Update the URL if we're on the Categories index
            // ---------------------------------------------------------------------

            if (this.settings.context === 'index' && typeof history !== 'undefined') {
                var uri = 'commerce/products';

                if (selectedSourceHandle) {
                    uri += '/' + selectedSourceHandle;
                }

                history.replaceState({}, '', Craft.getUrl(uri));
            }
        },

        _getProductTypeTriggerHref: function(productType) {
            if (this.settings.context === 'index') {
                var uri = 'commerce/products/' + productType.handle + '/new';
                if (this.siteId && this.siteId != Craft.primarySiteId) {
                    for (var i = 0; i < Craft.sites.length; i++) {
                        if (Craft.sites[i].id == this.siteId) {
                            uri += '/'+Craft.sites[i].handle;
                        }
                    }
                }
                return 'href="' + Craft.getUrl(uri) + '"';
            }
            else {
                return 'data-id="' + productType.id + '"';
            }
        },

        _openCreateProductModal: function(productTypeId) {
            if (this.$newProductBtn.hasClass('loading')) {
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

            this.$newProductBtn.addClass('inactive');
            var newProductBtnText = this.$newProductBtn.text();
            this.$newProductBtn.text(Craft.t('app', 'New {productType} product', {productType: productType.name}));

            Craft.createElementEditor(this.elementType, {
                hudTrigger: this.$newProductBtnProductType,
                elementType: 'craft\\elements\\Product',
                siteId: this.siteId,
                attributes: {
                    productTypeId: productTypeId
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

// Register it!
Craft.registerElementIndexClass('craft\\commerce\\elements\\Product', Craft.Commerce.ProductIndex);
