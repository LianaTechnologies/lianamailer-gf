<?php
/**
 * Plugin Name:       LianaMailer for Gravity Forms
 * Description:       LianaMailer for Gravity Forms.
 * Version:           1.0.73
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Liana Technologies Oy
 * Author URI:        https://www.lianatech.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0-standalone.html
 * Text Domain:       lianamailer-gf
 * Domain Path:       /languages
 *
 * PHP Version 7.4
 *
 * @package LianaMailer
 * @license https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link    https://www.lianatech.com
 */

namespace GF_LianaMailer;

define( 'LMCGF_VERSION', '1.0.73' );

// if Gravity Forms is installed (and active?).
if ( class_exists( 'GFForms' ) ) {

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
