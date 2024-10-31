=== Newsletter Converter ===
Contributors: ericjuden
Tags: newsletter, css, conversion, inline styles
Requires at least: 2.7
Tested up to: 3.2.1

== Description ==

Ever need to send your home page as a newsletter? This baby takes your home page's HTML/CSS and cleans it up to work with different email clients.

The main way of doing this is by removing the embedded styles and making them inline styles.

This plugin generates the html to use in your newsletter application.

== Installation ==

1. Copy the plugin files to <code>wp-content/plugins/</code>

2. Activate plugin from Plugins page

3. Go to Settings -> Newsletter Converter to adjust plugin settings

4. Go to Tools -> Newsletter Converter to start using

== Screenshots ==

1. The main screen for the plugin. It runs automatically when going to the plugin page.
2. The settings page.

== Changelog ==

= 1.0.10 =
* Some instances wouldn't show anything when trying to convert. I tracked it down to the regex line removing comments from the css

= 1.0.9 =
* Updated newsletter_converter_getURLContents() to use built-in wp_remote_post() function instead of using cURL and/or file_get_contents()

= 1.0.8 =
* Updated readme.txt for Wordpress 3.0
* Updated translateCSStoXpath() to match latest source from emogrifier (http://www.pelagodesign.com/sidecar/emogrifier/) 

= 1.0.7 =
* Updated readme.txt for WordPress 2.9
* Fixed a couple minor php errors that were happening during saving the settings.

= 1.0.6 =
* Automatically remove html comments and browser hacks
* Hopefully fixed timeout error

= 1.0.5 =
* Fixed script tags not being removed.

= 1.0.4 =
* Fixed a spot where I was still calling file_get_contents()
* Moved block to remove script tags before all processing for file...hopefully speed up processing.

= 1.0.3 =
* Added setting for generating newsletter from another URL
* Added setting to try using cURL for retrieving files instead of php's file_get_contents() function

= 1.0.2 =
* Bug fix for readme.txt file. Was missing a colon for the version number making it show up as 0.0

= 1.0.1 =
* Initial release
