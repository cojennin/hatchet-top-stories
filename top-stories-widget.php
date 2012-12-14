<?php
 /* Plugin Name: Top Stories Widget
    Plugin URI: http://www.gwhatchet.com
    Description: This widget grabs the top posts from the last issue
    Author: Connor Jennings
    Version: 0.1
 */

 require_once 'src/apiClient.php';
 require_once 'src/contrib/apiAnalyticsService.php';

wp_register_style('top_stories_stylesheet', plugin_dir_url(__FILE__).'top_stories_stylesheet.css');
wp_enqueue_style( 'top_stories_stylesheet');

class TopStoriesWidget extends WP_Widget {

 protected $client;
 protected $service;
 
 function TopStoriesWidget(){
  $widget_ops = array('classname' => 'TopStoriesWidget', 'description' => 'Grabs top stories from Analytics for current issue');
  $this->WP_Widget('TopStoriesWidget', 'Hatchet Top Stories', $widget_ops);
 }

 function form($instance){
  ?> This widget will display the top 10 stories from www.gwhatchet.com <?php
  $this->client = new apiClient();
  $this->client->setApplicationName("Google Analytics");
  // Visit https://code.google.com/apis/console?api=analytics to generate your
  // client id, client secret, and to register your redirect uri.
  $this->client->setClientId('');
  $this->client->setClientSecret('');
  $this->client->setRedirectUri('');

  $this->service = new apiAnalyticsService($this->client);

  if(isset($_GET['code'])){
   try{
	    $this->client->authenticate();
        update_option('analytics_token_new', $this->client->getAccessToken(), '', 'no');
      }
   catch(apiException $e){
    echo "Could not authenticate";
   }
  }

  $token_to_use = get_option('analytics_token_new');
  if(!empty($token_to_use)){
   $this->client->setAccessToken($token_to_use);
  } 
  if($this->client->getAccessToken()){
   try {
   ?> Connection good. <?php
   } catch (apiException $e) {
    ?> An error has occured. Please email web@gwhatchet.com. <?php
   }
  } else {
     $authUrl = $this->client->createAuthUrl();
     print "<a class='login' href='$authUrl'>Connect Me!</a>";
    } 
}

 function widget($args, $instance){
  //Should change this to Memcached.
   $memcache_object = new Memcache;
   //if($memcache_object->connect('127.0.0.1', 11211)){
   $memcache_object->connect('127.0.0.1', 11211);
   if($memcache_object->get('top_stories_cache') === false){
		  extract($args, EXTR_SKIP);
	  $this->client = new apiClient();
	  $this->client->setApplicationName("");
	  $this->client->setClientId('');
	  $this->client->setClientSecret('');
	  $this->client->setRedirectUri('');
	  $this->service = new apiAnalyticsService($this->client);
	  echo $before_widget;
	  ob_start();
	  echo '<div id="top-stories-box">';
	  echo '<p id="top-stories-header">Most Popular</p>';
	  echo '<ul id="top-stories-list">';
	  $token_to_use = get_option('analytics_token_new', false);
	  if(!empty($token_to_use)){
	   $this->client->setAccessToken($token_to_use);
	  }

	  if($this->client->getAccessToken()){
	   $count = 1;
           $pass_through = 1;
	   while($count < 6) {
		$most_recent_term = get_terms('issue', array('number' => $pass_through, 'order' => 'DESC'));	
		$most_recent_term = str_replace('-', '/', $most_recent_term[$pass_through-1]->name);
		try { $all_results = $this->service->data_ga->get('ga:' . 'ga_goes_here', date('Y-m-d', time()-18140000), date('Y-m-d'),'ga:pageviews', array('dimensions' => 'ga:pagePath', 'max-results'=>100, 'filters'=>'ga:pagePath=@'.$most_recent_term.';ga:pagePath!@preview=true;ga:hostname!=blogs.gwhatchet.com;ga:hostname!=projects.gwhatchet.com;ga:hostname!=media.gwhatchet.com;ga:hostname!=archives.gwhatchet.com;ga:pagePath!@?', 'sort' => '-ga:pageviews'));
	   } catch (apiException $e) {
	    ?> An error has occured. Please try again later. <?php
	   }
	  if(is_array($all_results['rows'])){
	  	foreach($all_results['rows'] as $story){
	    		if($story[0] != 'www.gwhatchet.com' && $count < 6){
				$url_slug = str_replace('/', '', preg_replace('/www\.gwhatchet\.com\/\d{4}\/\d{2}\/\d{2}\//', '', $story[0]));
				$slug_args = array(
					'name' => $url_slug,
					'post_type' => 'post',
					'post_status' => 'publish',
					'numberposts' => 1
				);
				$single_top_post = get_posts($slug_args);
				//Get outta here if we can't find anything
				if($single_top_post == NULL)
					break;
				$single_top_post = $single_top_post[0]; 
				echo '<li class="single-top-post"><span class="single-top-count">'.$count.'.</span> <a class="top-stories-link" href="'.get_permalink($single_top_post->ID).'">'.$single_top_post->post_title.'</a></li>';
	    
		 		$count++;
	    		} else{
     				break;
    			}
  		}
	}
    $pass_through++;
   }
  } else {
   ?> An error has occured. Please try again later. <?php
  }
  echo '</ul>';
  echo '</div>';
  $analytics_to_store = ob_get_contents();
  if(!$memcache_object->replace('top_stories_cache', $analytics_to_store, 0, 300)){
    $memcache_object->set('top_stories_cache', $analytics_to_store, 0, 300);
  }
  ob_end_clean();
  } else {
  echo $memcache_object->get('top_stories_cache');
  }
  echo $after_widget;
 }
 
}

function register_top_stories(){
 register_widget('TopStoriesWidget');
}

add_action('widgets_init', 'register_top_stories');

?>
