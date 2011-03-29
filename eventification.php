<?php
/*
Plugin Name: Eventification Events
Plugin URI: http://eventification.com/
Description: Create pages for upcoming events found via the Eventification API
Version: 1.0
Author: Brian Shaler
Author URI: http://brianshaler.com/
*/

class Eventification {
  
  // How frequently to request new events from the Eventification API
  var $update_frequency = 3600;
  // User's API string
  var $api_string = "";
  // Custom post type
  var $post_type = "eventification_event";
  // Cached JSON of upcoming events
  var $upcoming_events = "";
  
  // Default templates
  var $event_template = "{description}<p>{venue_info}</p><p><a href=\"{url}\">Event home page</a> / <a href=\"http://eventification.com{event_url}\">Eventification page</a></p><p><a href=\"http://eventification.com/tag/view/{tags:0:tag_code}\" title=\"{tags:0:tag_text} events in {city}\">Discover more {tags:0:tag_text} events in {city} using Eventification!</a></p>";
  var $short_event_template = "<p><strong style=\"font-size: 120%;\"><a href=\"{local_url}\">{name}</a></strong><br />{starttime:date:l F j, Y g:ia}<br />{short_description}</p>";
  var $title_template = "{name} in {city}, {state} - {starttime:date:l, F j, Y g:ia}";
  var $slug_template = "{name}-{city}-{state}-{starttime:date:F-j-Y}";
  
  var $initialized = false;
  
  /**
   * Add options page to wp-admin and retrieve custom options
   */
  function init() {
    if (function_exists('add_options_page') && !$this->initialized)
      add_options_page('Eventification', 'Eventification', 9, __FILE__, array($this, 'show_options'));
    
    $this->api_string = get_option("eventification_api", $this->api_string);
    $this->upcoming_events = get_option("eventification_upcoming_events", $this->upcoming_events);
    $this->event_template = stripcslashes(get_option("eventification_event_template", $this->event_template));
    $this->short_event_template = stripcslashes(get_option("eventification_short_event_template", $this->short_event_template));
    $this->title_template = stripcslashes(get_option("eventification_title_template", $this->title_template));
    $this->slug_template = stripcslashes(get_option("eventification_slug_template", $this->slug_template));
    //$this-> = get_option("eventification_", $this->);
    $this->initialized = true;
  }
  
