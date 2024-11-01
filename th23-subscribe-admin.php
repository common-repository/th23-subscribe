<?php
/*
th23 Subscribe
Admin area

Copyright 2012-2020, Thorsten Hartmann (th23)
http://th23.net
*/

// Security - exit if accessed directly
if(!defined('ABSPATH')) {
    exit;
}

class th23_subscribe_admin extends th23_subscribe_pro {

	function __construct() {

		parent::__construct();

		// Setup basics (additions for backend)
		$this->plugin['settings_base'] = 'options-general.php';
		$this->plugin['settings_handle'] = 'th23-subscribe';
		$this->plugin['settings_permission'] = 'manage_options';
		$this->plugin['extendable'] = __('<p>Offer <strong>subscribe option for your visitors</strong>, while protecting your website against spam. Visitors can easily become fully registered members later.</p><p>Send <strong>personalized email notifications</strong>, addressing users by name for a personal note. Support for nicely formatted HTML emails.</p>', 'th23-subscribe');
		// icon: "square" 48 x 48px (footer) / "horizontal" 36px height (header, width irrelevant) / both (resized if larger)
		$this->plugin['icon'] = array('square' => 'img/th23-subscribe-square.png', 'horizontal' => 'img/th23-subscribe-horizontal.png');
		$this->plugin['extension_files'] = array('th23-subscribe-pro.php');
		$this->plugin['download_url'] = 'https://th23.net/th23-subscribe/';
		$this->plugin['support_url'] = 'https://th23.net/th23-subscribe-support/';
		$this->plugin['requirement_notices'] = array();

		// Install/ uninstall
		add_action('activate_' . $this->plugin['basename'], array(&$this, 'install'));
		add_action('deactivate_' . $this->plugin['basename'], array(&$this, 'uninstall'));

		// Update
		add_action('upgrader_process_complete', array(&$this, 'pre_update'), 10, 2);
		add_action('plugins_loaded', array(&$this, 'post_update'));

		// Requirements
		add_action('plugins_loaded', array(&$this, 'requirements'));
		add_action('admin_notices', array(&$this, 'admin_notices'));

		// Modify plugin overview page
		add_filter('plugin_action_links_' . $this->plugin['basename'], array(&$this, 'settings_link'), 10);
		add_filter('plugin_row_meta', array(&$this, 'contact_link'), 10, 2);

		// Add admin page and JS/ CSS
		add_action('admin_init', array(&$this, 'register_admin_js_css'));
		add_action('admin_menu', array(&$this, 'add_admin'));
		add_action('wp_ajax_th23_subscribe_screen_options', array(&$this, 'set_screen_options'));

		// == customization: from here on plugin specific ==

		// Protect meta values from being edited "raw" by user
		add_filter('is_protected_meta', array(&$this, 'set_protected_meta'), 10, 3);

		// Add admin page to manage subscriptions of each user
		add_action('admin_menu', array(&$this, 'add_manage_subscriptions'));

		// Clean up subscription and notification table in case a user, post or comment is deleted
		// (note: functions placed in main php file as they are used from frontend as well)
		add_action('delete_user', array(&$this, 'delete_user_clean_up'));
		add_action('trashed_post', array(&$this, 'delete_post_clean_up'));
		add_action('deleted_post', array(&$this, 'delete_post_clean_up'));
		add_action('trashed_comment', array(&$this, 'delete_comment_clean_up'));
		add_action('deleted_comment', array(&$this, 'delete_comment_clean_up'));

		// Reset cached meta for auto-excerpts - upon content update
		add_action('save_post', array(&$this, 'auto_excerpt_reset'));

		// th23 Social: Add subscription option to social services
		add_filter('th23_social_services_defaults', array(&$this, 'add_subscribe_th23_social_default'));

		// Settings: Screen options
		// note: default can handle boolean, integer or string
		$this->plugin['screen_options'] = array(
			'hide_description' => array(
				'title' => __('Hide settings descriptions', 'th23-subscribe'),
				'default' => false,
			),
		);

		// Settings: Help
		// note: use HTML formatting within content and help_sidebar text eg always wrap in "<p>", use "<a>" links, etc
		$this->plugin['help_tabs'] = array(
			'th23_subscribe_help_overview' => array(
				'title' => __('Settings and support', 'th23-subscribe'),
				'content' => __('<p>You can find video tutorials explaning the plugin settings for on <a href="https://www.youtube.com/channel/UCS3sNYFyxhezPVu38ESBMGA">my YouTube channel</a>.</p><p>More details and explanations are available on <a href="https://th23.net/th23-subscribe-support/">my Frequently Asked Questions (FAQ) page</a> or the <a href="https://wordpress.org/support/plugin/th23-subscribe/">plugin support section on WordPress.org</a>.</p>', 'th23-subscribe'),
			),
		);
		$this->plugin['help_sidebar'] = __('<p>Support me by <a href="https://wordpress.org/support/plugin/th23-subscribe/reviews/#new-post">leaving a review</a> or check out some of <a href="https://wordpress.org/plugins/search/th23/">my other plugins</a> <strong>:-)</strong></p>', 'th23-subscribe');

		// Settings: Define plugin options
		$this->plugin['options'] = array();

		// used multiple times

		$subscriptions_removal_warning = __('Warning: Disabling this option, will delete all existing related subscriptions irreversably!', 'th23-subscribe');

		// global_subscriptions

		$this->plugin['options']['global_subscriptions'] = array(
			'section' => __('Subscriptions', 'th23-subscribe'),
			'title' => __('Posts', 'th23-subscribe'),
			'description' => $subscriptions_removal_warning,
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Allow users to subscribe to new posts being published', 'th23-subscribe'),
			),
			'attributes' => array(
				'data-childs' => '.option-global_preselected',
			),
		);

		// global_preselected

		$this->plugin['options']['global_preselected'] = array(
			'title' => __('Pre-selection', 'th23-subscribe'),
			'description' => __('Can be changed by user via checkbox', 'th23-subscribe'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 0,
				0 => '',
				1 => __('Pre-select subscription option upon user registration', 'th23-subscribe'),
			),
		);

		// comment_subscriptions

