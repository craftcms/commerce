/* jshint esversion: 6 */
/* globals Craft, Garnish, $ */
if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.UpdateOrderStatusModal = Garnish.Modal.extend({
  id: null,
  orderStatusId: null,
  originalStatus: null,
  currentStatus: null,
  originalStatusId: null,
  $statusSelect: null,
  $selectedStatus: null,
  $orderStatusIdInput: null,
  $message: null,
  $error: null,
  $updateBtn: null,
  $statusMenuBtn: null,
  $cancelBtn: null,
  $suppress: null,
  init: function (currentStatus, orderStatuses, settings) {
    this.id = Math.floor(Math.random() * 1000000000);

    settings.onHide = $.proxy(function () {
      this.destroy();
    }, this);

    this.setSettings(settings, {
      resizable: false,
    });

    this.originalStatusId = currentStatus.id;
    this.currentStatus = currentStatus;

    var $form = $(
      '<form class="modal fitted" method="post" accept-charset="UTF-8"/>'
    ).appendTo(Garnish.$bod);
    var $body = $('<div class="body"></div>').appendTo($form);
    var $inputs = $('<div/>', {
      class: 'content',
    })
      .append(
        $('<h2/>', {
          class: 'first',
          text: Craft.t('commerce', 'Update Order Status'),
        })
      )
      .appendTo($body);

    // Build menu button
    this.$statusSelect = $('<a/>', {
      class: 'btn menubtn',
      href: '#',
      html: $('<span class="status ' + currentStatus.color + '"/>'),
    })
      .append(currentStatus.name)
      .appendTo($inputs);
    var $menu = $('<div class="menu"/>').appendTo($inputs);
    var $list = $('<ul class="padded"/>').appendTo($menu);
    var classes = '';
    for (var i = 0; i < orderStatuses.length; i++) {
      if (this.currentStatus.id === orderStatuses[i].id) {
        classes = 'sel';
      } else {
        classes = '';
      }
      $('<li/>')
        .append(
          $('<a/>', {
            class: classes,
            'data-id': orderStatuses[i].id,
            'data-name': orderStatuses[i].name,
            'data-color': orderStatuses[i].color,
          })
            .append('<span class="status ' + orderStatuses[i].color + '"/>')
            .append(orderStatuses[i].name)
        )
        .appendTo($list);
    }

    this.$selectedStatus = $('.sel', $list);

    // Build message input
    this.$message = $('<div/>', {
      class: 'field',
    })
      .append(
        $('<div/>', {
          class: 'heading',
        })
          .append(
            $('<label/>', {
              text: Craft.t('commerce', 'Message'),
            })
          )
          .append(
            $('<div/>', {
              class: 'instructions',
              text: Craft.t('commerce', 'Status change message'),
            })
          )
      )
      .append(
        $('<div/>', {
          class: 'input ltr',
        }).append(
          $('<textarea/>', {
            name: 'message',
            rows: 2,
            cols: 50,
            maxlength: 10000,
            class: 'text fullwidth',
          })
        )
      )
      .appendTo($inputs);

    var $suppressInput = $('<div/>', {class: 'input'})
      .append(
        $('<input/>', {
          id: 'order-action-suppress-emails',
          name: 'suppressEmails',
          type: 'checkbox',
          class: 'checkbox',
          value: '1',
        })
      )
      .append(
        $('<label/>', {
          for: 'order-action-suppress-emails',
          text: Craft.t('commerce', 'Suppress emails'),
        })
      );
    this.$suppress = $('<div/>', {class: 'field'})
      .append($suppressInput)
      .appendTo($inputs);

    // Error notice area
    this.$error = $('<div class="error"/>').appendTo($inputs);

    // Footer and buttons
    var $footer = $('<div class="footer"/>').appendTo($form);
    var $mainBtnGroup = $('<div class="buttons right"/>').appendTo($footer);
    this.$cancelBtn = $('<button/>', {
      type: 'button',
      class: 'btn',
      text: Craft.t('commerce', 'Cancel'),
    }).appendTo($mainBtnGroup);
    this.$updateBtn = $('<button/>', {
      type: 'button',
      class: 'btn submit',
      text: Craft.t('commerce', 'Update'),
    }).appendTo($mainBtnGroup);

    this.$updateBtn.addClass('disabled');

    // Listeners and
    this.$statusMenuBtn = new Garnish.MenuBtn(this.$statusSelect, {
      onOptionSelect: $.proxy(this, 'onSelectStatus'),
    });

    this.addListener(this.$cancelBtn, 'click', 'onCancelClick');
    this.addListener(this.$updateBtn, 'click', function (ev) {
      ev.preventDefault();
      if (!$(ev.target).hasClass('disabled')) {
        this.updateStatus();
      }
    });
    this.base($form, settings);
  },

  onCancelClick: function () {
    Craft.elementIndex.setIndexAvailable();
    this.hide();
  },

  onSelectStatus: function (status) {
    this.deselectStatus();

    this.$selectedStatus = $(status);

    this.$selectedStatus.addClass('sel');

    this.currentStatus = {
      id: $(status).data('id'),
      name: $(status).data('name'),
      color: $(status).data('color'),
    };

    var newHtml = $('<span/>', {
      html: $('<span class="status ' + this.currentStatus.color + '"/>'),
    }).append(Craft.uppercaseFirst(this.currentStatus.name));
    this.$statusSelect.html(newHtml);

    if (this.originalStatusId === this.currentStatus.id) {
      this.$updateBtn.addClass('disabled');
    } else {
      this.$updateBtn.removeClass('disabled');
    }
  },

  deselectStatus: function () {
    if (this.$selectedStatus) {
      this.$selectedStatus.removeClass('sel');
    }
  },

  updateStatus: function () {
    var data = {
      orderStatusId: this.currentStatus.id,
      message: this.$message.find('textarea[name="message"]').val(),
      color: this.currentStatus.color,
      name: this.currentStatus.name,
      suppressEmails: this.$suppress
        .find('input[name="suppressEmails"]')
        .is(':checked'),
    };

    this.settings.onSubmit(data);
  },

  defaults: {
    onSubmit: $.noop,
  },
});
