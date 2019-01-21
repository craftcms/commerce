# Project Config

Craft 3.1 introduced the [**project config**](https://docs.craftcms.com/v3/project-config.html), a sharable configuration store that makes it easier for developers to collaborate and deploy site changes across multiple environments.

Craft Commerce 2 stores the following items in the project config:

- Commerce general settings
- Email settings
- Gateways settings
- Order field layout
- Order Statuses
- Product types
- Fields and field groups
- Subscription field layout

Not everything should be stored in the project config. Some items are considered content, which will change in production.  
The following items are not stored in the project config:

- Discount promotions
- Sales promotions
- Shipping categories
- Shipping zones
- Shipping methods and rules
- Subscription plans
- Subscriptions elements 
- Tax categories
- Tax zones
- Tax rates
- Order elements
- Products & Variant elements