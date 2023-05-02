/* jshint esversion: 6 */
/* globals Craft, Garnish, $ */
if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.CatalogPricing = Garnish.Base.extend({
  $clearSearchBtn: null,
  $filterBtn: null,
  $search: null,
  $searchContainer: null,
  $tableContainer: null,
  filterHuds: {},
  searchTimeout: null,
  searching: false,
  view: null,
  defaults: {},

  init: function (view, tableContainer, settings) {
    this.view = view;
    this.$tableContainer = tableContainer;
    this.$searchContainer = this.view.find('.search-container:first');
    this.$search = this.$searchContainer.find('input:first');
    this.$clearSearchBtn = this.$searchContainer.children('.clear-btn:first');
    this.$filterBtn = this.$searchContainer.children('.filter-btn:first');
    this.setSettings(settings, this.defaults);

    if (this.settings.filterBtnActive) {
      this.$filterBtn.addClass('active');
    }

    this.addListener(this.$filterBtn, 'click', 'showFilterHud');

    this.addListener(this.$search, 'input', () => {
      if (!this.searching && this.$search.val()) {
        this.startSearching();
      } else if (this.searching && !this.$search.val()) {
        this.stopSearching();
      }

      if (this.searchTimeout) {
        clearTimeout(this.searchTimeout);
      }

      this.searchTimeout = setTimeout(
        this.updateTableIfSearchTextChanged.bind(this),
        500
      );
    });

    // Clear the search when the X button is clicked
    this.addListener(this.$clearSearchBtn, 'click', () => {
      this.clearSearch(true);

      if (!Garnish.isMobileBrowser(true)) {
        this.$search.trigger('focus');
      }
    });
  },

  startSearching: function () {
    // Show the clear button
    this.$clearSearchBtn.removeClass('hidden');
    this.searching = true;
  },

  clearSearch: function (updateTable) {
    if (!this.searching) {
      return;
    }

    this.$search.val('');

    if (this.searchTimeout) {
      clearTimeout(this.searchTimeout);
    }

    this.stopSearching();

    if (updateTable) {
      this.updateTableIfSearchTextChanged();
    } else {
      this.searchText = null;
    }
  },

  stopSearching: function () {
    // Hide the clear button
    this.$clearSearchBtn.addClass('hidden');
    this.searching = false;
  },

  showFilterHud: function () {
    if (!this.getFilterHud()) {
      this.createFilterHud();
      this.updateFilterBtn();
    } else {
      this.getFilterHud().show();
    }
  },

  updateFilterBtn: function () {
    this.$filterBtn.removeClass('active');

    if (this.getFilterHud()) {
      this.$filterBtn
        .attr('aria-controls', this.getFilterHud().id)
        .attr('aria-expanded', this.getFilterHud().showing ? 'true' : 'false');

      if (this.getFilterHud().showing || this.getFilterHud().hasRules()) {
        this.$filterBtn.addClass('active');
      }
    } else {
      this.$filterBtn.attr('aria-controls', null);
    }
  },

  serializeConditionForm: function () {
    if (!this.getFilterHud()) {
      return null;
    }

    const el = this.getFilterHud().$body.find('.condition-container:first');
    let _ = {};

    $.map(el.serializeArray(), function (n) {
      const keys = n.name.match(/[a-zA-Z0-9_\\]+|(?=\[\])/g);

      if (keys.length > 1) {
        let tmp = _;
        let pop = keys.pop();
        for (let i = 0; i < keys.length; i++) {
          let j = keys[i];
          (tmp[j] = !tmp[j] ? (pop == '' ? [] : {}) : tmp[j]), (tmp = tmp[j]);
        }
        if (pop == '') {
          tmp = !Array.isArray(tmp) ? [] : tmp;
          tmp.push(n.value);
        } else {
          tmp[pop] = n.value;
        }
      } else {
        _[keys.pop()] = n.value;
      }
    });

    return _;
  },

  getFilterHudKey: function () {
    return `site-${this.settings.siteId}`;
  },

  createFilterHud: function () {
    this.filterHuds[this.getFilterHudKey()] =
      new Craft.Commerce.CatalogPricingHud(this, this.settings.siteId);
  },

  getFilterHud: function () {
    if (Object.keys(this.filterHuds).indexOf(this.getFilterHudKey()) === -1) {
      return null;
    }

    return this.filterHuds[this.getFilterHudKey()];
  },

  destroyFilterHud: function () {
    if (this.getFilterHud()) {
      delete this.filterHuds[this.getFilterHudKey()];
    }
  },

  updateTableIfSearchTextChanged: function () {
    if (
      this.searchText !==
      (this.searchText = this.searching ? this.$search.val() : null)
    ) {
      this.updateTable();
    }
  },

  updateTable: function () {
    let params = {
      searchText: this.$search.val(),
      siteId: this.settings.siteId,
      condition: this.serializeConditionForm(),
    };

    Craft.sendActionRequest('POST', 'commerce/catalog-pricing/prices', {
      data: params,
    }).then((response) => {
      if (response.data && response.data.tableHtml) {
        Craft.appendHeadHtml(response.data.headHtml);
        Craft.appendBodyHtml(response.data.bodyHtml);

        this.$tableContainer.html(response.data.tableHtml);
      }
    });
  },
});

