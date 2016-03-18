<?php
/**
 * Plugin Name: Post Meta Manager
 * Plugin URI: http://andrewnorcross.com/plugins/post-meta-manager/
 * Description: Manage post meta keys in bulk
 * Author: Andrew Norcross
 * Author http://andrewnorcross.com
 * Version: 1.0.4
 * Text Domain: post-meta-manager
 * Domain Path: languages
 * GitHub Plugin URI: https://github.com/norcross/post-meta-manager
 */
/*
 * Copyright 2012 Andrew Norcross
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see <http://www.gnu.org/licenses/>.
 *
 */

// Define our plugin base.
if ( ! defined( 'PMETA_MANAGER_BASE ' ) ) {
	define( 'PMETA_MANAGER_BASE', plugin_basename( __FILE__ ) );
}

// Define our plugin version.
if ( ! defined( 'PMETA_MANAGER_VER' ) ) {
	define( 'PMETA_MANAGER_VER', '1.0.4' );
}

/**
 * Call our class.
 */
class PMetaManager
{
	/**
	 * Static property to hold our singleton instance.
	 *
	 * @var instance
	 */
	static $instance = false;

	/**
	 * This is our constructor. There are many like it, but this one is mine.
	 */
	private function __construct() {
		add_action( 'plugins_loaded',         array( $this, 'textdomain'      )         );
		add_action( 'admin_menu',             array( $this, 'menu_settings'   )         );
		add_action( 'admin_enqueue_scripts',  array( $this, 'scripts_styles'  ), 10     );
		add_action( 'wp_ajax_key_change',     array( $this, 'key_change'      )         );
		add_action( 'wp_ajax_key_delete',     array( $this, 'key_delete'      )         );
	}

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @return $instance
	 */
	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Load our textdomain for localization.
	 *
	 * @return void
	 */
	public function textdomain() {
		load_plugin_textdomain( 'post-meta-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Load the CSS and JS for the admin display.
	 *
	 * @param  string $hook  The admin page this is loading on.
	 *
	 * @return void
	 */
	public function scripts_styles( $hook ) {

		// Bail if not on our settings page.
		if ( empty( $hook ) || ! empty( $hook ) && ! in_array( $hook, array( 'tools_page_pmm-pmeta-settings', 'users_page_pmm-umeta-settings' ) ) ) {
			return;
		}

		// Set the version and file suffix based on dev or not.
		$vers   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : PMETA_MANAGER_VER;
		$cssfx  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.css' : '.min.css';
		$jsfx   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.js' : '.min.js';

		// Load our CSS and JS.
		wp_enqueue_style( 'pmm-admin', plugins_url( '/lib/css/pmm.admin' . $cssfx, __FILE__ ), array(), $vers, 'all' );
		wp_enqueue_script( 'pmm-ajax', plugins_url( '/lib/js/pmm.ajax' . $jsfx, __FILE__ ), array( 'jquery' ), $vers, true );
		wp_localize_script( 'pmm-ajax', 'pmmAjaxData', array(
			'genError'   => __( 'There was an error. Please try again.', 'post-meta-manager' ),
		));
	}

	/**
	 * Build out settings pages
	 *
	 * @return void
	 */
	public function menu_settings() {

		// Add the menu item for post meta.
		add_submenu_page( 'tools.php', _x( 'Post Meta Manager', 'Page title', 'post-meta-manager' ), _x( 'Post Meta Manager', 'Menu title', 'post-meta-manager' ), apply_filters( 'pmeta_manager_user_cap', 'manage_options' ), 'pmm-pmeta-settings', array( $this, 'pmm_pmeta_display' ) );

		// Add the menu item for user meta.
		add_submenu_page( 'users.php', _x( 'User Meta Manager', 'Page title', 'post-meta-manager' ), _x( 'User Meta Manager', 'Menu title', 'post-meta-manager' ), apply_filters( 'pmeta_manager_user_cap', 'manage_options' ), 'pmm-umeta-settings', array( $this, 'pmm_umeta_display' ) );
	}

	/**
	 * Run AJAX key change processing.
	 *
	 * @return void
	 */
	public function key_change() {

		// Only run this on the admin side.
		if ( ! is_admin() ) {
			die();
		}

		// Verify request came from authorized location.
		check_ajax_referer( 'pmm-change', 'nonce' );

		// Get keys from POST.
		$old    = ! empty( $_POST['keyold'] ) ? sanitize_key( $_POST['keyold'] ) : false;
		$new    = ! empty( $_POST['keynew'] ) ? sanitize_key( $_POST['keynew'] ) : false;

		// Get our table name with fallback.
		$table  = ! empty( $_POST['table'] ) && in_array( sanitize_key( $_POST['table'] ), array( 'postmeta', 'usermeta' ) ) ? sanitize_key( $_POST['table'] ) : 'postmeta';

		// Set up return array for ajax responses.
		$ret = array();

		// Missing both keys? bail.
		if ( false === $old && false === $new ) {
			$ret['success'] = false;
			$ret['errcode'] = 'NO_KEYS';
			$ret['message'] = __( 'You must enter a value in this field', 'post-meta-manager' );
			echo json_encode( $ret );
			die();
		}

		// Missing old key? bail.
		if ( false === $old ) {
			$ret['success'] = false;
			$ret['errcode'] = 'NO_KEY_OLD';
			$ret['message'] = __( 'You must enter a value in this field', 'post-meta-manager' );
			echo json_encode( $ret );
			die();
		}

		// Missing new key? bail.
		if ( false === $new ) {
			$ret['success'] = false;
			$ret['errcode'] = 'NO_KEY_NEW';
			$ret['message'] = __( 'You must enter a value in this field', 'post-meta-manager' );
			echo json_encode( $ret );
			die();
		}

		// Run our query.
		$query  = self::update_key_name( $old, $new, $table );

		// No matches, return message.
		if ( 0 === $query ) {
			$ret['success'] = false;
			$ret['errcode'] = 'KEY_MISSING';
			$ret['message'] = __( 'There are no keys matching this criteria. Please try again.', 'post-meta-manager' );
			echo json_encode( $ret );
			die();
		}

		// We had matches. return the success message with a count.
		if ( 0 < $query ) {
			$ret['success'] = true;
			$ret['errcode'] = null;
			$ret['updated'] = $query;
			$ret['message'] = sprintf( _n( '%d entry has been updated.', '%d entries have been updated.', $query, 'post-meta-manager' ), $query );
			echo json_encode( $ret );
			die();
		}
	}

	/**
	 * Run AJAX key deletion processing.
	 *
	 * @return void
	 */
	public function key_delete() {

		// Only run this on the admin side.
		if ( ! is_admin() ) {
			die();
		}

		// Verify request came from authorized location.
		check_ajax_referer( 'pmm-remove', 'nonce' );

		// Get key from POST.
		$kill   = ! empty( $_POST['keykill'] ) ? sanitize_key( $_POST['keykill'] ) : false;

		// Get our table name with fallback.
		$table  = ! empty( $_POST['table'] ) && in_array( sanitize_key( $_POST['table'] ), array( 'postmeta', 'usermeta' ) ) ? sanitize_key( $_POST['table'] ) : 'postmeta';

		// Set up return array for ajax responses.
		$ret = array();

		// Missing keys? bail.
		if ( false === $kill ) {
			$ret['success'] = false;
			$ret['errcode'] = 'NO_KEY_ENTERED';
			$ret['message'] = __( 'You must enter a value in this field', 'post-meta-manager' );
			echo json_encode( $ret );
			die();
		}

		// Run the DB query.
		$query  = self::delete_key_name( $kill, $table );

		// No keys found that matched.
		if ( 0 === $query ) {
			$ret['success'] = false;
			$ret['errcode'] = 'NO_KEYS_FOUND';
			$ret['message'] = sprintf( __( 'There were no items with the %s meta key found. Please try again.', 'post-meta-manager' ), '<strong><em>' . esc_attr( $kill ) . '</em></strong>' );
			echo json_encode( $ret );
			die();
		}

		// We had keys and we deleted them.
		if ( 0 < $query ) {
			$ret['success'] = true;
			$ret['errcode'] = null;
			$ret['message'] = sprintf( _n( '%d entry has been deleted.', '%d entries have been deleted.', $query, 'post-meta-manager' ), $query );
			echo json_encode( $ret );
			die();
		}
	}

	/**
	 * Display main options page structure for post meta.
	 *
	 * @return void
	 */
	public function pmm_pmeta_display() {
		?>
		<div class="wrap">
		<h1 class="pmm-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<div class="pmm-inner">

			<div class="pm-form-content">
			<p><?php esc_html_e( 'Use the fields below to change or delete post meta keys (custom fields) in bulk.', 'post-meta-manager' ); ?></p>
			</div>

			<form class="pm-form-wrapper pm-form-wrapper-update">
			<div class="pm-form-options">
			<h3><?php esc_html_e( 'Change Meta Keys', 'post-meta-manager' ); ?></h3>

				<table class="form-table pmm-table"><tbody>
				<tr class="existing-key-row">
					<th scope="row"><?php esc_html_e( 'Existing Meta Key', 'post-meta-manager' ); ?></th>
					<td>
						<input type="text" class="regular-text meta-key-field" value="" id="meta-key-old" name="meta-key-old">
						<p id="key-old-text" class="description"><?php esc_html_e( 'Enter the current meta key name you want to change', 'post-meta-manager' ); ?></p>
					</td>
				</tr>

				<tr class="replacement-key-row">
					<th scope="row"><?php esc_html_e( 'New Meta Key Name', 'post-meta-manager' ); ?></th>
					<td>
						<input type="text" class="regular-text meta-key-field" value="" id="meta-key-new" name="meta-key-new">
						<p id="key-new-text" class="description"><?php esc_html_e( 'Enter the new meta key name you want to use', 'post-meta-manager' ); ?></p>
					</td>
				</tr>

				</tbody></table>

				<p class="process change-process">
					<input id="pmm-change" data-nonce="<?php echo wp_create_nonce( "pmm-change" ); ?>" data-tablename="postmeta" type="button" class="button button-secondary pmm-button" value="<?php esc_attr_e( 'Process Change', 'post-meta-manager' ); ?>" />
				</p>

			</div>
			</form>

			<form class="pm-form-wrapper pm-form-wrapper-delete">
			<div class="pm-form-options">
			<h3><?php esc_html_e( 'Delete Meta Keys', 'post-meta-manager' ); ?></h3>

				<table class="form-table pmm-table"><tbody>
				<tr class="removal-key-row">
					<th scope="row"><?php esc_html_e( 'Meta Key Name', 'post-meta-manager' ); ?></th>
					<td>
						<input type="text" class="regular-text meta-key-field" value="" id="meta-key-kill" name="meta-key-kill">
						<p id="key-kill-text" class="description"><?php esc_html_e( 'Enter the meta key name you want to remove', 'post-meta-manager' ); ?></p>
					</td>
				</tr>

				</tbody></table>

				<p class="process remove-process">
					<input id="pmm-remove" data-nonce="<?php echo wp_create_nonce( 'pmm-remove' ); ?>" data-tablename="postmeta" type="button" class="button button-secondary delete pmm-button" value="<?php esc_attr_e( 'Delete Keys', 'post-meta-manager' ); ?>" />
				</p>

			</div>

			</form>

			<div class="pm-form-content">
			<p class="warning"><?php esc_html_e( 'NOTE: this is not reversible! Run a backup of your database if you aren\'t comfortable doing this.', 'post-meta-manager' ); ?></p>
			</div>

		</div>
		</div>
	<?php }

	/**
	 * Display main options page structure for users meta
	 *
	 * @return void
	 */
	public function pmm_umeta_display() {
		?>

		<div class="wrap">
		<h1 class="pmm-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<div class="pmm-inner">

			<div class="pm-form-content">
			<p><?php esc_html_e( 'Use the fields below to change or delete user meta keys (custom fields) in bulk.', 'post-meta-manager' ); ?></p>
			</div>

			<form class="pm-form-wrapper pm-form-wrapper-update">
			<div class="pm-form-options">
			<h3><?php esc_html_e( 'Change Meta Keys', 'post-meta-manager' ); ?></h3>

				<table class="form-table pmm-table"><tbody>
				<tr class="existing-key-row">
					<th scope="row"><?php esc_html_e( 'Existing Meta Key', 'post-meta-manager' ); ?></th>
					<td>
						<input type="text" class="regular-text meta-key-field" value="" id="meta-key-old" name="meta-key-old">
						<p id="key-old-text" class="description"><?php esc_html_e( 'Enter the current meta key name you want to change', 'post-meta-manager' ); ?></p>
					</td>
				</tr>

				<tr class="replacement-key-row">
					<th scope="row"><?php esc_html_e( 'New Meta Key Name', 'post-meta-manager' ); ?></th>
					<td>
						<input type="text" class="regular-text meta-key-field" value="" id="meta-key-new" name="meta-key-new">
						<p id="key-new-text" class="description"><?php esc_html_e( 'Enter the new meta key name you want to use', 'post-meta-manager' ); ?></p>
					</td>
				</tr>

				</tbody></table>

				<p class="process change-process">
					<input id="pmm-change" data-nonce="<?php echo wp_create_nonce( "pmm-change" ); ?>" data-tablename="usermeta" type="button" class="button button-secondary pmm-button" value="<?php _e( 'Process Change', 'post-meta-manager' ); ?>" />
				</p>

			</div>
			</form>

			<form class="pm-form-wrapper pm-form-wrapper-delete">
			<div class="pm-form-options">
			<h3><?php esc_html_e( 'Delete Meta Keys', 'post-meta-manager' ); ?></h3>

				<table class="form-table pmm-table"><tbody>
				<tr class="removal-key-row">
					<th scope="row"><?php esc_html_e( 'Meta Key Name', 'post-meta-manager' ); ?></th>
					<td>
						<input type="text" class="regular-text meta-key-field" value="" id="meta-key-kill" name="meta-key-kill">
						<p id="key-kill-text" class="description"><?php esc_attr_e( 'Enter the meta key name you want to remove', 'post-meta-manager' ); ?></p>
					</td>
				</tr>

				</tbody></table>

				<p class="process remove-process">
					<input id="pmm-remove" data-nonce="<?php echo wp_create_nonce( 'pmm-remove' ); ?>" data-tablename="usermeta" type="button" class="button button-secondary delete pmm-button" value="<?php esc_attr_e( 'Delete Keys', 'post-meta-manager' ); ?>" />
				</p>

			</div>
			</form>

			<div class="pm-form-content">
			<p class="warning"><strong><?php esc_html_e( 'NOTE: this is not reversible! Run a backup of your database if you aren\'t comfortable doing this.', 'post-meta-manager' ); ?></strong></p>
			</div>

		</div>
		</div>

	<?php }

	/**
	 * The database function for updating DB key names.
	 *
	 * @param  string $old    The name of the old key.
	 * @param  string $new    The name of the new key.
	 * @param  string $name   The name of the DB table we are updating.
	 *
	 * @return array  $query  The resulting database query.
	 */
	protected static function update_key_name( $old = '', $new = '', $name = '' ) {

		// Make sure we have our stuff.
		if ( empty( $old ) || empty( $new ) || empty( $name ) ) {
			return false;
		}

		// Call global DB class.
		global $wpdb;

		// Set our table.
		$table  = $wpdb->$name;

		// Confirm the table exists before running any updates.
		if( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			return false;
		}

		// Prepare my query.
		$setup  = $wpdb->prepare("
			UPDATE $table
			SET meta_key = REPLACE (meta_key, %s, %s)",
			esc_sql( $old ), esc_sql( $new )
		);

		// Run SQL query.
		$query = $wpdb->query( $setup );

		// Send it back.
		return $query;
	}

	/**
	 * The database function for delete DB keys.
	 *
	 * @param  string $key    The name of the old key.
	 * @param  string $name   The name of the DB table we are updating.
	 *
	 * @return array  $query  The resulting database query.
	 */
	protected static function delete_key_name( $key = '', $name = '' ) {

		// Make sure we have our stuff.
		if ( empty( $key ) || empty( $name ) ) {
			return false;
		}

		// Call global DB class.
		global $wpdb;

		// Set our table.
		$table  = $wpdb->$name;

		// Confirm the table exists before running any updates.
		if( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			return false;
		}

		// Prepare my query.
		$setup  = $wpdb->prepare("
			DELETE FROM $table
			WHERE meta_key = %s",
			esc_sql( $key )
		);

		// Run SQL query.
		$query = $wpdb->query( $setup );

		// Send it back.
		return $query;
	}

	// End class.
}

// Instantiate our class.
$PMetaManager = PMetaManager::getInstance();
