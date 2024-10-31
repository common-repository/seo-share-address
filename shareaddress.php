<?php
/*
Plugin Name: SEO ShareAddress
Plugin URI: http://shareaddress.com/
Description:Share your POST to other WP site, Analyze your post content and Auto Exchange Link with Related POST.  
Version: 0.3.0
Author: Shareaddress
Author URI: http://shareaddress.com
License: GPLv3
*/
$sad_api_key = '';
function sad_init(){
	global $sad_api_key, $sad_api_host, $sad_api_port;

		$sad_api_host = 'shareaddress.com';

	$sad_api_port = 80;
	add_action('admin_menu', 'sad_config_page');
}
add_action('init', 'sad_init');

if ( !function_exists('wp_nonce_field') ) {
	function sad_nonce_field($action = -1) { return; }
	$sad_nonce = -1;
} else {
	function sad_nonce_field($action = -1) { return wp_nonce_field($action); }
	$sad_nonce = 'sad-update-key';
}

if ( !function_exists('number_format_i18n') ) {
	function number_format_i18n( $number, $decimals = null ) { return number_format( $number, $decimals ); }
}

function sad_config_page() {
	if ( function_exists('add_submenu_page') )
		add_submenu_page('plugins.php', 'Shareeadress Configuration', 'Shareaddress Configuration', 'manage_options', 'sad-key-config', 'sad_conf');

}

function sad_get_version(){
	return '0.3.0';
}

function sad_conf() {
	global $sad_nonce, $sad_api_key;

	if ( isset($_POST['submit']) ) {
		if ( function_exists('current_user_can') && !current_user_can('manage_options') )
			die(__('Cheatin&#8217; uh?'));

		check_admin_referer( $sad_nonce );
		$key = preg_replace( '/[^a-h0-9]/i', '', $_POST['key'] );

		if ( empty($key) ) {
			$key_status = 'empty';
			$ms[] = 'new_key_empty';
			delete_option('sad_api_key');
		} else {
			$key_status = sad_verify_key( $key );
		}

		if ( $key_status == 'valid' ) {
			update_option('sad_api_key', $key);
			$ms[] = 'new_key_valid';
		} else if ( $key_status == 'invalid' ) {
			$ms[] = 'new_key_invalid';
		} else if ( $key_status == 'failed' ) {
			$ms[] = 'new_key_failed';
		}

		if ( isset( $_POST['sad_website_link'] ) )
			update_option( 'sad_website_link', 'true' );
		else
			update_option( 'sad_website_link', 'false' );
		}

	if ( $key_status != 'valid' ) {
		$key = get_option('sad_api_key');
		if ( empty( $key ) ) {
			if ( $key_status != 'failed' ) {
				if ( sad_verify_key( '1234567890ab' ) == 'failed' )
					$ms[] = 'no_connection';
				else
					$ms[] = 'key_empty';
			}
			$key_status = 'empty';
		} else {
			$key_status = sad_verify_key( $key );
		}
		if ( $key_status == 'valid' ) {
			$ms[] = 'key_valid';
		} else if ( $key_status == 'invalid' ) {
			delete_option('sad_api_key');
			$ms[] = 'key_empty';
		} else if ( !empty($key) && $key_status == 'failed' ) {
			$ms[] = 'key_failed';
		}
	}

	$messages = array(
		'new_key_empty' => array('color' => 'aa0', 'text' => __('Your key has been cleared.')),
		'new_key_valid' => array('color' => '2d2', 'text' => __('Your key has been verified. Happy blogging!')),
		'new_key_invalid' => array('color' => 'd22', 'text' => __('The key you entered is invalid. Please double-check it.')),
		'new_key_failed' => array('color' => 'd22', 'text' => __('The key you entered could not be verified because a connection to shareaddress.com could not be established. Please check your server configuration.')),
		'no_connection' => array('color' => 'd22', 'text' => __('There was a problem connecting to the ShareAddress server. Please check your server configuration.')),
		'key_empty' => array('color' => 'aa0', 'text' => sprintf(__('Please enter an API key. (<a href="%s" style="color:#fff">Get your key.</a>)'), 'http://shareaddress.com/')),
		'key_valid' => array('color' => '2d2', 'text' => __('This key is valid.')),
		'key_failed' => array('color' => 'aa0', 'text' => __('The key below was previously validated but a connection to shareaddress.com can not be established at this time. Please check your server configuration.')));
?>
<?php if ( !empty($_POST ) ) : ?>
<div id="message" class="updated fade"><p><strong><?php _e('Options saved.') ?></strong></p></div>
<?php endif; ?>
<div class="wrap">
<h2><?php _e('ShareAddress Configuration'); ?></h2>
<div class="narrow">
<form action="" method="post" id="sad-conf" style="margin: auto; width: 500px; ">
<?php if ( !$sad_api_key ) { ?>
	<p><?php printf(__('<a href="http://shareaddress.com/">ShareAddress</a> will help you build inter-POST backlink on your site. If one does happen to get through, If you don\'t have a Shareaddress.com account yet, you can get one at <a href="http://shareaddress.com">ShareAddress.com</a>.'), 'http://shareaddress.com/', 'http://shareaddress.com/'); ?></p>

<?php sad_nonce_field($sad_nonce) ?>
<h3><label for="key"><?php _e('Shareaddress.com API Key'); ?></label></h3>
<?php foreach ( $ms as $m ) : ?>
	<p style="padding: .5em; background-color: #<?php echo $messages[$m]['color']; ?>; color: #fff; font-weight: bold;"><?php echo $messages[$m]['text']; ?></p>
<?php endforeach; ?>
<p><input id="key" name="key" type="text" size="32" maxlength="32" value="<?php echo get_option('sad_api_key'); ?>" style="font-family: 'Courier New', Courier, mono; font-size: 1.5em;" /></p>
<?php if ( $invalid_key ) { ?>
<h3><?php _e('Why might my key be invalid?'); ?></h3>
<p><?php _e('This can mean one of two things, either you copied the key wrong or that the plugin is unable to reach the Shareaddress servers, which is most often caused by an issue with your web host around firewalls or similar.'); ?></p>
<?php } ?>
<?php } ?>
	<p class="submit"><input type="submit" name="submit" value="<?php _e('Update options &raquo;'); ?>" /></p>
</form>
</div>
</div>
<?php
}

