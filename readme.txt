=== PriceWaiter for WooCommerce ===
Contributors: pricewaiter, mattbain, curtisl, mthnz
Tags: commerce, ecommerce, e-commerce, conversion, store, shop, sales, pricing, woocommerce, lead capture,
Requires at least: 4.0
Tested up to: 4.7
Stable tag: 1.1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin integrates PriceWaiter's powerful price negotiation and conversion tools with your WooCommerce store.

== Description ==

__Why PriceWaiter?__

PriceWaiter® lets customers make offers on products you sell -- offers you can accept, reject or counter. The widget embeds a configurable Name Your Price -or- Make an Offer button on product pages in your WooCommerce storefront. Simply install the plugin and you’ll have full control over PriceWaiter in your WooCommerce admin panel.

* Engage more visitors
* Convert comparision shoppers
* Make M.A.P. moot
* Increase conversion rates
* Sell more

For more information visit our [WooCommerce Integration Page](https://www.pricewaiter.com/integrations/woocommerce/?utm_source=wp-plugins&utm_medium=website&utm_campaign=integration) or [WooCommerce Docs Page](https://docs.pricewaiter.com/platforms/woocommerce.html?utm_source=wp-plugins&utm_medium=website&utm_campaign=integration).

== Installation ==

Getting started with PriceWaiter.

1. You must have WooCommerce version 2.6.0 or higher installed _(Will not cause errors, just won't do anything if not)_
1. Upload `woocommerce-pricewaiter` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. From your admin panel go to `WooCommerce` > `Settings` > `Integration` _(tab)_ > `PriceWaiter`
1. Follow the on screen instructions

== Frequently Asked Questions ==

= Do I need a PriceWaiter account? =

Yes, you will need a PriceWaiter account. The installation process will guide you through that quickly.

= What if I already have a PriceWaiter account? =

No problem. You can use your exisiting PriceWaiter store API Key in the WooCommerce PriceWaiter settings panel.

= How do I change the look of the PriceWaiter button? =

You can change your button style in you stores PriceWaiter account. There is a "Configure Button" link in your WooCommerce PriceWaiter settings"

= What is "disable conversion tools"? =

Conversion tools are customizeable and configurable ways to help you sell more. [Exit Intent Offers](https://www.pricewaiter.com/feature/exit-intent-offers/?utm_source=wp-plugins&utm_medium=website&utm_campaign=integration) is one of our leading conversion boosting tools.

= Why does PriceWaiter need API access? =

In order to function, PriceWaiter needs the ability to write orders, check inventory, margins and other important product information. Don't worry, this information is kept private.

= Do I have to enter my product costs? =

No, you do not have to enter your product costs. We recommend you do so that PriceWaiter will be able to make accurate decisions if you choose to enable our Advanced "Automation" mode.

= How do I disable the PriceWaiter button on certain products? =

There is a checkbox "Disable PriceWaiter" for each product. Simply tick the box and the PriceWaiter button will no longer appear on that product.

= Does PriceWaiter work with Grouped Products? =

No. At the moment PriceWaiter does not work with grouped products. The PriceWaiter Button is automagically excluded from showing up on grouped products.

== Screenshots ==

1. PriceWaiter plugin settings screen.
2. PriceWaiter per-product settings.
3. Simple, FREE -or- Premium signup process for the PriceWaiter service.
4. Easily get an overview of your stores performance from you PriceWaiter dashboard.

== Changelog ==

= 1.1.1 =
* Fix two php warnings that can cause issues when error reporting shows notices

= 1.1.0 =
* Tested for WordPress up to 4.7.4
* Adds support for WooCommerce 3.0.x
* Writes orders via WooCommerce REST API for WooCommerce 3.0+

= 1.0.2 =
* Fixes plugin activation error "The plugin does not have a valid header."

= 1.0.1 =
* Adds support for renaming the analytics objects used for ecommerce tracking.

= 1.0 =
* Initial plugin.
* Features order callback via IPN and checkout redirect analytics.

== Upgrade Notice ==

= 1.0 =
n/a.


== Product Cost Field ==

PriceWaiter adds a `Cost of Good` field to all of your products. This should be what the product costs you.
We recommend you complete these fields so that PriceWaiter will be able to make accurate decisions if you choose to enable our Advanced "Automation" mode.

If you are already using the [WooCommerce - Cost of Goods plugin](http://www.woothemes.com/products/woocommerce-cost-of-goods/), that's fine our plugin works with it.
