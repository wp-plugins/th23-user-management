<?php
/*
th23 User Management
Admin area

Copyright 2010-2015, Thorsten Hartmann (th23)
http://th23.net
*/

class th23_user_management_admin extends th23_user_management_pro {

	function th23_user_management_admin() {

		parent::th23_user_management_pro();

		// Setup basic variables (additions for backend)
		$this->requirements = $this->check_requirements();
		$this->settings_base = 'options-general.php';
		$this->settings_base_url = $this->settings_base . '?page=' . $this->slug;
		$this->settings_permission = 'manage_options';

		// Retrieve plugin data
		add_action('admin_menu', array(&$this, 'plugin_data'));

		// Install/ uninstall
		add_action('activate_' . $this->base_name, array(&$this, 'install'));
		add_action('deactivate_' .$this->base_name, array(&$this, 'uninstall'));
		
		// Modify plugin overview page
		add_filter('plugin_row_meta', array(&$this, 'settings_link'), 10, 2);

		// Add admin page and JS/ CSS
		add_action('admin_init', array(&$this, 'register_admin_js_css'));
		add_action('admin_menu', array(&$this, 'add_admin'));

		// If the user management page "dummy" is deleted, trashed or un-published create a new one
		add_action('after_delete_post', array(&$this, 'delete_user_management_page'));
		add_action('transition_post_status', array(&$this, 'unpublish_user_management_page'), 10, 3);

		// User role "pending" should not be choosable as "New User Default Role" - which will be assigned to users after admin approval, if required
		add_filter('editable_roles', array(&$this, 'hide_user_role_pending'));

	}

	// Check requirements
	function check_requirements() {
		if(is_multisite()) {
			return false;
		}
		return true;
	}

	// Load plugin data for admin
	function plugin_data() {
		$this->plugin_data = get_plugin_data($this->file);
	}

	// Install
	function install() {
		add_role($this->plugin . '_pending', __('Pending', $this->plugin), array('read' => true));
		update_option($this->plugin . '_options', $this->get_options(array_merge($this->options, $this->create_user_management_page())));
		$this->options = get_option($this->plugin . '_options');
	}

	// Create "dummy" post that will be used to show the user management page
	function create_user_management_page() {
		global $current_user, $wpdb;
		$admin_id = 0;
		if(isset($current_user->roles) && in_array('administrator', $current_user->roles) && isset($current_user->ID)) {
			$admin_id = $current_user->ID;
		}
		else {
			$admin = $wpdb->get_row('SELECT ' . $wpdb->users . '.ID FROM ' . $wpdb->users . ' WHERE (SELECT ' . $wpdb->usermeta . '.meta_value FROM ' . $wpdb->usermeta . ' WHERE ' . $wpdb->usermeta . '.user_id = ' . $wpdb->users . '.ID AND ' . $wpdb->usermeta . '.meta_key = "wp_capabilities") LIKE "%administrator%" LIMIT 1;');
			if(isset($admin->ID)) {
				$admin_id = $admin->ID;
			}
		}
		$user_management_page_details = array(
			'post_title' => __('User Management', $this->plugin),
			'post_name' => 'user-management',
			'post_content' => __('<h2><strong>You can NOT delete this page!</strong></h2><p>This page belongs to the <a href="http://th23.net/' . $this->slug . '/">th23 User Management</a> plugin and is required by it to work properly!</p><p>Don\'t worry...your visitors on the site will never see this text - it will be replaced by the appropriate page, e.g. the registration page, the edit profile page, etc.</p><p><span style="color: #c0c0c0;">Copyright 2015, Thorsten Hartmann (th23)</span></p>', $this->plugin),
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_author' => $admin_id,
			'comment_status' => 'closed'
		);
		return array('page_id' => wp_insert_post($user_management_page_details));
	}

	// If the user management page "dummy" is deleted, trashed or un-published create a new one
	function delete_user_management_page($item_id) {
		if($item_id == $this->options['page_id']) {
			$this->install();
		}
	}
	function unpublish_user_management_page($new_status, $old_status, $post) {
		if($post->ID == $this->options['page_id'] && $new_status != 'publish') {
			wp_delete_post($this->options['page_id'], true);
		}
	}

