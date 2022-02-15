if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.Coupons = Garnish.Base.extend(
  {
    couponBtnSelector: null,
    couponTable: null,

    tempTableId: null,

    $couponsBtn: null,
    $couponsContainer: null,
    $generateBtn: null,
    $slideout: null,
    $slideoutContents: null,


    init(couponBtnSelector, settings) {
      this.couponBtnSelector = couponBtnSelector;
      this.setSettings(settings, Craft.Commerce.Coupons.defaults);

      this.$couponsBtn = document.querySelector(this.couponBtnSelector);

      this.$couponsContainer = document.querySelector(this.settings.couponsContainerSelector);
      this.$couponsContainerInner = document.querySelector(this.settings.couponsContainerInnerSelector);
      this.$slideoutContents = this.$couponsContainer.cloneNode(true);

      this.tempTableId = this.$slideoutContents.querySelector('table.editable').id
      this.$slideoutContents.querySelector('table.editable').id = this.settings.tableSlideoutId;

      this.$generateBtn = this.$slideoutContents.querySelector(this.settings.generateBtnSelector);

      this._addListeners();
    },

    _addListeners() {
      this.addListener(this.$couponsBtn, 'click', 'openSlideout');
      this.addListener(this.$generateBtn, 'click', 'generateCoupons');
    },

    openSlideout(ev) {
      ev.preventDefault();

      this.$slideout = new Craft.Slideout(this.$slideoutContents, {
        autoOpen: false,
        containerElement: 'form',
        containerAttributes: {
          action: '',
          method: 'post',
          novalidate: '',
          class: 'coupon-editor',
        },
      });

      this.$slideout.on('open', () => {
        setTimeout(function() {
          this.couponTable = new Craft.EditableTable(this.settings.tableSlideoutId, this.settings.table.name, this.settings.table.cols, {
            defaultValues: this.settings.table.defaultValues,
            staticRows: false,
            minRows: null,
            allowAdd: true,
            allowDelete: true,
            maxRows: null,
            onDeleteRow: this.onDeleteCoupon
          });
        }.bind(this), 300);
      });

      this.$slideout.on('close', () => {
        this.$slideoutContents.querySelector(`#${this.settings.tableSlideoutId}`).id = this.tempTableId;
        this.$couponsContainer.replaceChild(this.$slideoutContents.querySelector(this.settings.couponsContainerInnerSelector), this.$couponsContainerInner);
        this.$slideout.destroy();
      });

      this.$slideout.open();
    },

    generateCoupons(ev) {
      ev.preventDefault();

      Craft.postActionRequest('commerce/discounts/generate-coupons', this.getGenerateData(), (response, status) => {
        if (status !== 'success') {
          console.log('throw an error');
          return;
        }

        this.appendCoupons(response.coupons);
      });
    },

    appendCoupons(coupons) {
      console.log(coupons);
      if (!coupons || !coupons.length) {
        return;
      }

      const row = this.couponTable.addRow(false);
      console.log(row.$tr.find('.singleline-cell textarea'));
    },

    getGenerateData() {
      if (!this.$slideoutContents) {
        return {};
      }

      const $countField = this.$slideoutContents.querySelector('input[name=count]');
      const $lengthField = this.$slideoutContents.querySelector('input[name=length]');
      return {
        count: $countField.value,
        length: $lengthField.value,
      };
    },

    onDeleteCoupon(id) {
      // prevent deletion of coupons that have been used
      console.log(id);
    }
  },
  {
    defaults: {
      couponsContainerInnerSelector: '#commerce-coupons-inner',
      couponsContainerSelector: '#commerce-coupons',
      generateSelectors: {
        count: 'input[name=count]',
        length: 'input[name=length]',
      },
      table: {},
      tableId: 'coupons-table',
      tableSlideoutId: 'slideout-coupons',
    }
  });
