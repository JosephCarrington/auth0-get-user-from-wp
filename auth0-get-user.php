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
  require( plugin_dir_path( __FILE__ ) . 'env.php' );
  try{
    $verifier = new JWTVerifier([
	  'supported_algs' => ['RS256'],
      'valid_audiences' => $env['valid_audiences'],
      'authorized_iss' => $env['authorized_iss'],
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


function create_user_for_auth0($query) {
  require( plugin_dir_path( __FILE__ ) . 'env.php' );
  try{
    $verifier = new JWTVerifier([
	  'supported_algs' => ['RS256'],
      'valid_audiences' => $env['valid_audiences'],
      'authorized_iss' => $env['authorized_iss'],
	  'cache' => new FileSystemCacheHandler()
    ]);
    $tokenInfo = $verifier->verifyAndDecode($query['token']);
	// TODO: Check exipration = $tokenInfo->exp

  }
  catch(\Auth0\SDK\Exception\CoreException $e) {
    return var_dump($e);
  }
  $emailToCreate = $query['email_to_create'];
  if(username_exists( $emailToCreate ) ) {
  	// We just checked this before we even got here, so this shouldn't really be possible
	slack_log($emailToCreate . ' didn\'t exist a second ago, but does now. WTF');
	die;
  }

  $password = wp_generate_password( 12, true );
  $user_id = wp_create_user( $emailToCreate, $password, $emailToCreate );
  update_user_meta( $user_id, 'first_name', $query['first_name'] );
  update_user_meta( $user_id, 'last_name', $query['last_name'] );
  return get_user_by_email( $emailToCreate );


};

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

  register_rest_route('mw_auth0/v1', '/user_create/', array(
  		'methods' => 'POST',
		'callback' => 'create_user_for_auth0',
		'args' => array(
			'token' => array(
				'required' => true,
				'type' => 'string',
				'desription' => 'Auth0\'s JWT'
			)
		)
	));
} );


