<?php
/*
Plugin Name: th23 Subscribe
Description: Subscribe for email notifactions upon new posts and comments. Easy way keep registered users and visitors informed about latest updates.
Version: 3.2.0
Author: Thorsten Hartmann (th23)
Author URI: http://th23.net/
Text Domain: th23-subscribe
Domain Path: /lang
License: GPLv2 only
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Copyright 2012-2020, Thorsten Hartmann (th23)
http://th23.net/

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2, as published by the Free Software Foundation. You may NOT assume that you can use any other version of the GPL.
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
This license and terms apply for the Basic part of this program as distributed, but NOT for the separately distributed Professional add-on!
*/

// Security - exit if accessed directly
if(!defined('ABSPATH')) {
    exit;
}

class th23_subscribe {

	// Initialize class-wide variables
	public $plugin = array(); // plugin (setup) information
	public $options = array(); // plugin options (user defined, changable)
	public $data = array(); // data exchange between plugin functions

	function __construct() {

		// Setup basics
		$this->plugin['file'] = __FILE__;
		$this->plugin['basename'] = plugin_basename($this->plugin['file']);
		$this->plugin['dir_url'] = plugin_dir_url($this->plugin['file']);
		$this->plugin['version'] = '3.2.0';

		// Load plugin options
		$this->options = (array) get_option('th23_subscribe_options');

		// Localization
		load_plugin_textdomain('th23-subscribe', false, dirname($this->plugin['basename']) . '/lang');

		// == customization: from here on plugin specific ==

		// Gather plugin related parameters and remove them from request URI - should not be part of URLs generated
		$gets = array('subscribe', 'unsubscribe', 'validation', 'viewsubscription');
		$this->data['gets'] = array();
		foreach($gets as $get) {
			if(isset($_GET[$get])) {
				$this->data['gets'][$get] = sanitize_text_field($_GET[$get]);
				unset($_GET[$get]);
			}
		}
		$_SERVER['REQUEST_URI'] = remove_query_arg($gets);

		// Trigger link initiated actions (eg subscribe, unsubscribe, ...)
		add_action('init', array(&$this, 'trigger_actions'));

		// Load CSS for plugin
		add_action('template_redirect', array(&$this, 'load_css'));

		// Provide option to subscribe upon user registration
		add_action('register_form', array(&$this, 'user_register_form')); // extend registration form
		add_filter('th23_user_management_register_options_html', array(&$this, 'user_register_form_html'), 15); // th23 User Management plugin provides special filter to hook in frontend page
		add_action('user_register', array(&$this, 'user_register_add_subscribtions')); // execute subscription

		// Provide option to subscribe upon commenting
		add_filter('comment_form_submit_button', array(&$this, 'comment_form')); // extend comment form
		add_action('wp_insert_comment', array(&$this, 'comment_subscribtions'), 10, 2); // execute (un-)subscription

		// Add link for subscription management page
		add_action('wp_meta', array(&$this, 'add_manage_subscriptions_link'), 10, 2);
		add_action('th23_user_management_widget_profile_link', array(&$this, 'add_manage_subscriptions_link'), 10, 2); // th23 User Management widget has a special hook to display this next to other user management actions

		// Show subscription management page
		// Note: For standard handling via admin user page see th23-subscribe-admin.php
		add_action('th23_user_management_prepare_output', array(&$this, 'manage_subscriptions_prepare_output')); // th23 User Management plugin provides special hook to link into frontend page

		// Execute changes of subscriptions
		// Note: For standard handling via admin user page see th23-subscribe-admin.php
		add_action('th23_user_management_do_normal', array(&$this, 'manage_subscriptions_do_normal')); // th23 User Management plugin provides special hook to link into frontend actions

		// Add subscription option to th23 Social plugin services
    	add_filter('th23_social_services', array(&$this, 'add_subscribe_th23_social'));

		// Let's do the magic - get ready for sending the notifications (new posts)
		add_filter('th23_subscribe_notification_content', array(&$this, 'plain_content')); // default: removing all tags from notification content to send as plain text mail
		add_action('transition_post_status', array(&$this, 'post_notification'), 10, 3); // notify about new post
		add_action('comment_post', array(&$this, 'comment_notification_direct'), 10, 2); // notify about new comment (directly approved)
		add_action('transition_comment_status', array(&$this, 'comment_notification'), 10, 3); // notify about new comment (after moderation/ approval)
		add_action('batch_notifications', array(&$this, 'send_notifications')); // own cron hook for batch sending messages

	}

	// Ensure PHP <5 compatibility
	function th23_subscribe() {
		self::__construct();
	}

	// Error logging
	function log($msg) {
		if(!empty(WP_DEBUG) && !empty(WP_DEBUG_LOG)) {
			if(empty($this->plugin['data'])) {
				$plugin_data = get_file_data($this->plugin['file'], array('Name' => 'Plugin Name'));
				$plugin_name = $plugin_data['Name'];
			}
			else {
				$plugin_name = $this->plugin['data']['Name'];
			}
			error_log($plugin_name . ': ' . print_r($msg, true));
		}
	}

	// == customization: from here on plugin specific ==

	// Add connector to URL
	// note: ensure "/" before "?" due to some mail programs / browsers otherwise breaking line and not recognize a URL
	function add_connector($url) {
		if(strpos($url, '?') !== false) {
			return $url . '&';
		}
		elseif(substr($url, -1) == '/') {
			return $url . '?';
		}
		else {
			return $url . '/?';
		}
	}

