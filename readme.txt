=== MainWP Child Reports ===
Contributors: mainwp
Tags: MainWP Child Reports, MainWP, MainWP Child, MainWP Client Reports Extension, child reports, reports, actions, activity, admin, analytics, dashboard, log, notification, users, Backupwordpress, Updraftplus
Author: mainwp
Author URI: https://mainwp.com
Plugin URI: https://mainwp.com
Requires at least: 3.6
Tested up to: 6.1.1
Requires PHP: 7.0
Stable tag: 2.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

The MainWP Child Report plugin tracks changes to Child sites for the Client Reports Extension.


== Description ==

**Note: This plugin requires PHP 5.6 or higher to be activated and is only useful if you are using [MainWP](https://wordpress.org/plugins/mainwp/) and the [MainWP Client Reports Extension](https://mainwp.com/extension/client-reports/).**

Install the [MainWP Child Plugin](https://wordpress.org/plugins/mainwp-child/) plugin first.

The MainWP Child Report plugin communicates changes on your Child sites to the [MainWP Client Reports Extension](https://mainwp.com/extension/client-reports/) in order to create the Client Reports.

Credit to the [Stream Plugin](https://wordpress.org/plugins/stream/) which the MainWP Child Reports plugin is built on.

== Installation ==

1. Upload the MainWP Child Reports folder to the /wp-content/plugins/ directory
2. Activate the MainWP Child Reports plugin through the 'Plugins' menu in WordPress

== Screenshots ==

1. The MainWP Child Reports Screen
2. The MainWP Child Reports Settings Screen

== Changelog ==

= 2.1 - 12-14-2022 =
* Updated: PHP 8.1 compatibility improvements
* Preventative: Multiple security enhancements

= 2.0.8 - 9-15-2021 =
* Fixed: An issue with logging certain actions triggered by WP Cron
* Fixed: An issue with displaying timestamps on some setups
* Fixed: Problems with the multibyte string functions usage
* Preventative: Multiple security improvements

= 2.0.7 - 2-4-2021 =
* Fixed: An issue with logging deleted plugins
* Updated: exclusion rules for certain custom post types

= 2.0.6 - 10-29-2020 =
* Added: PHP Docs blocks
* Updated: MainWP Child 4.1 compatibility

= 2.0.5 - 8-31-2020 =
* Fixed: jQuery warning
* Fixed: Compatibility issues with MySQL 8
* Fixed: An issue with logging maintenance tasks

= 2.0.4 - 4-30-2020 =
* Fixed: an issue with logging themes updates
* Fixed: an issue with logging created posts
* Added: option to recreate the plugin database tables
* Added: support for logging WPVivid backups

= 2.0.3 - 2-7-2020 =
* Fixed: an issue logging UpdraftPlus scheduled backups
* Fixed: an issue with dismissing missing database tables warning

= 2.0.2 - 1-22-2020 =
* Fixed: an issue with logging some backups
* Fixed: an issue with logging Maintenance data
* Fixed: an issue with logging security scan data
* Fixed: an issue with displaying empty data

= 2.0.1 - 12-13-2019 =
* Fixed: data Child Reports conversion problem

= 2.0 - 12-9-2019 =
* Added: support for the Pro Reports extension
* Updated: plugin functionality for better performance

= 1.9.3 - 2-14-2019 =
* Fixed: an issue with catching Media upload records
* Fixed: "Undefined variable: branding_header" PHP warning

= 1.9.2 - 1-30-2019 =
* Fixed: an issue with cleaning the plugin database tables on some setups
* Updated: MySQL query improvements

= 1.9.1 - 11-13-2018 =
* Fixed: an issue with missing data fields
* Updated: WooCommerce order notes excluded from showing as comments
* Updated: translation files

= 1.9 - 9-4-2018 =
* Fixed: an issue with recording UpdraftPlus backups
* Added: support for recording WPTC backups

= 1.8 - 8-2-2018 =
* Fixed: an issue with logging plugin installations
* Fixed: an issue with displaying double records
* Fixed: multiple PHP Warnings
* Improved: support for UpdraftPlus backups

= 1.7 - 5-12-2017 =
* Fixed: an issue with recording version numbers
* Fixed: conflict with Select2 library

= 1.6 - 4-4-2017 =
* Fixed: Select2 conflict with WooCommerce 3.0
* Fixed: an issue with returning incorrect date range in reports

= 1.5 - 3-15-2017 =
* Fixed: a few typos

= 1.4 - 2-13-2017 =
* Fixed: an issue with creating database table on first installation

= 1.3 - 2-9-2017 =
* Fixed: an issue with recording duplicate values for UpdraftPlus backups
* Fixed: multiple issues with recording backups made by supported plugins
* Fixed: an issue with recording incorrect values for plugins and themes versions
* Added: support for Wordfence tokens
* Added: support for Maintenance tokens
* Added: support for Page Speed tokens
* Added: support for Broken Links tokens
* Updated: system compatibility updates required by upcoming MainWP Client Reports Extension version

= 1.2 - 11-9-2016 =
* Fixed: Issue with hiding the plugin in Client Reports
* Fixed: Conflict with the auto backup feature of the UpdraftPlus Backups plugin (#8435)
* Fixed: Issue with double records for the UpdraftPlus backups
* Fixed: Issue with recording UpdraftPlus and BackUpWordPress backups
* Added: Support for the BackupBuddy plugin
* Added: Support for the MainWP Branding (#10609)

= 1.1 - 4-28-2016 =
* Updated: Support for the MainWP Child Plugin version 3.1.3

= 1.0 - 3-9-2016 =
* Fixed: Issue with recreating tables
* Fixed: Issue with recreating manually deleted tables
* Fixed: Issue with updating actions on auto-save Post and Page
* Fixed: Layout and javascript issue when custom branding is applied
* Added: Feature to copy reports from the Stream plugin
* Added: Support for recording BackWPup backups
* Added: Install Plugins, Install Themes, Delete Plugins, Delete Themes action logging
* Updated: New timeago js library version

* First version - 07-24-15
