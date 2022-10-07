<?php
/**
 * LianaMailer Contact Form 7 admin panel
 *
 * PHP Version 7.4
 *
 * @package  LianaMailer
 * @author   Liana Technologies <websites@lianatech.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */

namespace GF_LianaMailer;

/**
 * LianaMailer / Contact Form 7 options panel class
 *
 * @package  LianaMailer
 * @author   Liana Technologies <websites@lianatech.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */
class LianaMailer_GF {
	/**
	 * REST API options to save
	 *
	 * @var lianamailer_gf_options array
	 */
	private $lianamailer_gf_options = array(
		'lianamailer_userid'     => '',
		'lianamailer_secret_key' => '',
		'lianamailer_realm'      => '',
		'lianamailer_url'        => '',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action(
			'admin_menu',
			array( $this, 'lianamailer_gf_add_plugin_page' )
		);

		add_action(
			'admin_init',
			array( $this, 'lianamailer_gf_page_init' )
		);
	}

	/**
	 * Add an admin page
	 */
	public function lianamailer_gf_add_plugin_page() {
		global $admin_page_hooks;

		// Only create the top level menu if it doesn't exist (via another plugin).
		if ( ! isset( $admin_page_hooks['lianamailer'] ) ) {
			add_menu_page(
				'LianaMailer',
				'LianaMailer',
				'manage_options',
				'lianamailer',
				array( $this, 'lianamailer_gf_create_admin_page' ),
				'dashicons-admin-settings',
				65
			);
		}
		add_submenu_page(
			'lianamailer',
			'Gravity Forms',
			'Gravity Forms',
			'manage_options',
			'lianamailergf',
			array( $this, 'lianamailer_gf_create_admin_page' )
		);

		// Remove the duplicate of the top level menu item from the sub menu.
		// to make things pretty.
		remove_submenu_page( 'lianamailer', 'lianamailer' );

	}


