<?php

// A&M quick commands.
return [
	[
		'name' => 'Manage Orders',
		'type' => 'Link',
		'url'  => \Craft\UrlHelper::getCpUrl('commerce/orders')
	],
	[
		'name' => 'Manage Products',
		'type' => 'Link',
		'url'  => \Craft\UrlHelper::getCpUrl('commerce/products')
	]
];