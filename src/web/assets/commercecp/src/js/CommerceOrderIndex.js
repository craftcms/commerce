/* jshint esversion: 6, strict: false */
/* globals Craft, Garnish, $ */
if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.OrderIndex
 */
Craft.Commerce.OrderIndex = Craft.BaseElementIndex.extend({
  startDate: null,
  endDate: null,

  init: function (elementType, $container, settings) {
    this.on('selectSource', $.proxy(this, 'updateSelectedSource'));
    this.base(elementType, $container, settings);

    Craft.ui
      .createDateRangePicker({
        onChange: function (startDate, endDate) {
          this.startDate = startDate;
          this.endDate = endDate;
          this.updateElements();
        }.bind(this),
      })
      .appendTo(this.$toolbar);

    if (
      window.orderEdit &&
      window.orderEdit.currentUserPermissions['commerce-editOrders']
    ) {
      let $submitBtn;
      if (this.settings.stores.length > 1) {
        let $submitBtn = $('<div class="btngroup submit"/>');
        const $btn = $('<button/>', {
          type: 'button',
          class: 'btn menubtn submit icon add',
          text: Craft.t('commerce', 'New Order'),
        }).appendTo($submitBtn);

        const $menu = $('<div/>', {class: 'menu'}).appendTo($submitBtn);
        const $ul = $('<ul/>').appendTo($menu);

        this.settings.stores.forEach((store) => {
          const $link = $('<a/>', {
            href: Craft.getUrl('commerce/orders/' + store.handle + '/create'),
            text: store.name,
          });

          $('<li/>').append($link).appendTo($ul);
        });

        const $menuBtn = new Garnish.MenuBtn($btn);
      } else {
        const $submitBtn = $('<a/>', {
          class: 'btn submit icon add',
          href: Craft.getUrl(
            'commerce/orders/' + this.settings.stores[0].handle + '/create'
          ),
          text: Craft.t('commerce', 'New Order'),
        });
      }

      // Add the New Order button
      this.addButton($submitBtn);
    }
  },

  updateSelectedSource() {
    var source = this.$source ? this.$source : 'all';
    var handle = source !== 'all' ? this.$source.data('handle') : null;

    if (this.settings.context === 'index' && typeof history !== 'undefined') {
      var uri = 'commerce/orders';

      if (handle) {
        uri += '/' + handle;
      }

      history.replaceState({}, '', Craft.getUrl(uri));
    }
  },

  getDefaultSourceKey() {
    var defaultStatusHandle = window.defaultStatusHandle;

    if (defaultStatusHandle) {
      for (var i = 0; i < this.$sources.length; i++) {
        var $source = $(this.$sources[i]);

        if ($source.data('handle') === defaultStatusHandle) {
          return $source.data('key');
        }
      }
    }

    return this.base();
  },

  getViewParams: function () {
    var params = this.base();

    if (this.startDate || this.endDate) {
      var dateAttr = this.$source.data('date-attr') || 'dateUpdated';
      params.criteria[dateAttr] = ['and'];

      if (this.startDate) {
        params.criteria[dateAttr].push('>=' + this.startDate.getTime() / 1000);
      }

      if (this.endDate) {
        params.criteria[dateAttr].push(
          '<' + (this.endDate.getTime() / 1000 + 86400)
        );
      }
    }

    return params;
  },

  updateSourcesBadgeCounts: function () {
    $.ajax({
      url: Craft.getActionUrl('commerce/orders/get-index-sources-badge-counts'),
      type: 'GET',
      dataType: 'json',
      success: $.proxy(function (data) {
        if (data.counts) {
          var $sidebar = this.$sidebar;
          $.each(data.counts, function (key, row) {
            var $item = $sidebar.find(
              'nav a[data-key="orderStatus:' + row.handle + '"]'
            );
            if ($item) {
              $item.find('.badge').text(row.orderCount);
            }
          });
        }

        if (data.total) {
          var $total = this.$sidebar.find('nav a[data-key="*"]');
          if ($total) {
            $total.find('.badge').text(data.total);
          }
        }
      }, this),
    });
  },

  setIndexAvailable: function () {
    this.updateSourcesBadgeCounts();
    this.base();
  },
});

// Register the Commerce order index class
Craft.registerElementIndexClass(
  'craft\\commerce\\elements\\Order',
  Craft.Commerce.OrderIndex
);