	// Get current URL
	function get_current_url() {
		$current_url = (is_ssl() ? 'https' : 'http').'://';
		$current_url .= ($_SERVER['SERVER_PORT'] != '80' && $_SERVER['SERVER_PORT'] != '443') ? $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'] : $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		if(strpos(get_option('home'), '://www.') === false) {
			$current_url = str_replace('://www.', '://', $current_url);
		}
		else {
			if(strpos($current_url, '://www.') === false) {
				$current_url = str_replace('://', '://www.', $current_url);
			}
		}
		return $current_url;
	}

	// Generate validation key based on content (action + item) parameter - unique for user due to ID (public) and (part of) the hashed user password, but also linked to site via wp_salt()
	// note: link to site allows invalidating links sent in the past by changing the WP salt centrally and without need to change user passwords individually
	function get_validation_key($content = '', $user) {
		if(empty($user)) {
			return '';
		}
		return 'u' . $user->ID . 'k' . substr(md5($content . $user->ID . substr($user->user_pass, -10) . substr(wp_salt(), -20)), -10);
	}

	// Check validation key based on content (action + item) parameter - for user enclosed in validation key
	// note: returns user ID validated for valid key, otherwise false (if user does not exist or key is invalid)
	function check_validation_key($content = '', $user_key) {

		// no user_key passed in "validation" / "viewsubscription" parameter
		if(empty($user_key)) {
			return false;
		}

		// separate user ID from key
		$key_start = (int) strpos($user_key, 'k');
		if($key_start < 2) {
			return false;
		}
		$user_id = (int) substr($user_key, 1, $key_start - 1);
		$key = (string) substr($user_key, $key_start + 1);

		// check for user with given ID
		$user = get_userdata($user_id);
		if(empty($user)) {
			return false;
		}

		// check key matching content
		if(substr(md5($content . $user->ID . substr($user->user_pass, -10) . substr(wp_salt(), -20)), -10) !== $key) {
			return false;
		}
		return $user->ID;

	}

	// Retrieve user subscriptions in an array
	function get_subscriptions($user_id = 0, $item = '') {
		global $wpdb;
		if(empty($user_id)) {
			$current_user = wp_get_current_user();
			$user_id = $current_user->ID;
		}
		$sql = $wpdb->prepare('SELECT item FROM ' . $wpdb->prefix . 'th23_subscribe_subscriptions WHERE user_id = %d', $user_id);
		if(!empty($item)) {
			$sql .= $wpdb->prepare(' AND item LIKE %s', $item . '%');
		}
		return $wpdb->get_results($sql, OBJECT_K);
	}

	// Clean up subscription and notification table in case a user, post or comment is deleted
	// (note: hooked in admin php, but used in frontend as well)
	function delete_user_clean_up($user_id) {
		global $wpdb;
		$wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_subscriptions WHERE user_id = %d', $user_id));
		$wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_notifications WHERE user_id = %d', $user_id));
	}
	function delete_post_clean_up($post_id) {
		global $wpdb;
		$wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_subscriptions WHERE item = %s', 'po' . $post_id));
		$wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_notifications WHERE item = %s OR content = %s', 'po' . $post_id, 'po' . $post_id));
	}
	function delete_comment_clean_up($comment_id) {
		global $wpdb;
		$wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_notifications WHERE content = %s', 'co' . $comment_id));
	}

	// Trigger link initiated actions (eg subscribe, unsubscribe, ...)
	function trigger_actions() {

		$this->data['action'] = '';
		if(isset($this->data['gets']['subscribe'])) {
			$this->data['action'] = 'subscribe';
			$item_type_id = $this->data['gets']['subscribe'];
		}
		elseif(isset($this->data['gets']['unsubscribe'])) {
			$this->data['action'] = 'unsubscribe';
			$item_type_id = $this->data['gets']['unsubscribe'];
		}
		elseif(isset($this->data['gets']['viewsubscription'])) {
			$this->data['action'] = 'viewsubscription';
			$this->data['user_key'] = $this->data['gets']['viewsubscription'];
		}
		if(isset($this->data['action']) && ('subscribe' == $this->data['action'] || 'unsubscribe' == $this->data['action'])) {
			if(isset($this->data['gets']['validation'])) {
				$this->data['user_key'] = $this->data['gets']['validation'];
			}
			if('global' == $item_type_id) {
				$this->data['item_type'] = 'global';
				$this->data['item_id'] = 0;
			}
			elseif('visitor' == $item_type_id) {
				$this->data['action'] = 'subscribe_visitor';
			}
			elseif('upgrade' == $item_type_id) {
				$this->data['action'] = 'upgrade_visitor';
			}
			else {
				$item_type = substr($item_type_id, 0, 2);
				if('po' == $item_type) {
					$this->data['item_type'] = 'po';
					$this->data['item_id'] = (int) substr($item_type_id, 2);
				}
				else {
					$this->data['item_type'] = '';
					$this->data['item_id'] = 0;
				}
			}
		}

		// Handle user request to subscribe (onsite via link, or via mail link)
		if('subscribe' == $this->data['action']) {
			$this->subscribe_link();
		}
		// Handle user request to unsubscribe (onsite via link, via mail link)
		elseif('unsubscribe' == $this->data['action']) {
			$this->unsubscribe_link();
		}
		// Handle user visit to site (via mail link)
		elseif('viewsubscription' == $this->data['action']) {
			$this->viewsubscription_link();
		}
		// Reset notification status when user visits page (logged in)
		else {
			$this->reset_subscription();
		}

	}

	// Subscribe link - via mail or on site
	// todo: consider error feedbacks for remaining "return" cases?
	function subscribe_link() {

		// check link for valid item type and id combination
		if(!empty($this->data['item_type']) && $this->data['item_type'] == 'global') {
			$item_type = 'global';
			$item_id = '';
		}
		elseif(!empty($this->data['item_type']) && $this->data['item_type'] == 'po' && !empty($this->data['item_id'])) {
			$item_type = 'po';
			$item_id = (int) $this->data['item_id'];
		}
		else {
			return;
		}

		// mail links always have a validation key attached - and should only act for the user specified in the link
		if(!empty($this->data['user_key'])) {
			$user_id = $this->check_validation_key('subscribe' . $item_type . $item_id, $this->data['user_key']);
			if(empty($user_id)) {
				$this->data['omsg'] = array(
					'msg_type' => 'error',
					'msg_title' => __('Subscription failed', 'th23-subscribe'),
					'msg_text' => __('Subscribe link used could not be validated - please try again, login to subscribe manually or contact an administrator', 'th23-subscribe')
				);
				add_action('template_redirect', array(&$this, 'overlay_message_html_js_css'));
				return;
			}
			$this->reset_subscription($user_id);
		}

		// on site link never have a validation key - and should only work for the current user
		if(is_user_logged_in()) {
			$current_user = wp_get_current_user();
			if(!empty($user_id) && $user_id != $current_user->ID) {
				$this->data['omsg'] = array(
					'msg_type' => 'error',
					'msg_title' => __('Subscription failed', 'th23-subscribe'),
					'msg_text' => __('Subscribe link used does not match currently logged in user - please log out and click the link again', 'th23-subscribe')
				);
				add_action('template_redirect', array(&$this, 'overlay_message_html_js_css'));
				return;
			}
			$user_id = $current_user->ID;
		}

		// no valid user given
		if(empty($user_id)) {
			return;
		}

		// todo: add hook eg for PRO visitor nickname updates - do_action('th23_subscribe_valid_subscription_link', $user_id, $item_type, $item_id, $this->data['user_key'])
		$this->add_subscription($item_id, $item_type, $user_id, true);

	}

	// Subscribe user
	// todo: consider error feedbacks for remaining "return" cases?
	function add_subscription($item_id, $item_type, $user_id, $notify) {

		// check for valid user - given id or current
		if(empty($user_id) && is_user_logged_in()) {
			$user = wp_get_current_user();
			$user_id = $user->ID;
		}
		if(empty($user_id) || !$user = get_userdata($user_id)) {
			return;
		}

		// check for valid item type, item id and permissions according to plugin options
		if(empty($item_type)) {
			return;
		}
		elseif($item_type == 'global') {
			if(empty($this->options['global_subscriptions']) || (in_array('th23_subscribe_visitor', $user->roles) && empty($this->options['visitors']))) {
				$this->data['omsg'] = array(
					'msg_type' => 'error',
					'msg_title' => __('Subscription failed', 'th23-subscribe'),
					'msg_text' => __('Global subscription is not available', 'th23-subscribe')
				);
				if($notify) {
					add_action('template_redirect', array(&$this, 'overlay_message_html_js_css'));
				}
				return;
			}
			$item_id = '';
			$msg_id = 'subscribe_success_global';
			$success_text = __('Thanks for your subscription, you will receive notifications upon new posts via mail', 'th23-subscribe');
		}
		elseif($item_type == 'po' && !empty($item_id)) {
			if(empty($this->options['comment_subscriptions']) || (in_array('th23_subscribe_visitor', $user->roles) && empty($this->options['visitors']))) {
				$this->data['omsg'] = array(
					'msg_type' => 'error',
					'msg_title' => __('Subscription failed', 'th23-subscribe'),
					'msg_text' => __('Subscription to replies and further comments is not available', 'th23-subscribe')
				);
				if($notify) {
					add_action('template_redirect', array(&$this, 'overlay_message_html_js_css'));
				}
				return;
			}
			if(!$post = get_post($item_id)) {
				return;
			}
			$item_id = $post->ID;
			$msg_id = 'subscribe_success_comment';
			$success_text = sprintf(__('Thanks for your subscription to the post "%s", you will receive notifications upon new comments via mail', 'th23-subscribe'), esc_html(wp_strip_all_tags($post->post_title)));
		}
		else {
			return;
		}

		global $wpdb;

		// check for something to change
		$sql = $wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'th23_subscribe_subscriptions WHERE item = %s AND user_id = %d', $item_type . $item_id, $user_id);
		if($subscription = $wpdb->get_row($sql)) {
			return;
		}

		// do subscription, log action and notify user
		$sql = $wpdb->prepare('INSERT INTO ' . $wpdb->prefix . 'th23_subscribe_subscriptions (item, user_id) VALUES (%s, %d)', $item_type . $item_id, $user_id);
		$wpdb->query($sql);

		$this->log_subscription(array('user_id' => $user_id), 'subscribe', $item_type . $item_id);

		if($notify) {
			$this->data['omsg'] = array(
				'msg_id' => $msg_id,
				'msg_type' => 'success',
				'msg_title' => __('Subscription successful', 'th23-subscribe'),
				'msg_text' => $success_text,
			);
			add_action('template_redirect', array(&$this, 'overlay_message_html_js_css'));
		}

	}

	// Unsubscribe link - via mail or on site
	// todo: consider error feedbacks for remaining "return" cases?
	function unsubscribe_link() {

		// check link for valid item type and id combination
		if(!empty($this->data['item_type']) && $this->data['item_type'] == 'global') {
			$item_type = 'global';
			$item_id = '';
		}
		elseif(!empty($this->data['item_type']) && $this->data['item_type'] == 'po' && !empty($this->data['item_id'])) {
			$item_type = 'po';
			$item_id = (int) $this->data['item_id'];
		}
		else {
			return;
		}

		// mail links always have a validation key attached - and should only act for the user specified in the link
		if(!empty($this->data['user_key'])) {
			$user_id = $this->check_validation_key('unsubscribe' . $item_type . $item_id, $this->data['user_key']);
			if(empty($user_id)) {
				$this->data['omsg'] = array(
					'msg_type' => 'error',
					'msg_title' => __('Unsubscribe failed', 'th23-subscribe'),
					'msg_text' => __('Unsubscribe link used could not be validated - please try again, login to unsubscribe manually or contact an administrator', 'th23-subscribe')
				);
				add_action('template_redirect', array(&$this, 'overlay_message_html_js_css'));
				return;
			}
			$this->reset_subscription($user_id);
		}

		// on site link never have a validation key - and should only work for the current user
		if(is_user_logged_in()) {
			$current_user = wp_get_current_user();
			if(!empty($user_id) && $user_id != $current_user->ID) {
				$this->data['omsg'] = array(
					'msg_type' => 'error',
					'msg_title' => __('Unsubscribe failed', 'th23-subscribe'),
					'msg_text' => __('Unsubscribe link used does not match currently logged in user - please log out and click the link again', 'th23-subscribe')
				);
				add_action('template_redirect', array(&$this, 'overlay_message_html_js_css'));
				return;
			}
			$user_id = $current_user->ID;
		}

		// no valid user given
		if(empty($user_id)) {
			return;
		}

		$this->remove_subscription($item_id, $item_type, $user_id, true);

	}

	// Unsubscribe user
	// todo: consider error feedbacks for remaining "return" cases?
	function remove_subscription($item_id, $item_type, $user_id, $notify) {

		// check for valid user - given id or current
		if(empty($user_id) && is_user_logged_in()) {
			$user = wp_get_current_user();
			$user_id = $user->ID;
		}
		if(empty($user_id) || !$user = get_userdata($user_id)) {
			return;
		}

		// check for valid item type and id
		if(empty($item_type)) {
			return;
		}
		elseif($item_type == 'global') {
			$item_id = '';
			$success_text = __('You will not receive any further notifications upon new posts via mail', 'th23-subscribe');
		}
		elseif($item_type == 'po' && !empty($item_id)) {
			if(!$post = get_post($item_id)) {
				return;
			}
			$item_id = $post->ID;
			$success_text = sprintf(__('You unsubscribed from the post "%s" and will not receive further notifications upon new comments', 'th23-subscribe'), esc_html(wp_strip_all_tags($post->post_title)));
		}
		else {
			return;
		}

		global $wpdb;

		// check for something to change
		$sql = $wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'th23_subscribe_subscriptions WHERE item = %s AND user_id = %d', $item_type . $item_id, $user_id);
		if(!$subscription = $wpdb->get_row($sql)) {
			return;
		}

		// cancel subscription and any pending notifications, log action and notify user
		$sql = $wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_subscriptions WHERE item = %s AND user_id = %d', $item_type . $item_id, $user_id);
		$wpdb->query($sql);
		$sql = $wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_notifications WHERE item = %s AND user_id = %d', $item_type . $item_id, $user_id);
		$wpdb->query($sql);

		$this->log_subscription(array('user_id' => $user_id), 'unsubscribe', $item_type . $item_id);

		if($notify) {
			$this->data['omsg'] = array(
				'msg_type' => 'success',
				'msg_title' => __('Subscription cancelled', 'th23-subscribe'),
				'msg_text' => $success_text
			);
			add_action('template_redirect', array(&$this, 'overlay_message_html_js_css'));
		}

	}

	// Load/ insert required HTML, JS and CSS for overlay messages
	function overlay_message_html_js_css() {

		// Ensure we have something to show
		if(empty($this->data['omsg'])) {
			return;
		}

		// Should be already loaded, but let's make sure jQuery is there
		wp_enqueue_script('jquery');

		// Note: CSS is included in normal CSS file

		// Upon 'wp_head' hook, insert custom JS for overlay message
		add_action('wp_head', array(&$this, 'overlay_message_js'));

		// Upon 'wp_footer' hook, insert required HTML for overlay message
		add_action('wp_footer', array(&$this, 'overlay_message_html'), 1);

	}

	function overlay_message_js() {
		?>
		<script type="text/javascript">
		// <![CDATA[
			jQuery(document).ready(function($) {
				// Specify omsg actions, if not previously defined (eg by theme JS)
				var omsg = (typeof $.th23omsg !== 'undefined' && $.isFunction($.th23omsg)) ? $.th23omsg : function(object, action, context) {
					var box = object.closest('.th23-subscribe-omsg');
					if(action == 'open') {
						box.fadeIn(500);
					}
					else if(action == 'close_click') {
						box.fadeOut(200);
					}
					else if(action == 'close_auto') {
						box.fadeOut(1000);
					}
				};
				// Show message - once all external sources (ie CSS) have been loaded and applied
				$(window).load(function(){
					omsg($('.th23-subscribe-omsg'), 'open', 'th23-subscribe-omsg');
					// Trigger automatic fade-out - for success messages, depending on setting
					<?php if($this->data['omsg']['msg_type'] != 'error' && empty($this->data['omsg']['persistent']) && $this->options['overlay_time'] > 0): ?>
					setTimeout(function() { omsg($('.th23-subscribe-omsg.success'), 'close_auto', 'th23-subscribe-omsg'); }, <?php echo ($this->options['overlay_time'] * 1000); ?>);
					<?php endif; ?>
				});
				// Attach close by user click
				$('.th23-subscribe-omsg .close').click(function() {
					omsg($(this), 'close_click', 'th23-subscribe-omsg');
				});
			});
		// ]]>
		</script>
		<?php
	}

	function overlay_message_html() {
		if(!empty($this->data['omsg']['msg_id'])) {
			$this->data['omsg']['msg_text'] = apply_filters('th23_subscribe_omsg_text', $this->data['omsg']['msg_text'], $this->data['omsg']['msg_id']);
		}
		?>
		<div class="th23-omsg th23-subscribe-omsg <?php echo $this->data['omsg']['msg_type']; ?>">
			<div class="headline">
				<div class="title"><?php echo $this->data['omsg']['msg_title']; ?></div>
				<div class="close" data-text="<?php esc_attr_e('Close', 'th23-subscribe'); ?>"></div>
			</div>
			<div class="message"><?php echo $this->data['omsg']['msg_text']; ?></div>
		</div>
		<?php
	}

	// Load CSS
	function load_css() {
		wp_register_style('th23-subscribe-css', $this->plugin['dir_url'] . 'th23-subscribe.css', array(), $this->plugin['version']);
		wp_enqueue_style('th23-subscribe-css');
	}

	// Extend register user form
	function user_register_form() {
		echo $this->user_register_form_html();
	}

	function user_register_form_html($html = '') {
		if(!empty($this->options['global_subscriptions'])) {
			$html .= '<p class="th23-subscribe-registration">';
			$checked = (!empty($this->options['global_preselected']) || !empty($_GET['presubscribe']) || !empty($_POST['subscribe'])) ? ' checked="checked"' : '';
			$html .= '<input name="subscribe[]" type="checkbox" id="subscribe_global" value="global"' . $checked . ' /> <label for="subscribe_global">' . __('I would like to be notified upon new posts via mail', 'th23-subscribe') . '</label>';
			$html .= '</p>';
		}
		return $html;
	}

	function user_register_add_subscribtions($user_id) {
		if(isset($_POST['subscribe']) && is_array($_POST['subscribe'])) {
			if(in_array('global', $_POST['subscribe'])) {
				$this->add_subscription('', 'global', $user_id, false);
				unset($_POST['subscribe']['global']);
			}
		}
	}

	// Extend comment form - for regsitered users
	function comment_form($submit_button) {
		if(is_user_logged_in() && !empty($this->options['comment_subscriptions'])) {
			// pre-check, if notification is suggested (admin setting) or user previously subscribed to comments on this post
			if(!empty($this->options['comment_preselected'])) {
				$checked = ' checked="checked"';
			}
			else {
				$post_id = get_the_ID();
				$current_user = wp_get_current_user();
				$user_id = $current_user->ID;
				global $wpdb;
				$sql = $wpdb->prepare('SELECT item FROM ' . $wpdb->prefix . 'th23_subscribe_subscriptions WHERE user_id = %d AND item = %s', $user_id, 'po' . $post_id);
				$result = $wpdb->get_results($sql, OBJECT_K);
				$checked = (!empty($result)) ? ' checked="checked"' : '';
			}
			$submit_button = '<p class="th23-subscribe-comment"><input type="checkbox" id="th23-subscribe-comment" name="subscribe" value="1"' . $checked . ' /> <label for="th23-subscribe-comment">' . __('Notify me upon responses and further comments', 'th23-subscribe') . '</label></p>' . $submit_button;
		}
		return $submit_button;
	}

	function comment_subscribtions($comment_id, $comment) {
		if(is_user_logged_in()) {
			if(isset($_POST['subscribe']) && $_POST['subscribe']) {
				$this->add_subscription($comment->comment_post_ID, 'po', 0, false);
			}
			else {
				$this->remove_subscription($comment->comment_post_ID, 'po', 0, false);
			}
		}
	}

	// Manage subscriptions

	// Add link for subscription management page
	function add_manage_subscriptions_link($base_url = '', $base_html = '<li>%s</li>') {
		if(is_user_logged_in()) {
			$url = (empty($base_url)) ? admin_url() . 'users.php?page=th23-subscribe-manage' : $base_url . '?managesubscriptions';
			printf($base_html, '<a href="' . $url . '">' . __('Manage subscriptions', 'th23-subscribe') . '</a>');
		}
	}

	// Prepare subscription management page (integration for th23 User Management plugin)
	function manage_subscriptions_prepare_output() {
		global $th23_user_management;
		// Any output yet defined?
		if(!empty($th23_user_management->data['page_content'])) {
			return;
		}
		// Are we asked to show something?
		if(!is_user_logged_in() || !isset($_REQUEST['managesubscriptions'])) {
			return;
		}

		$th23_user_management->data['page_title'] = $this->manage_subscriptions_title('%s');

		$current_user = wp_get_current_user();

		if(current_user_can('edit_user', $current_user->ID)) {
			$html = '<p class="message">' . __('Modify your subscriptions below.', 'th23-subscribe') . '</p>';
			$html .= '<form name="subscriptionsform" id="subscriptionsform" action="' . $th23_user_management->user_management_url() . '?managesubscriptions" method="post">';
			$html .= $this->manage_subscriptions_html();
			$html .= ' <div class="submit">';
			$html .= '  <input type="hidden" name="managesubscriptions" value="1" />';
			$html .= '  <input type="submit" name="submit" id="submit" class="button button-primary" value="' . __('Save', 'th23-subscribe') . '" tabindex="10" />';
			$html .= '  <input type="submit" name="cancel" id="cancel" class="button button-secondary" value="' . __('Cancel', 'th23-subscribe') . '" tabindex="11" />';
			$html .= '  ' . wp_nonce_field('th23_subscribe_manage_subscriptions', 'th23-subscribe-manage-subscriptions-nonce', true, false);
			$html .= ' </div>';
			$html .= '</form>';
		}
		else {
			$html = '<div class="th23-message th23-subscribe-message error"><strong>' . __('Error', 'th23-subscribe') . '</strong>: ' . __('You are not allowed to edit your subscriptions', 'th23-subscribe') . '</div>';
		}

		$th23_user_management->data['page_content'] = $html;

	}

	// Execute changes in subscriptions
	function manage_subscriptions_do_normal() {
		global $th23_user_management;
		// Has anything else already been done?
		if(!empty($th23_user_management->data['action_done'])) {
			return;
		}
		// Do we have something to do - and is it allowed to do this?
		if(!is_user_logged_in() || !isset($_REQUEST['managesubscriptions'])) {
			return;
		}
		$th23_user_management->data['action_done'] = true;

		// Handle cancellation
		if(isset($_REQUEST['cancel'])) {
			unset($_POST);
			$th23_user_management->data['msg'][] = array('type' => 'info', 'text' => __('Action cancelled, no changes have been saved', 'th23-subscribe'));
			return;
		}

		$current_user = wp_get_current_user();

		// Check, that user has sufficien permissions
		if(!isset($_REQUEST['submit']) || !current_user_can('edit_user', $current_user->ID)) {
			return;
		}

		// Validate nonce - abort registration if failed
		if(!wp_verify_nonce($_POST['th23-subscribe-manage-subscriptions-nonce'], 'th23_subscribe_manage_subscriptions')) {
			unset($_POST);
			$th23_user_management->data['msg'][] = array('type' => 'error', 'text' => __('Invalid request - please use the form below to manage your subscriptions', 'th23-subscribe'));
			return;
		}

		// Do it!
		$msg = $this->manage_subscriptions_update();
		$th23_user_management->data['msg'][] = $msg[0];

	}

	function manage_subscriptions_title($title = '<h2>%s</h2>') {
		return sprintf($title, __('Your Subscriptions', 'th23-subscribe'));
	}

	function manage_subscriptions_html() {
		$html = '';
		if(!empty($this->options['global_subscriptions'])) {
			$html .= '<h3>' . __('Updates', 'th23-subscribe') . '</h3>';
			$html .= ' <p class="th23-subscribe-manage-global">';
			$global_subscribed = $this->get_subscriptions(0, 'global');
			$checked = (isset($global_subscribed['global'])) ? ' checked="checked"' : '';
			$html .= '<input name="subscribe[]" type="checkbox" id="subscribe_global" value="global"' . $checked . ' /> <label for="subscribe_global">' . __('Get notifications for new posts via mail', 'th23-subscribe') . '</label>';
			$html .= ' </p>';
		}
		if(!empty($this->options['comment_subscriptions'])) {
			$html .= '<h3>' . __('Posts', 'th23-subscribe') . '</h3>';
			$html .= ' <p class="th23-subscribe-manage-posts">';
			$posts_subscribed = $this->get_subscriptions(0, 'po');
			if(!empty($posts_subscribed)) {
				$html .= '  ' . __('Unselect posts you want to receive no further notifications upon replies and additional comments', 'th23-subscribe') . '<br />';
				krsort($posts_subscribed);
				foreach($posts_subscribed as $item) {
					$post_id = (int) substr($item->item, 2);
					$post = get_post($post_id);
					$html .= '  <input name="subscribe[]" type="checkbox" id="subscribe_po_' . esc_attr($post->ID) . '" value="po' . esc_attr($post->ID) . '" checked="checked" /> <label for="subscribe_po_' . esc_attr($post->ID) . '">' . esc_html(wp_strip_all_tags($post->post_title)) . '</label><br />';
				}
			}
			else {
				$html .= '  ' . __('You are currently not subscribed to any posts - to subscribe to a post write a comment and select the subscription option', 'th23-subscribe');
			}
			$html .= ' </p>';
		}
		return $html;
	}

	function manage_subscriptions_update() {

		$success = true;

		$subscribe = (!empty($_POST['subscribe']) && is_array($_POST['subscribe'])) ? $_POST['subscribe'] : array();

		// global

		$global_subscribed = (!empty($this->get_subscriptions(0, 'global'))) ? true : false;
		$global_wanted = in_array('global', $subscribe) ? true : false;

		$this->data['omsg']['msg_type'] = '';
		if($global_subscribed && !$global_wanted) {
			$this->remove_subscription('', 'global', 0, false);
		}
		elseif(!$global_subscribed && $global_wanted && !empty($this->options['global_subscriptions'])) {
			$this->add_subscription('', 'global', 0, false);
		}
		if($this->data['omsg']['msg_type'] == 'error') {
			$success = false;
		}

		// comments

		$posts_subscribed = array_keys($this->get_subscriptions(0, 'po'));
		$remove_posts = array_diff($posts_subscribed, $subscribe);

		foreach($remove_posts as $post) {
			$this->data['omsg']['msg_type'] = '';
			$this->remove_subscription(substr($post, 2), 'po', 0, false);
			if($this->data['omsg']['msg_type'] == 'error') {
				$success = false;
			}
		}

		return ($success) ? array(array('type' => 'success', 'text' => __('Changes to your subscriptions have been saved successfully', 'th23-subscribe'))) : array(array('type' => 'error', 'text' => __('An error occured - please check your subscriptions and try again', 'th23-subscribe')));

	}

	// Add subscription option to th23 Social plugin services
	function add_subscribe_th23_social($services) {
		if(isset($services['th23_subscribe'])) {
			if(!is_user_logged_in()) {
				$services['th23_subscribe']['follow_url'] = '%registration_url%presubscribe=global';
			}
			else {
				// only show option on frontend, if user is not yet subscribed - note: exclude admin, as otherwise option in backend is not changable!
				$subscriptions = $this->get_subscriptions();
				if(!empty($subscriptions['global']) && !is_admin()) {
					unset($services['th23_subscribe']);
				}
			}
		}
		return $services;
	}

	// Let's do the magic - get ready for sending the notifications (new posts)
	function post_notification($new_status, $old_status, $post) {
		// no notification if not published, if already published before or if not a post
		if($new_status != 'publish' || $old_status == 'publish' || $post->post_type != 'post') {
			return;
		}
		$this->prepare_notifications(array('global' => 'global'), 'po' . $post->ID);
	}

	function comment_notification_direct($comment_id, $approval) {
		// check for matching type with "===" due to status "spam" otherwise matching "1" used for "approved"
		if($approval === 1) {
			$comment = get_comment($comment_id);
			$this->comment_notification('approved', 'new', $comment);
		}
	}

	function comment_notification($new_status, $old_status, $comment) {
		// no notification if not approved, if already approved before or if not a user comment (but only a pingback/ trackback)
		if($new_status != 'approved' || $old_status == 'approved' || !empty($comment->comment_type)) {
			return;
		}
		$this->prepare_notifications(array('po' . $comment->comment_post_ID => 'po' . $comment->comment_post_ID), 'co' . $comment->comment_ID);
	}

	function prepare_notifications($items, $content) {

		global $wpdb;

		// check for older notifications to be ignored
		if($this->options['old_notifications'] > 0) {
			$sql = $wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_notifications WHERE timestamp < %d', (current_time('timestamp', 1) - $this->options['old_notifications']));
			$wpdb->query($sql);
		}

		// check, if we have any users that are subscribed to the respective element (global / post) and need to be notified
		foreach($items as $key => $item) {
			$items[$key] = $wpdb->prepare('%s', $item);
		}
		$sql = 'SELECT s.* FROM ' . $wpdb->prefix . 'th23_subscribe_subscriptions s LEFT JOIN ' . $wpdb->prefix . 'th23_subscribe_notifications n ON (s.user_id = n.user_id AND s.item = n.item) WHERE s.item IN (' . implode(',', $items) . ') AND n.user_id IS NULL';
		$subscriptions = $wpdb->get_results($sql, OBJECT);
		if(empty($subscriptions)) {
			return;
		}

		// schedule notifications
		$sql = 'INSERT INTO ' . $wpdb->prefix . 'th23_subscribe_notifications (item, user_id, content, status) VALUES ';
		foreach($subscriptions as $subscription) {
			$sql .= $wpdb->prepare('(%s, %d, %s, %s),', $subscription->item, $subscription->user_id, $content, 'due');
		}
		$sql = substr($sql, 0, -1);
		$wpdb->query($sql);

		// trigger notification sending
		if(!wp_next_scheduled('batch_notifications')) {
			$this->send_notifications();
		}

	}

	// get cached content of posts / comments prepared for sending notifications
	function get_mail_content($item_type, $item) {

		$item_id = ($item_type == 'comment') ? $item->comment_ID : $item->ID;

		if(!$mail_content = get_metadata($item_type, $item_id, 'th23_subscribe_mail_content', true)) {

			// password protected post
			if($item_type == 'post' && post_password_required($item)) {
				return __('This is a password protected post - please continue to read it.', 'th23-subscribe');
			}
			// use manual post excerpt
			elseif($item_type == 'post' && !empty($item->post_excerpt)) {
				$mail_content = $item->post_excerpt;
				$shortened = true;
			}
			// use post until <!--more--> tag
			elseif($item_type == 'post' && preg_match('/<!--more(.*?)?-->/', $item->post_content, $matches)) {
				$mail_content = substr($item->post_content, 0, strpos($item->post_content, $matches[0]));
				$shortened = true;
			}
			// use full content as starting point
			else {
				$mail_content = ($item_type == 'post') ? $item->post_content : $item->comment_content;
			}

			// strip shortcodes and (non-core, selected) blocks
			$mail_content = strip_shortcodes($mail_content);
			$mail_content = excerpt_remove_blocks($mail_content);

			// make sure we remove the "more" tag, and strip out CDATA tags
			$mail_content = str_replace(array('<!--more-->', '<![CDATA[', ']]>'), '', $mail_content);

			// replace multiple line breaks (2 or more in a row) with only 2
			$mail_content = preg_replace("/(\r\n){2,}|\r{2,}|\n{2,}/", "\r\n\r\n", $mail_content);

			if(empty($shortened)) {
				// without tidy, remove all markup already before shortening - as we can not clean up open tags and this way markup doesn't count for length
				if($item_type == 'comment' || !function_exists('tidy_repair_string')) {
					$mail_content = wp_strip_all_tags($mail_content);
				}
				// use excerpt length defined for WP overall
				$excerpt_length = apply_filters('excerpt_length', 55);
				$mail_content = wp_trim_words($mail_content, $excerpt_length, '');
			}

			// in general we assume manual and "more" excerpts to be fine, but let's try to clean up any open HTML tags left behind - anyhow at least required after wp_trim_words
			if(function_exists('tidy_repair_string')) {
				$mail_content = tidy_repair_string($mail_content, array('show-body-only' => true, 'wrap' => 0, 'char-encoding' => 'raw', 'input-encoding' => 'raw', 'output-encoding' => 'raw'));
			}

			// add raw "..." in new line indicating shortened content
			$mail_content .= "\r\n\r\n" . '...';

			// cache notification content
			update_metadata($item_type, $item_id, 'th23_subscribe_mail_content', $mail_content);

		}

		return $mail_content;

	}

	// pre-process notification content
	function plain_content($mail_content) {
		// remove all markup to have a clean plain text message
		return wp_strip_all_tags($mail_content);
	}

	function send_notifications() {

		global $wpdb;

		// get notifications to be sent - prefer those for new posts (ORDER BY) over those for new comments, select one more than we allow each batch to have in order to check if we need another batch to be scheduled
		$sql = 'SELECT item, user_id, content FROM ' . $wpdb->prefix . 'th23_subscribe_notifications WHERE status = \'due\' GROUP BY user_id, content ORDER BY item ASC';
		if($this->options['max_batch'] > 0) {
			$sql .= $wpdb->prepare(' LIMIT %d', ($this->options['max_batch'] + 1));
		}
		$notifications = $wpdb->get_results($sql, OBJECT);
		if(count($notifications) == ($this->options['max_batch'] + 1)) {
			wp_schedule_single_event(time() + $this->options['delay_batch'], 'batch_notifications', array(time()));
			array_pop($notifications);
		}

		// prepare the messages for this batch
		foreach($notifications as $notification) {

			// get user details (especially mail address) - and prepare user ident string for links
			if(!$notification_receipient = get_userdata($notification->user_id)) {
				$this->delete_user_clean_up($notification->user_id);
				continue;
			}

			// blog name needs to be converted for use in plain text environment
			$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

			// todo: consider using mail template system, parsing available standard items into message layout given by admin
			/*
				standard items:
					%intro% (PRO only, otherwise ignored, eg "Dear Daniel,"),
					%event% (eg "A new post has...", "A new comment on %post_title% has...")
					%post_title%
					%content% (post text, excerpt, comment text)
					%continue_reading% (eg "To read the full article please visit %continue_link%")
					%continue_link%
					%unsubscribe% (eg "To unsubscribe please click %unsubscribe_link%")
					%unsubscribe_link%
			*/

			// filter allowing for personal greeting / intro of the mail to be sent (see PRO class)
			$notification_text = apply_filters('th23_subscribe_mail_body_intro', '', $notification_receipient);

			// do we have to handle message about a new post or a new comment?
			$notification_content_type = substr($notification->content, 0, 2);
			if($notification_content_type == 'po') {

				// get the post - and prepare variable used more than once later
				$notification_post_id = substr($notification->content, 2);
				if(!$notification_post = get_post($notification_post_id)) {
					$this->delete_post_clean_up($notification_post_id);
					continue;
				}
				$notification_post_title = wp_strip_all_tags($notification_post->post_title);

				// put together subject and intro of the notification
				// notification to post author
				if($notification_receipient->ID == $notification_post->post_author) {
					$notification_subject = sprintf(__('[%1$s] Your post: %2$s', 'th23-subscribe'), $blogname, $notification_post_title);
					$notification_text .= __('Your post has been published and subscribed users (like you) are now being notified about it:', 'th23-subscribe') . "\r\n\r\n";
				}
				// notification to other global subscribers
				else {
					$notification_subject = sprintf(__('[%1$s] New post: %2$s', 'th23-subscribe'), $blogname, $notification_post_title);
					$notification_text .= sprintf(__('A new post has been published on %s:', 'th23-subscribe'), $blogname) . "\r\n\r\n";
				}

				// add post title
				$notification_text .= apply_filters('th23_subscribe_notification_post_title', $notification_post_title, $notification_post->ID) . "\r\n\r\n";

				// add post content
				$notification_text .= apply_filters('th23_subscribe_notification_content', $this->get_mail_content('post', $notification_post)) . "\r\n";

				// add post link
				$notification_post_link = $this->add_connector(get_permalink($notification_post->ID)) . 'viewsubscription=' . $this->get_validation_key('viewsubscription', $notification_receipient);
				$notification_text .= apply_filters('th23_subscribe_notification_post_link', sprintf(__('Continue reading - to read the full article please visit %s', 'th23-subscribe'), $notification_post_link), $notification_post_link) . "\r\n\r\n";

				// add unsubscribe link
				$notification_unsubscribe_link = $this->add_connector(get_home_url()) . 'unsubscribe=' . $notification->item . '&validation=' . $this->get_validation_key('unsubscribe' . $notification->item, $notification_receipient);
				$notification_text .= apply_filters('th23_subscribe_notification_unsubscribe_link', sprintf(__('No further notifications? To unsubscribe please click %s', 'th23-subscribe'), $notification_unsubscribe_link), $notification_unsubscribe_link);

			}
			elseif($notification_content_type == 'co') {

				// get the comment
				$notification_comment_id = substr($notification->content, 2);
				if(!$notification_comment = get_comment($notification_comment_id)) {
					$this->delete_comment_clean_up($notification_comment_id);
					continue;
				}

				// get the post - and prepare variable used more than once later
				$notification_post_id = substr($notification->item, 2);
				if(!$notification_post = get_post($notification_post_id)) {
					$this->delete_post_clean_up($notification_post_id);
					continue;
				}
				$notification_post_title = wp_strip_all_tags($notification_post->post_title);

				// put together subject and intro of the notification
				if($notification_receipient->ID == $notification_comment->user_id) {
					// notiy comment author with a different message
					$notification_subject = sprintf(__('[%1$s] Your comment on "%2$s"', 'th23-subscribe'), $blogname, $notification_post_title);
					$notification_text .= sprintf(__('Your comment on "%s" has been published and subscribed users (like you) are now being notified about it. You wrote:', 'th23-subscribe'), $notification_post_title) . "\r\n\r\n";
				}
				else {
					$notification_subject = sprintf(__('[%1$s] New comment on "%2$s"', 'th23-subscribe'), $blogname, $notification_post_title);
					$notification_text .= sprintf(__('A new comment on "%s" which you are subscribed to has been published:', 'th23-subscribe'), $notification_post_title) . "\r\n\r\n";
				}

				// add comment content
				$notification_text .= apply_filters('th23_subscribe_notification_content', $this->get_mail_content('comment', $notification_comment)) . "\r\n";

				// add comment link
				$notification_comment_link = get_comment_link($notification_comment->comment_ID);
				$notification_comment_link = explode('#', $notification_comment_link);
				$notification_comment_link_output = $this->add_connector($notification_comment_link[0]) . 'viewsubscription=' . $this->get_validation_key('viewsubscription', $notification_receipient);
				if(isset($notification_comment_link[1])) {
					$notification_comment_link_output .= '#' . $notification_comment_link[1];
				}
				$notification_text .= apply_filters('th23_subscribe_notification_comment_link', sprintf(__('Continue reading - to read the full comment please visit %s', 'th23-subscribe'), $notification_comment_link_output), $notification_comment_link_output) . "\r\n\r\n";

				// add unsubscribe link
				$notification_unsubscribe_link = $this->add_connector(get_permalink($notification_post->ID)) . 'unsubscribe=po' . $notification_post->ID . '&validation=' . $this->get_validation_key('unsubscribe' . 'po' . $notification_post->ID, $notification_receipient);
				$notification_text .= apply_filters('th23_subscribe_notification_unsubscribe_link', sprintf(__('No further notifications? To unsubscribe please click %s', 'th23-subscribe'), $notification_unsubscribe_link), $notification_unsubscribe_link);

			}
			else {
				// that should not exist as the content needs to start with "po" or "co" - delete
				$wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_notifications WHERE content = %s', $notification->content));
				continue;
			}

			// send the notification - using wp_mail function
			if(!wp_mail($notification_receipient->user_email, $notification_subject, $notification_text)) {
				// if sending a mail fails - delete entry from notification table, so we try to notify again upon next post/ comment
				$wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_notifications WHERE user_id = %d AND content = %s', $notification->user_id, $notification->content));
				$result = 'failed';
			}
			else {
				// mark as "sent"
				$wpdb->query($wpdb->prepare('UPDATE ' . $wpdb->prefix . 'th23_subscribe_notifications SET status = %s, timestamp = %d WHERE user_id = %d AND content = %s', 'sent', current_time('timestamp', 1), $notification->user_id, $notification->content));
				$result = 'sent';
			}

			// log sending notification
			$this->log_subscription(array('user_id' => $notification->user_id, 'user_login' => $notification_receipient->user_login, 'user_email' => $notification_receipient->user_email), 'notification - ' . $result, $notification->content);

		}

	}

	// Handle user visit to site following link in a notification mail - verify link/ validation and reset subscription
	function viewsubscription_link() {
		if($user_id = $this->check_validation_key('viewsubscription', $this->data['user_key'])) {
			$this->reset_subscription($user_id);
		}
	}

	// Reset notification status upon user visiting the site - either logged in or via mail link (see validation above)
	// (note: does NOT require visit to specific post or comment - we believe the user grabs news interesting for him and then wants to get a mail upon further news wherever on the site!)
	function reset_subscription($user_id = 0) {

		// Not called via a mail link (empty user_id) - get current user
		if(empty($user_id) && is_user_logged_in()) {
			$current_user = wp_get_current_user();
			$user_id = $current_user->ID;
			$user_login = $current_user->user_login;
		}
		// Called via mail link - get full user data (for the mail link user)
		else {
			$user = get_userdata($user_id);
			if(!empty($user)) {
				$user_id = $user->ID;
				$user_login = $user->user_login;
				// IMPORTANT: Used for own purposes (deletion of unconfirmed visitors by th23 Subscribe plugin) as well as to support "Last Visit" tracking of th23 User Management plugin
				update_user_meta($user_id, 'th23-user-management-last-visit', current_time('timestamp'));
			}
		}

		// No valid user found
		if(empty($user_id) || empty($user_login)) {
			return;
		}

		// Check for sent notifications - reset, if existing
		global $wpdb;
		if($result = $wpdb->get_row($wpdb->prepare('SELECT content FROM ' . $wpdb->prefix . 'th23_subscribe_notifications WHERE status = \'sent\' AND user_id = %d', $user_id))) {
			$wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_notifications WHERE user_id = %d', $user_id));
			$this->log_subscription(array('user_id' => $user_id), 'visit');
		}

	}

	// Keep logfile about user actions
	function log_subscription($user, $action, $content = '') {

		if(empty($this->options['log'])) {
			return;
		}

		// valid user information and something to log
		if(empty($user['user_id']) || empty($action)) {
			return;
		}
		if(empty($user['user_login']) || empty($user['user_email'])) {
			$user_data = get_userdata($user['user_id']);
			if(empty($user_data)) {
				return;
			}
			if(empty($user['user_login'])) {
				$user['user_login'] = $user_data->user_login;
			}
			if(empty($user['user_email'])) {
				$user['user_email'] = $user_data->user_email;
			}
		}

		// get user IP address
		$ip = 'unknown';
		if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		elseif(!empty($_SERVER['REMOTE_ADDR'])) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		// write log entry - create file, if required
		global $th23_subscribe_path;
		$data = (!file_exists($th23_subscribe_path . 'log.csv')) ? "User ID;User login;User mail;Action;Content;Timestamp;Date/ Time;IP address\n" : "";
		$data .= $user['user_id'] . ";" . $user['user_login']  . ";" . $user['user_email'] . ";" . $action . ";" . $content . ";" . current_time('timestamp', 1) . ";" . date_i18n(get_option('date_format') . " - " . get_option('time_format'), current_time('timestamp')) . ";" . $ip . "\n";
		file_put_contents($th23_subscribe_path . 'log.csv', $data, FILE_APPEND);

	}

}

