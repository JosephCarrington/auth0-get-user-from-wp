<?php
/**
 * Plugin Name: Auth0 Get User JSON Endpoint
 * Plugin URI: https://meowwolf.com/
 * Description: Auth0 needs to be able to get a user by it's username from WordPress. This plugin provides an endpoint to do so.
 * Author: Joseph Carrington
 * Version: 0.1
 * Author URI: https://meowwolf.com
 * Text Domain: meow-wolf-auth0
 * License: GPLv3
 *
 */
require_once 'vendor/autoload.php';
use Auth0\SDK\JWTVerifier;
use Auth0\SDK\Helpers\Cache\FileSystemCacheHandler;
function get_user_object_for_auth0($query) {
  try{
    $verifier = new JWTVerifier([
	  'supported_algs' => ['RS256'],
      'valid_audiences' => AUTHVAR['valid_audiences'],
      'authorized_iss' => AUTHVAR['authorized_iss'],
	  'cache' => new FileSystemCacheHandler()
    ]);
    $tokenInfo = $verifier->verifyAndDecode($query['token']);
	// TODO: Check exipration = $tokenInfo->exp

  }
  catch(\Auth0\SDK\Exception\CoreException $e) {
    return var_dump($e);
  }
  $emailToFind = $query['email_to_find'];

  $user = get_user_by('email', $emailToFind);
  if(!$user) return;
  global $wpdb;

  $auth0_id = get_user_meta( $user->ID, $wpdb->prefix.'auth0_id', true);
  if(!$auth0_id) {
      $sites = wp_get_sites();
      foreach($sites as $i => $site) {
        if(!$auth0_id) {
            $site_id = $site['blog_id'];
            $auth0_id = get_user_meta( $user->ID, $wpdb->prefix . $site_id . '_auth0_id', true );
        }
      }
  }

  if(!$auth0_id) return;

  $user_meta = get_user_meta($user->ID);

  $auth0_user = array(
    'user_id' => $user->ID,
    'username' => $user->data->user_login,
    'nickname' => $user->data->user_nicename,
    'email' => $user->data->user_email,
    'display_name' => $user->data->display_name,
    'nicename' => $user->data->user_nicename,
    'first_name' => $user_meta['first_name'][0],
    'last_name' => $user_meta['last_name'][0],
    'given_name' => $user_meta['first_name'][0],
    'family_name' => $user_meta['last_name'][0]
  );
  return $auth0_user;
};

function verify_token($token) {
	$verifier = new JWTVerifier([
	  'supported_algs' => ['RS256'],
	  'valid_audiences' => AUTHVAR['valid_audiences'],
	  'authorized_iss' => AUTHVAR['authorized_iss'],
	  'cache' => new FileSystemCacheHandler()
	]);
	return $verifier->verifyAndDecode($token);
}

function upsert_user_for_auth0($query) {
	// Safety first
	verify_token($query['token']);

	// We fuck around with Auth0 functions that nobody undetstands
  	$a0_options = WP_Auth0_Options::Instance();
  	$user_repo = new WP_Auth0_UsersRepo( $a0_options );

	$user_object = $query['user'];
	$user_first_name = $user_object['user_metadata']['first_name'] ?: $user_object['given_name'];
	$user_last_name = $user_object['user_metadata']['last_name'] ?: $user_object['family_name'];
	// Does this user already exist?
    // TODO: CHANGE THIS WHEN YOU GO LIVE DANGIT, AND ALSO ADD TO SANTAFE!!!!!!!!!
    $shopid = 3;
	$existing_user = get_user_by( 'email', $user_object['email'] );
	if( $existing_user ) {
		// A user with this email already exists, so we set the Auth0 data to whatever Auth0 sent along
		  $user_repo->update_auth0_object( $existing_user->ID, (object)$user_object );
		  add_user_to_blog( $shopid, $existing_user->ID, 'customer' );
	} else {
		// No user with this email exists, so we create them and run Auth0's mystery functions
		$password = wp_generate_password( 12, true );
		$user_id = wp_create_user( $user_object['email'], $password, $user_object['email'] );
		add_user_to_blog( $shopid, $user_id, 'customer' );
		$user_repo->update_auth0_object( $user_id, (object)$user_object );
	}

	// Get the existing or newly created user
	$wp_user = get_user_by_email( $user_object['email'] );
	// We have certain variables we want to overwrite if the user has just signed up
  	if($user_first_name) update_user_meta( $wp_user->ID, 'first_name', $user_first_name );
  	if($user_last_name) update_user_meta( $wp_user->ID, 'last_name', $user_last_name );

	// We want to send some of the user meta back to Auth0
	$user_meta = get_user_meta($wp_user->ID);
	$wp_user->meta = $user_meta;
	return $wp_user;
}



add_action( 'rest_api_init', function() {

  register_rest_route('mw_auth0/v1', '/user/', array(
    'methods' => 'POST',
    'callback' => 'get_user_object_for_auth0',
    'args' => array(
      'token' => array(
        'required' => true,
		'type' => 'string',
		'decription' => 'Auth0\'s JWT',
      )
    )
  ) );
 

  register_rest_route('mw_auth0/v1', '/upsert_user/', array(
  		'methods' => 'POST',
		'callback' => 'upsert_user_for_auth0',
		'args' => array(
			'token' => array(
				'required' => true,
				'type' => 'string',
				'desription' => 'Auth0\'s JWT'
			)
		)
	));
} );


function slack_log($var) {
	$string_val = json_encode($var, JSON_PRETTY_PRINT);
	if(strlen($string_val) > 1000) {
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://hooks.slack.com/services/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');
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('text' => '```' . $string_val . '```')));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_exec($ch);
	curl_close($ch);
}
