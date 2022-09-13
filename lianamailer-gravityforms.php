<?php
/**
 * Plugin Name:       LianaMailer - Gravity Forms
 * Plugin URI:        https://www.lianatech.com/solutions/websites
 * Description:       LianaMailer for Gravity Forms.
 * Version:           1.052
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Liana Technologies
 * Author URI:        https://www.lianatech.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0-standalone.html
 * Text Domain:       lianamailer
 * Domain Path:       /languages
 *
 * PHP Version 7.4
 *
 * @category Components
 * @package  WordPress
 * @author   Liana Technologies <websites@lianatech.com>
 * @author   Timo Pohjanvirta <timo.pohjanvirta@lianatech.com>
 * @license  GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */

namespace GF_LianaMailer;

// if Gravity Forms is installed (and active?)
if(class_exists('GFForms')) {

	// TODO: Autoloader?
	require_once dirname(__FILE__) . '/includes/Mailer/Rest.php';
	require_once dirname(__FILE__) . '/includes/Mailer/LianaMailerConnection.php';

	// LianaMailer plugin for Gravity Forms
	require_once dirname(__FILE__) . '/includes/LianaMailerPlugin.php';

	try {
		$lmPlugin = new LianaMailerPlugin();
		$lmPlugin->add_hooks();
	} catch( \Exception $e ) {
		$error_messages[] = 'Error: ' . $e->getMessage();
	}

	/**
	 * Include admin menu & panel code
	 */
	require_once dirname(__FILE__) . '/admin/lianamailer-admin.php';
}