  /**
   * Regisert the Event post type with WP
   */
  function event_post_type() {
    $labels = array('name'=>__('Events'),'singular_name'=>__('Event'));
    $args = array('labels'=>$labels, 'public' => true, 'show_ui'=>true, 'has_archive' => true, 'rewrite' => array('slug' => 'events'), 'supports'=>array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'comments', 'custom-fields', 'revisions', 'page-attributes'));
  	register_post_type('eventification_event', $args);
  }
  
  /**
   * Display options page and process form if posted.
   */
  function show_options() {
    if (isset($_POST["action"]) && $_POST["action"] == "update_settings")
      $this->process_form();
    
    $this->show_form();
    
    // Just in case, trigger new call
    $this->get_events();
  }
  
  /**
   * Update custom options in DB and re-initialize
   */
  function process_form() {
    $this->api_string = $_POST["api_string"];
    //$this->post_type = strtolower($_POST["post_type"]);
    //if ($this->post_type != "post" && $this->post_type != "page") { $this->post_type = "post"; }
    $event_template = $_POST["event_template"];
    $short_event_template = $_POST["short_event_template"];
    $title_template = $_POST["title_template"];
    $slug_template = $_POST["slug_template"];
    
    if ($this->api_string != get_option("eventification_api"))
    {
      update_option('eventification_last_request', 0);
      update_option('eventification_api', $this->api_string);
    }
    //update_option('eventification_post_type', $this->post_type);
    update_option('eventification_event_template', $event_template);
    update_option('eventification_short_event_template', $short_event_template);
    update_option('eventification_title_template', $title_template);
    update_option('eventification_slug_template', $slug_template);
    //update_option('eventification_', $);
    //update_option('eventification_', $);
    
    $this->init();
  }
  
  /**
   * Display HTML form in admin
   */
  function show_form () {
    ?>
    <style type="text/css">
    .evnt_small {
      font-size: 80%;
    }
    </style>
    <div class="wrap" style="width: 600px;">
      <h2>Eventification Events</h2>
      <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
        <input type="hidden" name="action" value="update_settings"/>
        <p>
          API Call: <a class="evnt_small" href="http://eventification.com/api">(API documentation)</a><br />
          <input type="text" name="api_string" style="width: 500px;" value="<?php echo $this->api_string ?>" /><br />
          <em class="evnt_small">Include everything <strong>after</strong> http://eventification.com/api/</em><br />
          <em class="evnt_small">Examples: get/events/?tag=technology or get/events/?venue_name=My%20Venue</em>
        </p>
        
        <p>
          Event Template: <em class="evnt_small">(see below for details)</em><br />
          <textarea rows="8" style="width: 500px;" name="event_template"><?php echo htmlentities(stripcslashes($this->event_template)); ?></textarea>
        </p>
        <p>
          Event Title Template: <em class="evnt_small">(see below for details)</em><br />
          <input type="text" name="title_template" style="width: 500px;" value="<?php echo $this->title_template ?>" />
        </p>
        <p>
          Event Slug Template: <em class="evnt_small">(see below for details)</em><br />
          <input type="text" name="slug_template" style="width: 500px;" value="<?php echo $this->slug_template ?>" />
        </p>
        <p>
          Short Event Template: <em class="evnt_small">(see below for details)</em><br />
          <textarea rows="4" style="width: 500px;" name="short_event_template"><?php echo htmlentities(stripcslashes($this->short_event_template)); ?></textarea>
        </p>
        <p>
          <input type="submit" value="Save" />
        </p>
      </form>
    
      <h3>
        Template instructions:
      </h3>
      <p>
        Any event property listed on the <a href="http://eventification.com/api">API documentation page</a> can be used within a template.
      </p>
      <p>
        For a typical string property, use {description} to show an event's description.
      </p>
      <p>
        For arrays, such as Tags, you can traverse the array using array keys. For example, {tags:0:tag_text} would be like writing $tags[0]["tag_text"] in PHP.
      </p>
      <p>
        To format a Unix timestamp (such as starttime or endtime), the syntax is {property:date:formatting} where "property" is starttime or endtime and "formatting" is a string representing the <a href="http://php.net/date">PHP date()</a> format to use. For example, "{starttime:date:l, F j, Y g:ia}" will show up as "Saturday, January 1, 2011 12:00am".
      </p>
    </div>
    <?php
  }
  
  /**
   * Retrieve events from the Eventification API. Create new Event pages in WP if necessary.
   */
  function get_events() {
    if (!$this->initialized)
      $this->init();
    
    if ($this->api_string == "") { return; } // Hasn't been set up yet
    
    $last_request = intval(get_option("eventification_last_request"));
    if ($last_request < time()-$this->update_frequency)
    {
      $url = "http://eventification.com/api/" . $this->api_string;
    
  		if (function_exists('file_get_contents')) {
  			$file = file_get_contents($url);
  		} else {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $file = curl_exec($curl);
        curl_close($curl);
      }
      update_option('eventification_last_request', time());
      $data = json_decode($file, true);
      
      if (isset($data["events"])) {
        update_option('eventification_upcoming_events', json_encode($data["events"]));
        foreach ($data["events"] as $event) {
          $title = $this->parse_template($this->title_template, $event);
          
          $post = get_event_by_title($title);

          if (!$post) {
            
            $tags = array();
            foreach ($event["tags"] as $tag) {
              $tags[] = $tag["tag_text"];
            }
            // Create post object
            $_p = array();
            $_p['post_title'] = $title;
            $_p['post_name'] = $this->parse_template($this->slug_template, $event);
            $_p['post_content'] = "[eventification event_id=\"".$event["event_id"]."\"]";// ".$event["event_id"]."}";
            $_p['post_status'] = 'publish';
            $_p['post_type'] = $this->post_type;
            $_p['post_category'] = array(1); // the default 'Uncategorised'
            $_p['tags_input'] = implode(", ", $tags);
            
            // Insert the post into the database
            $pid = wp_insert_post( $_p );
            
          } else {
            $pid = $post->ID;
          }
          update_post_meta($pid, "eventification", str_replace("\\", "\\\\\\\\", json_encode($event)));
        }
      }
    }
  }
  
  /**
   * Replace shortcode with a list of upcoming events
   */
  function show_upcoming() {
    if (!$this->initialized)
      $this->init();
    
    $events = json_decode($this->upcoming_events, true);
    
    $event_str = "";
    
    foreach ($events as $event) {
      
      $title = $this->parse_template($this->title_template, $event);
      
      $p = get_event_by_title($title);
      
      $event["local_url"] = $p->guid;
      
      $event_str .= $this->parse_template($this->short_event_template, $event);
    }
    if ($event_str == "") {
      $event_str = "<div>No upcoming events at this time.</div>";
    }
    //if ($content != "")
      //$event_str = "<h4>Upcoming Events</h4>" . $event_str;
    
    return $event_str;
  }
  
  /**
   * Custom templates
   */
  function parse_template ($template, $obj) {
    $matches = array();
    $i = 0;
    $max = 50; // don't spin our wheels too much
    while (preg_match("/[^\\\\]?(\{[^}]*\})/", $template, $matches) > 0 && $i<$max) {
      // prep short_description if requested
      if ($matches[1] == "{short_description}" && !isset($obj["short_description"]) && isset($obj["description"])) {
        $desc = $obj["description"];
        $short = strip_tags($desc);
        if (strlen($short) > 155 && strpos($short, " ", 150) > 0)
        {
          $short = substr($short, 0, strpos($short, " ", 150)) . " ...";
        }
        $obj["short_description"] = $short;
      }
      
      // {one:two:three} => ["one", "two", "three"]
      $shortcode = explode(":", substr($matches[1], 1, -1));
      
      // get value from object for first chunk
      $replace = $obj[array_shift($shortcode)];
      
      // traverse array or run date function if more than one element
      while (count($shortcode) > 0) {
        // move cursor and trim $shortcode
        $chunk = array_shift($shortcode);
        
        if ($chunk == "date") {
          // if second element is a date, run date() on the third and quit
          $str = implode(":", $shortcode);
          $sec_offset = get_option('gmt_offset') * 3600;
          $replace = date($str, intval($replace) + $sec_offset);
          $shortcode = array();
        } else {
          // traverse array
          $replace = $replace[$chunk];
        }
      }
      
      // replace all instances of the matched shortcode
      $template = str_replace($matches[1], $replace, $template);
      
      // while loops are dangerous
      $i++;
    }
    
    return $template;
  }
  
  /**
   * Display a detailed event view or upcoming list.
   * 
   * Usage: [eventification event_id="1"] or [eventification events="upcoming"]
   */
  function shortcode ($attr) {
    
    if (!$this->initialized)
      $this->init();
    
    if (intval($attr["event_id"]) > 0) {
      $custom = get_post_custom();
      if (!isset($custom["eventification"])) { return 'error'; }
      
      $event_str = $custom["eventification"][0];
      $event = json_decode($custom["eventification"][0], true);
      if (!isset($event["name"])) { $event = json_decode(stripcslashes($custom["eventification"][0]), true); }
      
      if (isset($event[0])) { $event = $event[0]; }
      return $this->parse_template($this->event_template, $event);
    } else
    if ($attr["events"] == "upcoming") {
      return $this->show_upcoming();
    }
  }
  
  /**
   * Schedule cron job if it doesn't already exist
   */
  function check_cron() {
  	if (!wp_next_scheduled('eventification_cron')) {
  		wp_schedule_event(time(), 'hourly', 'eventification_cron');
  	}
  }
}

$eventification = new Eventification();

// Actions
add_action('init', array($eventification, 'event_post_type'));
add_action('wp', array($eventification, 'check_cron'));
add_action('admin_menu', array($eventification, 'init'));
add_action('eventification_cron', array($eventification, 'get_events'));

// Filters

// Shortcodes
add_shortcode('eventification', array($eventification, 'shortcode'));

/**
 * Retrieve an event given its title.
 *
 * @uses $wpdb
 *
 * @param string $event_title Event title
 * @param string $output Optional. Output type. OBJECT, ARRAY_N, or ARRAY_A.
 * @return mixed
 */
if (!function_exists("get_event_by_title")) {
  function get_event_by_title($event_title, $output = OBJECT) {
  	global $wpdb;
  	$event = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type='eventification_event'", $event_title));
  	if ($event)
  		return get_post($event, $output);

  	return null;
  }
}

// Testing github pull request
?>
