import {registerComponent, registerPage} from '@craftcms/cp';
import CouponGeneratorNode from './modules/ui/CouponGeneratorNode.vue';
import OrderActionsNode from './modules/ui/OrderActionsNode.vue';
import SiteStoresControl from './modules/ui/SiteStoresControl.vue';
import TransferDetailsControl from './modules/ui/TransferDetailsControl.vue';
import TransferReceiveControl from './modules/ui/TransferReceiveControl.vue';
import UsageCounterNode from './modules/ui/UsageCounterNode.vue';

/**
 * Component names are prefixed `commerce:` (rather than reusing `cms`'s own
 * `craft:` prefix) so a Commerce-contributed type can never collide with a
 * future `cms`-native one of a similar name.
 */
registerComponent('commerce:coupon-generator', CouponGeneratorNode);
registerComponent('commerce:usage-counter', UsageCounterNode);
registerComponent('commerce:order-actions', OrderActionsNode);
registerComponent('commerce:site-stores', SiteStoresControl);
registerComponent('commerce:transfer-details', TransferDetailsControl);
registerComponent('commerce:transfer-receive', TransferReceiveControl);

registerComponent(
  'commerce:widget-number-stat',
  () => import('./modules/dashboard/NumberStatWidget.vue')
);
registerComponent(
  'commerce:widget-table',
  () => import('./modules/dashboard/TableWidget.vue')
);
registerComponent(
  'commerce:widget-chart',
  () => import('./modules/dashboard/ChartWidget.vue')
);

// Loaded on demand, so the element index `cms` shares through the import map
// is only fetched on the pages that use it.
registerPage(
  'commerce::products/Index',
  () => import('./pages/products/Index.vue')
);
registerPage(
  'commerce::variants/Index',
  () => import('./pages/variants/Index.vue')
);
registerPage(
  'commerce::orders/Index',
  () => import('./pages/orders/Index.vue')
);
registerPage(
  'commerce::prices/Index',
  () => import('./pages/prices/Index.vue')
);
registerPage(
  'commerce::inventory/transfers/Index',
  () => import('./pages/inventory/transfers/Index.vue')
);
registerPage(
  'commerce::licenses/Index',
  () => import('./pages/licenses/Index.vue')
);
