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

    // random id for the admin table
    this.adminTableId =
      'inventory-admin-table-' + Math.random().toString(36).substring(7);
    this.$adminTable = $('<div id="' + this.adminTableId + '"></div>').appendTo(
      this.$container
    );

    this.initAdminTable();
  },

  initAdminTable: function (container) {
    self = this;

    this.columns = [
      {name: 'item', sortField: 'item', title: Craft.t('commerce', 'Item')},
      {
        name: 'reserved',
        sortField: 'reservedTotal',
        title: Craft.t('commerce', 'Reserved'),
      },
      {
        name: 'damaged',
        sortField: 'damagedTotal',
        title: Craft.t('commerce', 'Damaged'),
      },
      {
        name: 'safety',
        sortField: 'safetyTotal',
        title: Craft.t('commerce', 'Safety'),
      },
      {
        name: 'qualityControl',
        sortField: 'qualityControlTotal',
        title: Craft.t('commerce', 'Quality Control'),
      },
      {
        name: 'committed',
        sortField: 'committedTotal',
        title: Craft.t('commerce', 'Committed'),
      },
      {
        name: 'available',
        sortField: 'availableTotal',
        title: Craft.t('commerce', 'Available'),
      },
      {
        name: 'onHand',
        sortField: 'onHandTotal',
        title: Craft.t('commerce', 'On Hand'),
      },
      {
        name: 'incoming',
        sortField: 'incomingTotal',
        title: Craft.t('commerce', 'Incoming'),
      },
    ];

    this.adminTable = new Craft.VueAdminTable({
      inventoryLocationId: this.settings.inventoryLocationId,
      inventoryItemId: this.settings.inventoryItemId,
      columns: self.columns,
      container: '#' + self.adminTableId,
      checkboxes: true,
      allowMultipleSelections: true,
      fullPane: false,
      tableDataEndpoint: 'commerce/inventory/inventory-levels-table-data',
      onQueryParams: function (params) {
        params.inventoryLocationId = self.settings.inventoryLocationId;
        if (self.settings.inventoryItemId) {
          params.inventoryItemId = self.settings.inventoryItemId;
        }
        params.containerId = self.containerId;
        return params;
      },
      search: true,
      searchPlaceholder: Craft.t('commerce', 'Search inventory'),
      emptyMessage: Craft.t('commerce', 'No inventory found.'),
      padded: true,
    });
  },

  defaultSettings: {
    inventoryLocationId: null,
    inventoryItemId: null,
  },
});
