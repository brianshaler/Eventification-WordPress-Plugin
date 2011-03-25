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
  
  public static $update_frequency = 3600;
  public static $api_string = "";
  public static $post_type = "page";
  public static $upcoming_page = 0;
  public static $upcoming_events = "";
  
  public static $init = false;
  
  function init() {
    if (function_exists('add_options_page'))
      add_options_page('Eventification', 'Eventification', 9, __FILE__, array('eventification', 'show_options'));
    
    ini_set("date.timezone", "America/Phoenix");
    
    self::$api_string = get_option("eventification_api", self::$api_string);
    self::$post_type = get_option("eventification_post_type", self::$post_type);
    self::$upcoming_page = get_option("eventification_upcoming_page", self::$upcoming_page);
    self::$upcoming_events = get_option("eventification_upcoming_events", self::$upcoming_events);
    //self::$ = get_option("eventification_");
    //self::$ = get_option("eventification_");
    self::$init = true;
  }
  
  function show_options() {
    if (isset($_POST["action"]) && $_POST["action"] == "update_settings")
      self::process_form();
    self::show_form();
    
    self::get_events();
  }
  
  function process_form() {
    $api_string = $_POST["api_string"];
    $post_type = strtolower($_POST["post_type"]);
    if ($post_type != "post" && $post_type != "page") { $post_type = "post"; }
    $upcoming_page = $_POST["upcoming_page"];
    
    if ($api_string != get_option("eventification_api"))
    {
      update_option('eventification_last_request', 0);
      update_option('eventification_api', $api_string);
    }
    update_option('eventification_post_type', $post_type);
    update_option('eventification_upcoming_page', $upcoming_page);
    
    self::init();
  }
  
  function show_form () {
    ?>
    <form name="settingsform" method="post" action="<?php echo get_option('siteurl') . '/wp-admin/options-general.php?page=eventification/eventification.php' ?>">
      <input type="hidden" name="action" value="update_settings"/>
      <p>
      <input type="text" name="api_string" value="<?php echo self::$api_string ?>" />
      </p>
      
      <p>
        Page to list upcoming events:<br />
        <select name="upcoming_page">
        <?php
        $page_ids = get_all_page_ids();
        foreach ($page_ids as $page_id) {
          $page_id = intval($page_id);
          $page = get_post($page_id);
          echo "<option value=\"$page_id\"";
          echo $page_id == self::$upcoming_page ? " selected=\"true\"" : "";
          echo ">" . $page->post_title . "</option>\n";
        }
        //echo "<pre>" . print_r(get_all_page_ids(), true) . "</pre>";
        ?>
        </select>
      </p>
      <p>
        When a new event is detected, create a new:<br />
        <input type="radio" name="post_type" id="post_type_post" value="post"<?php if (self::$post_type=="post") { echo " checked=\"true\""; } ?> /> <label for="post_type_post">Post</label>
        <input type="radio" name="post_type" id="post_type_page" value="page"<?php if (self::$post_type=="page") { echo " checked=\"true\""; } ?> /> <label for="post_type_page">Page</label>
      </p>
      <p>
        <input type="submit" value="Save" />
      </p>
    </form>
    <?php
  }
  
  function api_string() {
    
  }
  
  function get_events() {
    if (!self::$init)
      self::init();
    
    $update_frequency = self::$update_frequency;
    $api_string = self::$api_string;
    $post_type = self::$post_type;
    
    $last_request = intval(get_option("eventification_last_request"));
    if ($last_request < time()-$update_frequency)
    {
      $url = "http://eventification.com/api/" . $api_string;
    
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
          //$title = self::make_event_url($event["name"].date("m-d-y", $event["starttime"]));
          $title = "Event: " . $event["name"] . " in " . $event["city"] . ", " . $event["state"] . " - " . date('l F j, Y g:ia', $event["starttime"]);
          
          if ($post_type == "page") {
            $post = get_page_by_title($title);
          } else {
            $post = get_post_by_title($title);
          }

          if (!$post) {
            
            $tags = array();
            foreach ($event["tags"] as $tag) {
              $tags[] = $tag["tag_text"];
            }
            // Create post object
            $_p = array();
            $_p['post_title'] = $title;
            $_p['post_content'] = "{eventification:".$event["event_id"]."}";
            $_p['post_status'] = 'publish';
            if ($post_type == "page") {
              $_p['post_type'] = 'page';
            } else {
              $_p['post_type'] = 'post';
            }
            $_p['post_category'] = array(1); // the default 'Uncategorised'
            $_p['tags_input'] = implode(", ", $tags);
            
            // Insert the post into the database
            $pid = wp_insert_post( $_p );
            
          } else {
            $pid = $post->ID;
          }
          update_post_meta($pid, "eventification", str_replace("\\", "\\\\", json_encode($event)));
        }
      }
    } else
    {
      // from cache
      $data = json_decode($file, true);
    }
    if (isset($data["events"]))
    {
      return $data["events"];
    } else
    {
      // check message
      return false;
    }
    return false;
  }
  
  function show_upcoming($content) {
    global $post;
    
    if (!self::$init)
      self::init();
    
    if (intval($post->ID) != intval(self::$upcoming_page))
      return $content;
    
    $events = json_decode(self::$upcoming_events, true);
    
    $event_str = "";
    
    foreach ($events as $event) {
      
      $title = "Event: " . $event["name"] . " in " . $event["city"] . ", " . $event["state"] . " - " . date('l F j, Y g:ia', $event["starttime"]);
      
      if (self::$post_type == "page") {
        $p = get_page_by_title($title);
      } else {
        $p = get_post_by_title($title);
      }
      
      $event_str .= "<p>";
      $event_str .= "<strong style=\"font-size: 120%;\"><a href=\"".$p->guid."\">" . $event["name"] . "</a></strong><br />";
      $event_str .= date("l F j, Y g:ia", $event["starttime"]) . "<br />";
      $short = strip_tags($event["description"]);
      if (strlen($event['description']) > 155 && strpos($event['description'], " ", 150) > 0)
      {
        $short = substr($short, 0, strpos($short, " ", 150)) . " ... <a href=\"".$p->guid."\" class=\"readMore\">[Read more]</a>";
      }
      $event_str .= $short . "<br />";
      $event_str .= "</p>\n";
    }
    if ($event_str == "") {
      $event_str = "<div>No upcoming events at this time.</div>";
    }
    //if ($content != "")
      //$event_str = "<h4>Upcoming Events</h4>" . $event_str;
    
    if (strpos($content, "{eventification:upcoming}") !== false) {
      $content = str_replace("{eventification:upcoming}", $event_str, $content);
    } else {
      $content .= $event_str;
    }
    
    return $content;
  }
  
  function eventify_post($content) {
    global $post;
    
    if (strpos($content, "{eventification:") !== false) {
      $event_str = get_post_meta($post->ID, 'eventification', true);
      if ($event_str != "") {
        $event = json_decode($event_str, true);
        
        $url = $event["url"] != "" ? $event["url"] : "http://eventification.com/" . $event["event_url"];
        
        $content = str_replace("{eventification:".$event["event_id"]."}", "<p>" . $event["description"] . "</p><p>" . $event["venue_info"] . "</p><p><a href=\"".$url."\">Event home page</a> / <a href=\"http://eventification.com".$event["event_url"]."\">Eventification page</a></p><p><a href=\"http://eventification.com/tag/view/".$event["tags"][0]["tag_code"]."\" title=\"".$event["tags"][0]["tag_text"]." events in ".$event["city"]."\">Discover more ".$event["tags"][0]["tag_text"]." events in ".$event["city"]." using Eventification!</a></p>", $content);
      }
    }
    
    return $content;
  }
  
  function make_event_url($str) {
    $str = strtolower($str);
    $str = preg_replace("/[^a-z^0-9]/g","-",$str);
    $str = preg_replace("/\-\-/g","-",$str);
    
    return $str;
  }
  
  function check_cron() {
  	if ( !wp_next_scheduled( 'eventification_cron' ) ) {
  		wp_schedule_event(time(), 'hourly', 'eventification_cron');
  	}
  }
}

add_action('wp', array('eventification', 'check_cron'));
add_action('admin_menu', array('eventification', 'init'));
add_action('eventification_cron', array('eventification', 'get_events'));

add_filter('the_content', array('eventification', 'eventify_post'));
add_filter('the_content', array('eventification', 'show_upcoming'));


function update_eventification() {
	// do something every hour
}

/**
 * Retrieve a page given its title.
 *
 * @since 2.1.0
 * @uses $wpdb
 *
 * @param string $page_title Page title
 * @param string $output Optional. Output type. OBJECT, ARRAY_N, or ARRAY_A.
 * @return mixed
 */
if (!function_exists("get_post_by_title")) {
function get_post_by_title($post_title, $output = OBJECT) {
	global $wpdb;
	$post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type='post'", $post_title ));
	if ( $post )
		return get_post($post, $output);

	return null;
}
}


?>