function sad_verify_key( $key ) {
	global $sad_api_host, $sad_api_port, $sad_api_key;
	$blog = urlencode( get_option('home') );
	if ( $sad_api_key )
		$key = $sad_api_key;
	$response = sad_http_post("sad_key=$key&blog=$blog", $sad_api_host, '/0.1/key-valid.php', $sad_api_port);

	if ( !is_array($response) || !isset($response[1]) || $response[1] != 'valid' && $response[1] != 'invalid' && $response[1] != 'Running' )
		return 'failed';
	return $response[1];
}

if ( !get_option('sad_api_key') && !$sad_api_key && !isset($_POST['submit']) ) {
	function sad_warning() {
		echo "
		<div id='sad-warning' class='updated fade'><p><strong>".__('ShareAddress is almost ready.')."</strong> ".sprintf(__('You must <a href="%1$s">enter your Free ShareAddress.com API key</a> for it to work.'), "plugins.php?page=sad-key-config")."</p></div>
		";
	}
	add_action('admin_notices', 'sad_warning');
	return;
}

// Returns array with headers in $response[0] and body in $response[1]
function sad_http_post($request, $host, $path, $port = 80) {
	global $wp_version;

	$http_request  = "POST $path HTTP/1.0\r\n";
	$http_request .= "Host: $host\r\n";
	$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=" . get_option('blog_charset') . "\r\n";
	$http_request .= "Content-Length: " . strlen($request) . "\r\n";
	$http_request .= "User-Agent: WordPress/$wp_version | Sad/0.1\r\n";
	$http_request .= "\r\n";
	$http_request .= $request;

	$response = '';
	if( false != ( $fs = @fsockopen($host, $port, $errno, $errstr, 10) ) ) {
		fwrite($fs, $http_request);

		while ( !feof($fs) )
			$response .= fgets($fs, 1160);
		fclose($fs);
		$response = explode("\r\n\r\n", $response, 2);
	}
	return $response;
}


