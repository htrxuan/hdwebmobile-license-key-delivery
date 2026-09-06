# HDWebmobile License Key Delivery

Sell software license keys through WooCommerce -- keys are pasted or generated in wp-admin, never imported from an uploaded file.

- **WordPress.org:** https://wordpress.org/plugins/hdwebmobile-license-key-delivery/
- **Requires:** WordPress 6.9+, WooCommerce, PHP 7.4+
- **License:** GPLv2 or later

## Description

HDWebmobile License Key Delivery turns any simple WooCommerce product into a license-key product. Add a pool of keys under the product's own "License Keys" tab -- paste them in or generate them randomly -- and one is automatically assigned to each customer the moment their order is completed.

## Why this plugin exists

A competing WooCommerce license-key plugin had an Unrestricted File Upload vulnerability (CVE-2026-28114, CVSS 9.1): a Shop Manager-level account -- a role routinely handed to shop staff and third-party agencies, and deliberately less trusted than Administrator -- could bulk-import license keys through a file upload with insufficient extension/MIME/content validation, letting a disguised `.php` file achieve remote code execution. This plugin closes that vulnerability class by construction, not mitigation:

* There is no file upload anywhere in this plugin. Bulk-adding keys is paste-a-textarea or generate-server-side only -- the vulnerable code path simply does not exist here.
* Keys you paste in are stored exactly as given (with a database-level uniqueness constraint so the same key can never be issued twice); keys the plugin generates for you use PHP's own cryptographically secure `random_bytes()`, never anything predictable.
* The key handed to each customer is claimed with a single atomic database update, so two orders completing at the same instant can never be issued the same key.
* A customer's "License Keys" page under My Account is scoped to their own account at the database query itself -- one customer can never see another's keys.

## Features

* Turn any simple product into a license-key product from its own Product Data tab
* Paste existing keys (one per line) or generate random ones on the spot
* One key delivered automatically per unit purchased when the order is marked Completed
* Delivered keys show on the order confirmation page, in order emails, and on the admin order screen
* A "License Keys" tab under My Account lists everything a customer has purchased
* If stock runs out, the order still completes normally and the admin is emailed to restock -- no fake or duplicate key is ever issued

## Development

Standard WordPress plugin structure:

```
hdwebmobile-license-key-delivery.php    Bootstrap
includes/class-hdlic-activator.php
includes/class-hdlic-admin-list-table.php
includes/class-hdlic-admin.php
includes/class-hdlic-core.php
includes/class-hdlic-hub.php
includes/class-hdlic-myaccount.php
includes/class-hdlic-order.php
includes/class-hdlic-product.php
includes/class-hdlic-repository.php
```

Part of the [HDWebmobile](https://hdwebmobile.com/plugins/) suite of focused, single-purpose WooCommerce plugins.

## License

GPLv2 or later. See [LICENSE](LICENSE).

