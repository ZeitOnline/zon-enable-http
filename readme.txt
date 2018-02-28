=== ZEIT ONLINE Enable HTTP (in HTTPS environment) ===
Contributors: codecandies  
Donate link: https://www.zeit.de  
Tags: SSL, https, maintenance  
Requires at least: 4.6.0  
Tested up to: 4.9  
Stable tag: 1.0.0  
License: GPLv3 or later  
License URI: http://www.gnu.org/licenses/gpl-3.0.html  

Filters the home_url from https to http on blogs that are https configured if the request scheme is http- 

== Description ==

While we switch our multiuser wordpress from http to https there will be a phase where both protocols should be available (mainly for SEO reasons). Therefor we switched the environment to use https. With this plugin activated, all uses of home_url() will be backported to http, when the user visits the page with http.

== Installation ==

1. Upload the folder `zon-enalble-hhttp` to the `/wp-content/plugins/` directory or use the Github Updater Plugin to install
2. (Network) activate the plugin through the 'Plugins' menu in WordPress

== Changelog ==

= 1.0.0 =
* Initial and stable release