function sad_submit_post ( $post_id='' ) {
	global $wpdb, $sad_api_host, $sad_api_port;
	$post_id = (int) $post_id;

	$mypost = get_post($mypost_id);
	if ( !$mypost ) // it was deleted
		return;
	$mypost->blog = get_option('home');
	$mypost->sad_key=get_option('sad_api_key');
	$mypost->guid=get_permalink($post->ID);
	if (!empty($mypost->sad_key)){

	$query_string = '';
	foreach ( $mypost as $key => $data )
		$query_string .= $key . '=' . urlencode( stripslashes($data) ) . '&';
	$response = sad_http_post($query_string, $sad_api_host, "/0.1/keywordgen.php", $sad_api_port);
	
	if ( !is_array($response) || !isset($response[1])){
	return "failed";
}else{
	update_post_meta($post_id,'_sad_linked','1');
	return $response[1];
	}	
}
return;
}

function sad_start_link($post_id=''){
	update_post_meta($post_id,'_sad_linked','0');
	}

function sad_related_link_auto($content=""){
	if ( !is_single()) return $content;
	$sad_api_key=get_option('sad_api_key');
	if (!empty($sad_api_key)){
	$output = sad_get_related_links();
	$content = $content . $output;
	}
	return $content;
}

function sad_related_posts_for_feed($content=""){
	if ( ! is_feed()) return $content;
	$sad_api_key=get_option('sad_api_key');
	if (!empty($sad_api_key)){
		$output = sad_get_related_links();
		$content = $content . $output;
	}
	return $content;
}

add_filter('the_content', 'sad_related_posts_for_feed',1);

function  sad_get_related_links(){
	global $wpdb, $post,$table_prefix,$sad_api_key, $sad_api_host, $sad_api_port;
	$postmeta=get_post_meta($post->ID,'_sad_linked',true);
	if ($postmeta=='1'){
		$mypostid=$post->ID;
		$mypostguid=get_permalink($post->ID);
		$response = sad_http_post("sad_key=$sad_api_key&guid=$mypostguid&id=$mypostid", $sad_api_host, '/0.1/getrlink.php', $sad_api_port);
		if ( !is_array($response) || !isset($response[1]))
		return;
		if (strstr($response[1],'nolinked')!= FALSE){
			update_post_meta($post_id,'_sad_linked','0');
		}
		return sad_get_links_output($response[1]);	
		
	}else{
		$response=sad_submit_post ( $post->ID );
		if ($response!='failed')
		return sad_get_links_output($response);	
		return;
	}
	
	}

function sad_get_links_output($sad_xml=''){
	if (empty($sad_xml)){
		return;
		}
preg_match_all( "/\<keyword\>(.*?)\<\/keyword\>/s", $sad_xml, $tagblocks );
  foreach( $tagblocks[1] as $block )
  {
  preg_match_all( "/\<title\>(.*?)\<\/title\>/", $block, $sadtitle );
  preg_match_all( "/\<link\>(.*?)\<\/link\>/", $block, $sadlink );
  $output .= '<li>';
  $output .=  '<a href="'.$sadlink[1][0].'" title="'.wptexturize($sadtitle[1][0]).'" target="_blank">'.wptexturize($sadtitle[1][0]).'';
	$output .=  '</a></li>';
	}
	
	$output='<ul class="shared_post">' . $output . '</ul>';
 	$output =  '<h3>Shared Post</h3>'. $output;		
return $output;
}


add_filter('the_content', 'sad_related_link_auto',99);
add_action('publish_post', 'sad_start_link');
?>