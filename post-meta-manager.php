<?php
/*
Plugin Name: Post Meta Manager
Plugin URI: http://andrewnorcross.com/plugins/post-meta-manager/
Description: Manage post meta keys in bulk
Version: 1.0.2
Author: norcross
Author URI: http://andrewnorcross.com
License: GPL v2

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if( ! defined( 'PMETA_MANAGER_BASE ' ) ) {
	define( 'PMETA_MANAGER_BASE', plugin_basename(__FILE__) );
}

if( ! defined( 'PMETA_MANAGER_VER' ) ) {
	define( 'PMETA_MANAGER_VER', '1.0.2' );
}

// Start up the engine
class PMetaManager
{
	/**
	 * Static property to hold our singleton instance
	 * @var PostMetaManager
	 */
	static $instance = false;


	/**
	 * This is our constructor, which is private to force the use of
	 * getInstance() to make this a Singleton
	 *
	 * @return PostMetaManager
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
	 * @return PostMetaManager
	 */
	public static function getInstance() {
		if ( !self::$instance )
			self::$instance = new self;
		return self::$instance;
	}

	/**
	 * [textdomain description]
	 * @return [type] [description]
	 */
	public function textdomain() {

		load_plugin_textdomain( 'post-meta-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Scripts and stylesheets
	 *
	 * @return PostMetaManager
	 */
	public function scripts_styles( $hook ) {

		// bail if not on our settings page
		if ( empty( $hook ) || ! empty( $hook ) && ! in_array( $hook, array( 'tools_page_pmm-pmeta-settings', 'users_page_pmm-umeta-settings' ) ) ) {
			return;
		}

		// load our CSS and JS
		wp_enqueue_style( 'pmm-admin', plugins_url('/lib/css/pmm.admin.css', __FILE__), array(), PMETA_MANAGER_VER, 'all' );
		wp_enqueue_script( 'pmm-ajax', plugins_url('/lib/js/pmm.ajax.js', __FILE__) , array('jquery'), PMETA_MANAGER_VER, true );
		wp_localize_script( 'pmm-ajax', 'pmmAjaxData', array(
			'genError'   => __( 'There was an error. Please try again.', 'post-meta-manager' ),
		));
	}

	/**
	 * build out settings page
	 *
	 * @return PostMetaManager
	 */
	public function menu_settings() {
		// add the menu item for post meta
		add_submenu_page( 'tools.php', _x( 'Post Meta Manager', 'Page title', 'post-meta-manager' ), _x( 'Post Meta Manager', 'Menu title', 'post-meta-manager' ), apply_filters( 'pmeta_manager_user_cap', 'manage_options' ), 'pmm-pmeta-settings', array( $this, 'pmm_pmeta_display' ) );

		// add the menu item for user meta
		add_submenu_page( 'users.php', _x( 'User Meta Manager', 'Page title', 'post-meta-manager' ), _x( 'User Meta Manager', 'Menu title', 'post-meta-manager' ), apply_filters( 'pmeta_manager_user_cap', 'manage_options' ), 'pmm-umeta-settings', array( $this, 'pmm_umeta_display' ) );
	}

	/**
	 * run AJAX key change processing
	 *
	 * @return PostMetaManager
	 */
	public function key_change() {

		// get keys from POST
		$old    = ! empty( $_POST['keyold'] ) ? $_POST['keyold'] : false;
		$new    = ! empty( $_POST['keynew'] ) ? $_POST['keynew'] : false;

		// get our table name with fallback
		$table  = ! empty( $_POST['table'] ) && in_array( $_POST['table'], array( 'postmeta', 'usermeta' ) ) ? $_POST['table'] : 'postmeta';

		// set up return array for ajax responses
		$ret = array();

		// missing keys? bail
		if( $old === false && $new === false ) {
			$ret['success'] = false;
			$ret['errcode'] = 'NO_KEYS';
			$ret['message'] = __( 'You must enter a value in this field', 'post-meta-manager' );
			echo json_encode( $ret );
			die();
		}

		if( $old === false ) {
			$ret['success'] = false;
			$ret['errcode'] = 'NO_KEY_OLD';
			$ret['message'] = __( 'You must enter a value in this field', 'post-meta-manager' );
			echo json_encode( $ret );
			die();
		}

		if( $new === false ) {
			$ret['success'] = false;
			$ret['errcode'] = 'NO_KEY_NEW';
			$ret['message'] = __( 'You must enter a value in this field', 'post-meta-manager' );
			echo json_encode( $ret );
			die();
		}

		// run our query
		$query  = self::update_key_name( $old, $new, $table );

		// no matches, return message
		if( $query == 0 ) {
			$ret['success'] = false;
			$ret['errcode'] = 'KEY_MISSING';
			$ret['message'] = __( 'There are no keys matching this criteria. Please try again.', 'post-meta-manager' );
			echo json_encode( $ret );
			die();
		}

		// we had matches. return the success message with a count
		if( $query > 0 ) {
			$ret['success'] = true;
			$ret['errcode'] = null;
			$ret['updated'] = $query;
			$ret['message'] = sprintf( _n( '%d entry has been updated.', '%d entries have been updated.', $query, 'post-meta-manager' ), $query );
			echo json_encode( $ret );
			die();
		}
	}

	/**
	 * run AJAX key deletion processing
	 *
	 * @return PostMetaManager
	 */
	public function key_delete() {

		// get keys from POST
		$kill   = ! empty( $_POST['keykill'] ) ? $_POST['keykill'] : false;

		// get our table name with fallback
		$table  = ! empty( $_POST['table'] ) && in_array( $_POST['table'], array( 'postmeta', 'usermeta' ) ) ? $_POST['table'] : 'postmeta';

		// set up return array for ajax responses
		$ret = array();

		// missing keys? bail
		if( $kill === false ) {
			$ret['success'] = false;
			$ret['errcode'] = 'NO_KEY_ENTERED';
			$ret['message'] = __( 'You must enter a value in this field', 'post-meta-manager' );
			echo json_encode( $ret );
			die();
		}

		// run the DB query
		$query  = self::delete_key_name( $kill, $table );

		// no keys found that matched
		if( $query == 0 ) {
			$ret['success'] = false;
			$ret['errcode'] = 'NO_KEYS_FOUND';
			$ret['message'] = sprintf( __( 'There were no items with the %s meta key found. Please try again.', 'post-meta-manager' ), '<strong><em>' . esc_attr( $kill ) . '</em></strong>' );
			echo json_encode( $ret );
			die();
		}

		// we had keys and we deleted tem
		if( $query > 0 ) {
			$ret['success'] = true;
			$ret['errcode'] = null;
			$ret['message'] = sprintf( _n( '%d entry has been deleted.', '%d entries have been deleted.', $query, 'post-meta-manager' ), $query );
			echo json_encode( $ret );
			die();
		}
	}

	/**
	 * Display main options page structure for post meta
	 *
	 * @return PostMetaManager
	 */
	public function pmm_pmeta_display() { ?>
		<div class="wrap">
		<h2 class="pmm-title"><?php _ex( 'Post Meta Manager', 'Title in options page', 'post-meta-manager' ); ?></h2>
		<div class="pmm-inner">

			<div class="pm-form-content">
			<p><?php _e( 'Use the fields below to change or delete post meta keys (custom fields) in bulk.', 'post-meta-manager' ); ?></p>
			</div>

			<form>
			<div class="pm-form-options">
			<h3><?php _e( 'Change Meta Keys', 'post-meta-manager' ); ?></h3>

				<table class="form-table pmm-table"><tbody>
				<tr class="existing-key-row">
					<th><?php _e( 'Existing Meta Key', 'post-meta-manager' ); ?></th>
					<td>
						<input type="text" class="regular-text meta-key-field" value="" id="meta-key-old" name="meta-key-old">
						<span id="key-old-text" class="description"><?php _e( 'Enter the current meta key name you want to change', 'post-meta-manager' ); ?></span>
					</td>
				</tr>

				<tr class="replacement-key-row">
					<th><?php _e( 'New Meta Key Name', 'post-meta-manager' ); ?></th>
					<td>
						<input type="text" class="regular-text meta-key-field" value="" id="meta-key-new" name="meta-key-new">
						<span id="key-new-text" class="description"><?php _e( 'Enter the new meta key name you want to use', 'post-meta-manager' ); ?></span>
					</td>
				</tr>

				</tbody></table>

				<p class="process change-process">
					<input id="pmm-change" data-tablename="postmeta" type="button" class="button button-secondary pmm-button" value="<?php _e( 'Process Change', 'post-meta-manager' ); ?>" />
				</p>

			</div>
			</form>

			<form>
			<div class="pm_form_options">
			<h3><?php _e( 'Delete Meta Keys', 'post-meta-manager' ); ?></h3>

				<table class="form-table pmm-table"><tbody>
				<tr class="removal-key-row">
					<th><?php _e( 'Meta Key Name', 'post-meta-manager' ); ?></th>
					<td>
						<input type="text" class="regular-text meta-key-field" value="" id="meta-key-kill" name="meta-key-kill">
						<span id="key-kill-text" class="description"><?php _e( 'Enter the meta key name you want to remove', 'post-meta-manager' ); ?></span>
					</td>
				</tr>

				</tbody></table>

				<p class="process remove-process">
				<input id="pmm-remove" data-tablename="postmeta" type="button" class="button button-secondary pmm-button" value="<?php _e( 'Delete Keys', 'post-meta-manager' ); ?>" />
				</p>

			</div>

			</form>

			<div class="pm-form-content">
			<p class="warning"><strong><?php _e( 'NOTE: this is not reversible! Run a backup of your database if you aren\'t comfortable doing this.', 'post-meta-manager' ); ?></strong></p>
			</div>

		</div>
		</div>
	<?php }

	/**
	 * Display main options page structure for users meta
	 *
	 * @return PostMetaManager
	 */
	public function pmm_umeta_display() { ?>

		<div class="wrap">
		<div class="icon32" id="icon-pmm"><br></div>
		<h2 class="pmm-title"><?php _e( 'User Meta Manager', 'post-meta-manager' ); ?></h2>
		<div class="pmm-inner">

			<div class="pm-form-content">
			<p><?php _e( 'Use the fields below to change or delete post meta keys (custom fields) in bulk.', 'post-meta-manager' ); ?></p>
			</div>

			<form>
			<div class="pm-form-options">
			<h3><?php _e( 'Change Meta Keys', 'post-meta-manager' ); ?></h3>

				<table class="form-table pmm-table"><tbody>
				<tr class="existing-key-row">
					<th><?php _e( 'Existing Meta Key', 'post-meta-manager' ); ?></th>
					<td>
						<input type="text" class="regular-text meta-key-field" value="" id="meta-key-old" name="meta-key-old">
						<span id="key-old-text" class="description"><?php _e( 'Enter the current meta key name you want to change', 'post-meta-manager' ); ?></span>
					</td>
				</tr>

				<tr class="replacement-key-row">
					<th><?php _e( 'New Meta Key Name', 'post-meta-manager' ); ?></th>
					<td>
						<input type="text" class="regular-text meta-key-field" value="" id="meta-key-new" name="meta-key-new">
						<span id="key-new-text" class="description"><?php _e( 'Enter the new meta key name you want to use', 'post-meta-manager' ); ?></span>
					</td>
				</tr>

				</tbody></table>

				<p class="process change-process">
					<input id="pmm-change" data-tablename="usermeta" type="button" class="button button-secondary pmm-button" value="<?php _e( 'Process Change', 'post-meta-manager' ); ?>" />
				</p>

			</div>
			</form>

			<form>
			<div class="pm_form_options">
			<h3><?php _e( 'Delete Meta Keys', 'post-meta-manager' ); ?></h3>

				<table class="form-table pmm-table"><tbody>
				<tr class="removal-key-row">
					<th><?php _e( 'Meta Key Name', 'post-meta-manager' ); ?></th>
					<td>
						<input type="text" class="regular-text meta-key-field" value="" id="meta-key-kill" name="meta-key-kill">
						<span id="key-kill-text" class="description"><?php _e( 'Enter the meta key name you want to remove', 'post-meta-manager' ); ?></span>
					</td>
				</tr>

				</tbody></table>

				<p class="process remove-process">
				<input id="pmm-remove" data-tablename="usermeta" type="button" class="button button-secondary pmm-button" value="<?php _e( 'Delete Keys', 'post-meta-manager' ); ?>" />
				</p>

			</div>
			</form>

			<div class="pm-form-content">
			<p class="warning"><strong><?php _e( 'NOTE: this is not reversible! Run a backup of your database if you aren\'t comfortable doing this.', 'post-meta-manager' ); ?></strong></p>
			</div>

		</div>
		</div>

	<?php }

	/**
	 * [update_key_name description]
	 * @param  string $old   [description]
	 * @param  string $new   [description]
	 * @param  string $name  [description]
	 * @return [type]        [description]
	 */
	protected static function update_key_name( $old = '', $new = '', $name = '' ) {

		// make sure we have our stuff
		if ( empty( $old ) || empty( $new ) || empty( $name ) ) {
			return false;
		}

		// call global DB class
		global $wpdb;

		// set our table
		$table  = $wpdb->$name;

		// prepare my query
		$setup  = $wpdb->prepare("
			UPDATE $table
			SET meta_key = REPLACE (meta_key, %s, %s)",
			esc_sql( $old ), esc_sql( $new )
		);

		// run SQL query
		$query = $wpdb->query( $setup );

		// send it back
		return $query;
	}

	/**
	 * [delete_key_name description]
	 * @param  string $key   [description]
	 * @param  string $name  [description]
	 * @return [type]        [description]
	 */
	protected static function delete_key_name( $key = '', $name = '' ) {

		// make sure we have our stuff
		if ( empty( $key ) || empty( $name ) ) {
			return false;
		}

		// call global DB class
		global $wpdb;

		// set our table
		$table  = $wpdb->$name;

		// prepare my query
		$setup  = $wpdb->prepare("
			DELETE FROM $table
			WHERE meta_key = %s",
			esc_sql( $key )
		);

		// run SQL query
		$query = $wpdb->query( $setup );

		// send it back
		return $query;
	}

/// end class
}

// Instantiate our class
$PMetaManager = PMetaManager::getInstance();
