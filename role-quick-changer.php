<?php

/*
 * Plugin Name: Role Quick Changer
 * Description: Allows the admin to easily and seamlessly switch user role privileges without ever logging out
 * Author: Joel Worsham
 * Author URI: http://joelworsham.com
 * Version: 0.1.0
 * License: GPU
 */

// Make sure the class doesn't already exist
if ( ! class_exists( 'RQC' ) ) {

	/**
	 * Class RQC
	 *
	 * The main class of Role Quick Changer.
	 *
	 * @package WordPress
	 * @subpackage Role Quick Changer
	 *
	 * @since Role Quick Changer 0.1.0
	 */
	class RQC {

		/**
		 * The plugin version.
		 *
		 * @since Role Quick Changer 0.1.0
		 */
		public $version = '0.1.0';

		/**
		 * The current user's default role.
		 *
		 * @since Role Quick Changer 0.1.0
		 */
		public $current_role;

		/**
		 * The role to change the current user's to.
		 *
		 * @since Role Quick Changer 0.1.0
		 */
		public $new_role;

		/**
		 * The main construct function that launches all the magical fun.
		 *
		 * @since Role Quick Changer 0.1.0
		 */
		function __construct() {

			// Set the plugin in motion
			add_action( 'init', array( $this, 'init' ) );
		}

		/**
		 * Initializes the plugin.
		 *
		 * The reason for all of this being here, instead of being in __construct(), is so that we
		 * have access to most WP functions, namely access to the current user so that we can stop
		 * execution of the entire plugin if the current user is NOT the admin.
		 *
		 * @since Role Quick Changer 0.1.0
		 */
		function init() {

			// Get our current role
			$this->get_current_role();

			// Only allow this plugin to run for admins
			if ( $this->current_role != 'administrator' ) {
				return;
			}

			// If a new role is set, deal with it
			$this->get_new_role();

			// Modify the user object to have the new role caps
			$this->modify_role();

			// Add all of our plugin files
			$this->register_files();
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_files' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_files' ) );

			// Add the role dropdown to the menu
			add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_node' ), 999 );

			// Instead of just dieing when changing to a lesser role, die a little
			// more gracefully
			add_action( 'admin_page_access_denied', array( $this, 'admin_page_access_denied' ) );
		}

		/**
		 * Gets the current user's default role.
		 *
		 * @since Role Quick Changer 0.1.0
		 */
		public function get_current_role() {

			global $current_user;

			$user_roles = $current_user->roles;

			$user_role = array_shift( $user_roles );

			$this->current_role = $user_role;
		}

		/**
		 * Get's the new role.
		 *
		 * Either grabs the new role from POST (if it was just set by the dropdown), or grabs
		 * the saved user meta containing the role to use, OR (if all of the above fails) sets
		 * the new role to false, indicating not to change the role at all.
		 *
		 * @since Role Quick Changer 0.1.0
		 */
		public function get_new_role() {

			global $current_user;

			// If this POST variable exists, the current user just changed the drop-down
			if ( isset( $_POST['rqc'] ) ) {

				// If the POST value is "default", then just turn it off
				if ( $_POST['rqc'] == 'default' ) {

					$this->new_role = false;
					delete_user_meta( $current_user->ID, 'rqc_current_role' );

					return;
				}

				// Set the new role to the POST value and update the meta
				$this->new_role = $_POST['rqc'];
				update_user_meta( $current_user->ID, 'rqc_current_role', $this->new_role );
			}

			// Otherwise, grab the role from the current user meta
			$role = get_user_meta( $current_user->ID, 'rqc_current_role', true );

			// ...and set the new role to that, OR set it to false
			$this->new_role = $role ? $role : false;
		}

		/**
		 * Modifies the $current_user object to contain the new role's capabilities.
		 *
		 * @since Role Quick Changer 0.1.0
		 */
		public function modify_role() {

			global $current_user, $wp_roles, $super_admins;

			// If new role is set to false then don't change the role at all
			if ( ! $this->new_role ) {
				return;
			}

			// If we're changing the role to something that's not an administrator, we need to make sure
			// that we make WP think the current user is NOT super admin, because that overrides all
			// capabilities
			if ( $this->new_role != 'administrator' && is_super_admin( $current_user->ID ) ) {
				$super_admins = [ ];
			}

			// Otherwise modify the current user object
			$current_user->allcaps  = $wp_roles->roles[ $this->new_role ]['capabilities'];
			$current_user->roles[0] = strtolower( $this->new_role );
			unset( $current_user->caps[ $this->current_role ] );
			$current_user->caps[ $this->new_role ] = true;
		}

		/**
		 * Registers the plugin's files and localizes some data.
		 *
		 * @since Role Quick Changer 0.1.0
		 */
		public function register_files() {

			global $wp_roles;

			wp_register_script(
				'rqc-main',
				plugins_url( 'assets/js/rqc.main.min.js', __FILE__ ),
				array( 'jquery' ),
				$this->version,
				true
			);

			// Build our roles array
			$data = [ ];
			foreach ( $wp_roles->roles as $role_id => $role ) {

				// For the administrator role, make it "default", because that will disable
				// modifiy role (assuming the current user is the admin, which they have to
				// be to use this plugin)
				if ( $role_id == 'administrator' ) {
					$role_id = 'default';
					$role    = array(
						'name' => 'Administrator (default)',
					);
				}

				$data['roles'][] = array(
					'name'   => $role['name'],
					'id'     => $role_id,
					'active' => $role_id == $this->new_role ? true : false,
				);
			}

			// Localize our data
			wp_localize_script( 'rqc-main', 'rqc', $data );
		}

		/**
		 * Enqueues the plugin's files
		 *
		 * @since Role Quick Changer 0.1.0
		 */
		public function enqueue_files() {

			// The main script
			wp_enqueue_script( 'rqc-main' );
		}

		/**
		 * Adds the RQC node to the admin bar.
		 *
		 * @since Role Quick Changer 0.1.0
		 *
		 * @param object $wp_admin_bar The admin bar object.
		 */
		function add_admin_bar_node( $wp_admin_bar ) {

			// Do not allow anyone aside from the admin to use this
			if ( $this->current_role != 'administrator' ) {
				return;
			}

			$args = array(
				'id'    => 'rqc',
				'title' => 'Role Quick Change',
				'href'  => '#'
			);
			$wp_admin_bar->add_node( $args );
		}

		/**
		 * Modify the death of the page when denied access.
		 *
		 * Normally, when you don't have sufficient privileges to view a page, you get a plain
		 * wp death screen. Well, if you change your role and then get that death, that's annoying.
		 * So this modifies it.
		 *
		 * @since Role Quick Changer 0.1.0
		 */
		public function admin_page_access_denied() {

			wp_die( "<form method='post'>This role ($this->new_role) would not have sufficient permissions to view this page. Click <input type='submit' value='here' /> to reset role to Administrator (default).<input type='hidden' name='rqc' value='default' /></form>" );
		}
	}

	$RQC = new RQC();

} else {

	// Something is wrong
	add_action( 'admin_notices', 'rqc_notice' );
}

/**
 * Notifies the user that something is conflicting.
 *
 * @since Role Quick Changer 0.1
 */
function rqc_notice() {

	?>
	<div class="error">
		<p>
			There seems to be something conflicting with Role Quick Changer. Try deactivating other plugins or changing
			the theme to see if the problem persists.
		</p>
	</div>
<?php
}