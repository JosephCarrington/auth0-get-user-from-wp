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
  if(!$auth0_id) return;

  $auth0_user = array(
    'user_id' => $user->ID,
    'username' => $user->data->user_login,
    'nickname' => $user->data->user_nicename,
    'email' => $user->data->user_email,
    'display_name' => $user->data->display_name,
    'nicename' => $user->data->user_nicename,
    'first_name' => null,
    'last_name' => null,
    'given_name' => null,
    'family_name' => null
  );
  return $auth0_user;
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
} );