	// User role "Pending" should not be choosable as "New User Default Role" on "General Settings" and plugin settings page
	// Note: "Pending" role will be assigned to users missing mail validation or admin approval!
	function hide_user_role_pending($roles) {
		unset($roles[$this->plugin . '_pending']);
		return $roles;
	}

	// Uninstall
	function uninstall() {
		// NOTICE: To keep all settings etc in case user wants to reactivate and not to start from scratch following lines are commented out!
		// delete_option($this->plugin . '_options');
		// NOTICE: We keep this role - otherwise unapproved/unvalidated users have no role assigned and will have access, role will become visible normally in admin area
		// remove_role($this->plugin . '_pending');
		remove_action('after_delete_post', array(&$this, 'delete_user_management_page'));
		wp_delete_post($this->options['page_id'], true);
	}

	// Modify display of plugin row in plugin overview page
	// Note: CSS styling needs to be "hardcoded" here as plugin CSS might not be loaded (e.g. when plugin deactivated) - and standard WordPress classes trigger repositioning of notices
	function settings_link($links, $file) {
		if(plugin_basename($this->file) !== $file) {
			return $links;
		}
		
		// Enhance version number with edition details
		if(!empty($this->pro)) {
			$links[0] = $links[0] . ' <span class="' . $this->slug . '-admin-professional" style="font-weight: bold; font-style: italic; color: #336600;">' . __('Professional', $this->plugin) . '</span>';
		}
		else {
			$upgrade = ($this->requirements) ? ' - <a href="http://th23.net/' . $this->slug . '/" title="' . __('Get additional functionality', $this->plugin) . '" class="' . $this->slug . '-admin-get-professional" style="color: #CC3333; font-weight: bold;">' . __('Upgrade to <i>Professional</i> version', $this->plugin) . '</a>' : '';
			$links[0] = $links[0] . ' <span class="' . $this->slug . '-admin-basic" style="font-style: italic;">' . __('Basic', $this->plugin) . '</span>' . $upgrade;
		}		

		$notice = '';
		// Check plugin requirements - show warning, if requirements are not met
		if(!$this->requirements) {
			$notice .= '<div style="margin: 1em 0; padding: 5px 10px; background-color: #FFFFFF; border-left: 4px solid #FFBA00; box-shadow: 0 1px 1px 0 rgba(0, 0, 0, 0.1);">' . sprintf(__('<strong>Warning</strong>: This plugin might not work properly on your installation - please check <a href="%s">Settings page</a> for details', $this->plugin), $this->settings_base_url) . '</div>';
		}
		// Check PRO file is matching version to rest of plugin
		if(!empty($this->pro) && $this->pro_version != $this->plugin_data['Version']) {
			$notice .= '<div style="margin: 1em 0; padding: 5px 10px; background-color: #FFFFFF; border-left: 4px solid #DD3D36; box-shadow: 0 1px 1px 0 rgba(0, 0, 0, 0.1);">' . sprintf(__('<strong>Error</strong>: The version of the <strong><i>Professional</i></strong> file (<i>%s-pro.php</i>, version %s) does not match with the overall plugin (version %s) - please make sure you update the overall plugin to the latest version e.g. via the <a href="update-core.php">automatic update function</a> and upload the latest version of the <strong><i>Professional</i></strong> file from <a href="http://th23.net/%s/">th23.net</a> onto your webserver', $this->plugin), $this->slug, $this->pro_version, $this->plugin_data['Version'], $this->slug) . '</div>';
		}

		// Add settings link - and notices afterwards
		$links[] = '<a href="' . $this->settings_base_url . '">' . __('Settings', $this->plugin) . '</a>' . $notice;

		return $links;
	
	}

	// Register admin JS and CSS
	function register_admin_js_css() {
		wp_register_script($this->slug . '-admin-js', plugins_url('/' . $this->slug . '-admin.js', $this->file));
		wp_register_style($this->slug . '-admin-css', plugins_url('/' . $this->slug . '-admin.css', $this->file));
	}
	
