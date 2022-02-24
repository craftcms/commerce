import '../css/coupons.scss';

if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.Coupons = Garnish.Base.extend(
  {
    couponsTable: null,
    couponFormat: null,
    hud: null,

    $couponsContainer: null,
    $couponFormatField: null,
    $generateBtn: null,
    $generateHudBody: null,

    $hudFormatField: null,
    $hudCountField: null,
    $hudSubmitButton: null,

    init(couponBtnSelector, settings) {
      this.setSettings(settings, Craft.Commerce.Coupons.defaults);
      this.$couponsContainer = $(this.settings.couponsContainerSelector);
      this.$couponFormatField = this.$couponsContainer.find(this.settings.couponFormatFieldSelector);

      this.$generateBtn = $(this.settings.generateBtnSelector);
      this.addListener(this.$generateBtn, 'click', 'showGenerateHud');

      this.couponsTable = new Craft.EditableTable(this.settings.couponsTableId, this.settings.table.name, this.settings.table.cols, {
        defaultValues: this.settings.table.defaultValues,
        staticRows: false,
        minRows: null,
        allowAdd: true,
        allowDelete: true,
        maxRows: null,
      });
    },

    showGenerateHud() {
      if (!this.hud) {
        this.$generateHudBody = $('<div/>').attr('id', 'commerce-coupons-hud');

        this.$hudCountField = Craft.ui.createTextField({
          label: Craft.t('commerce', 'Number of Coupons'),
          name: 'couponCount',
          type: 'number',
          value: 1,
          max: 400,
        }).appendTo(this.$generateHudBody);

        this.$hudFormatField = Craft.ui.createTextField({
          label: Craft.t('commerce', 'Generated Coupon Format'),
          name: 'couponFormat',
          type: 'text',
          instructions: Craft.t('commerce', 'The format used to generate new coupons. `#` characters will be replaced with a random letter.'),
          value: this.settings.couponFormat,
        });

        this.$generateHudBody.append(this.$hudFormatField);

        this.$hudSubmitButton = Craft.ui.createSubmitButton({
          spinner: true,
        }).appendTo(this.$generateHudBody);

        this.hud = new Garnish.HUD(this.$generateBtn, this.$generateHudBody, {
          hudClass: 'hud',
          onSubmit: $.proxy(this, 'generateCoupons'),
        });

        return;
      }

      this.hud.show();
    },


    generateCoupons() {
      this.$hudSubmitButton.addClass('loading');
      Craft.ui.clearErrorsFromField(this.$hudFormatField);

      if (this.$hudFormatField.find('input').val().indexOf('#') === -1) {
        Craft.ui.addErrorsToField(this.$hudFormatField, [Craft.t('commerce', 'Coupon format is required and must contain at least one `#`.')]);
        this.$hudSubmitButton.removeClass('loading');
        return;
      }

      Craft.sendActionRequest('POST', 'commerce/discounts/generate-coupons', { data: this.getGenerateData() })
        .then((response) => {
          this.$couponFormatField.val(this.$hudFormatField.find('input').val());
          const {coupons} = response.data;

          if (coupons) {
            this.addCodes(coupons);
          }

          this.$hudSubmitButton.removeClass('loading');
          this.hud.hide();
        })
        .catch(({response}) => {
          if (response.data.errors && response.data.errors.length) {
            Craft.ui.addErrorsToField(this.$hudFormatField, response.data.errors);
          }

          this.$hudSubmitButton.removeClass('loading');
        });
    },

    addCodes(coupons) {
      if (!coupons || !coupons.length) {
        return;
      }

      // Loop through `coupons` and append each to the table
      coupons.forEach((coupon) => {
        const row = this.couponsTable.addRow(false, true);
        const codeField = row.$tr.find('textarea[name*="[code]"]');
        codeField.val(coupon);
      });
    },

    getAllCodesFromTable() {
      const codes = [];
      this.couponsTable.$tbody.find('[name*="[code]"]').each((index, element) => {
        codes.push($(element).val());
      });

      return codes;
    },

    getGenerateData() {
      if (!this.$couponsContainer) {
        return {};
      }

      return {
        count: this.$hudCountField.find('input').val(),
        format: this.$hudFormatField.find('input').val(),
        existingCodes: this.getAllCodesFromTable(),
      };
    },
  },
  {
    defaults: {
      couponsContainerSelector: '#commerce-coupons',
      couponFormatFieldSelector: 'input[name="couponFormat"]',
      couponsTableId: 'commerce-coupons-table',
      generateBtnSelector: '#commerce-coupons-generate',
      table: {},
    }
  });
