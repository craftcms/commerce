if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.Coupons = Garnish.Base.extend(
  {
    $couponsContainer: null,
    $addRowBtn: null,
    $couponsBtn: null,
    $slideout: null,

    init: function(settings) {
      this.setSettings(settings);
      console.log('init coupons');

      this.$addRowBtn = document.querySelector('#commerce-add-coupon-row');
      this.$couponsContainer = document.querySelector('#commerce-coupons');
      this.$couponsBtn = document.querySelector('#commerce-coupon-button');

      this.addListener(this.$couponsBtn, 'click', 'openSlideout');
      this.addListener(this.$addRowBtn, 'click', 'addCouponRow');
    },

    openSlideout: function (ev) {
      ev.preventDefault();
      this.$slideout = new Craft.Slideout(this.$couponsContainer, {
        containerElement: 'form',
        containerAttributes: {
          action: '',
          method: 'post',
          novalidate: '',
          class: 'coupon-editor',
        },
      });

      this.$slideout.on('close', () => {
        console.log('closing');
        $slideout.destroy();
      });
    },

    addCouponRow: function() {
      let $row = this.getCouponRowTemplate();

      this.$couponsContainer.appendChild($row);
    },

    getCouponRowTemplate: function() {
      let $row = document.createElement('div');
      $row.createTextNode('Coupon')

      return $row;
    },
  },
  {
    defaults: {}
  });
