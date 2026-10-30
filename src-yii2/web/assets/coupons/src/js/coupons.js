import '../css/coupons.scss';
/* global Craft */
/* global Garnish */
/* global $ */

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
      this.$couponFormatField = this.$couponsContainer.find(
        this.settings.couponFormatFieldSelector
      );

      this.$generateBtn = $(this.settings.generateBtnSelector);
      this.couponsTable = $('#' + this.settings.couponsTableId);

      this.eventListeners();
    },

    eventListeners(remove = false) {
      if (remove) {
        this.$couponsContainer.find('button.btn.add').off('click');
        this.couponsTable.find('tbody button.delete').off('click');
      }

      this.addListener(this.$generateBtn, 'click', 'showGenerateHud');

      this.$couponsContainer.find('button.add').on('click', (e) => {
        e.preventDefault();
        const rowCount = this.couponsTable.find('tbody tr').length;
        this.createRow(rowCount, '', null);
        this.couponsTable.removeClass('hidden');
        this.eventListeners(true);
      });

      this.couponsTable.find('tbody button.delete').on('click', (e) => {
        e.preventDefault();
        $(e.currentTarget).closest('tr').remove();
        if (!this.couponsTable.find('tbody tr').length) {
          this.couponsTable.addClass('hidden');
        }
      });
    },

    showGenerateHud() {
      if (!this.hud) {
        this.$generateHudBody = $('<div/>').attr('id', 'commerce-coupons-hud');

        this.$hudCountField = Craft.ui
          .createTextField({
            label: Craft.t('commerce', 'Number of Coupons'),
            name: 'couponCount',
            type: 'number',
            value: 1,
            max: 400,
          })
          .appendTo(this.$generateHudBody);

        this.$hudFormatField = Craft.ui.createTextField({
          label: Craft.t('commerce', 'Generated Coupon Format'),
          name: 'couponFormat',
          type: 'text',
          instructions: Craft.t(
            'commerce',
            'The format used to generate new coupons, e.g. {example}. Any `#` characters will be replaced with a random letter.',
            {
              example: '`summer_####`',
            }
          ),
          value: this.settings.couponFormat,
        });

        this.$generateHudBody.append(this.$hudFormatField);

        this.$hudMaxUsesField = Craft.ui.createTextField({
          label: Craft.t('commerce', 'Max Uses'),
          name: 'maxUses',
          type: 'number',
          value: '',
          max: 400,
        });

        this.$generateHudBody.append(this.$hudMaxUsesField);

        this.$hudSubmitButton = Craft.ui
          .createSubmitButton({
            spinner: true,
          })
          .appendTo(this.$generateHudBody);

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
        Craft.ui.addErrorsToField(this.$hudFormatField, [
          Craft.t(
            'commerce',
            'Coupon format is required and must contain at least one `#`.'
          ),
        ]);
        this.$hudSubmitButton.removeClass('loading');
        return;
      }

      Craft.sendActionRequest('POST', 'commerce/discounts/generate-coupons', {
        data: this.getGenerateData(),
      })
        .then((response) => {
          this.$couponFormatField.val(this.$hudFormatField.find('input').val());
          const {coupons} = response.data;

          if (coupons) {
            this.addCodes(coupons);
          }

          this.hud.hide();
        })
        .catch(({response}) => {
          if (response.data.message) {
            Craft.ui.addErrorsToField(this.$hudFormatField, [
              response.data.message,
            ]);
          }
        })
        .finally(() => {
          this.$hudSubmitButton.removeClass('loading');
        });
    },

    addCodes(coupons) {
      if (!coupons || !coupons.length) {
        return;
      }

      let rowCount = this.couponsTable.find('tbody tr').length;
      const maxUses = this.hud.$main.find('input[name="maxUses"]').val();
      // Loop through `coupons` and append each to the table
      coupons.forEach((coupon) => {
        this.createRow(rowCount, coupon, maxUses);
        rowCount++;
      });

      this.couponsTable.removeClass('hidden');

      this.eventListeners(true);
    },

    createRow(rowCount, coupon, maxUses = null) {
      const _row = $('<tr>');
      _row.data('id', rowCount);

      const _idField = $('<td>');
      _idField.addClass('hidden singleline-cell textual');
      const _idFieldTextarea = $('<textarea>');
      _idFieldTextarea.attr(
        'aria-labelledby',
        'commerce-coupons-table-heading-1'
      );
      _idFieldTextarea.attr('aria-describedby', '');
      _idFieldTextarea.attr('name', `coupons[${rowCount}][id]`);
      _idFieldTextarea.attr('rows', '1');

      _idField.append(_idFieldTextarea);
      _row.append(_idField);

      const _codeField = $('<td>');
      _codeField.addClass('singleline-cell textual');
      const _codeFieldTextarea = $('<textarea>');
      _codeFieldTextarea.attr(
        'aria-labelledby',
        'commerce-coupons-table-heading-2'
      );
      _codeFieldTextarea.attr('aria-describedby', '');
      _codeFieldTextarea.attr('name', `coupons[${rowCount}][code]`);
      _codeFieldTextarea.attr('rows', '1');
      _codeFieldTextarea.val(coupon);

      _codeField.append(_codeFieldTextarea);
      _row.append(_codeField);

      const _usesField = $('<td>');
      _usesField.addClass('singleline-cell textual');
      const _usesFieldTextarea = $('<textarea>');
      _usesFieldTextarea.attr(
        'aria-labelledby',
        'commerce-coupons-table-heading-3'
      );
      _usesFieldTextarea.attr('aria-describedby', '');
      _usesFieldTextarea.attr('name', `coupons[${rowCount}][uses]`);
      _usesFieldTextarea.attr('rows', '1');
      _usesFieldTextarea.val('0');

      _usesField.append(_usesFieldTextarea);
      _row.append(_usesField);

      const _maxUsesField = $('<td>');
      _maxUsesField.addClass('singleline-cell textual has-info');
      const _maxUsesFieldTextarea = $('<textarea>');
      _maxUsesFieldTextarea.attr(
        'aria-labelledby',
        'commerce-coupons-table-heading-4'
      );
      _maxUsesFieldTextarea.attr('aria-describedby', '');
      _maxUsesFieldTextarea.attr('name', `coupons[${rowCount}][maxUses]`);
      _maxUsesFieldTextarea.attr('rows', '1');
      maxUses = maxUses !== null ? maxUses : '';
      _maxUsesFieldTextarea.val(maxUses);

      _maxUsesField.append(_maxUsesFieldTextarea);
      _row.append(_maxUsesField);

      const _actionField = $('<td>');
      _actionField.addClass('thin action');
      const _actionFieldButton = $('<button>');
      _actionFieldButton.attr('type', 'button');
      _actionFieldButton.addClass('delete icon');
      _actionFieldButton.attr('title', 'Delete');
      _actionFieldButton.attr('aria-label', `Delete row ${rowCount}`);

      _actionField.append(_actionFieldButton);
      _row.append(_actionField);

      this.couponsTable.find('tbody').append(_row);
    },

    getAllCodesFromTable() {
      const codes = [];
      this.couponsTable
        .find('tbody [name*="[code]"]')
        .each((index, element) => {
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
    },
  }
);
