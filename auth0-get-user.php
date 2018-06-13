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

function get_user_object_for_auth0($query) {
  $user = get_user_by('email', $query['email']);
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
      'email' => array(
        'required' => true,
	'type' => 'string',
	'decription' => 'The user\'s email address',
	'format' => 'email'
      )
    )
  ) );
} );
