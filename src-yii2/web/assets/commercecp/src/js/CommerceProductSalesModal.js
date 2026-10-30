/* jshint esversion: 6 */
/* globals Craft, Garnish, $ */
if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.ProductSalesModal = Garnish.Modal.extend({
  id: null,
  $newSale: null,
  $cancelBtn: null,
  $select: null,
  $saveBtn: null,
  $spinner: null,
  $purchasableCheckboxes: [],

  init: function (sales, settings) {
    this.id = Math.floor(Math.random() * 1000000000);

    this.setSettings(settings, this.defaults);
    this.$form = $(
      '<form class="modal fitted" method="post" accept-charset="UTF-8"/>'
    ).appendTo(Garnish.$bod);
    var $body = $('<div class="body"></div>').appendTo(this.$form);
    var $inputs = $('<div/>', {class: 'content'})
      .append(
        $('<h2/>', {
          class: 'first',
          text: Craft.t('commerce', 'Add Product to Sale'),
        })
      )
      .append(
        $('<p/>', {
          text: Craft.t(
            'commerce',
            'Add this product to an existing sale. This will change the conditions of the sale, please review the sale.'
          ),
        })
      )
      .appendTo($body);

    if (this.settings.purchasables.length) {
      var $checkboxField = $('<div class="field" />');
      $('<div/>', {class: 'heading'})
        .append(
          $('<label/>', {
            text: Craft.t('commerce', 'Select Variants'),
          })
        )
        .appendTo($checkboxField);

      var $inputContainer = $('<div class="input ltr" />');
      $.each(
        this.settings.purchasables,
        $.proxy(function (key, purchasable) {
          var $pCheck = $('<input>', {
            class: 'checkbox',
            type: 'checkbox',
            name: 'ids[]',
            id: 'add-to-sale-purchasable-' + purchasable.id,
            value: purchasable.id,
            checked: true,
          });

          var $checkboxContainer = $('<div/>').append(
            $('<label/>', {
              for: 'add-to-sale-purchasable-' + purchasable.id,
              text: purchasable.title + ' ',
            }).append('<span/>', {
              class: 'extralight',
              text: purchasable.sku,
            })
          );

          $pCheck.on(
            'change',
            $.proxy(function () {
              this.updateNewSaleUrl();
            }, this)
          );
          this.$purchasableCheckboxes.push($pCheck);
          $pCheck.prependTo($checkboxContainer);
          $checkboxContainer.appendTo($inputContainer);
        }, this)
      );

      $inputContainer.appendTo($checkboxField);
      $checkboxField.appendTo($inputs);
    }

    if (sales && sales.length) {
      this.$select = $('<select name="sale" />');
      $('<option value="">----</option>').appendTo(this.$select);

      for (var i = 0; i < sales.length; i++) {
        var sale = sales[i];
        var disabled = false;

        if (
          this.settings.existingSaleIds &&
          this.settings.existingSaleIds.length &&
          this.settings.existingSaleIds.indexOf(sale.id) >= 0
        ) {
          disabled = true;
        }

        this.$select.append(
          $('<option/>', {
            disabled: disabled,
            text: Craft.escapeHtml(sale.name),
            value: sale.id,
          })
        );
      }
      var $field = $('<div class="input ltr"></div>');
      var $container = $('<div class="select" />');
      this.$select.appendTo($container);
      $container.appendTo($field);

      var $fieldContainer = $('<div class="field"/>');
      $('<div class="heading"/>')
        .append(
          $('<label/>', {
            text: Craft.t('commerce', 'Sale'),
          })
        )
        .appendTo($fieldContainer);

      $container.appendTo($fieldContainer);

      $fieldContainer.appendTo($inputs);

      this.$select.on('change', $.proxy(this, 'handleSaleChange'));
    }

    // Error notice area
    this.$error = $('<div class="error"/>').appendTo($inputs);

    // Footer and buttons
    var $footer = $('<div class="footer"/>').appendTo(this.$form);
    var $newSaleBtnGroup = $('<div class="btngroup left"/>').appendTo($footer);
    this.$newSale = $('<a/>', {
      class: 'btn icon add',
      target: '_blank',
      href: '',
      text: Craft.t('commerce', 'Create Sale'),
    }).appendTo($newSaleBtnGroup);

    var $rightWrapper = $('<div class="right"/>').appendTo($footer);
    var $mainBtnGroup = $('<div class="btngroup"/>').appendTo($rightWrapper);
    this.$cancelBtn = $('<input/>', {
      type: 'button',
      class: 'btn',
      value: Craft.t('commerce', 'Cancel'),
    }).appendTo($mainBtnGroup);
    this.$saveBtn = $('<input/>', {
      type: 'button',
      class: 'btn submit',
      value: Craft.t('commerce', 'Add'),
    }).appendTo($mainBtnGroup);
    this.$spinner = $('<div class="spinner hidden" />').appendTo($rightWrapper);

    this.$saveBtn.addClass('disabled');

    this.addListener(this.$cancelBtn, 'click', 'hide');
    this.addListener(
      this.$saveBtn,
      'click',
      $.proxy(function (ev) {
        ev.preventDefault();
        if (!$(ev.target).hasClass('disabled')) {
          this.$spinner.removeClass('hidden');
          this.saveSale();
        }
      }, this)
    );

    this.updateNewSaleUrl();

    this.base(this.$form, this.settings);
  },

  updateNewSaleUrl: function () {
    var href = Craft.getUrl('commerce/promotions/sales/new');
    if (this.settings.id) {
      href = Craft.getUrl(
        'commerce/promotions/sales/new?purchasableIds=' + this.settings.id
      );
    }

    if (this.$purchasableCheckboxes.length) {
      var purchasableIds = [];
      this.$purchasableCheckboxes.forEach(function (el) {
        if ($(el).prop('checked')) {
          purchasableIds.push($(el).val());
        }
      });

      if (purchasableIds.length) {
        href = Craft.getUrl(
          'commerce/promotions/sales/new?purchasableIds=' +
            purchasableIds.join('|')
        );
      }
    }

    this.$newSale.attr('href', href);
  },

  saveSale: function () {
    var saleId = this.$form.find('select[name="sale"]').val();
    var ids = [];

    if (this.settings.purchasables.length) {
      this.$form.find('input.checkbox:checked').each(function (el) {
        ids.push($(this).val());
      });
    } else if (this.settings.id) {
      ids = [this.settings.id];
    }

    var data = {
      ids: ids,
      saleId: saleId,
    };

    Craft.sendActionRequest('POST', 'commerce/sales/add-purchasable-to-sale', {
      data,
    })
      .then((response) => {
        Craft.cp.displayNotice(Craft.t('commerce', 'Added to Sale.'));
        this.hide();
      })
      .catch(({response}) => {
        Craft.cp.displayError(response.data && response.data.message);
      })
      .finally(() => {
        this.$spinner.addClass('hidden');
      });
  },

  handleSaleChange: function (ev) {
    if (this.$select.val() != '') {
      this.$saveBtn.removeClass('disabled');
    } else {
      this.$saveBtn.addClass('disabled');
    }
  },

  defaults: {
    onSubmit: $.noop,
    id: null,
    productId: null,
    purchasables: [],
    existingSaleIds: [],
  },
});
