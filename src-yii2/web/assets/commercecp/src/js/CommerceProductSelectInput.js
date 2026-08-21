if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

/**
 * Product Select Input
 *
 * Extends BaseElementSelectInput to enable inline product creation
 */
Craft.Commerce.ProductSelectInput = Craft.BaseElementSelectInput.extend({
  get productType() {
    if (!this.settings.productTypeId) {
      return null;
    }

    return Craft.Commerce.editableProductTypes.find(
      (productType) => productType.id === this.settings.productTypeId
    );
  },

  canCreateElements() {
    return !!this.productType;
  },

  async createElement(title) {
    const response = await Craft.sendActionRequest(
      'POST',
      'commerce/products/create',
      {
        data: {
          siteId: this.settings.criteria.siteId,
          productType: this.productType.handle,
          title: title,
        },
      }
    );

    const product = response.data.product;

    try {
      await this.showElementEditor(product);
    } catch {
      return null;
    }

    return product.id;
  },

  showElementEditor(product) {
    return new Promise((resolve, reject) => {
      const slideout = Craft.createElementEditor(
        'craft\\commerce\\elements\\Product',
        {
          siteId: this.settings.criteria.siteId,
          elementId: product.id,
          draftId: product.draftId,
          params: {
            fresh: 1,
          },
        }
      );

      let submitted = false;

      slideout.on('submit', () => {
        submitted = true;
        resolve();
      });

      slideout.on('close', () => {
        if (!submitted) {
          reject();
        }
      });
    });
  },
});
