<?php
/*
Plugin Name: Newsletter Converter
Plugin URI: http://ericjuden.com/projects/newsletter-converter-for-wordpress/ 
Description: Create HTML for email-friendly newsletter
Version: 1.0.10
Author: Eric Juden
Author URI: http://ericjuden.com
*/

/*  Copyright 2009  Eric Juden  (email : ericjuden@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if ( !defined('WP_CONTENT_URL') )
    define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if ( !defined('WP_CONTENT_DIR') )
    define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if (!defined('PLUGIN_URL'))
    define('PLUGIN_URL', WP_CONTENT_URL . '/plugins/');
define('NC_PLUGIN_URL', PLUGIN_URL .'newsletter-converter/');

add_action('admin_menu', 'newsletter_converter_admin_menu');
add_action('admin_init', 'newsletter_converter_register_settings');

function newsletter_converter_admin_menu() {
	add_management_page('Newsletter Converter', 'Newsletter Converter', 8, 'newsletter-converter', 'newsletter_converter_plugin');
	add_options_page('Newsletter Converter Options', 'Newsletter Converter', 8, 'newsletter-converter', 'newsletter_converter_plugin_options');
}

function newsletter_converter_register_settings() {
	register_setting('newsletter-converter-group', 'newsletter-converter_stripLinks');
	register_setting('newsletter-converter-group', 'newsletter-converter_stripStyles');
	register_setting('newsletter-converter-group', 'newsletter-converter_stripScripts');
	register_setting('newsletter-converter-group', 'newsletter-converter_convertURL');
}

// Used to remove certain html tags from a string
function newsletter_converter_stripTag($tag, $text, $hasEndTag = true, $endTag = '>'){
	$myPosition = 0;
	if($hasEndTag){
		$endTag = "</". $tag . ">";
	}
	
	while($myPosition !== false){
		$myPosition = strpos($text, "<" . $tag);
		if($myPosition === false){
			break;
		}
		$myEndPosition = strpos($text, $endTag, $myPosition);
		$block = substr($text, $myPosition, ($myEndPosition-$myPosition)+strlen($endTag));
		$text = str_replace($block, "", $text);				// Remove block from page
		$myPosition = 0;
	}
	
	return $text;
}

function newsletter_converter_plugin() {
	$url = '';
	if(get_option('newsletter-converter_convertURL') != ''){
		$url = get_option('newsletter-converter_convertURL');
	} else {
		$url = get_bloginfo('url');
	}
	$file = newsletter_converter_getURLContents($url);
	
	if(get_option('newsletter-converter_stripScripts')){
		$file = newsletter_converter_stripTag('script', $file);
	}
	
	$file = newsletter_converter_stripTag('!--', $file, false, '-->');	// Remove html comments
	
	$styleCount = substr_count($file, "<style");
	$linkCount = substr_count($file, "<link");
	
	$filePosition = 0;
	$aRules = array();
	
	// Search through <style> elements
	if($styleCount > 0){	// process styles
		$myPosition = 0;
		$i = 0;
		while($i < $styleCount){
			if($myPosition == 0){
				$myPosition = strpos($file, "<style");
			}
			$endStyleTag = strpos($file, ">", $myPosition);
			$myEndPosition = strpos($file, "</style>", $myPosition);
			 
			$css = substr($file, $endStyleTag+1, $myEndPosition);

			$tempRules = cleanCSS($css);
			$aRules = array_merge((array)$tempRules, (array)$aRules);
			
			$myPosition = $myEndPosition;
			$i++;
		}
	}
	
	// Search through <link /> elements
	if($linkCount > 0){
		$myPosition = 0;
		$endLinkTag = 0;
		$i = 0;
		while($i < $linkCount){
			if($myPosition == 0){
				$myPosition = strpos($file, "<link", $endLinkTag);
			}
			$endLinkTag = strpos($file, "/>", $myPosition);
			
			$link = substr($file, $myPosition, ($endLinkTag+2) - $myPosition);	// get entire <link /> tag
			
			// Remove quotes from string
			$linkNoQuotes = str_replace('"', '', $link);
			$linkNoQuotes = str_replace("'", "", $linkNoQuotes); 
			
			if(substr_count($linkNoQuotes, "rel=stylesheet") > 0){	// Make sure we are working with a stylesheet
				if((substr_count($linkNoQuotes, "media=screen") > 0) || (substr_count($linkNoQuotes, "media=all") > 0)){	// only want all or screen
					$hrefStart = strpos($linkNoQuotes, "href=");
					if($hrefStart !== false) {
						$hrefEnd = strpos($linkNoQuotes, " ", $hrefStart); 	// Look for space after link
						
						if($hrefEnd !== false){
							$url = substr($linkNoQuotes, $hrefStart+5, ($hrefEnd-5) - $hrefStart);
							$css = newsletter_converter_getURLContents($url);
							if(strlen($css) > 0){
								$tempRules = cleanCSS($css);
								if(is_array($tempRules)){
									$aRules = array_merge((array)$tempRules, (array)$aRules);
								}
							}
						}
					}
				}
			}
			
			$myPosition = $endLinkTag;
			$i++;
		}
	}
	
	if(get_option('newsletter-converter_stripStyles')){
		$file = newsletter_converter_stripTag('style', $file);
	}
	
	if(get_option('newsletter-converter_stripLinks')){
		$file = newsletter_converter_stripTag('link', $file, false);
	}
	
	error_reporting(0);		// Hide $xmldoc and $xpath warnings
	
	$xmldoc = new DOMDocument();
	$xmldoc->strictErrorChecking = false;
	$xmldoc->formatOutput = true;
	$xmldoc->loadHTML($file);
	$xmldoc->normalizeDocument();
	
	$xpath = new DOMXPath($xmldoc);
	
	foreach($aRules as $key => $css) {
		$nodes = $xpath->query(translateCSStoXpath(trim($key)));
		
		if($nodes){
			foreach($nodes as $node){
				// If it has style attribute
				if($node->hasAttribute('style')){
					$style = $node->getAttribute('style');
					
					$style .= $css;
				} else {
					$style = $css;	
				}
				
				$node->setAttribute('style', $style);
			}
		}
	}
	
	//$css = substr($file, 0);
	
	
	echo '<div class="wrap">';
	echo '<h2>Newsletter Converter</h2>';
	echo '<p id="hideme">BAM! Done. Here is your formatted HTML for a newsletter!</p>';
	echo '<textarea name="txtHTML" id="txtHTML" cols="100" rows="25" onClick="SelectAll(\'txtHTML\');">'. $xmldoc->saveHTML() .'</textarea>';
	echo '<script type="text/javascript">
			function SelectAll(id)
			{
	    		document.getElementById(id).focus();
	    		document.getElementById(id).select();
			}
		  </script>
		';
	echo '</div>';
}

function newsletter_converter_plugin_options() {
	echo '<div class="wrap">';
	echo '<h2>Newsletter Converter Options</h2>';
	if(isset($_GET['updated'])){
		echo '<div id="message" class="updated fade"><p>'. urldecode($_GET['updatedmsg']) .'</p></div>';
	}
	
	$action = "";
	if(isset($_GET['action'])){
		$action = $_GET['action'];
	}
	
	switch($action) {
		case "update":
			update_option('newsletter-converter_stripLinks', (isset($_POST['newsletter-converter_stripLinks']) ? $_POST['newsletter-converter_stripLinks'] : 0));
			update_option('newsletter-converter_stripStyles', (isset($_POST['newsletter-converter_stripStyles']) ? $_POST['newsletter-converter_stripStyles'] : 0));
			update_option('newsletter-converter_stripScripts', (isset($_POST['newsletter-converter_stripScripts']) ? $_POST['newsletter-converter_stripScripts'] : 0));
			update_option('newsletter-converter_convertURL', $_POST['newsletter-converter_convertURL']);
			
			echo "
			<SCRIPT LANGUAGE='JavaScript'>
			window.location='options-general.php?page=newsletter-converter&updated=true&updatedmsg=" . urlencode(__('Settings saved.')) . "';
			</script>
			";
			break;
			
		default:
			echo '<form method="post" action="options-general.php?page=newsletter-converter&action=update">';
			wp_nonce_field('update-options');
			echo '<table class="form-table">';
			echo '<tr valign="top">';
			echo '<th scope="row">Remove link tags?</th>';
			echo '<td><input type="checkbox" name="newsletter-converter_stripLinks" value="1" '. ((get_option('newsletter-converter_stripLinks') > 0) ? 'checked="checked"' : '') .'" /></td>';
			echo '</tr>';
			echo '<tr valign="top">';
			echo '<th scope="row">Remove style tags?</th>';
			echo '<td><input type="checkbox" name="newsletter-converter_stripStyles" value="1" '. ((get_option('newsletter-converter_stripStyles') > 0) ? 'checked="checked"' : '') .'" /></td>';
			echo '</tr>';
			echo '<tr valign="top">';
			echo '<th scope="row">Remove script tags?</th>';
			echo '<td><input type="checkbox" name="newsletter-converter_stripScripts" value="1" '. ((get_option('newsletter-converter_stripScripts') > 0) ? 'checked="checked"' : '') .'" /></td>';
			echo '</tr>';
			echo '<tr valign="top">';
			echo '<th scope="row">Alternate Page to convert (URL)</th>';
			echo '<td><input type="text" name="newsletter-converter_convertURL" value="'. get_option('newsletter-converter_convertURL') .'"</td>';
			echo '</tr>';
			echo '</table>';
			echo '<input type="hidden" name="action" value="update" />';
			echo '<input type="hidden" name="page_options" value="newsletter-converter_stripLinks,newsletter-converter_stripStyles,newsletter-converter_stripScripts,newsletter-converter_convertURL" />';
			settings_fields('newsletter-converter-group');
			echo '<p class="submit"><input type="submit" class="button-primary" value="Save Changes" /></p>';
			echo '</form>';
			break;
	}
	echo '</div>';
}

// Used for cleaning css and turning into array of css rules
function cleanCSS($text){
	$aCSS = array();
	
	if(substr_count($text, '@import') > 0){	// import statement...look for link and pull back file
		$start = strpos($text, 'http://');
		$end = strpos($text, '.css');
		$link = substr($text, $start, ($end+4)-$start);		// $end+4 to put extension back on
		$text = newsletter_converter_getURLContents($link);
	}
	
	// Should have css now...remove comments
	$text = preg_replace('/\*(.|[\r\n])*?\*/', '', $text);
	
	// Split string on ending bracket }
	// This splits string into array of css rules
	$strCSS = strtok($text, '}');
	
	$i = 0;
	while($strCSS != false){		
		$rule = $strCSS;
		$endRuleNamePOS = strpos($rule, "{", 0);
		$rulename = rtrim(ltrim(substr($rule, 0, $endRuleNamePOS)));
		
		$rule = substr($rule, $endRuleNamePOS + 1);	// Remove beginning of rule
		$rule = str_replace('  ', '', $rule);		// Remove multiple spaces
		$rule = preg_replace('/\t/', '', $rule);					// Remove tabs
		$rule = preg_replace('/\n\r|\r\n|\n|\r/', '', $rule);		// Remove line breaks
		
		$aCSS[$rulename] = $rule;	// Add to array
		$strCSS = strtok('}');		// Go to next item
		$i++;
	}
	return $aCSS;
}

