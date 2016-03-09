<?php

/*
 * Plugin Name: Role Quick Changer
 * Description: Allows the admin to easily and seamlessly switch user role privileges without ever logging out
 * Author: Joel Worsham
 * Author URI: http://joelworsham.com
 * Version: 0.1.2
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
	 * @since 0.1.0
	 */
	class RQC {

		/**
		 * The plugin version.
		 *
		 * @since 0.1.0
		 */
		public $version = '0.1.2';

		/**
		 * The current user's default role.
		 *
		 * @since 0.1.0
		 */
		private $current_role;

		/**
		 * The role to change the current user's to.
		 *
		 * @since 0.1.0
		 */
		private $new_role;

		/**
		 * The new role's capabilities.
		 *
		 * @since {{VERSION}}
		 *
		 * @var array
		 */
		private $new_role_caps;

		/**
		 * The main construct function that launches all the magical fun.
		 *
		 * @since 0.1.0
		 */
		function __construct() {

			add_action( 'init', array( $this, 'setup_roles' ), 1 );
			add_action( 'init', array( $this, 'register_assets' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_node' ), 1000 );
			add_filter('show_admin_bar', array( $this, 'force_admin_bar' ), 1000 );
			add_action( 'admin_page_access_denied', array( $this, 'admin_page_access_denied' ) );

			add_filter( 'user_has_cap', array( $this, 'modify_role_capabilities' ), 1000, 4 );
		}

		/**
		 * Setup the current role, and (if set) setup the new role and capabilities.
		 *
		 * @since {{VERSION}}
		 * @access private
		 */
		function setup_roles() {

			global $current_user;

			if ( ! $current_user ) {
				$current_user = get_current_user();
			}

			if ( ! $this->current_role ) {
				$this->current_role = array_shift( $current_user->roles );
			}

			if ( ! $this->new_role ) {

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
				$role           = get_user_meta( $current_user->ID, 'rqc_current_role', true );
				$this->new_role = $role;

				if ( $role = get_role( $this->new_role ) ) {
					$this->new_role_caps = $role->capabilities;
				}
			}
		}

		/**
		 * Sets the role (capabilities) based on the chosen role.
		 *
		 * @since {{VERSION}}
		 * @access private
		 */
		function modify_role_capabilities( $user_caps ) {

			// Only allow this plugin to run for admins
			if ( $this->current_role != 'administrator' ) {
				return $user_caps;
			}

			// Return the new capablities
			if ( $this->new_role_caps ) {
				return $this->new_role_caps;
			}

			return $user_caps;
		}

		/**
		 * Registers the plugin's files and localizes some data.
		 *
		 * @since 0.1.0
		 * @access private
		 */
		function register_assets() {

			global $wp_roles;

			wp_register_script(
				'rqc-main',
				plugins_url( 'assets/js/rqc.main.min.js', __FILE__ ),
				array( 'jquery' ),
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->version,
				true
			);

			// Build our roles array
			$data = array();
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
		 * @since 0.1.0
		 * @access private
		 */
		function enqueue_assets() {

			// The main script
			wp_enqueue_script( 'rqc-main' );
		}

		/**
		 * Adds the RQC node to the admin bar.
		 *
		 * @since 0.1.0
		 * @access private
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
		 * Force the adminbar to show if a new roles is set.
		 *
		 * @since 0.1.0
		 * @access private
		 *
		 * @param $bool
		 *
		 * @return bool
		 */
		function force_admin_bar( $bool ) {

			if ( $this->new_role ) {
				return true;
			}

			return $bool;
		}

		/**
		 * Modify the death of the page when denied access.
		 *
		 * Normally, when you don't have sufficient privileges to view a page, you get a plain
		 * wp death screen. Well, if you change your role and then get that death, that's annoying.
		 * So this modifies it.
		 *
		 * @since 0.1.0
		 * @access private
		 */
		function admin_page_access_denied() {

			ob_start(); ?>
			<form method="post">
				This role (<?php echo $this->new_role; ?>) would not have sufficient permissions to view this page.
				Click <input type="submit" value="here"/> to reset role to Administrator (default).

				<input type="hidden" name="rqc" value="default" />
			</form>
			<?php $html = ob_get_clean();

			wp_die( $html );
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
 * @since 0.1
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