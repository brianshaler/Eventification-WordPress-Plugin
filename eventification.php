<?php
/*
Plugin Name: Eventification Events
Plugin URI: http://eventification.com/
Description: Create pages for upcoming events found via the Eventification API
Version: 1.0
Author: Brian Shaler
Author URI: http://brianshaler.com/
*/

class eventification {
  
  var $cache_dir = ABSPATH . "wp-content/uploads/eventification/";
  var $prefix = "eventification-";
  
  function init() {
    add_submenu_page('options-general.php', __('Eventification', 'eventification'), __('Eventification', 'eventification'), 10, $file, array($this, 'show_options'));
  }
  
  function show_options() {
    echo "test";
  }
  
  function setup_dir() {
  	$parent_dir = dirname($this->cache_dir);
  	if (!file_exists($this->cache_dir)) {
  		if (!is_writable($parent_dir) || !(@mkdir($this->cache_dir, 0777))) {
				// Error: cache dir not writable
				return false;
  		}
  	}
  	if (!is_writable($this->cache_dir)) {
  		// Error: wp-content not writable
  		return false;
  	}
    
  	if ($this->cache_dir{strlen($this->cache_dir)-1} != "/") {
  		$this->cache_dir .= "/";
  	}
  	return true;
  }
}

//add_action('', array('emailer', 'send'));

?>