Craft.Commerce.CatalogPricingHud = Garnish.HUD.extend({
  view: null,
  siteId: null,
  id: null,
  loading: true,
  serialized: null,
  $clearBtn: null,
  cleared: false,

  init: function (view, siteId) {
    this.view = view;

    this.siteId = siteId;
    this.id = `filter-${Math.floor(Math.random() * 1000000000)}`;

    const $loadingContent = $('<div/>')
      .append(
        $('<div/>', {
          class: 'spinner',
        })
      )
      .append(
        $('<div/>', {
          text: Craft.t('app', 'Loading'),
          class: 'visually-hidden',
          'aria-role': 'alert',
        })
      );

    this.base(this.view.$filterBtn, $loadingContent, {
      hudClass: 'hud element-filter-hud loading',
    });

    this.$hud.attr({
      id: this.id,
      'aria-live': 'polite',
      'aria-busy': 'false',
    });
    this.$tip.remove();
    this.$tip = null;

    this.$body.on('submit', (ev) => {
      ev.preventDefault();
      this.hide();
    });

    Craft.sendActionRequest('POST', 'commerce/catalog-pricing/filter', {
      data: {
        condition: this.view.settings.condition,
        id: `${this.id}-filters`,
      },
    })
      .then((response) => {
        this.loading = false;
        this.$hud.removeClass('loading');
        $loadingContent.remove();

        this.$main.append(response.data.hudHtml);
        Craft.appendHeadHtml(response.data.headHtml);
        Craft.appendBodyHtml(response.data.bodyHtml);
        this.view.settings.condition = response.data.condition;
        this.serialized = this.view.serializeConditionForm();

        const $btnContainer = $('<div/>', {
          class: 'flex flex-nowrap',
        }).appendTo(this.$main);
        $('<div/>', {
          class: 'flex-grow',
        }).appendTo($btnContainer);
        this.$clearBtn = $('<button/>', {
          type: 'button',
          class: 'btn',
          text: Craft.t('app', 'Cancel'),
        }).appendTo($btnContainer);
        $('<button/>', {
          type: 'submit',
          class: 'btn secondary',
          text: Craft.t('app', 'Apply'),
        }).appendTo($btnContainer);
        this.$clearBtn.on('click', () => {
          this.clear();
        });

        this.$hud.find('.condition-container').on('htmx:beforeRequest', () => {
          this.setBusy();
        });

        this.$hud.find('.condition-container').on('htmx:load', () => {
          this.setReady();
          this.updateSizeAndPosition(true);
        });
        this.setFocus();
      })
      .catch(() => {
        Craft.cp.displayError(Craft.t('app', 'A server error occurred.'));
      });

    this.$hud.css('position', 'fixed');

    this.addListener(Garnish.$win, 'scroll,resize', () => {
      this.updateSizeAndPosition(true);
    });
  },

  addListener: function (elem, events, data, func) {
    if (elem === this.$main && events === 'resize') {
      return;
    }
    this.base(elem, events, data, func);
  },

  setBusy: function () {
    this.$hud.attr('aria-busy', 'true');

    $('<div/>', {
      class: 'visually-hidden',
      text: Craft.t('app', 'Loading'),
    }).insertAfter(this.$main.find('.htmx-indicator'));
  },

  setReady: function () {
    this.$hud.attr('aria-busy', 'false');
  },

  setFocus: function () {
    Garnish.setFocusWithin(this.$main);
  },

  clear: function () {
    this.cleared = true;
    this.destroy();
    this.hide();
  },

  updateSizeAndPositionInternal: function () {
    const searchOffset = this.view.$searchContainer[0].getBoundingClientRect();

    // Ensure HUD is scrollable if content falls off-screen
    const windowHeight = Garnish.$win.height();
    let hudHeight;
    const availableSpace = windowHeight - searchOffset.bottom;

    if (this.$body.height() > availableSpace) {
      hudHeight = windowHeight - searchOffset.bottom - 10;
    }

    this.$hud.css({
      width: this.view.$searchContainer.outerWidth() - 2,
      top: searchOffset.top + this.view.$searchContainer.outerHeight(),
      left: searchOffset.left + 1,
      height: hudHeight ? `${hudHeight}px` : 'unset',
      overflowY: hudHeight ? 'scroll' : 'unset',
    });
  },

  onShow: function () {
    this.base();

    // Cancel => Clear
    if (this.$clearBtn && this.hasRules()) {
      this.$clearBtn.text(Craft.t('app', 'Clear'));
    } else if (this.$clearBtn && !this.hasRules()) {
      this.$clearBtn.text(Craft.t('app', 'Cancel'));
    }

    this.view.updateFilterBtn();
    this.setFocus();
  },

  onHide: function () {
    this.base();

    // If something changed, update the elements
    if (
      this.serialized !== (this.serialized = this.view.serializeConditionForm())
    ) {
      this.view.updateTable();
    }

    if (!this.cleared) {
      this.$hud.detach();
      this.$shade.detach();
    }

    this.view.updateFilterBtn();
    this.view.$filterBtn.focus();
  },

  hasRules: function () {
    return this.$main.has('.condition-rule').length !== 0;
  },

  destroy: function () {
    this.base();

    if (this.view.getFilterHud()) {
      this.view.destroyFilterHud();
    }
  },
});
