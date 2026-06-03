/* jshint esversion: 6, strict: false */
/* globals Craft, Garnish, $ */
if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.InventoryLevelsManager = Garnish.Base.extend({
  settings: null,
  containerId: null,
  $container: null,
  adminTableId: null,
  viewPreferences: null,

  // Required columns that cannot be hidden
  requiredColumns: ['purchasable', 'sku'],

  // Optional columns that can be toggled
  optionalColumns: [
    'reserved',
    'damaged',
    'safety',
    'qualityControl',
    'committed',
    'available',
    'onHand',
    'incoming',
  ],

  init: function (container, settings) {
    this.containerId = container;
    this.setSettings(settings, Craft.Commerce.InventoryLevelsManager.defaults);

    this.$container = $(this.containerId);

    // If this element already has an Inventory Levels Manager, destroy it
    if (this.$container.data('inventoryLevelsManager')) {
      console.warn(
        'Double-instantiating an Inventory Levels Manager on an element.'
      );
      this.$container.data('inventoryLevelsManager').destroy();
    }
    this.$container.data('inventoryLevelsManager', this);

    // Load view preferences from localStorage
    this.loadViewPreferences();

    // Create the view menu first (outside the admin table)
    this.initViewMenu();

    // random id for the admin table
    this.adminTableId =
      'inventory-admin-table-' + Math.random().toString(36).substring(2);
    this.$adminTable = $('<div id="' + this.adminTableId + '"></div>').appendTo(
      this.$container
    );

    this.initAdminTable();
  },

  getStorageKey: function () {
    return (
      'Commerce.InventoryLevels.viewPreferences.' +
      this.settings.inventoryLocationId +
      '.' +
      Craft.userId
    );
  },

  loadViewPreferences: function () {
    var storageKey = this.getStorageKey();
    var stored = localStorage.getItem(storageKey);

    if (stored) {
      try {
        this.viewPreferences = JSON.parse(stored);
      } catch (e) {
        this.viewPreferences = this.getDefaultViewPreferences();
      }
    } else {
      this.viewPreferences = this.getDefaultViewPreferences();
    }
  },

  getDefaultViewPreferences: function () {
    // Default: all columns visible
    var visibleColumns = {};
    this.optionalColumns.forEach(function (col) {
      visibleColumns[col] = true;
    });

    return {
      visibleColumns: visibleColumns,
    };
  },

  saveViewPreferences: function () {
    var storageKey = this.getStorageKey();
    localStorage.setItem(storageKey, JSON.stringify(this.viewPreferences));
  },

  getAllColumns: function () {
    return [
      {
        name: 'purchasable',
        sortField: 'item',
        title: Craft.t('commerce', 'Purchasable'),
      },
      {name: 'sku', sortField: 'sku', title: Craft.t('commerce', 'SKU')},
      {
        name: 'reserved',
        sortField: 'reservedTotal',
        titleClass: 'inventory-headers',
        dataClass: 'inventory-cell',
        title: Craft.t('commerce', 'Reserved'),
      },
      {
        name: 'damaged',
        sortField: 'damagedTotal',
        titleClass: 'inventory-headers',
        dataClass: 'inventory-cell',
        title: Craft.t('commerce', 'Damaged'),
      },
      {
        name: 'safety',
        sortField: 'safetyTotal',
        titleClass: 'inventory-headers',
        dataClass: 'inventory-cell',
        title: Craft.t('commerce', 'Safety'),
      },
      {
        name: 'qualityControl',
        sortField: 'qualityControlTotal',
        titleClass: 'inventory-headers',
        dataClass: 'inventory-cell',
        title: Craft.t('commerce', 'Quality Control'),
      },
      {
        name: 'committed',
        sortField: 'committedTotal',
        titleClass: 'inventory-headers',
        dataClass: 'inventory-cell',
        title: Craft.t('commerce', 'Committed'),
      },
      {
        name: 'available',
        sortField: 'availableTotal',
        titleClass: 'inventory-headers',
        dataClass: 'inventory-cell',
        title: Craft.t('commerce', 'Available'),
      },
      {
        name: 'onHand',
        sortField: 'onHandTotal',
        titleClass: 'inventory-headers',
        dataClass: 'inventory-cell',
        title: Craft.t('commerce', 'On Hand'),
      },
      {
        name: 'incoming',
        sortField: 'incomingTotal',
        titleClass: 'inventory-headers',
        dataClass: 'inventory-cell',
        title: Craft.t('commerce', 'Incoming'),
      },
    ];
  },

  getVisibleColumns: function () {
    var self = this;
    var allColumns = this.getAllColumns();

    return allColumns.filter(function (col) {
      // Required columns are always visible
      if (self.requiredColumns.indexOf(col.name) !== -1) {
        return true;
      }
      // Check view preferences for optional columns
      return self.viewPreferences.visibleColumns[col.name] !== false;
    });
  },

  initAdminTable: function () {
    this.columns = this.getVisibleColumns();

    this.adminTable = new Craft.VueAdminTable({
      columns: this.columns,
      container: '#' + this.adminTableId,
      checkboxes: false,
      allowMultipleSelections: true,
      fullPane: false,
      perPage: 15,
      tableDataEndpoint: 'commerce/inventory/inventory-levels-table-data',
      onQueryParams: (params) => {
        // Arrow function to maintain 'this' context
        params.inventoryLocationId = this.settings.inventoryLocationId;
        if (this.settings.inventoryItemId) {
          params.inventoryItemId = this.settings.inventoryItemId;
        }
        params.containerId = this.containerId;
        return params;
      },
      search: true,
      searchPlaceholder: Craft.t('commerce', 'Search inventory'),
      emptyMessage: Craft.t('commerce', 'No inventory found.'),
      padded: true,
    });
  },

  initViewMenu: function () {
    var self = this;
    var allColumns = this.getAllColumns();

    // Generate unique ID for the menu
    this.viewMenuId =
      'inventory-view-menu-' + Math.random().toString(36).substring(2);

    // Create a toolbar container for the view button (positioned at top right)
    this.$viewToolbar = $('<div/>', {
      class: 'flex inventory-levels-toolbar',
    }).appendTo(this.$container);

    // Spacer to push button to the right
    $('<div class="flex-grow"/>').appendTo(this.$viewToolbar);

    // Create the view button (matching Craft's element index view button)
    this.$viewBtn = $('<button/>', {
      type: 'button',
      class: 'btn menubtn',
      text: Craft.t('commerce', 'View'),
      'aria-controls': this.viewMenuId,
      'data-icon': 'sliders',
    }).appendTo(this.$viewToolbar);

    // Create the menu container (matching Craft's element-index-view-menu)
    this.$viewMenu = $('<div/>', {
      id: this.viewMenuId,
      class: 'menu menu--disclosure element-index-view-menu',
      'data-align': 'right',
    }).appendTo(Garnish.$bod);

    // Build the menu content
    this._buildViewMenu(allColumns);

    // Initialize the disclosure menu (like Craft's element index)
    this.viewMenu = new Garnish.DisclosureMenu(this.$viewBtn);

    this.viewMenu.on('show', function () {
      self.$viewBtn.addClass('active');
    });

    this.viewMenu.on('hide', function () {
      self.$viewBtn.removeClass('active');
    });

    // Prevent menu from closing when clicking inside
    this.$viewMenu.on('mousedown', function (ev) {
      ev.stopPropagation();
    });

    // Bind events
    this.bindViewMenuEvents();
  },

  _buildViewMenu: function (allColumns) {
    var self = this;

    // Meta container (like Craft's view menu)
    var $metaContainer = $('<div class="meta"/>').appendTo(this.$viewMenu);

    // Table columns field
    this.$tableColumnsField =
      this._createTableColumnsField(allColumns).appendTo($metaContainer);

    // Footer with close button
    var $footerContainer = $('<div/>', {
      class: 'flex menu-footer',
    }).appendTo(this.$viewMenu);

    $('<div class="flex-grow"/>').appendTo($footerContainer);

    this.$closeBtn = $('<button/>', {
      type: 'button',
      class: 'btn',
      text: Craft.t('app', 'Close'),
    })
      .appendTo($footerContainer)
      .on('click', function () {
        self.viewMenu.hide();
      });
  },

  _createTableColumnsField: function (allColumns) {
    var self = this;

    // Build checkbox options (only for optional columns)
    var options = allColumns
      .filter(function (col) {
        return self.optionalColumns.indexOf(col.name) !== -1;
      })
      .map(function (col) {
        return {
          label: col.title,
          value: col.name,
        };
      });

    // Get currently visible columns
    var visibleValues = options
      .filter(function (opt) {
        return self.viewPreferences.visibleColumns[opt.value] !== false;
      })
      .map(function (opt) {
        return opt.value;
      });

    // Create checkbox select using Craft's UI helper
    this.$tableColumnsContainer = Craft.ui.createCheckboxSelect({
      options: options,
      values: visibleValues,
    });

    // Wrap in a field
    var $field = Craft.ui.createField(this.$tableColumnsContainer, {
      label: Craft.t('commerce', 'Table Columns'),
      fieldset: true,
    });
    $field.addClass('table-columns-field');

    return $field;
  },

  bindViewMenuEvents: function () {
    var self = this;

    // Column visibility change
    this.$tableColumnsContainer
      .find('input[type="checkbox"]')
      .on('change', function () {
        var $checkbox = $(this);
        var columnName = $checkbox.val();
        var isChecked = $checkbox.is(':checked');

        self.viewPreferences.visibleColumns[columnName] = isChecked;
        self.saveViewPreferences();
        self.rebuildTable();
      });
  },

  rebuildTable: function () {
    // Store current search value
    var searchValue =
      this.$container.find('.vue-admin-table input[type="search"]').val() || '';

    // Destroy the old Vue instance before removing its DOM
    if (this.adminTable && typeof this.adminTable.destroy === 'function') {
      this.adminTable.destroy();
    }

    this.$adminTable.empty();

    // Reinitialize with new columns
    this.initAdminTable();

    // Reapply search if there was one
    if (searchValue) {
      var attempts = 0;
      var checkSearch = setInterval(() => {
        var $searchInput = this.$container.find(
          '.vue-admin-table input[type="search"]'
        );
        if ($searchInput.length || ++attempts > 50) {
          clearInterval(checkSearch);
          if ($searchInput.length) {
            $searchInput.val(searchValue);
            $searchInput.trigger('input');
          }
        }
      }, 100);
    }
  },

  destroy: function () {
    if (this.$viewToolbar) {
      this.$viewToolbar.remove();
    }
    if (this.$viewMenu) {
      this.$viewMenu.remove();
    }
    if (this.viewMenu && typeof this.viewMenu.destroy === 'function') {
      this.viewMenu.destroy();
    }
    if (this.adminTable && typeof this.adminTable.destroy === 'function') {
      this.adminTable.destroy();
    }
    this.base();
  },

  defaultSettings: {
    inventoryLocationId: null,
    inventoryItemId: null,
  },
});