	// Register admin page in admin menu/ prepare loading admin JS and CSS
	function add_admin() {
		$page = add_submenu_page($this->settings_base, $this->plugin_data['Name'], $this->plugin_data['Name'], $this->settings_permission, $this->slug, array(&$this, 'show_admin'));
		add_action('admin_print_scripts-' . $page, array(&$this, 'load_admin_js'));
		add_action('admin_print_styles-' . $page, array(&$this, 'load_admin_css'));
	}

	// Load admin JS
	function load_admin_js() {
        wp_enqueue_script('jquery');
		wp_enqueue_script($this->slug . '-admin-js');
		wp_localize_script($this->slug . '-admin-js', 'tumadminJSlocal', array('approve' => __('Approve', $this->plugin)));
	}

	// Load admin CSS
	function load_admin_css() {
		wp_enqueue_style($this->slug . '-admin-css');
	}

	// Show admin page
	function show_admin() {

		global $wpdb;

		// Open wrapper and show plugin header
		echo '<div class="wrap">';
		echo '<img class="' . $this->slug . '-admin-icon" src="' . plugins_url('/img/admin-icon-48x41.png', $this->file) . '" /><h2>' . $this->plugin_data['Name'] . '</h2>';

		// Requirement details - warn user if requirements are not met
		if(is_multisite()) {
			echo '<div class="notice notice-warning"><p><strong>' . __('Warning', $this->plugin) . '</strong>: ' . __('This plugin is not designed to work on a multisite setup - it is recommended not to use this plugin in such an environment', $this->plugin) . '</p></div>';
		}

		// Display PRO information - if all requirements are met
		if(!$this->pro && $this->requirements) {
			echo '<div class="notice notice-info ' . $this->slug . '-admin-notice-upgrade" style="background-image: url(\'' . plugins_url('/img/admin-notice-upgrade-450x150.png', $this->file) . '\')"><p>' . __('You are currently using the free version of this plugin, in which we support a lot of very useful features to give your users a better feel browsing your site. To even improve that experience further, there is a <strong><i>Professional</i></strong> version available adding some equally useful features:', $this->plugin) . '<ul><li>' . __('<strong>All user management actions available on frontend</strong> styled according to theme - including profile changes, lost password, reset password', $this->plugin) . '</li><li>' . __('<strong>Access to the unstyled admin area can be restricted</strong> based on user groups - "wp-login.php" can be disabled completely', $this->plugin) . '</li><li>' . __('<strong>User chosen password upon registration</strong> option available - including initial e-mail validation', $this->plugin) . '</li><li>' . __('<strong>Admin approval for new users</strong> option available - before user can login', $this->plugin) . '</li><li>' . __('<strong>Use reCaptcha against spam and bots</strong> upon registration, lost password and login', $this->plugin) . '</li><li>' . __('Introduction of e-mail re-validation upon changes of address', $this->plugin) . '</li></ul>' . sprintf(__('Get all these additional features - %supgrade to the <i>Professional</i> version today%s', $this->plugin), '<a href="http://th23.net/' . $this->slug . '/" class="' . $this->slug . '-admin-notice-upgrade">', '</a>') . '</p></div>';
		}
		
		// === UPDATE ===

		// Do update of plugin options if required
		if(isset($_POST[$this->slug . '-options-submit'])) {
			check_admin_referer($this->plugin . '_settings', $this->slug . '-settings-nonce');
			$new_options = $this->get_options(array(), true);
			// IMPORTANT: Keep page_id static, as instanciated upon activation!
			$new_options['page_id'] = $this->options['page_id'];
			$update_done = false;
			if($new_options != $this->options) {
				update_option($this->plugin . '_options', $new_options);
				$update_done = true;
				$this->options = $new_options;
			}
			// Note: Following some options regarding user registration are handled specially, as they usually are set via Options - General
			$users_can_register = ((isset($_POST['users_can_register'])) ? 1 : 0);
			if(get_option('users_can_register') != $users_can_register) {
				update_option('users_can_register', $users_can_register);
				$update_done = true;
			}
			if(get_option('default_role') != $_POST['default_role'] && get_role($_POST['default_role'])) {
				update_option('default_role', $_POST['default_role']);
				$update_done = true;
			}
			// Show update message
			if($update_done) {
				echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Done', $this->plugin) . '</strong>: ' . __('Settings saved', $this->plugin) . '</p><button class="notice-dismiss" type="button"></button></div>';
			}
		}

		// Warn the user if user management "dummy" page is not found
		$user_management_page = get_page($this->options['page_id']);
		if(!isset($user_management_page) || !isset($user_management_page->post_status) || $user_management_page->post_status != 'publish') {
			// Note: Call both to be sure, we remove any unpublished / trashed older version AND create a properly registered new one!
			wp_delete_post($this->options['page_id'], true);
			$this->install();
			echo '<div class="notice notice-error"><p><strong>' . __('Error', $this->plugin) . '</strong>: ' . __('No valid/ published user management page has been found - it was automatically attempted to create a new one, please refresh this page and see if the error persists', $this->plugin) . '</p></div>';
		}

		// Warn the user if reCAPTCHA is activated, but no keys are defined
		if($this->options['captcha'] && (empty($this->options['captcha_public']) || empty($this->options['captcha_private']))) {
			echo '<div class="notice notice-error"><p><strong>' . __('Error', $this->plugin) . '</strong>: ' . __('reCAPTCHA requires a public and a private key to work - despite your settings it will be disabled until you define them, see settings below for information on how obtain these keys', $this->plugin) . '</p></div>';
		}

		// === SETTINGS ===
		
		$disabled = ($this->pro) ? '' : ' disabled="disabled"';
		
		echo '<form method="post" action="' . esc_url($this->settings_base_url) . '">';
		echo '<table class="form-table"><tbody>';

		echo '<tr valign="top"><th class="' . $this->slug . '-admin-section" colspan="2">' . __('General', $this->plugin) . '</th></tr>';

		// overlay_time
		echo '<tr valign="top">';
		echo ' <th scope="row"><label for="overlay_time">' . __('Overlay message time', $this->plugin) . '</label></th>';
		echo ' <td><input type="text" class="small-text" value="' . esc_attr($this->options['overlay_time']) . '" id="overlay_time" name="overlay_time" /><br /><span class="description">' . __('Duration in seconds until overlay messages shown to the user upon login/ logout disappears automatically - set to \'0\' for no automatic disappearance - Note: Error messages will never disappear automatically', $this->plugin) . '</span></td>';
		echo '</tr>';

		echo '<tr valign="top"><th class="' . $this->slug . '-admin-section" colspan="2">' . __('Access', $this->plugin) . '</th></tr>';

		// admin_access
		echo '<tr valign="top">';
		echo ' <th scope="row"><label for="admin_access">' . __('Access to admin area for', $this->plugin) . '</label></th>';
		echo ' <td><select class="postform" id="admin_access" name="admin_access"' . $disabled . '>';
		$options = array(
			'install_themes' => __('Admins only', $this->plugin),
			'edit_others_posts' => __('Admins and Editors only', $this->plugin),
			'publish_posts' => __('Admins, Editors and Authors only', $this->plugin),
			'edit_posts' => __('Admins, Editors, Authors and Contributors only [recommended]', $this->plugin),
			'default' => __('All registered users (Admins, Editors, Authors, Contributors and Subscribers) [WordPress default]', $this->plugin)
		);
		foreach($options as $value => $title) {
			echo '<option value="' . $value . '"' . (($this->options['admin_access'] == $value) ? ' selected="selected"' : '') . '>' . $title . '</option>';
		}
		echo ' </select><br /><span class="description">' . __('Users not allowed to access the admin area will get an error message - forcing them to return to the home page of your site', $this->plugin) . '</span></td>';
		echo '</tr>';

		// admin_bar
		echo '<tr valign="top">';
		echo ' <th scope="row"><label for="admin_bar">' . __('Show admin bar for', $this->plugin) . '</label></th>';
		echo ' <td><select class="postform" id="admin_bar" name="admin_bar"' . $disabled . '>';
		$options = array(
			'admin_access' => __('Groups having access to the admin area only [recommended]', $this->plugin),
			'install_themes' => __('Admins only', $this->plugin),
			'edit_others_posts' => __('Admins and Editors only', $this->plugin),
			'publish_posts' => __('Admins, Editors and Authors only', $this->plugin),
			'edit_posts' => __('Admins, Editors, Authors and Contributors only', $this->plugin),
			'default' => __('All registered users (Admins, Editors, Authors, Contributors and Subscribers) [WordPress default]', $this->plugin),
			'disable' => __('No one (disable admin bar)', $this->plugin)
		);
		foreach($options as $value => $title) {
			echo '<option value="' . $value . '"' . (($this->options['admin_bar'] == $value) ? ' selected="selected"' : '') . '>' . $title . '</option>';
		}
		echo ' </select><br /><span class="description">' . __('Defines which users will see the admin bar on the frontend of your site', $this->plugin) . '</span></td>';
		echo '</tr>';

		// allow_wplogin
		echo '<tr valign="top">';
		echo ' <th scope="row">' . __('Allow <code>wp-login.php</code>', $this->plugin) . '</th>';
		echo ' <td><fieldset><label for="allow_wplogin"><input type="checkbox" value="1" id="allow_wplogin" name="allow_wplogin"' . (($this->options['allow_wplogin']) ? ' checked="checked"' : '') . $disabled . '/> ' . __('Allow access to <code>wp-login.php</code>', $this->plugin) . '</label><br /><span class="description">' . __('<strong>Warning</strong>: Allowing access to <code>wp-login.php</code> allows users to circumvent some settings below, e.g. mail validation, admin approval, captcha - it is <strong>strongly recomended to leave this unchecked</strong>!', $this->plugin) . '</span></fieldset></td>';
		echo '</tr>';

		echo '<tr valign="top"><th class="' . $this->slug . '-admin-section" colspan="2">' . __('Registration', $this->plugin) . '</th></tr>';

		// users_can_register
		echo '<tr valign="top">';
		echo ' <th scope="row">' . __('Membership', $this->plugin) . '</th>';
		echo ' <td><fieldset><label for="users_can_register"><input name="users_can_register" type="checkbox" id="users_can_register" value="1"' . ((get_option('users_can_register')) ? ' checked="checked"' : '') . '/> ' . __('New users can register', $this->plugin) . '</label><br /><span class="description">' . __('This setting changes the standard defined under Settings - General', $this->plugin) . '</span></fieldset></td>';
		echo '</tr>';

		$sub_users_can_register_class = ($this->pro) ? ' class="user-registration-settings"' : '';
		$sub_users_can_register = ($this->pro && !get_option('users_can_register')) ? ' style="display: none;"' : '';

		// password_user
		echo '<tr valign="top"' . $sub_users_can_register_class . $sub_users_can_register . '>';
		echo ' <th scope="row" style="padding-left: 20px;">' . __('Password selection', $this->plugin) . '</th>';
		echo ' <td><fieldset><label for="password_user"><input type="checkbox" value="1" id="password_user" name="password_user"' . (($this->options['password_user']) ? ' checked="checked"' : '') . $disabled . '/> ' . __('Allow user to choose password upon registration - will require user to validate his mail address', $this->plugin) . '</label></fieldset></td>';
		echo '</tr>';

		// user_approval
		echo '<tr valign="top"' . $sub_users_can_register_class . $sub_users_can_register . '>';
		echo ' <th scope="row" style="padding-left: 20px;">' . __('User approval', $this->plugin) . '</th>';
		echo ' <td><fieldset><label for="user_approval"><input type="checkbox" value="1" id="user_approval" name="user_approval"' . (($this->options['user_approval']) ? ' checked="checked"' : '') . $disabled . '/> ' . __('Newly registered users require approval by an admin before being allowed to login', $this->plugin) . '</label></fieldset></td>';
		echo '</tr>';

		$sub_user_approval_class = ($this->pro) ? ' class="user-approval-settings"' : '';
		$sub_user_approval = ($this->pro && (!get_option('users_can_register') || !$this->options['user_approval'])) ? ' style="display: none;"' : '';

		// registration_question
		echo '<tr valign="top"' . $sub_user_approval_class . $sub_user_approval . '>';
		echo ' <th scope="row" style="padding-left: 40px;"><label for="registration_question">' . __('Registration question', $this->plugin) . '</label></th>';
		echo ' <td><input type="text" class="regular-text" value="' . esc_attr($this->options['registration_question']) . '" id="registration_question" name="registration_question"' . $disabled . '/><br /><span class="description">' . __('Question to request additional information from users upon registration, e.g. "How did you find out about this page?" to determine if the request for approving a newly registered user is valid or should be denied - leave empty to not show any question', $this->plugin) . '</span></td>';
		echo '</tr>';
		
		// default_role
		echo '<tr valign="top"' . $sub_user_approval_class . $sub_user_approval . '>';
		echo ' <th scope="row" style="padding-left: 40px;"><label for="default_role">' . __('New User Default Role', $this->plugin) . '</label></th>';
		echo ' <td><select name="default_role" id="default_role">';
		wp_dropdown_roles(get_option('default_role'));
		echo ' </select><br /><span class="description">' . __('In case admin approval is required for new users, they will be assigned to this selection after the approval has been granted by an admin!<br/>This setting changes the standard defined under Settings - General', $this->plugin) . '</span></td>';
		echo '</tr>';

		// approver_mail
		echo '<tr valign="top"' . $sub_user_approval_class . $sub_user_approval . '>';
		echo ' <th scope="row" style="padding-left: 40px;"><label for="approver_mail">' . __('Approver e-mail', $this->plugin) . '</label></th>';
		echo ' <td><input type="text" class="regular-text" value="' . esc_attr($this->options['approver_mail']) . '" id="approver_mail" name="approver_mail"' . $disabled . '/><br /><span class="description">' . __('Provide mail address of the approver, to receive notification mails on each new user registration pending approval - leave empty to receive no notifications', $this->plugin) . '</span></td>';
		echo '</tr>';
		
		echo '<tr valign="top"><th class="' . $this->slug . '-admin-section" colspan="2">' . __('Security', $this->plugin) . '</th></tr>';

		// captcha
		echo '<tr valign="top">';
		echo ' <th scope="row">' . __('Enable <i>reCaptcha</i>', $this->plugin) . '</th>';
		echo ' <td><fieldset><label for="captcha"><input type="checkbox" value="1" id="captcha" name="captcha"' . (($this->options['captcha']) ? ' checked="checked"' : '') . $disabled . '/> ' . __('Enable usage of <i>reCaptcha</i> for better protection against spam and bot registrations - Note: This is a service provided by <i>Google</i> and requires <a href="https://www.google.com/recaptcha/intro/index.html">signing up for a free key</a>', $this->plugin) . '</label></fieldset></td>';
		echo '</tr>';

		$sub_captcha_class = ($this->pro) ? ' class="captcha-settings"' : '';
		$sub_captcha = ($this->pro && (!$this->options['captcha'])) ? ' style="display: none;"' : '';

		// captcha_public
		echo '<tr valign="top"' . $sub_captcha_class . $sub_captcha . '>';
		echo ' <th scope="row" style="padding-left: 20px;"><label for="captcha_public">' . __('Public key', $this->plugin) . '</label></th>';
		echo ' <td><input type="text" class="regular-text" value="' . esc_attr($this->options['captcha_public']) . '" id="captcha_public" name="captcha_public"' . $disabled . '/><br /><span class="description">' . __('Required, public <i>reCaptcha</i> key - see above link to obtain a key', $this->plugin) . '</span></td>';
		echo '</tr>';

		// captcha_private
		echo '<tr valign="top"' . $sub_captcha_class . $sub_captcha . '>';
		echo ' <th scope="row" style="padding-left: 20px;"><label for="captcha_private">' . __('Private key', $this->plugin) . '</label></th>';
		echo ' <td><input type="text" class="regular-text" value="' . esc_attr($this->options['captcha_private']) . '" id="captcha_private" name="captcha_private"' . $disabled . '/><br /><span class="description">' . __('Required, private <i>reCaptcha</i> key - see above link to obtain a key', $this->plugin) . '</span></td>';
		echo '</tr>';

		// captcha_register
		echo '<tr valign="top"' . $sub_captcha_class . $sub_captcha . '>';
		echo ' <th scope="row" style="padding-left: 20px;">' . __('Use captcha upon registration', $this->plugin) . '</th>';
		echo ' <td><fieldset><label for="captcha_register"><input type="checkbox" value="1" id="captcha_register" name="captcha_register"' . (($this->options['captcha_register']) ? ' checked="checked"' : '') . $disabled . '/> ' . __('Users need to solve a captcha upon registering for a new account', $this->plugin) . '</label></fieldset></td>';
		echo '</tr>';

		// captcha_lostpassword
		echo '<tr valign="top"' . $sub_captcha_class . $sub_captcha . '>';
		echo ' <th scope="row" style="padding-left: 20px;">' . __('Use captcha upon lost password', $this->plugin) . '</th>';
		echo ' <td><fieldset><label for="captcha_lostpassword"><input type="checkbox" value="1" id="captcha_lostpassword" name="captcha_lostpassword"' . (($this->options['captcha_lostpassword']) ? ' checked="checked"' : '') . $disabled . '/> ' . __('Users need to solve a captcha upon requesting a password reset', $this->plugin) . '</label></fieldset></td>';
		echo '</tr>';

		// captcha_login
		echo '<tr valign="top"' . $sub_captcha_class . $sub_captcha . '>';
		echo ' <th scope="row" style="padding-left: 20px;"><label for="captcha_login">' . __('Use captcha upon login', $this->plugin) . '</label></th>';
		echo ' <td><input type="text" class="small-text" value="' . esc_attr($this->options['captcha_login']) . '" id="captcha_login" name="captcha_login"' . $disabled . '/><br /><span class="description">' . __('Specify at which attempt (unsuccessful, in a row) users need to solve a captcha upon login - set to \'0\' to disable, set to e.g. \'4\' for allowing three attempts without captcha', $this->plugin) . '</span></td>';
		echo '</tr>';

		echo '</tbody></table>';
		echo '<br/>';
		
		// submit
		echo '<input type="submit" name="' . $this->slug . '-options-submit" class="button-primary" value="' . esc_attr(__('Save Changes')) . '"/>';
		wp_nonce_field($this->plugin . '_settings', $this->slug . '-settings-nonce');
		
		echo '</form>';
		echo '<br/>';

		// Plugin information
		if($this->pro) {
			$version_details = ' <span class="' . $this->slug . '-admin-professional">' . __('Professional', $this->plugin) . '</span>';
			$about_link = '<div class="' . $this->slug . '-admin-about-feedback"><a href="' . esc_url('http://th23.net/' . $this->slug) . '" title="' . __('We like to hear your feedback...', $this->plugin) . '"><img src="' . plugins_url('/img/admin-about-feedback-309x100.png', $this->file) . '" /></a></div>';
		}
		else {
			$version_details = ' <span class="' . $this->slug . '-admin-basic">' . __('Basic', $this->plugin) . '</span> - <a href="' . esc_url('http://th23.net/' . $this->slug) . '" title="' . __('Get additional functionality', $this->plugin) . '" class="' . $this->slug . '-admin-about-upgrade">' . __('Upgrade to <i>Professional</i> version', $this->plugin) . '</a>';
			$about_link = '<div class="' . $this->slug . '-admin-about-upgrade"><a href="' . esc_url('http://th23.net/' . $this->slug) . '" title="' . __('Get additional functionality', $this->plugin) . '"><img src="' . plugins_url('/img/admin-about-upgrade-275x70.png', $this->file) . '" /></a></div>';
		}		
		echo '<div class="' . $this->slug . '-admin-about notice notice-info"><p><strong>' . $this->plugin_data['Name'] . '</strong> | ' . sprintf(__('Version %s'), $this->plugin_data['Version']) . $version_details . ' | ' . sprintf(__('By %s'), $this->plugin_data['Author']) . ' | ' . sprintf('<a href="%s">%s</a>', esc_url($this->plugin_data['PluginURI']), __('Visit plugin site')) . '</p>' . $about_link . '</div>';

		// Close wrapper
		echo '</div>';

	}

}

?>