		$this->plugin['options']['comment_subscriptions'] = array(
			'title' => __('Comments', 'th23-subscribe'),
			'description' => $subscriptions_removal_warning,
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Allow users to subscribe to responses and further comments', 'th23-subscribe'),
			),
			'attributes' => array(
				'data-childs' => '.option-comment_preselected',
			),
		);

		// comment_preselected

		$this->plugin['options']['comment_preselected'] = array(
			'title' => __('Pre-selection', 'th23-subscribe'),
			'description' => __('Can be changed by user via checkbox', 'th23-subscribe'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 0,
				0 => '',
				1 => __('Pre-select subscription option upon writing a comment', 'th23-subscribe'),
			),
		);

		// Settings: Professional options (placeholders shown to Basic users)
		// note: ensure all are at least defined in general admin module to ensure settings are kept upon updates
		if(!empty($this->plugin['extendable']) || !empty($this->plugin['pro'])) {

			// Professional description
			$pro_description = '<span class="notice notice-description notice-warning">' . sprintf(__('This option is only available with the %1$s version of this plugin', 'th23-subscribe'), $this->plugin_professional()) . '</span>';

			// visitors

			$this->plugin['options']['visitors'] = array(
				'section' => __('Visitors', 'th23-subscribe'),
				'title' => __('Visitor subscriptions', 'th23-subscribe'),
				'description' => $pro_description,
				'element' => 'checkbox',
				'default' => array(
					'single' => 0,
					0 => '',
					1 => __('Allow visitors to subscribe to new posts and comments without being regsitered', 'th23-subscribe'),
				),
				'attributes' => array(
					'data-childs' => '.option-visitors_terms,.option-captcha,.option-delete_unconfirmed',
					'disabled' => 'disabled',
				),
			);

			// visitors_terms

			$this->plugin['options']['visitors_terms'] = array(
				'title' => __('Terms', 'th23-subscribe'),
				'description' => $pro_description,
				'element' => 'checkbox',
				'default' => array(
					'single' => 1,
					0 => '',
					1 => __('Visitors are required to accept terms of usage to be able to subscribe to new posts and to upgrade becoming a registered user', 'th23-subscribe'),
				),
				'attributes' => array(
					'disabled' => 'disabled',
				),
			);

			// captcha

			$this->plugin['options']['captcha'] = array(
				/* translators: parses in "reCaptcha" as name of the service */
				'title' => sprintf(__('Enable %s', 'th23-subscribe'), '<i>reCaptcha</i>'),
				'description' => $pro_description,
				'element' => 'checkbox',
				'default' => array(
					'single' => 1,
					0 => '',
					/* translators: parses in "reCaptcha v2" as name of the service */
					1 => sprintf(__('Use %s to check visitor subscriptions stopping spam and bots', 'th23-subscribe'), '<i>reCaptcha v2</i>'),
				),
				'attributes' => array(
					'data-childs' => '.option-captcha_public,.option-captcha_private',
					'disabled' => 'disabled',
				),
			);

			// captcha_public

			$this->plugin['options']['captcha_public'] = array(
				'title' => __('Public Key', 'th23-subscribe'),
				'description' => $pro_description,
				'default' => '',
				'attributes' => array(
					'disabled' => 'disabled',
				),
			);

			// captcha_private

			$this->plugin['options']['captcha_private'] = array(
				'title' => __('Secret Key', 'th23-subscribe'),
				'description' => $pro_description,
				'default' => '',
				'attributes' => array(
					'disabled' => 'disabled',
				),
			);

			// delete_unconfirmed

			$this->plugin['options']['delete_unconfirmed'] = array(
				'title' => __('Delete unconfirmed', 'th23-subscribe'),
				'description' => __('Number of days, after which visitors, that have not confirmed their subscription (via the link provided), will be deleted automatically - set to "0" to disable automatic deletion', 'th23-subscribe') . $pro_description,
				'default' => 0,
				/* translators: part of "x day(s)" where "x" is user input in an input field */
				'unit' => __('day(s)', 'th23-subscribe'),
				'attributes' => array(
					'class' => 'small-text',
					'disabled' => 'disabled',
				),
			);

		}

		// old_notifications

		$old_notifications_description = __('Time span in seconds after which previous notifications to the same user/ for the same item will be ignored - set to "0" to only send a new notification after user visited the site', 'th23-subscribe');
		$old_notifications_description .= '<br />' . __('Warning: A very short time frame might lead to spamming users with notifications!', 'th23-subscribe');
		$old_notifications_description .= '<br />' . '<a href="" class="toggle-switch">' . __('Show / hide examples', 'th23-subscribe') . '</a>';
		$old_notifications_description .= '<span class="toggle-show-hide" style="display: none;">';
		$old_notifications_description .= '<br />' . __('1 minute are 60 seconds', 'th23-subscribe');
		$old_notifications_description .= '<br />' . __('1 hour are 3600 seconds', 'th23-subscribe');
		$old_notifications_description .= '<br />' . __('1 day are 86400 seconds', 'th23-subscribe');
		$old_notifications_description .= '<br />' . __('1 week are 604800 seconds', 'th23-subscribe');
		$old_notifications_description .= '</span>';

		$this->plugin['options']['old_notifications'] = array(
			'section' => __('Notifications', 'th23-subscribe'),
			'title' => __('Ignore old notifications', 'th23-subscribe'),
			'description' => $old_notifications_description,
			'default' => 604800,
			/* translators: part of "x seconds(s)" where "x" is user input in an input field */
			'unit' => __('second(s)', 'th23-subscribe'),
			'attributes' => array(
				'class' => 'small-text',
			),
		);

		// max_batch

		$this->plugin['options']['max_batch'] = array(
			'title' => __('Batch size', 'th23-subscribe'),
			'description' => __('Maximal number of notifications/ mails to be sent in one batch to avoid failure due to host restrictions, spam filters, etc. - set to "0" to send all at once', 'th23-subscribe'),
			'default' => 50,
			/* translators: part of "x message(s)" where "x" is user input in an input field */
			'unit' => __('message(s)', 'th23-subscribe'),
			'attributes' => array(
				'class' => 'small-text',
			),
		);

		// delay_batch

		$this->plugin['options']['delay_batch'] = array(
			'title' => __('Batch delay', 'th23-subscribe'),
			'description' => __('Idle time period between sending batches in seconds', 'th23-subscribe'),
			'default' => 300,
			/* translators: part of "x seconds(s)" where "x" is user input in an input field */
			'unit' => __('second(s)', 'th23-subscribe'),
			'attributes' => array(
				'class' => 'small-text',
			),
		);

		if(!empty($this->plugin['extendable']) || !empty($this->plugin['pro'])) {

			// button_color

			$this->plugin['options']['button_color'] = array(
				'title' => __('Button color', 'th23-subscribe'),
				'description' => __('Color of "call to action" button in HTML notification mails in hex format - default is dark red "#820000", text on it is always white', 'th23-subscribe') . $pro_description,
				'default' => '#820000',
				'attributes' => array(
					'disabled' => 'disabled',
				),
			);

		}

		// overlay_time

		$overlay_time_description = __('Duration until overlay messages disappear automatically - set "0" for users to close manually', 'th23-subscribe');
		$overlay_time_description .= '<br />' . __('Note: Error messages and requests for user input will never disappear automatically!', 'th23-subscribe');

		$this->plugin['options']['overlay_time'] = array(
			'section' => __('General', 'th23-subscribe'),
			'title' => __('Overlay message time', 'th23-subscribe'),
			'description' => $overlay_time_description,
			'default' => 5,
			/* translators: part of "x seconds(s)" where "x" is user input in an input field */
			'unit' => __('second(s)', 'th23-subscribe'),
			'attributes' => array(
				'class' => 'small-text',
			),
		);

		// log
		// todo: show/delete log file via ajax

		$this->plugin['options']['log'] = array(
			'title' => __('Log', 'th23-subscribe'),
			/* translators: adds the logfile name */
			'description' => sprintf(__('The logfile is kept in the plugin folder and named %s - to reset, delete the file', 'th23-subscribe'), '<code>log.csv</code>'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Keep a logfile about subscription actions', 'th23-subscribe'),
			),
		);

		// cache_reset

		$this->plugin['options']['cache_reset'] = array(
			'title' => __('Reset cache', 'th23-subscribe'),
			'description' => __('Note: Will be re-created automatically before sending any pending emails', 'th23-subscribe'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 0,
				0 => '',
				1 => __('Delete cached content for notifications', 'th23-subscribe'),
			),
		);

		// Settings: Define presets for template option values (pre-filled, but changable by user)
		$this->plugin['presets'] = array();

	}

	// Ensure PHP <5 compatibility
	function th23_subscribe_admin() {
		self::__construct();
	}

	// Plugin versions
	// Note: Any CSS styling needs to be "hardcoded" here as plugin CSS might not be loaded (e.g. on plugin overview page)
	function plugin_professional($highlight = false) {
		$title = '<i>Professional</i>';
		return ($highlight) ? '<span style="font-weight: bold; color: #336600;">' . $title . '</span>' : $title;
	}
	function plugin_basic() {
		return '<i>Basic</i>';
	}
	function plugin_upgrade($highlight = false) {
		/* translators: "Professional" as name of the version */
		$title = sprintf(__('Upgrade to %s version', 'th23-subscribe'), $this->plugin_professional());
		return ($highlight) ? '<span style="font-weight: bold; color: #CC3333;">' . $title . '</span>' : $title;
	}

	// Get validated plugin options
	function get_options($options = array(), $html_input = false) {
		$checked_options = array();
		foreach($this->plugin['options'] as $option => $option_details) {
			$default = $option_details['default'];
			// default array can be template or allowing multiple inputs
			$default_value = $default;
			$type = '';
			if(is_array($default)) {
				$default_value = reset($default);
				$type = key($default);
			}

			// if we have a template, pass all values for each element through the check against the template defaults
			if($type == 'template') {
				unset($default['template']);
				// create complete list of all elements - those from previous settings (re-activation), overruled by (most recent) defaults and merged with any possible user input
				$elements = array_keys($default);
				if($html_input && !empty($option_details['extendable']) && !empty($_POST['input_' . $option . '_elements'])) {
					$elements = array_merge($elements, explode(',', $_POST['input_' . $option . '_elements']));
				}
				else {
					$elements = array_merge(array_keys($options[$option]), $elements);
				}
				$elements = array_unique($elements);
				// loop through all elements - and validate previous / user values
				$checked_options[$option] = array();
				$sort_elements = array();
				foreach($elements as $element) {
					$checked_options[$option][$element] = array();
					// loop through all (sub-)options
					foreach($default_value as $sub_option => $sub_option_details) {
						$sub_default = $sub_option_details['default'];
						$sub_default_value = $sub_default;
						$sub_type = '';
						if(is_array($sub_default)) {
							$sub_default_value = reset($sub_default);
							$sub_type = key($sub_default);
						}
						unset($value);
						// force pre-set options for elements given in default
						if(isset($default[$element][$sub_option])) {
							$value = $default[$element][$sub_option];
						}
						// html input
						elseif($html_input) {
							if(isset($_POST['input_' . $option . '_' . $element . '_' . $sub_option])) {
								// if only single value allowed, only take first element from value array for validation
								if($sub_type == 'single' && is_array($_POST['input_' . $option . '_' . $element . '_' . $sub_option])) {
									$value = reset($_POST['input_' . $option . '_' . $element . '_' . $sub_option]);
								}
								else {
									$value = stripslashes($_POST['input_' . $option . '_' . $element . '_' . $sub_option]);
								}
							}
							// avoid empty items filled with default - will be filled with default in case empty/0 is not allowed for single by validation
							elseif($sub_type == 'multiple') {
								$value = array();
							}
							elseif($sub_type == 'single') {
								$value = '';
							}
						}
						// previous value
						elseif(isset($options[$option][$element][$sub_option])) {
							$value = $options[$option][$element][$sub_option];
						}
						// in case no value is given, take default
						if(!isset($value)) {
							$value = $sub_default_value;
						}
						// verify and store value
						$value = $this->get_valid_option($sub_default, $value);
						$checked_options[$option][$element][$sub_option] = $value;
						// prepare sorting
						if($sub_option == 'order') {
							$sort_elements[$element] = $value;
						}
					}
				}
				// sort verified elements according to order field (after validation to sort along valid order values)
				if(isset($default_value['order'])) {
					asort($sort_elements);
					$sorted_elements = array();
					foreach($sort_elements as $element => $null) {
						$sorted_elements[$element] = $checked_options[$option][$element];
					}
					$checked_options[$option] = $sorted_elements;
				}
			}
			// normal input fields
			else {
				unset($value);
				// html input
				if($html_input) {
					if(isset($_POST['input_' . $option])) {
						// if only single value allowed, only take first element from value array for validation
						if($type == 'single' && is_array($_POST['input_' . $option])) {
							$value = reset($_POST['input_' . $option]);
						}
						elseif($type == 'multiple' && is_array($_POST['input_' . $option])) {
							$value = array();
							foreach($_POST['input_' . $option] as $key => $val) {
								$value[$key] = stripslashes($val);
							}
						}
						else {
							$value = stripslashes($_POST['input_' . $option]);
						}
					}
					// avoid empty items filled with default - will be filled with default in case empty/0 is not allowed for single by validation
					elseif($type == 'multiple') {
						$value = array();
					}
					elseif($type == 'single') {
						$value = '';
					}
				}
				// previous value
				elseif(isset($options[$option])) {
					$value = $options[$option];
				}
				// in case no value is given, take default
				if(!isset($value)) {
					$value = $default_value;
				}
				// check value defined by user
				$checked_options[$option] = $this->get_valid_option($default, $value);
			}
		}
		return $checked_options;
	}

	// Validate / type match value against default
	function get_valid_option($default, $value) {
		if(is_array($default)) {
			$default_value = reset($default);
			$type = key($default);
			unset($default[$type]);
			if($type == 'multiple') {
				// note: multiple selections / checkboxes can be empty
				$valid_value = array();
				foreach($value as $selected) {
					// force allowed type - determined by first default element / no mixed types allowed
					if(gettype($default_value[0]) != gettype($selected)) {
						settype($selected, gettype($default_value[0]));
					}
					// check against allowed values - including type check
					if(isset($default[$selected])) {
						$valid_value[] = $selected;
					}
				}
			}
			else {
				// force allowed type - determined default value / no mixed types allowed
				if(gettype($default_value) != gettype($value)) {
					settype($value, gettype($default_value));
				}
				// check against allowed values
				if(isset($default[$value])) {
					$valid_value = $value;
				}
				// single selections (radio buttons, dropdowns) should have a valid value
				else {
					$valid_value = $default_value;
				}
			}
		}
		else {
			// force allowed type - determined default value
			if(gettype($default) != gettype($value)) {
				settype($value, gettype($default));
			}
			$valid_value = $value;
		}
		return $valid_value;
	}

	// Install
	function install() {

		// Prefill values in an option template, keeping them user editable (and therefore not specified in the default value itself)
		// need to check, if items exist(ed) before and can be reused - so we dont' overwrite them (see uninstall with delete_option inactive)
		if(isset($this->plugin['presets'])) {
			if(!isset($this->options) || !is_array($this->options)) {
				$this->options = array();
			}
			$this->options = array_merge($this->plugin['presets'], $this->options);
		}
		// Set option values
		update_option('th23_subscribe_options', $this->get_options($this->options));
		$this->options = (array) get_option('th23_subscribe_options');

		// customization: Add visitor user role
		add_role('th23_subscribe_visitor', __('Visitor', 'th23-subscribe'), array('read' => true));

		// customization: Setup tables in DB
		global $wpdb;
		$charset_collate = '';
		if($wpdb->has_cap('collation')) {
			$charset_collate .= (!empty($wpdb->charset)) ? ' DEFAULT CHARACTER SET ' . $wpdb->charset : '';
			$charset_collate .= (!empty($wpdb->collate)) ? ' COLLATE ' . $wpdb->collate : '';
		}
		$wpdb->show_errors();
		/*
		th23_subscribe_subscriptions
		- [indexed] item = ##text ("global", post "po" + respective ID)##
		- [indexed] user_id = ##id##
		*/
		$sql = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'th23_subscribe_subscriptions (
			id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			item VARCHAR (25) NOT NULL,
			user_id INT (10) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY item (item),
			KEY user_id (user_id)
		)' . $charset_collate;
		$wpdb->query($sql);
		/*
		th23_subscribe_notifications
		- [indexed] item = ##text ("global", post "po" + respective ID)##
		- [indexed] user_id = ##id##
		- content = ##text (post "po" / comment "co" + respective ID)##
		- [indexed] status = ##text ("due", "sent")##
		- timestamp = ##time of sending notification##
		*/
		$sql = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'th23_subscribe_notifications (
			id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			item VARCHAR (25) NOT NULL,
			user_id INT (10) UNSIGNED NOT NULL DEFAULT 0,
			content VARCHAR (25) NOT NULL,
			status VARCHAR (5) NOT NULL,
			timestamp INT (10) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY item (item),
			KEY user_id (user_id),
			KEY status (status)
		)' . $charset_collate;
		$wpdb->query($sql);
		$wpdb->hide_errors();

	}

	// Uninstall
	function uninstall() {

		// NOTICE: To keep all settings etc in case the plugin is reactivated, return right away - if you want to remove previous settings and data, comment out the following line!
		return;

		// Delete option values
		delete_option('th23_subscribe_options');

		// customization: Remove visitor user role
		remove_role('th23_subscribe_visitor');

		// customization: Remove visitor deletion event
		if(!empty($timestamp = wp_next_scheduled('th23_subscribe_delete_unconfirmed'))) {
			wp_unschedule_event($timestamp, 'th23_subscribe_delete_unconfirmed');
		}

		// customization: Delete tables
		global $wpdb;
		$wpdb->show_errors();
		$wpdb->query('DROP TABLE ' . $wpdb->prefix . 'th23_subscribe_subscriptions');
		$wpdb->query('DROP TABLE ' . $wpdb->prefix . 'th23_subscribe_notifications');
		$wpdb->hide_errors();

	}

	// Update - store previous version before plugin is updated
	// note: this function is still run by the old version of the plugin, ie before the update
	function pre_update($upgrader_object, $options) {
		if('update' == $options['action'] && 'plugin' == $options['type'] && !empty($options['plugins']) && is_array($options['plugins']) && in_array($this->plugin['basename'], $options['plugins'])) {
			set_transient('th23_subscribe_update', $this->plugin['version']);
			if(!empty($this->plugin['pro'])) {
				set_transient('th23_subscribe_update_pro', $this->plugin['pro']);
			}
		}
	}

	// Update - check for previous update and trigger requird actions
	function post_update() {

		// previous Professional extension - remind to update/re-upload
		if(!empty(get_transient('th23_subscribe_update_pro')) && empty($this->plugin['pro'])) {
			add_action('th23_subscribe_requirements', array(&$this, 'post_update_missing_pro'));
		}

		if(empty($previous = get_transient('th23_subscribe_update'))) {
			return;
		}

		/* execute required update actions, optionally depending on previously installed version
		if(version_compare($previous, '1.6.0', '<')) {
			// action required
		}
		*/

		// upon successful update, delete transient (update only executed once)
		delete_transient('th23_subscribe_update');

	}
	// previous Professional extension - remind to update/re-upload
	function post_update_missing_pro($context) {
		if('plugin_settings' == $context) {
			$missing = '<label for="th23-subscribe-pro-file"><strong>' . __('Upload Professional extension?', 'th23-subscribe') . '</strong></label>';
		}
		else {
			$missing = '<a href="' . esc_url($this->plugin['settings_base'] . '?page=' . $this->plugin['settings_handle']) . '"><strong>' . __('Go to plugin settings page for upload...', 'th23-subscribe') . '</strong></a>';
		}
		/* translators: 1: "Professional" as name of the version, 2: link to "th23.net" plugin download page, 3: link to "Go to plugin settings page to upload..." page or "Upload updated Professional extension?" link */
		$notice = sprintf(__('Due to an update the previously installed %1$s extension is missing. Please get the latest version of the %1$s extension from %2$s. %3$s', 'th23-subscribe'), $this->plugin_professional(), '<a href="' . esc_url($this->plugin['download_url']) . '" target="_blank">th23.net</a>', $missing);
		$this->plugin['requirement_notices']['missing_pro'] = '<strong>' . __('Error', 'th23-subscribe') . '</strong>: ' . $notice;
	}

	// Requirements - checks
	function requirements() {

		// check requirements only on relevant admin pages
		global $pagenow;
		if(empty($pagenow)) {
			return;
		}
		if('index.php' == $pagenow) {
			// admin dashboard
			$context = 'admin_index';
		}
		elseif('plugins.php' == $pagenow) {
			// plugins overview page
			$context = 'plugins_overview';
		}
		elseif($this->plugin['settings_base'] == $pagenow && !empty($_GET['page']) && $this->plugin['settings_handle'] == $_GET['page']) {
			// plugin settings page
			$context = 'plugin_settings';
		}
		else {
			return;
		}

		// Check - plugin not designed for multisite setup
		if(is_multisite()) {
			$this->plugin['requirement_notices']['multisite'] = '<strong>' . __('Warning', 'th23-subscribe') . '</strong>: ' . __('Your are running a multisite installation - the plugin is not designed for this setup and therefore might not work properly', 'th23-subscribe');
		}

		// allow further checks by Professional extension (without re-assessing $context)
		do_action('th23_subscribe_requirements', $context);

	}

	// Requirements - show requirement notices on admin dashboard
	function admin_notices() {
		global $pagenow;
		if(!empty($pagenow) && 'index.php' == $pagenow && !empty($this->plugin['requirement_notices'])) {
			echo '<div class="notice notice-error">';
			echo '<p style="font-size: 14px;"><strong>' . $this->plugin['data']['Name'] . '</strong></p>';
			foreach($this->plugin['requirement_notices'] as $notice) {
				echo '<p>' . $notice . '</p>';
			}
			echo '</div>';
		}
	}

	// Add settings link to plugin actions in plugin overview page
	function settings_link($links) {
		$links['settings'] = '<a href="' . esc_url($this->plugin['settings_base'] . '?page=' . $this->plugin['settings_handle']) . '">' . __('Settings', 'th23-subscribe') . '</a>';
		return $links;
	}

	// Add supporting information (eg links and notices) to plugin row in plugin overview page
	// Note: Any CSS styling needs to be "hardcoded" here as plugin CSS might not be loaded (e.g. when plugin deactivated)
	function contact_link($links, $file) {
		if($this->plugin['basename'] == $file) {
			// Use internal version number and expand version details
			if(!empty($this->plugin['pro'])) {
				/* translators: parses in plugin version number (optionally) together with upgrade link */
				$links[0] = sprintf(__('Version %s', 'th23-subscribe'), $this->plugin['version']) . ' ' . $this->plugin_professional(true);
			}
			elseif(!empty($this->plugin['extendable'])) {
				/* translators: parses in plugin version number (optionally) together with upgrade link */
				$links[0] = sprintf(__('Version %s', 'th23-subscribe'), $this->plugin['version']) . ' ' . $this->plugin_basic() . ((empty($this->plugin['requirement_notices']) && !empty($this->plugin['download_url'])) ? ' - <a href="' . esc_url($this->plugin['download_url']) . '">' . $this->plugin_upgrade(true) . '</a>' : '');
			}
			// Add support link
			if(!empty($this->plugin['support_url'])) {
				$links[] = '<a href="' . esc_url($this->plugin['support_url']) . '">' . __('Support', 'th23-subscribe') . '</a>';
			}
			// Show warning, if installation requirements are not met - add it after/ to last link
			if(!empty($this->plugin['requirement_notices'])) {
				$notices = '';
				foreach($this->plugin['requirement_notices'] as $notice) {
					$notices .= '<div style="margin: 1em 0; padding: 5px 10px; background-color: #FFFFFF; border-left: 4px solid #DD3D36; box-shadow: 0 1px 1px 0 rgba(0, 0, 0, 0.1);">' . $notice . '</div>';
				}
				$last = array_pop($links);
				$links[] = $last . $notices;
			}
		}
		return $links;
	}

	// Register admin JS and CSS
	function register_admin_js_css() {
		wp_register_script('th23-subscribe-admin-js', $this->plugin['dir_url'] . 'th23-subscribe-admin.js', array('jquery'), $this->plugin['version'], true);
		wp_register_style('th23-subscribe-admin-css', $this->plugin['dir_url'] . 'th23-subscribe-admin.css', array(), $this->plugin['version']);
	}

	// Register admin page in admin menu/ prepare loading admin JS and CSS/ trigger screen options
	function add_admin() {
		$this->plugin['data'] = get_plugin_data($this->plugin['file']);
		$page = add_submenu_page($this->plugin['settings_base'], $this->plugin['data']['Name'], $this->plugin['data']['Name'], $this->plugin['settings_permission'], $this->plugin['settings_handle'], array(&$this, 'show_admin'));
		add_action('admin_print_scripts-' . $page, array(&$this, 'load_admin_js'));
		add_action('admin_print_styles-' . $page, array(&$this, 'load_admin_css'));
		if(!empty($this->plugin['screen_options'])) {
			add_action('load-' . $page, array(&$this, 'add_screen_options'));
		}
		if(!empty($this->plugin['help_tabs'])) {
			add_action('load-' . $page, array(&$this, 'add_help'));
		}
	}

	// Load admin JS
	function load_admin_js() {
		wp_enqueue_script('th23-subscribe-admin-js');
	}

	// Load admin CSS
	function load_admin_css() {
		wp_enqueue_style('th23-subscribe-admin-css');
	}

	// Handle screen options
	function add_screen_options() {
		add_filter('screen_settings', array(&$this, 'show_screen_options'), 10, 2);
	}
	function show_screen_options($html, $screen) {
		$html .= '<div id="th23-subscribe-screen-options">';
		$html .= '<input type="hidden" id="th23-subscribe-screen-options-nonce" value="' . wp_create_nonce('th23-subscribe-screen-options-nonce') . '" />';
		$html .= $this->get_screen_options(true);
		$html .= '</div>';
		return $html;
	}
	function get_screen_options($html = false) {
		if(empty($this->plugin['screen_options'])) {
			return array();
		}
		if(empty($user = get_user_meta(get_current_user_id(), 'th23_subscribe_screen_options', true))) {
			$user = array();
		}
		$screen_options = ($html) ? '' : array();
		foreach($this->plugin['screen_options'] as $option => $details) {
			$type = gettype($details['default']);
			$value = (isset($user[$option]) && gettype($user[$option]) == $type) ? $user[$option] : $details['default'];
			if($html) {
				$name = 'th23_subscribe_screen_options_' . $option;
				$class = 'th23-subscribe-screen-option-' . $option;
				if('boolean' == $type) {
					$checked = (!empty($value)) ? ' checked="checked"' : '';
					$screen_options .= '<fieldset class="' . $name . '"><label><input name="' . $name .'" id="' . $name .'" value="1" type="checkbox"' . $checked . ' data-class="' . $class . '">' . esc_html($details['title']) . '</label></fieldset>';
				}
				elseif('integer' == $type) {
					$min_max = (isset($details['range']['min'])) ? ' min="' . $details['range']['min'] . '"' : '';
					$min_max .= (isset($details['range']['max'])) ? ' max="' . $details['range']['max'] . '"' : '';
					$screen_options .= '<fieldset class="' . $name . '"><label for="' . $name . '">' . esc_html($details['title']) . '</label><input id="' . $name . '" name="' . $name . '" type="number"' . $min_max . ' value="' . $value . '" data-class="' . $class . '" /></fieldset>';
				}
				elseif('string' == $type) {
					$screen_options .= '<fieldset class="' . $name . '"><label for="' . $name . '">' . esc_html($details['title']) . '</label><input id="' . $name . '" name="' . $name . '" type="text" value="' . esc_attr($value) . '" data-class="' . $class . '" /></fieldset>';
				}
			}
			else {
				$screen_options[$option] = $value;
			}
		}
		return $screen_options;
	}
	// update user preference for screen options via AJAX
	function set_screen_options() {
		if(!empty($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'th23-subscribe-screen-options-nonce')) {
			$screen_options = $this->get_screen_options();
			$new = array();
			foreach($screen_options as $option => $value) {
				$name = 'th23_subscribe_screen_options_' . $option;
				if('boolean' == gettype($value)) {
					if(empty($_POST[$name])) {
						$screen_options[$option] = $value;
					}
					elseif('true' == $_POST[$name]) {
						$screen_options[$option] = true;
					}
					else {
						$screen_options[$option] = false;
					}
				}
				else {
					settype($_POST[$name], gettype($value));
					$screen_options[$option] = $_POST[$name];
				}
			}
			update_user_meta(get_current_user_id(), 'th23_subscribe_screen_options', $screen_options);
		}
		wp_die();
	}

	// Add help
	function add_help() {
		$screen = get_current_screen();
		foreach($this->plugin['help_tabs'] as $id => $details) {
			$screen->add_help_tab(array(
				'id' => $id,
				'title' => $details['title'],
				'content' => $details['content'],
			));
		}
		if(!empty($this->plugin['help_sidebar'])) {
			$screen->set_help_sidebar($this->plugin['help_sidebar']);
		}
	}

	// Show admin page
	function show_admin() {

		global $wpdb;
		$form_classes = array();

		// Open wrapper and show plugin header
		echo '<div class="wrap th23-subscribe-options">';

		// Header - logo / plugin name
		echo '<h1>';
		if(!empty($this->plugin['icon']['horizontal'])) {
			echo '<img class="icon" src="' . esc_url($this->plugin['dir_url'] . $this->plugin['icon']['horizontal']) . '" alt="' . esc_attr($this->plugin['data']['Name']) . '" />';
		}
		else {
			echo $this->plugin['data']['Name'];
		}
		echo '</h1>';

		// Get screen options, ie user preferences - and build CSS class
		if(!empty($this->plugin['screen_options'])) {
			$screen_options = $this->get_screen_options();
			foreach($screen_options as $option => $value) {
				if($value === true) {
					$form_classes[] = 'th23-subscribe-screen-option-' . $option;
				}
				elseif(!empty($value)) {
					$form_classes[] = 'th23-subscribe-screen-option-' . $option . '-' . esc_attr(str_replace(' ', '_', $value));
				}
			}
		}

		// start form
		echo '<form method="post" enctype="multipart/form-data" id="th23-subscribe-options" action="' . esc_url($this->plugin['settings_base'] . '?page=' . $this->plugin['settings_handle']) . '" class="' . implode(' ', $form_classes) . '">';

		// Show warnings, if requirements are not met
		if(!empty($this->plugin['requirement_notices'])) {
			foreach($this->plugin['requirement_notices'] as $notice) {
				echo '<div class="notice notice-error"><p>' . $notice . '</p></div>';
			}
		}

		// Do update of plugin options if required
		if(!empty($_POST['th23-subscribe-options-do'])) {
			check_admin_referer('th23_subscribe_settings', 'th23-subscribe-settings-nonce');
			$new_options = $this->get_options($this->options, true);

			// customization: check for "manual" request to delete all cached entry contents (prepared for notification mails), eg after changing default length or filters to be taken into account
			// note: reset to 0 to prevent this from triggering an option update and to ensure the checkbox is always unchecked upon page load
			if(!empty($new_options['cache_reset'])) {
				delete_post_meta_by_key('th23_subscribe_mail_content');
				echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Done', 'th23-subscribe') . '</strong>: ' . __('Cache cleared', 'th23-subscribe') . '</p><button class="notice-dismiss" type="button"></button></div>';
			}
			$new_options['cache_reset'] = 0;

			// re-acquire options from DB to ensure we check against unfiltered options (in case filters are allowed somewhere)
			$options_unfiltered = (array) get_option('th23_subscribe_options');
			if($new_options != $options_unfiltered) {

				// customization: Remove user subscriptions, if subscription to new posts is not allowed anymore
				if(!empty($options_unfiltered['global_subscriptions']) && empty($new_options['global_subscriptions'])) {
					$sql = $wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_subscriptions WHERE item = %s', 'global');
					$wpdb->query($sql);
				}

				// customization: Remove user subscriptions, if subscription to new comments is not allowed anymore
				if(!empty($options_unfiltered['comment_subscriptions']) && empty($new_options['comment_subscriptions'])) {
					$sql = $wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_subscriptions WHERE item LIKE %s', 'po%');
					$wpdb->query($sql);
				}

				// customization: Remove visitor subscriptions, if subscriptions for visitors are not allowed anymore
				if(!empty($options_unfiltered['visitors']) && empty($new_options['visitors'])) {
					$sql = $wpdb->prepare('SELECT user_id FROM ' . $wpdb->prefix . 'usermeta WHERE meta_key = %s AND meta_value LIKE %s', $wpdb->prefix . 'capabilities', '%th23_subscribe_visitor%');
					$visitors = $wpdb->get_results($sql, OBJECT_K);
					if(!empty($visitors)) {
						$sql = 'DELETE FROM ' . $wpdb->prefix . 'th23_subscribe_subscriptions WHERE user_id IN (' . implode(',', array_keys($visitors)) . ')';
						$wpdb->query($sql);
					}
				}

				update_option('th23_subscribe_options', $new_options);
				$this->options = $new_options;
				echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Done', 'th23-subscribe') . '</strong>: ' . __('Settings saved', 'th23-subscribe') . '</p><button class="notice-dismiss" type="button"></button></div>';
			}
		}

		// Handle Profesional extension upload and show upgrade information
		if(empty($this->pro_upload()) && empty($this->plugin['pro']) && empty($this->plugin['requirement_notices']) && !empty($this->plugin['extendable']) && !empty($this->plugin['download_url'])) {
			echo '<div class="th23-subscribe-admin-about">';
			echo '<p>' . $this->plugin['extendable'] . '</p>';
			echo '<p><a href="' . esc_url($this->plugin['download_url']) . '">' . $this->plugin_upgrade(true) . '</a></p>';
			echo '</div>';
		}

		// Show plugin settings
		// start table
		echo '<table class="form-table"><tbody>';

		// collect all children options - and the no shows
		$child_list = '';
		$sub_child_list = '';
		$no_show_list = '';

		// loop through all options
		foreach($this->plugin['options'] as $option => $option_details) {

			// add children options and no shows
			if(isset($option_details['element']) && $option_details['element'] == 'checkbox' && !empty($option_details['attributes']['data-childs'])) {
				// if the current option itself is on the child list, then the options in data-childs are sub childs
				if(strpos($child_list, 'option-' . $option . ',') !== false) {
					$sub_child_list .= $option_details['attributes']['data-childs'] . ',';
				}
				// otherwise we have first level children
				else {
					$child_list .= $option_details['attributes']['data-childs'] . ',';
				}
				if(empty($this->options[$option]) || strpos($no_show_list, 'option-' . $option . ',') !== false) {
					$no_show_list .= $option_details['attributes']['data-childs'] . ',';
				}
			}
			// assign proper child or sub-child class - for proper indent
			$child_class = '';
			if(strpos($child_list, 'option-' . $option . ',') !== false) {
				$child_class = ' child';
			}
			elseif(strpos($sub_child_list, 'option-' . $option . ',') !== false) {
				$child_class = ' sub-child';
			}
			// prepare show/hide style for current element
			$no_show_style = (strpos($no_show_list, 'option-' . $option . ',') !== false) ? ' style="display: none;"' : '';

			$key = '';
			if(is_array($option_details['default'])) {
				$default_value = reset($option_details['default']);
				$key = key($option_details['default']);
				unset($option_details['default'][$key]);
				if($key == 'template') {

					echo '</tbody></table>';
					echo '<div class="option option-template option-' . $option . $child_class . '"' . $no_show_style . '>';
					echo '<h2>' . $option_details['title'] . '</h2>';
					if(!empty($option_details['description'])) {
						echo '<p class="section-description">' . $option_details['description'] . '</p>';
					}
					echo '<table class="option-template"><tbody>';

					// create template headers
					echo '<tr>';
					foreach($default_value as $sub_option => $sub_option_details) {
						$hint_open = '';
						$hint_close = '';
						if(isset($sub_option_details['description'])) {
							$hint_open = '<span class="hint" title="' . esc_attr($sub_option_details['description']) . '">';
							$hint_close = '</span>';
						}
						echo '<th class="' . $sub_option . '">' . $hint_open . $sub_option_details['title'] . $hint_close . '</th>';
					}
					// show add button, if template list is user editable
					if(!empty($option_details['extendable'])) {
						echo '<td class="template-actions"><button type="button" id="template-add-' . $option . '" value="' . $option . '">' . __('+', 'th23-subscribe') . '</button></td>';
					}
					echo '</tr>';

					// get elements for rows - and populate hidden input (adjusted by JS for adding/ deleting rows)
					$elements = array_keys(array_merge($this->options[$option], $option_details['default']));
					// sort elements array according to order field
					if(isset($default_value['order'])) {
						$sorted_elements = array();
						foreach($elements as $element) {
							$sorted_elements[$element] = (isset($this->options[$option][$element]['order'])) ? $this->options[$option][$element]['order'] : 0;
						}
						asort($sorted_elements);
						$elements = array_keys($sorted_elements);
					}

					// add list of elements and empty row as source for user inputs - filled with defaults
					if(!empty($option_details['extendable'])) {
						echo '<input id="input_' . $option . '_elements" name="input_' . $option . '_elements" value="' . implode(',', $elements) . '" type="hidden" />';
						$elements[] = 'template';
					}

					// show template rows
					foreach($elements as $element) {
						echo '<tr id="' . $option . '-' . $element . '">';
						foreach($default_value as $sub_option => $sub_option_details) {
							echo '<td>';
							// get sub value default - and separate any array to show as sub value
							$sub_key = '';
							if(is_array($sub_option_details['default'])) {
								$sub_default_value = reset($sub_option_details['default']);
								$sub_key = key($sub_option_details['default']);
								unset($sub_option_details['default'][$sub_key]);
							}
							else {
								$sub_default_value = $sub_option_details['default'];
							}
							// force current value to be default and disable input field for preset elements / fields (not user changable / editable)
							if(isset($option_details['default'][$element][$sub_option])) {
								// set current value to default (not user-changable)
								$this->options[$option][$element][$sub_option] = $option_details['default'][$element][$sub_option];
								// disable input field
								if(!isset($sub_option_details['attributes']) || !is_array($sub_option_details['attributes'])) {
									$sub_option_details['attributes'] = array();
								}
								$sub_option_details['attributes']['disabled'] = 'disabled';
								// show full value in title, as field is disabled and thus sometimes not scrollable
								$sub_option_details['attributes']['title'] = esc_attr($this->options[$option][$element][$sub_option]);
							}
							// set to template defined default, if not yet set (eg options added via filter before first save)
							elseif(!isset($this->options[$option][$element][$sub_option])) {
								$this->options[$option][$element][$sub_option] = $sub_default_value;
							}
							// build and show input field
							$html = $this->build_input_field($option . '_' . $element . '_' . $sub_option, $sub_option_details, $sub_key, $sub_default_value, $this->options[$option][$element][$sub_option]);
							if(!empty($html)) {
								echo $html;
							}
							echo '</td>';
						}
						// show remove button, if template list is user editable and element is not part of the default set
						if(!empty($option_details['extendable'])) {
							$remove = (empty($this->plugin['options'][$option]['default'][$element]) || $element == 'template') ? '<button type="button" id="template-remove-' . $option . '-' . $element . '" value="' . $option . '" data-element="' . $element . '">' . __('-', 'th23-subscribe') . '</button>' : '';
							echo '<td class="template-actions">' . $remove . '</td>';
						}
						echo '</tr>';
					}

					echo '</tbody></table>';
					echo '</div>';
					echo '<table class="form-table"><tbody>';

					continue;

				}
			}
			else {
				$default_value = $option_details['default'];
			}

			// separate option sections - break table(s) and insert heading
			if(!empty($option_details['section'])) {
				echo '</tbody></table>';
				echo '<h2 class="option option-section option-' . $option . $child_class . '"' . $no_show_style . '>' . $option_details['section'] . '</h2>';
				if(!empty($option_details['section_description'])) {
					echo '<p class="section-description">' . $option_details['section_description'] . '</p>';
				}
				echo '<table class="form-table"><tbody>';
			}

			// Build input field and output option row
			if(!isset($this->options[$option])) {
				// might not be set upon fresh activation
				$this->options[$option] = $default_value;
			}
			$html = $this->build_input_field($option, $option_details, $key, $default_value, $this->options[$option]);
			if(!empty($html)) {
				echo '<tr class="option option-' . $option . $child_class . '" valign="top"' . $no_show_style . '>';
				$option_title = $option_details['title'];
				if(!isset($option_details['element']) || ($option_details['element'] != 'checkbox' && $option_details['element'] != 'radio')) {
					$brackets = (isset($option_details['element']) && ($option_details['element'] == 'list' || $option_details['element'] == 'dropdown')) ? '[]' : '';
					$option_title = '<label for="input_' . $option . $brackets . '">' . $option_title . '</label>';
				}
				echo '<th scope="row">' . $option_title . '</th>';
				echo '<td><fieldset>';
				// Rendering additional field content via callback function
				// passing on to callback function as parameters: $default_value = default value, $this->options[$option] = current value
				if(!empty($option_details['render']) && method_exists($this, $option_details['render'])) {
					$render = $option_details['render'];
					echo $this->$render($default_value, $this->options[$option]);
				}
				echo $html;
				if(!empty($option_details['description'])) {
					echo '<span class="description">' . $option_details['description'] . '</span>';
				}
				echo '</fieldset></td>';
				echo '</tr>';
			}

		}

		// end table
		echo '</tbody></table>';
		echo '<br/>';

		// submit
		echo '<input type="hidden" name="th23-subscribe-options-do" value=""/>';
		echo '<input type="button" id="th23-subscribe-options-submit" class="button-primary th23-subscribe-options-submit" value="' . esc_attr(__('Save Changes', 'th23-subscribe')) . '"/>';
		wp_nonce_field('th23_subscribe_settings', 'th23-subscribe-settings-nonce');

		echo '<br/>';

		// Plugin information
		echo '<div class="th23-subscribe-admin-about">';
		if(!empty($this->plugin['icon']['square'])) {
			echo '<img class="icon" src="' . esc_url($this->plugin['dir_url'] . $this->plugin['icon']['square']) . '" alt="' . esc_attr($this->plugin['data']['Name']) . '" /><p>';
		}
		else {
			echo '<p><strong>' . $this->plugin['data']['Name'] . '</strong>' . ' | ';
		}
		if(!empty($this->plugin['pro'])) {
			/* translators: parses in plugin version number (optionally) together with upgrade link */
			echo sprintf(__('Version %s', 'th23-subscribe'), $this->plugin['version']) . ' ' . $this->plugin_professional(true);
		}
		else {
			/* translators: parses in plugin version number (optionally) together with upgrade link */
			echo sprintf(__('Version %s', 'th23-subscribe'), $this->plugin['version']);
			if(!empty($this->plugin['extendable'])) {
				echo ' ' . $this->plugin_basic();
				if(empty($this->plugin['requirement_notices']) && !empty($this->plugin['download_url'])) {
					echo ' - <a href="' . esc_url($this->plugin['download_url']) . '">' . $this->plugin_upgrade(true) . '</a> (<label for="th23-subscribe-pro-file">' . __('Upload upgrade', 'th23-subscribe') . ')</label>';
				}
			}
		}
		// embed upload for Professional extension
		if(!empty($this->plugin['extendable'])) {
			echo '<input type="file" name="th23-subscribe-pro-file" id="th23-subscribe-pro-file" />';
		}
		/* translators: parses in plugin author name */
		echo ' | ' . sprintf(__('By %s', 'th23-subscribe'), $this->plugin['data']['Author']);
		if(!empty($this->plugin['support_url'])) {
			echo ' | <a href="' . esc_url($this->plugin['support_url']) . '">' . __('Support', 'th23-subscribe') . '</a>';
		}
		elseif(!empty($this->plugin['data']['PluginURI'])) {
			echo ' | <a href="' . $this->plugin['data']['PluginURI'] . '">' . __('Visit plugin site', 'th23-subscribe') . '</a>';
		}
		echo '</p></div>';

		// Close form and wrapper
		echo '</form>';
		echo '</div>';

	}

	// Handle Profesional extension upload
	function pro_upload() {

		if(empty($_FILES['th23-subscribe-pro-file']) || empty($pro_upload_name = $_FILES['th23-subscribe-pro-file']['name'])) {
			return;
		}

		global $th23_subscribe_path;
		$files = array();
		$try_again = '<label for="th23-subscribe-pro-file">' . __('Try again?', 'th23-subscribe') . '</label>';

		// zip archive
		if('.zip' == substr($pro_upload_name, -4)) {
			// check required ZipArchive class (core component of most PHP installations)
			if(!class_exists('ZipArchive')) {
				echo '<div class="notice notice-error"><p><strong>' . __('Error', 'th23-subscribe') . '</strong>: ';
				/* translators: parses in "Try again?" link */
				echo sprintf(__('Your server can not handle zip files. Please extract it locally and try again with the individual files. %s', 'th23-subscribe'), $try_again) . '</p></div>';
				return;
			}
			// open zip file
			$zip = new ZipArchive;
			if($zip->open($_FILES['th23-subscribe-pro-file']['tmp_name']) !== true) {
				echo '<div class="notice notice-error"><p><strong>' . __('Error', 'th23-subscribe') . '</strong>: ';
				/* translators: parses in "Try again?" link */
				echo sprintf(__('Failed to open zip file. %s', 'th23-subscribe'), $try_again) . '</p></div>';
				return;
			}
			// check zip contents
			for($i = 0; $i < $zip->count(); $i++) {
			    $zip_file = $zip->statIndex($i);
				$files[] = $zip_file['name'];
			}
			if(!empty(array_diff($files, $this->plugin['extension_files']))) {
				echo '<div class="notice notice-error"><p><strong>' . __('Error', 'th23-subscribe') . '</strong>: ';
				/* translators: parses in "Try again?" link */
				echo sprintf(__('Zip file seems to contain files not belonging to the Professional extension. %s', 'th23-subscribe'), $try_again) . '</p></div>';
				return;
			}
			// extract zip to plugin folder (overwrites existing files by default)
			$zip->extractTo($th23_subscribe_path);
			$zip->close();
		}
		// (invalid) individual file
		elseif(!in_array($pro_upload_name, $this->plugin['extension_files'])) {
			echo '<div class="notice notice-error"><p><strong>' . __('Error', 'th23-subscribe') . '</strong>: ';
			/* translators: parses in "Try again?" link */
			echo sprintf(__('This does not seem to be a proper Professional extension file. %s', 'th23-subscribe'), $try_again) . '</p></div>';
			return;
		}
		// idividual file
		else {
			move_uploaded_file($_FILES['th23-subscribe-pro-file']['tmp_name'], $th23_subscribe_path . $pro_upload_name);
			$files[] = $pro_upload_name;
		}

		// ensure proper file permissions (as done by WP core function "_wp_handle_upload" after upload)
		$stat = stat($th23_subscribe_path);
		$perms = $stat['mode'] & 0000666;
		foreach($files as $file) {
			chmod($th23_subscribe_path . $file, $perms);
		}

		// check for missing extension files
		$missing_file = false;
		foreach($this->plugin['extension_files'] as $file) {
			if(!is_file($th23_subscribe_path . $file)) {
				$missing_file = true;
				break;
			}
		}

		// upload success message
		if($missing_file) {
			$missing = '<label for="th23-subscribe-pro-file">' . __('Upload missing file(s)!', 'th23-subscribe') . '</label>';
			echo '<div class="notice notice-warning"><p><strong>' . __('Done', 'th23-subscribe') . '</strong>: ';
			/* translators: parses in "Upload missing files!" link */
			echo sprintf(__('Professional extension file uploaded. %s', 'th23-subscribe'), $missing) . '</p></div>';
			return true;
		}
		else {
			$reload = '<a href="' . esc_url($this->plugin['settings_base'] . '?page=' . $this->plugin['settings_handle']) . '">' . __('Reload page to see Professional settings!', 'th23-subscribe') . '</a>';
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Done', 'th23-subscribe') . '</strong>: ';
			/* translators: parses in "Reload page to see Professional settings!" link */
			echo sprintf(__('Professional extension file uploaded. %s', 'th23-subscribe'), $reload) . '</p><button class="notice-dismiss" type="button"></button></div>';
			return true;
		}

	}

	// Create admin input field
	// note: uses the chance to point out any invalid combinations for element and validation options
	function build_input_field($option, $option_details, $key, $default_value, $current_value) {

		if(!isset($option_details['element'])) {
			$option_details['element'] = 'input';
		}
		$element_name = 'input_' . $option;
		$element_attributes = array();
		if(!isset($option_details['attributes']) || !is_array($option_details['attributes'])) {
			$option_details['attributes'] = array();
		}
		$element_attributes_suggested = array();
		$valid_option_field = true;
		if($option_details['element'] == 'checkbox') {
			// exceptional case: checkbox allows "single" default to handle (yes/no) checkbox
			if(empty($key) || ($key == 'multiple' && !is_array($default_value)) || ($key == 'single' && is_array($default_value))) {
				$valid_option_field = false;
			}
			$element_name .= '[]';
			$element_attributes['type'] = 'checkbox';
		}
		elseif($option_details['element'] == 'radio') {
			if(empty($key) || $key != 'single' || is_array($default_value)) {
				$valid_option_field = false;
			}
			$element_name .= '[]';
			$element_attributes['type'] = 'radio';
		}
		elseif($option_details['element'] == 'list') {
			if(empty($key) || $key != 'multiple' || !is_array($default_value)) {
				$valid_option_field = false;
			}
			$element_name .= '[]';
			$element_attributes['multiple'] = 'multiple';
			$element_attributes_suggested['size'] = '5';
		}
		elseif($option_details['element'] == 'dropdown') {
			if(empty($key) || $key != 'single' || is_array($default_value)) {
				$valid_option_field = false;
			}
			$element_name .= '[]';
			$element_attributes['size'] = '1';
		}
		elseif($option_details['element'] == 'hidden') {
			if(!empty($key)) {
				$valid_option_field = false;
			}
			$element_attributes['type'] = 'hidden';
		}
		else {
			if(!empty($key)) {
				$valid_option_field = false;
			}
			$element_attributes_suggested['type'] = 'text';
			$element_attributes_suggested['class'] = 'regular-text';
		}
		// no valid option field, due to missmatch of input field and default value
		if(!$valid_option_field) {
			$support_open = '';
			$support_close = '';
			if(!empty($this->plugin['support_url'])) {
				$support_open = '<a href="' . esc_url($this->plugin['support_url']) . '">';
				$support_close = '</a>';
			}
			elseif(!empty($this->plugin['data']['PluginURI'])) {
				$support_open = '<a href="' . $this->plugin['data']['PluginURI'] . '">';
				$support_close = '</a>';
			}
			echo '<div class="notice notice-error"><p><strong>' . __('Error', 'th23-subscribe') . '</strong>: ';
			/* translators: 1: option name, 2: opening a tag of link to support/ plugin page, 3: closing a tag of link */
			echo sprintf(__('Invalid combination of input field and default value for "%1$s" - please %2$scontact the plugin author%3$s', 'th23-subscribe'), $option, $support_open, $support_close);
			echo '</p></div>';
			return '';
		}

		$html = '';

		// handle repetitive elements (checkboxes and radio buttons)
		if($option_details['element'] == 'checkbox' || $option_details['element'] == 'radio') {
			$html .= '<div>';
			// special handling for single checkboxes (yes/no)
			$checked = ($option_details['element'] == 'radio' || $key == 'single') ? array($current_value) : $current_value;
			foreach($option_details['default'] as $value => $text) {
				// special handling for yes/no checkboxes
				if(!empty($text)){
					$html .= '<div><label><input name="' . $element_name . '" id="' . $element_name . '_' . $value . '" value="' . $value . '" ';
					foreach(array_merge($element_attributes_suggested, $option_details['attributes'], $element_attributes) as $attr => $attr_value) {
						$html .= $attr . '="' . $attr_value . '" ';
					}
					$html .= (in_array($value, $checked)) ? 'checked="checked" ' : '';
					$html .= '/>' . $text . '</label></div>';
				}
			}
			$html .= '</div>';
		}
		// handle repetitive elements (dropdowns and lists)
		elseif($option_details['element'] == 'list' || $option_details['element'] == 'dropdown') {
			$html .= '<select name="' . $element_name . '" id="' . $element_name . '" ';
			foreach(array_merge($element_attributes_suggested, $option_details['attributes'], $element_attributes) as $attr => $attr_value) {
				$html .= $attr . '="' . $attr_value . '" ';
			}
			$html .= '>';
			$selected = ($option_details['element'] == 'dropdown') ? array($current_value) : $current_value;
			foreach($option_details['default'] as $value => $text) {
				$html .= '<option value="' . $value . '"';
				$html .= (in_array($value, $selected)) ? ' selected="selected"' : '';
				$html .= '>' . $text . '</option>';
			}
			$html .= '</select>';
			if($option_details['element'] == 'dropdown' && !empty($option_details['unit'])) {
				$html .= '<span class="unit">' . $option_details['unit'] . '</span>';
			}
		}
		// textareas
		elseif($option_details['element'] == 'textarea') {
			$html .= '<textarea name="' . $element_name . '" id="' . $element_name . '" ';
			foreach(array_merge($element_attributes_suggested, $option_details['attributes'], $element_attributes) as $attr => $attr_value) {
				$html .= $attr . '="' . $attr_value . '" ';
			}
			$html .= '>' . stripslashes($current_value) . '</textarea>';
		}
		// simple (self-closing) inputs
		else {
			$html .= '<input name="' . $element_name . '" id="' . $element_name . '" ';
			foreach(array_merge($element_attributes_suggested, $option_details['attributes'], $element_attributes) as $attr => $attr_value) {
				$html .= $attr . '="' . $attr_value . '" ';
			}
			$html .= 'value="' . stripslashes($current_value) . '" />';
			if(!empty($option_details['unit'])) {
				$html .= '<span class="unit">' . $option_details['unit'] . '</span>';
			}
		}

		return $html;

	}

	// == customization: from here on plugin specific ==

	// Protect meta values from being edited "raw" by user on edit post / page
	function set_protected_meta($protected, $meta_key, $meta_type) {
		if(in_array($meta_key, array('th23_subscribe_mail_content'))) {
			return true;
		}
		return $protected;
	}

	// Add admin page to manage subscriptions of each user
	function add_manage_subscriptions() {
		$page = add_submenu_page('users.php', __('Your Subscriptions', 'th23-subscribe'), __('Your Subscriptions', 'th23-subscribe'), 'read', 'th23-subscribe-manage', array(&$this, 'show_manage_subscriptions'));
		add_action('admin_print_scripts-' . $page, array(&$this, 'load_admin_js'));
		add_action('admin_print_styles-' . $page, array(&$this, 'load_admin_css'));
	}

	// Show admin manage subscriptions page
	// Note: Functions (_title, _update, _html) are kept in frontend part - for easier compatability with th23 User Management
	function show_manage_subscriptions() {
		echo '<div id="profile-page" class="wrap">';
		echo '<h1>' . $this->manage_subscriptions_title() . '</h1>';
		// Do update of subscriptions if required
		if(!empty($_POST['th23-subscribe-manage-submit'])) {
			check_admin_referer('th23_subscribe_manage', 'th23-subscribe-manage-nonce');
			$result = $this->manage_subscriptions_update();
			if($result[0]['type'] == 'success') {
				echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Done', 'th23-subscribe') . '</strong>: ' . $result[0]['text'] . '</p><button class="notice-dismiss" type="button"></button></div>';
			}
			else {
				echo '<div class="notice notice-error"><p><strong>' . __('Error', 'th23-subscribe') . '</strong>: ' . $result[0]['text'] . '</p></div>';
			}
		}
		// Output subscriptions form
		echo '<form method="post" action="users.php?page=th23-subscribe-manage">';
		echo $this->manage_subscriptions_html();
		echo '<br/>';
		echo '<input type="submit" name="th23-subscribe-manage-submit" class="button-primary" value="' . esc_attr(__('Save Changes', 'th23-subscribe')) . '"/>';
		wp_nonce_field('th23_subscribe_manage', 'th23-subscribe-manage-nonce');
		echo '</form>';
		echo '</div>';
	}

	// Reset cached meta for auto-excerpts - upon content update
	function auto_excerpt_reset($entry_id) {
		delete_post_meta($entry_id, 'th23_subscribe_mail_content');
	}

	// Add subscription option to th23 Social plugin services
	function add_subscribe_th23_social_default($services_defaults) {
		if(!isset($services_defaults['th23_subscribe']) || !is_array($services_defaults['th23_subscribe'])) {
			$services_defaults['th23_subscribe'] = array();
		}
		// these defaults can not be changed / deleted by user, but order, show, etc can be via admin area
		$services_defaults['th23_subscribe'] = array_merge($services_defaults['th23_subscribe'], array(
			'name' => 'Subscribe',
			'css_class' => 'th23-subscribe',
			'own_account' => '',
			'follow_url' => '%current_url%subscribe=global',
			'share_url' => '',
		));
		return $services_defaults;
	}

}

?>
