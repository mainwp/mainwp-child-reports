=== MainWP Child Reports ===
Contributors: MainWP
Donate link: 
Tags: MainWP Child Reports, MainWP, MainWP Child, MainWP Client Reports Extension, child reports, reports, actions, activity, admin, analytics, dashboard, log, notification, users, Backupwordpress, Updraftplus
Author: MainWP
Author URI: https://mainwp.com
Plugin URI: https://mainwp.com
Requires at least: 3.6
Tested up to: 4.7.2
Stable tag: 1.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The MainWP Child Report plugin tracks changes to Child sites for the Client Reports Extension. 


== Description ==

**Note: This plugin requires PHP 5.3 or higher to be activated and is only useful if you are using [MainWP](https://wordpress.org/plugins/mainwp/) and the [MainWP Client Reports Extension](https://mainwp.com/extension/client-reports/).**

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

= 1.4 - 2-13-2017 =
* Fixed: an issue with creating database table on first installation

= 1.3 - 2-9-2017 =
* Fixed: an issue with recording duplicate values for UpdraftPlus backups
* Fixed: multiple issues with recording backups made by supported plugins
* Fixed: an issue with recording incorrect values for plugins and themes versions
* Added: support for Wordfence tokens
* Added: support for Maintanence tokens
* Added: support for Page Speed tokens
* Added: support for Broken Links tokens 
* Updated: system compatibility udpates required by upcoming MainWP Client Reports Extension versoin

= 1.2 - 11-9-2016 =
* Fixed: Issue with hiding the plugin in Client Reports
* Fixed: Conflict with the auto backup feature of the UpdraftPlus Backups plugin (#8435)
* Fixed: Issue with double records for the UpdraftPlus backups
* Fixed: Issue with recorging UpdraftPlus and BackUpWordPress backups
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