	/**
	 * Construct an admin page.
	 */
	public function lianamailer_gf_create_admin_page() {
		$this->lianamailer_gf_options = get_option( 'lianamailer_gf_options' );
		?>

		<div class="wrap">
			<h2>LianaMailer API Options for Gravity Forms</h2>
		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'lianamailer_gf_option_group' );
			do_settings_sections( 'lianamailer_gf_admin' );
			submit_button();
			?>
		</form>
		</div>
		<?php
	}

	/**
	 * Init a Contact Form 7 admin page
	 */
	public function lianamailer_gf_page_init() {

		$page    = 'lianamailer_gf_admin';
		$section = 'lianamailer_gf_section';

		register_setting(
			'lianamailer_gf_option_group',
			'lianamailer_gf_options',
			array(
				$this,
				'lianamailer_gf_sanitize',
			)
		);

		add_settings_section(
			$section,
			'',
			array( $this, 'lianamailer_gf_section_info' ),
			$page
		);

		$inputs = array(
			// API UserID.
			array(
				'name'     => 'lianamailer_gf_userid',
				'title'    => 'LianaMailer API UserID',
				'callback' => array( $this, 'lianamailer_user_id_callback' ),
				'page'     => $page,
				'section'  => $section,
			),
			// API Secret key.
			array(
				'name'     => 'lianamailer_gf_secret',
				'title'    => 'LianaMailer API Secret key',
				'callback' => array( $this, 'lianamailer_secret_key_callback' ),
				'page'     => $page,
				'section'  => $section,
			),
			// API URL.
			array(
				'name'     => 'lianamailer_gf_url',
				'title'    => 'LianaMailer API URL',
				'callback' => array( $this, 'lianamailer_url_callback' ),
				'page'     => $page,
				'section'  => $section,
			),
			// API Realm.
			array(
				'name'     => 'lianamailer_gf_realm',
				'title'    => 'LianaMailer API Realm',
				'callback' => array( $this, 'lianamailer_realm_callback' ),
				'page'     => $page,
				'section'  => $section,
			),
			// Status check.
			array(
				'name'     => 'lianamailer_gf_status_check',
				'title'    => 'LianaMailer Connection Check',
				'callback' => array( $this, 'lianamailer_connection_check_callback' ),
				'page'     => $page,
				'section'  => $section,
			),
		);

		$this->add_inputs( $inputs );

	}

	/**
	 * Adds setting inputs for admin view
	 *
	 * @param array $inputs - Array of inputs.
	 */
	private function add_inputs( $inputs ) {
		if ( empty( $inputs ) ) {
			return;
		}

		foreach ( $inputs as $input ) {
			try {
				add_settings_field(
					$input['name'],
					$input['title'],
					$input['callback'],
					$input['page'],
					$input['section'],
					( ! empty( $input['options'] ) ? $input['options'] : null )
				);
			} catch ( \Exception $e ) {
				$this->error_messages[] = 'Oops, something went wrong: ' . $e->getMessage();
			}
		}
	}

	/**
	 * Basic input sanitization function
	 *
	 * @param string $input String to be sanitized.
	 *
	 * @return null
	 */
	public function lianamailer_gf_sanitize( $input ) {
		$sanitary_values = array();

		// for LianaMailer inputs.
		if ( isset( $input['lianamailer_userid'] ) ) {
			$sanitary_values['lianamailer_userid']
				= sanitize_text_field( $input['lianamailer_userid'] );
		}
		if ( isset( $input['lianamailer_secret_key'] ) ) {
			$sanitary_values['lianamailer_secret_key']
				= sanitize_text_field( $input['lianamailer_secret_key'] );
		}
		if ( isset( $input['lianamailer_realm'] ) ) {
			$sanitary_values['lianamailer_realm']
				= sanitize_text_field( $input['lianamailer_realm'] );
		}
		if ( isset( $input['lianamailer_url'] ) ) {
			$sanitary_values['lianamailer_url']
				= sanitize_text_field( $input['lianamailer_url'] );
		}
		return $sanitary_values;
	}

	/**
	 * Empty section info.
	 */
	public function lianamailer_gf_section_info() {
		// Intentionally empty section here.
		// Could be used to generate info text.
	}

	/**
	 * LianaMailer API UserID.
	 */
	public function lianamailer_user_id_callback() {
		printf(
			'<input class="regular-text" type="text" '
			. 'name="lianamailer_gf_options[lianamailer_userid]" '
			. 'id="lianamailer_userid" value="%s">',
			isset( $this->lianamailer_gf_options['lianamailer_userid'] ) ? esc_attr( $this->lianamailer_gf_options['lianamailer_userid'] ) : ''
		);
	}

		/**
		 * LianaMailer API UserID
		 */
	public function lianamailer_secret_key_callback() {
		printf(
			'<input class="regular-text" type="text" '
			. 'name="lianamailer_gf_options[lianamailer_secret_key]" '
			. 'id="lianamailer_secret_key" value="%s">',
			isset( $this->lianamailer_gf_options['lianamailer_secret_key'] ) ? esc_attr( $this->lianamailer_gf_options['lianamailer_secret_key'] ) : ''
		);
	}


	/**
	 * LianaMailer API URL
	 */
	public function lianamailer_url_callback() {

		printf(
			'<input class="regular-text" type="text" '
			. 'name="lianamailer_gf_options[lianamailer_url]" '
			. 'id="lianamailer_url" value="%s">',
			isset( $this->lianamailer_gf_options['lianamailer_url'] ) ? esc_attr( $this->lianamailer_gf_options['lianamailer_url'] ) : ''
		);
	}
	/**
	 * LianaMailer API Realm
	 */
	public function lianamailer_realm_callback() {
		printf(
			'<input class="regular-text" type="text" '
			. 'name="lianamailer_gf_options[lianamailer_realm]" '
			. 'id="lianamailer_realm" value="%s">',
			isset( $this->lianamailer_gf_options['lianamailer_realm'] ) ? esc_attr( $this->lianamailer_gf_options['lianamailer_realm'] ) : ''
		);
	}

	/**
	 * LianaMailer Status check
	 */
	public function lianamailer_connection_check_callback() {

		$return = '💥Fail';

		if ( ! empty( $this->lianamailer_gf_options['lianamailer_userid'] ) || ! empty( $this->lianamailer_gf_options['lianamailer_secret_key'] ) || ! empty( $this->lianamailer_gf_options['lianamailer_realm'] ) || ! empty( $this->lianamailer_gf_options['lianamailer_url'] ) ) {
			$rest = new Rest(
				$this->lianamailer_gf_options['lianamailer_userid'],
				$this->lianamailer_gf_options['lianamailer_secret_key'],
				$this->lianamailer_gf_options['lianamailer_realm'],
				$this->lianamailer_gf_options['lianamailer_url']
			);

			$status = $rest->get_status();
			if ( $status ) {
				$return = '💚 OK';
			}
		}

		echo esc_html( $return );

	}
}
if ( is_admin() ) {
	$lianamailer_gf = new LianaMailer_GF();
}
