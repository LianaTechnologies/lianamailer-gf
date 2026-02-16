<?php
/**
 * Plugin Name:       LianaMailer for Gravity Forms
 * Description:       LianaMailer for Gravity Forms.
 * Version:           1.0.85
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Liana Technologies Oy
 * Author URI:        https://www.lianatech.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0-standalone.html
 * Text Domain:       lianamailer-gf
 * Domain Path:       /languages
 *
 * @package LianaMailer
 * @license https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link    https://www.lianatech.com
 */

namespace GF_LianaMailer;

define( 'LMCGF_VERSION', '1.0.85' );
define( 'LMCGF_PATH', plugin_dir_path( __FILE__ ) );
define( 'LMCGF_URL', plugin_dir_url( __FILE__ ) );

// if Gravity Forms is installed (and active?).
if ( class_exists( 'GFForms' ) ) {
	if ( \is_admin() ) {
		// Load admin notices.
		require_once dirname( __FILE__ ) . '/admin/class-admin-notices.php';
		new Admin_Notices();
	}

	include_once dirname( __FILE__ ) . '/includes/Mailer/class-rest.php';
	include_once dirname( __FILE__ ) . '/includes/Mailer/class-lianamailerconnection.php';

	// LianaMailer plugin for Gravity Forms.
	include_once dirname( __FILE__ ) . '/includes/class-lianamailerplugin.php';

	try {
		$lm_plugin = new LianaMailerPlugin();
		$lm_plugin->add_hooks();
	} catch ( \Exception $e ) {
		$error_messages[] = 'Error: ' . $e->getMessage();
	}

	/**
	 * Include admin menu & panel code.
	 */
	include_once dirname( __FILE__ ) . '/admin/class-lianamailer-gf.php';
}
