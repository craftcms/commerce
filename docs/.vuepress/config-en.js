module.exports = {
    selectText: 'Language',
    label: 'English',
    sidebar: {
        '/': [
            {
                title: 'Introduction',
                collapsable: false,
                children: [
                    'editions',
                ]
            },
            {
                title: 'Installing Craft Commerce',
                collapsable: false,
                children: [
                    'requirements',
                    'installation',
                    'changes-in-commerce-2',
                ]
            },
            {
                title: 'Configuration',
                collapsable: false,
                children: [
                    'configuration',
                    'project-config'
                ]
            },
            {
                title: 'Core Concepts',
                collapsable: false,
                children: [
                    'cart',
                    'orders',
                    'products',
                    'product-types',
                    'donations',
                    'customers',
                    'sales',
                    'discounts',
                    'tax',
                    'shipping',
                    'custom-order-statuses',
                    'order-status-emails',
                    'payment-currencies',
                    'subscriptions',
                ]
            },
            {
                title: 'Payment Gateways',
                collapsable: false,
                children: [
                    'payment-gateways',
                    'gateway-config',
                ]
            },
            {
                title: 'Getting Elements',
                collapsable: false,
                children: [
                    'dev/element-queries/order-queries',
                    'dev/element-queries/product-queries',
                    'dev/element-queries/variant-queries',
                    'dev/element-queries/subscription-queries',
                    'craft-commerce-carts-cart',
                ]
            },
            {
                title: 'Developers',
                collapsable: false,
                children: [
                    'events',
                    'extensibility',
                    'purchasables',
                    'adjusters',
                    'shipping-methods',
                ]
            },
            {
                title: 'Template Guides',
                collapsable: false,
                children: [
                    'example-templates',
                    'available-variables',
                    'adding-to-and-updating-the-cart',
                    'estimate-cart-addresses',
                    'update-cart-addresses',
                    'update-cart-customer',
                    'coupon-codes',
                    'customer-address-management',
                    'twig-filters',
                    'making-payments',
                    'saving-payment-sources',
                    'subscription-templates',
                ]
            },
            {
                title: 'Fields',
                collapsable: false,
                children: [
                    'products-fields',
                ]
            },
        ]
    }
};
