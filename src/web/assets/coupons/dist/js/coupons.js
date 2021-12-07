if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.Coupons = Garnish.Base.extend(
  {
    $couponsBtn: null,
    $couponsContainer: null,
    $generateBtn: null,
    $slideout: null,
    $slideoutContents: null,

    init: function(settings) {
      this.setSettings(settings);
      console.log('init coupons');

      this.$couponsBtn = document.querySelector('#commerce-coupon-button');


      this.addListener(this.$couponsBtn, 'click', 'openSlideout');
    },

    openSlideout: function (ev) {
      ev.preventDefault();
      this.$couponsContainer = document.querySelector('#commerce-coupons');
      this.$couponsContainerInner = document.querySelector('#commerce-coupons .commerce-coupons-inner');
      this.$slideoutContents = this.$couponsContainer.cloneNode(true);
      this.$slideoutContents.querySelector('table#coupons').id = 'slideout-coupons';

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
        console.log('slideout opening');
        this.$generateBtn = this.$couponsContainer.querySelector('.commerce-coupon-generate');
        console.log(this.$generateBtn);
        this.addListener(this.$generateBtn, 'click', function(ev) { ev.preventDefault(); });
        // this.addListener(this.$generateBtn, 'click', 'generateCoupons');

        new Craft.EditableTable(this.settings.table.id, this.settings.table.name, this.settings.table.cols, {
          defaultValues: this.settings.table.defaultValues,
          staticRows: false,
          minRows: null,
          allowAdd: true,
          allowDelete: true,
          maxRows: null,
          onDeleteRow: this.onDeleteCoupon
        });
        this.$couponsContainer.style.display = 'block';
      });

      this.$slideout.on('close', () => {
        this.$slideoutContents.querySelector('table#slideout-coupons').id = 'coupons';
        this.$couponsContainer.replaceChild(this.$slideoutContents.querySelector('.commerce-coupons-inner'), this.$couponsContainerInner);
        this.$slideout.destroy();
      });

      this.$slideout.open();
    },

    generateCoupons: ev => {
      console.log('generate');
      ev.preventDefault();

      let $countField = this.$couponsContainer.querySelector('input[name=count]');
      let $lengthField = this.$couponsContainer.querySelector('input[name=length]');
      let data = {
        count: $countField.value,
        length: $lengthField.value,
      };

      console.log(Craft.csrfTokenValue);

      Craft.postActionRequest('commerce/discounts/generate-coupons', data, (response, status) => {
        console.log('returned.', response);
      });
    },

    onDeleteCoupon: id => {
      // prevent deletion of coupons that have been used
      console.log(id);
    }
  },
  {
    defaults: {
      table: {},
    }
  });
