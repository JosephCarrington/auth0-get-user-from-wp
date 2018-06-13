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

function get_user_object_for_auth0($email) {
  $user = get_user_by('carrington@gmail.com');
  return $user;
};

add_action( 'rest_api_init', function() {
  register_rest_route('mw_auth0/v2', '/user/(?P<email>\d+)', array(
    'methods' => 'GET',
    'callback' => 'get_user_object_for_auth0'
  ) );
} );
