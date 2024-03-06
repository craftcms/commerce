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
      // Add the New Order button
      var $btn = $('<a/>', {
        class: 'btn submit icon add',
        href: Craft.getUrl('commerce/orders/create'),
        text: Craft.t('commerce', 'New Order'),
      });
      this.addButton($btn);
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
              let $badge = $item.find('.badge');

              if (row.orderCount === 0) {
                if ($badge.length) {
                  $badge.remove();
                }

                return;
              }

              if (!$badge.length) {
                $badge = $('<span class="badge"/>').appendTo($item);
              }

              $item.find('.badge').text(row.orderCount);
            }
          });
        }

        if (data.total) {
          var $total = this.$sidebar.find('nav a[data-key="*"]');
          if ($total) {
            let $totalBadge = $total.find('.badge');

            if (data.total === 0) {
              if ($totalBadge.length) {
                $totalBadge.remove();
              }

              return;
            }

            if (!$totalBadge.length) {
              $totalBadge = $('<span class="badge"/>').appendTo($total);
            }

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
