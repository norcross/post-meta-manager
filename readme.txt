=== Post Meta Manager ===
Contributors: norcross, tripflex
Donate link: http://andrewnorcross.com/donate
Tags: custom field, custom fields, post meta, postmeta, metadata
Requires at least: 3.0
Tested up to: 4.4
Stable tag: 1.0.4

A simple utility plugin for changing or deleting post or user meta (custom fields) keys in bulk.

== Description ==

Creates a panel to change or delete meta keys in bulk. Useful for when you are switching plugins or themes that use specific meta keys for functionality, or for general cleanup for older sites that may have older meta data that is no longer in use.


== Installation ==
1. Upload the `post-meta-manager` folder and all its contents to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to the `Post Meta Manager` page under the 'Tools' menu

== Frequently Asked Questions ==

= Can I only change or delete keys? =

At this point, yes. That may change in the future.

= I made a typo in the key name I wanted to change to / from =

That's OK. Just run the process again with the correct spelling. Remember, if the typo was in the 'new' value, that's now the 'old' value to change.

= OH NOES I deleted a key I didn't mean to! =

That is...unfortunate. The process, however, is not reversible. You DID run a backup before, correct?

== Screenshots ==

1. The Post Meta manager for WordPress interface.

== Changelog ==

= 1.0.4 =
* Adding minified versions of CSS and JS files
* Code cleanup for WP coding standards

= 1.0.3 =
* Added ajax nonce security check
* Fixed screenshot on GitHub

= 1.0.2 =
* Added second menu item for removing user meta
* Updates for WP core changes

= 1.0.1 =
* JS cleanup
* inclusion of custom post types for key removal
* Bugfix for prepare() statement in database query

= 1.0 =
* Initial release
