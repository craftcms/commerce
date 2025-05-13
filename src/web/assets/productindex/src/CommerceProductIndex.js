/** global: Craft */
/** global: Garnish */
/**
 * Product index class
 */
Craft.Commerce.ProductIndex = Craft.BaseElementIndex.extend({
  editableProductTypes: null,
  $newProductBtnGroup: null,
  $newProductBtn: null,

  init: function (elementType, $container, settings) {
    this.on('selectSource', $.proxy(this, 'updateButton'));
    this.on('selectSite', $.proxy(this, 'updateButton'));
    this.base(elementType, $container, settings);
  },

  afterInit: function () {
    // Find which of the visible productTypes the user has permission to create new products in
    this.editableProductTypes = [];

    for (const productType of Craft.Commerce.editableProductTypes) {
      if (this.getSourceByKey(`productType:${productType.uid}`)) {
        this.editableProductTypes.push(productType);
      }
    }

    this.base();
  },

  getDefaultSourceKey: function () {
    // Did they request a specific product productType in the URL?
    if (
      this.settings.context === 'index' &&
      typeof defaultProductTypeHandle !== 'undefined'
    ) {
      for (var i = 0; i < this.$sources.length; i++) {
        var $source = $(this.$sources[i]);

        if ($source.data('handle') === defaultProductTypeHandle) {
          return $source.data('key');
        }
      }
    }

    return this.base();
  },

  updateButton: function () {
    if (!this.$source) {
      return;
    }

    // Get the handle of the selected source
    const productTypeHandle = this.$source.data('handle');

    // Update the New Product button
    // ---------------------------------------------------------------------

    if (this.editableProductTypes.length) {
      // Remove the old button, if there is one
      if (this.$newProductBtnGroup) {
        this.$newProductBtnGroup.remove();
      }

      // Determine if they are viewing a productType that they have permission to create products in
      const selectedProductType = this.editableProductTypes.find(
        (t) => t.handle === productTypeHandle
      );

      this.$newProductBtnGroup = $(
        '<div class="btngroup submit" data-wrapper/>'
      );
      let $menuBtn;
      const menuId = `new-product-menu-${Craft.randomString(10)}`;

      // If they are, show a primary "New product" button, and a dropdown of the other productTypes (if any).
      // Otherwise only show a menu button
      if (selectedProductType) {
        const visibleLabel =
          this.settings.context === 'index'
            ? Craft.t('commerce', 'New product')
            : Craft.t('commerce', 'New {productType} product', {
                productType: selectedProductType.name,
              });

        const ariaLabel =
          this.settings.context === 'index'
            ? Craft.t('commerce', 'New {productType} product', {
                productType: selectedProductType.name,
              })
            : visibleLabel;

        // In index contexts, the button functions as a link
        // In non-index contexts, the button triggers a slideout editor
        const role = this.settings.context === 'index' ? 'link' : null;

        this.$newProductBtn = Craft.ui
          .createButton({
            label: visibleLabel,
            ariaLabel: ariaLabel,
            spinner: true,
            role: role,
          })
          .addClass('submit add icon')
          .appendTo(this.$newProductBtnGroup);

        this.addListener(this.$newProductBtn, 'click mousedown', (ev) => {
          // If this is the element index, check for Ctrl+clicks and middle button clicks
          if (
            this.settings.context === 'index' &&
            ((ev.type === 'click' && Garnish.isCtrlKeyPressed(ev)) ||
              (ev.type === 'mousedown' && ev.originalEvent.button === 1))
          ) {
            window.open(
              Craft.getUrl(`commerce/products/${productType.handle}/new`)
            );
          } else if (ev.type === 'click') {
            this._createProduct(selectedProductType.id);
          }
        });

        if (this.editableProductTypes.length > 1) {
          $menuBtn = $('<button/>', {
            type: 'button',
            class: 'btn submit menubtn btngroup-btn-last',
            'aria-controls': menuId,
            'data-disclosure-trigger': '',
            'aria-label': Craft.t('commerce', 'New product, choose a type'),
          }).appendTo(this.$newProductBtnGroup);
        }
      } else {
        this.$newProductBtn = $menuBtn = Craft.ui
          .createButton({
            label: Craft.t('app', 'New product'),
            ariaLabel: Craft.t('app', 'New product, choose a type'),
            spinner: true,
          })
          .addClass('submit add icon menubtn btngroup-btn-last')
          .attr('aria-controls', menuId)
          .attr('data-disclosure-trigger', '')
          .appendTo(this.$newProductBtnGroup);
      }

      this.addButton(this.$newProductBtnGroup);

      if ($menuBtn) {
        const $menuContainer = $('<div/>', {
          id: menuId,
          class: 'menu menu--disclosure',
        }).appendTo(this.$newProductBtnGroup);
        const $ul = $('<ul/>').appendTo($menuContainer);

        for (const productType of this.editableProductTypes) {
          const anchorRole =
            this.settings.context === 'index' ? 'link' : 'button';
          if (
            this.settings.context === 'index' ||
            productType !== selectedProductType
          ) {
            const $li = $('<li/>').appendTo($ul);
            const $a = $('<a/>', {
              role: anchorRole === 'button' ? 'button' : null,
              href: Craft.getUrl(`commerce/products/${productType.handle}/new`),
              type: anchorRole === 'button' ? 'button' : null,
              text: Craft.t('commerce', 'New {productType} product', {
                productType: productType.name,
              }),
            }).appendTo($li);
            this.addListener($a, 'activate', () => {
              $menuBtn.data('trigger').hide();
              this._createProduct(productType.id);
            });

            if (anchorRole === 'button') {
              this.addListener($a, 'keydown', (event) => {
                if (event.keyCode === Garnish.SPACE_KEY) {
                  event.preventDefault();
                  $menuBtn.data('trigger').hide();
                  this._createProduct(productType.id);
                }
              });
            }
          }
        }

        new Garnish.DisclosureMenu($menuBtn);
      }
    }

    // Update the URL if we're on the Categories index
    // ---------------------------------------------------------------------

    if (this.settings.context === 'index') {
      let uri = 'commerce/products';

      if (productTypeHandle) {
        uri += '/' + productTypeHandle;
      }

      Craft.setPath(uri);
    }
  },

  _createProduct: function (productTypeId) {
    if (this.$newProductBtn.hasClass('loading')) {
      console.warn('New product creation already in progress.');
      return;
    }

    // Find the product type
    const productType = this.editableProductTypes.find(
      (t) => t.id === productTypeId
    );

    if (!productType) {
      throw `Invalid product type ID: ${productTypeId}`;
    }

    this.$newProductBtn.addClass('loading');

    Craft.sendActionRequest('POST', 'commerce/products/create', {
      data: {
        siteId: this.siteId,
        productType: productType.handle,
      },
    })
      .then(({data}) => {
        if (this.settings.context === 'index') {
          document.location.href = Craft.getUrl(data.cpEditUrl, {fresh: 1});
        } else {
          const slideout = Craft.createElementEditor(this.elementType, {
            siteId: this.siteId,
            elementId: data.product.id,
            draftId: data.product.draftId,
            params: {
              fresh: 1,
            },
          });
          slideout.on('submit', () => {
            this.clearSearch();
            this.setSelectedSortAttribute('dateCreated', 'desc');
            this.selectElementAfterUpdate(data.product.id);
            this.updateElements();
          });
        }
      })
      .finally(() => {
        this.$newProductBtn.removeClass('loading');
      });
  },
});

// Register it!
Craft.registerElementIndexClass(
  'craft\\commerce\\elements\\Product',
  Craft.Commerce.ProductIndex
);