function newsletter_converter_getURLContents($url){
	$ret = wp_remote_post($url);
	
	//$ret = array();
	if(is_array($ret)) {
		return $ret['body'];
	} else {
		return '';
	}
}

function newsletter_converter_isURL($url){
	$pattern = '/^(([\w]+:)?\/\/)?(([\d\w]|%[a-fA-f\d]{2,2})+(:([\d\w]|%[a-fA-f\d]{2,2})+)?@)?([\d\w][-\d\w]{0,253}[\d\w]\.)+[\w]{2,4}(:[\d]+)?(\/([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)*(\?(&amp;?([-+_~.\d\w]|%[a-fA-f\d]{2,2})=?)*)?(#([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)?$/';
	if(preg_match($pattern, $url)){
		return true;
	} else {
		return false;
	}
}

// Function came from Emogrifier: http://www.pelagodesign.com/sidecar/emogrifier/
// right now we only support CSS 1 selectors, but include CSS2/3 selectors are fully possible.
// http://plasmasturm.org/log/444/
function translateCSStoXpath($css_selector) {
    // returns an Xpath selector
    $search = array(
                       '/\s+>\s+/', // Matches any F element that is a child of an element E.
                       '/(\w+)\s+\+\s+(\w+)/', // Matches any F element that is a child of an element E.
                       '/\s+/', // Matches any F element that is a descendant of an E element.
                       '/(\w)\[(\w+)\]/', // Matches element with attribute
                       '/(\w)\[(\w+)\=[\'"]?(\w+)[\'"]?\]/', // Matches element with EXACT attribute
                       '/(\w+)?\#([\w\-]+)/e', // Matches id attributes
                       '/(\w+|\*)?((\.[\w\-]+)+)/e', // Matches class attributes
    );
    $replace = array(
                       '/',
	                       '\\1/following-sibling::*[1]/self::\\2',
	                       '//',
                           '\\1[@\\2]',
                           '\\1[@\\2="\\3"]',
	                       "(strlen('\\1') ? '\\1' : '*').'[@id=\"\\2\"]'",
	                       "(strlen('\\1') ? '\\1' : '*').'[contains(concat(\" \",@class,\" \"),concat(\" \",\"'.implode('\",\" \"))][contains(concat(\" \",@class,\" \"),concat(\" \",\"',explode('.',substr('\\2',1))).'\",\" \"))]'",
    );
    return '//'.preg_replace($search,$replace,trim($css_selector));
}
?>