// === WIDGET ===

// Subscribe widget
class th23_subscribe_widget extends WP_Widget {

	function __construct() {
		parent::__construct(false, $name = 'th23 Subscribe', array('description' => __('Displays option to subscribe to udpates', 'th23-subscribe')));
	}

	// Ensure PHP <5 compatibility
	function th23_subscribe_widget() {
		self::__construct();
	}

	function widget($args, $instance) {

		$output = apply_filters('th23_subscribe_widget', '', $instance);
		if(empty($output)) {

			global $th23_subscribe;

			if(!empty($th23_subscribe->options['global_subscriptions'])) {
				if(is_user_logged_in()) {
					// show option only, if user is not yet subscribed
					$subscriptions = $th23_subscribe->get_subscriptions();
					if(!isset($subscriptions['global'])) {
						$current_url = $th23_subscribe->add_connector($th23_subscribe->get_current_url());
						// note: wrap in default existing "widget_meta" class and use "ul" / "li" tags for one-link-by-line items, to ensure proper basic widget styling by themes
						$output = '<div class="widget_meta">';
						if(!empty($instance['description'])) {
							$output .= '<div class="text">' . $instance['description'] . '</div>';
						}
						$output .= '<ul><li class="link global-link"><a href="' . esc_url($current_url . 'subscribe=global') . '">' . __('Subscribe', 'th23-subscribe') . '</a></li></ul>';
						$output .= '</div>';
					}
				}
				else {
					$register_url = $th23_subscribe->add_connector(wp_registration_url());
					// note: wrap in default existing "widget_meta" class and use "ul" / "li" tags for one-link-by-line items, to ensure proper basic widget styling by themes
					$output = '<div class="widget_meta">';
					$output .= '<div class="text">' . sprintf(__('Please %sregister%s to subscribe for updates', 'th23-subscribe'), '<a href="' . esc_url($register_url . 'presubscribe=global') . '">', '</a>') . '</div>';
					$output .= '</div>';
				}
			}

		}

		if(!empty($output)) {
			extract($args);
			// title
			$title = (!empty($instance['title'])) ? $before_title . apply_filters('widget_title', $instance['title']) . $after_title : '';
			echo $before_widget . $title . $output . $after_widget;
		}

	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['description'] = $new_instance['description'];
		return $instance;
	}

