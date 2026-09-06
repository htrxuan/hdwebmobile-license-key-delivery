=== HDWebmobile License Key Delivery ===
Contributors: htrxuan
Donate link: https://paypal.me/htrxuan/20
Tags: woocommerce, license key, software license, digital delivery, serial key
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell software license keys through WooCommerce -- keys are pasted or generated in wp-admin, never imported from an uploaded file.

== Description ==

HDWebmobile License Key Delivery turns any simple WooCommerce product into a license-key product. Add a pool of keys under the product's own "License Keys" tab -- paste them in or generate them randomly -- and one is automatically assigned to each customer the moment their order is completed.

= Why this plugin exists =
A competing WooCommerce license-key plugin had an Unrestricted File Upload vulnerability (CVE-2026-28114, CVSS 9.1): a Shop Manager-level account -- a role routinely handed to shop staff and third-party agencies, and deliberately less trusted than Administrator -- could bulk-import license keys through a file upload with insufficient extension/MIME/content validation, letting a disguised `.php` file achieve remote code execution. This plugin closes that vulnerability class by construction, not mitigation:

* There is no file upload anywhere in this plugin. Bulk-adding keys is paste-a-textarea or generate-server-side only -- the vulnerable code path simply does not exist here.
* Keys you paste in are stored exactly as given (with a database-level uniqueness constraint so the same key can never be issued twice); keys the plugin generates for you use PHP's own cryptographically secure `random_bytes()`, never anything predictable.
* The key handed to each customer is claimed with a single atomic database update, so two orders completing at the same instant can never be issued the same key.
* A customer's "License Keys" page under My Account is scoped to their own account at the database query itself -- one customer can never see another's keys.

= Key Features =
* Turn any simple product into a license-key product from its own Product Data tab
* Paste existing keys (one per line) or generate random ones on the spot
* One key delivered automatically per unit purchased when the order is marked Completed
* Delivered keys show on the order confirmation page, in order emails, and on the admin order screen
* A "License Keys" tab under My Account lists everything a customer has purchased
* If stock runs out, the order still completes normally and the admin is emailed to restock -- no fake or duplicate key is ever issued

= Limitations (please read before installing) =
* No file-upload bulk import -- paste or generate only, by design (see "Why this plugin exists")
* Simple products only in this version -- no variable-product support
* No per-key activation limits or remote deactivation; a key is either unassigned or assigned

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hdwebmobile-license-key-delivery` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress. WooCommerce must already be installed and active.
3. Edit a simple product, open its new "License Keys" tab under Product Data, check "Sell as license key product", and add some keys.

== How to Use ==

= 1. Add keys to a product =
On a simple product's "License Keys" tab, check "Sell as license key product", then either paste your own keys (one per line) or use "Generate Keys" to create random ones.

= 2. Customer buys the product =
Nothing changes at checkout -- it's a normal WooCommerce purchase.

= 3. Key delivered automatically =
Once the order is marked Completed, one key per unit purchased is claimed and shown on the order confirmation page, in the order email, and under My Account > License Keys.

== Screenshots ==

1. The "License Keys" tab on a product, with paste and generate options.
2. A delivered key shown on the order confirmation page.
3. The License Keys list under WooCommerce > HDWebmobile.

== Changelog ==

= 1.0.0 =
* Initial release: per-product key pools (paste or generate, never file-upload), atomic key assignment on order completion, "License Keys" My Account tab, backorder handling with admin notification.