	function form($instance) {
		// defaults upon first adding the widget
		$instance = wp_parse_args((array) $instance, array(
			'title' => __('Updates', 'th23-subscribe'),
			'description' => __('Get notifications for new posts via mail', 'th23-subscribe'),
		));
		// title
		$title = !empty($instance['title']) ? esc_attr($instance['title']) : '';
		echo '<p><label for="' . $this->get_field_id('title') . '">' . __('Title') . '</label><input class="widefat" id="' . $this->get_field_id('title') . '" name="' . $this->get_field_name('title') . '" type="text" value="' . $title . '" /></label></p>';
		// description
		$description = !empty($instance['description']) ? esc_attr($instance['description']) : '';
		echo '<p><label for="' . $this->get_field_id('description') . '">' . __('Description', 'th23-social') . '</label><textarea class="widefat" id="' . $this->get_field_id('description') . '" name="' . $this->get_field_name('description') . '" size="3">' . $description . '</textarea></p>';
	}

}
add_action('widgets_init', function() { return register_widget('th23_subscribe_widget'); });

// === INITIALIZATION ===

$th23_subscribe_path = plugin_dir_path(__FILE__);

// Load additional PRO class, if it exists
if(file_exists($th23_subscribe_path . 'th23-subscribe-pro.php')) {
	require($th23_subscribe_path . 'th23-subscribe-pro.php');
}
// Mimic PRO class, if it does not exist
if(!class_exists('th23_subscribe_pro')) {
	class th23_subscribe_pro extends th23_subscribe {
		function __construct() {
			parent::__construct();
		}
		// Ensure PHP <5 compatibility
		function th23_subscribe_pro() {
			self::__construct();
		}
	}
}

// Load additional admin class, if required...
if(is_admin() && file_exists($th23_subscribe_path . 'th23-subscribe-admin.php')) {
	require($th23_subscribe_path . 'th23-subscribe-admin.php');
	$th23_subscribe = new th23_subscribe_admin();
}
// ...or initiate plugin via (mimiced) PRO class
else {
	$th23_subscribe = new th23_subscribe_pro();
}

?>
