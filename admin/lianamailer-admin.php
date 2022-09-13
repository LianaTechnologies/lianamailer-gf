<?php
/**
 * LianaMailer Contact Form 7 admin panel
 *
 * PHP Version 7.4
 *
 * @category Components
 * @package  WordPress
 * @author   Liana Technologies <websites@lianatech.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later 
 * @link     https://www.lianatech.com
 */

namespace GF_LianaMailer;

/**
 * LianaMailer / Contact Form 7 options panel class
 *
 * @category Components
 * @package  WordPress
 * @author   Liana Technologies <websites@lianatech.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */
class LianaMailerGravityForms {
	private $lianamailer_gravityforms_options = [
		'lianamailer_userid' => '',
		'lianamailer_secret_key' => '',
		'lianamailer_realm' => '',
		'lianamailer_url' => ''
	];

    /**
     * Constructor
     */
    public function __construct() {
        add_action(
            'admin_menu',
            [ $this, 'lianaMailerGravityFormsAddPluginPage' ]
        );

		add_action(
            'admin_init',
            [ $this, 'lianaMailerGravityFormsPageInit' ]
        );
    }

    /**
     * Add an admin page
     *
     * @return null
     */
    public function lianaMailerGravityFormsAddPluginPage() {
        global $admin_page_hooks;

        // Only create the top level menu if it doesn't exist (via another plugin)
        if (!isset($admin_page_hooks['lianamailer'])) {
            add_menu_page(
                'LianaMailer', // page_title
                'LianaMailer', // menu_title
                'manage_options', // capability
                'lianamailer', // menu_slug
				[$this, 'lianaMailerGravityFormsCreateAdminPage' ],
                'dashicons-admin-settings', // icon_url
                65 // position
            );
        }
        add_submenu_page(
            'lianamailer',
            'Gravity Forms',
            'Gravity Forms',
            'manage_options',
            'lianamailergravityforms',
            [ $this, 'lianaMailerGravityFormsCreateAdminPage' ],
        );

        // Remove the duplicate of the top level menu item from the sub menu
        // to make things pretty.
        remove_submenu_page('lianamailer', 'lianamailer');

    }


    /**
     * Construct an admin page
     *
     * @return null
     */
    public function lianaMailerGravityFormsCreateAdminPage() {
		$this->lianamailer_gravityforms_options = get_option('lianamailer_gravityforms_options');
		?>

		<div class="wrap">
		<?php
		// LianaMailer API Settings
		?>
			<h2>LianaMailer API Options for Gravity Forms</h2>
		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php
			settings_fields('lianamailer_gravityforms_option_group');
			do_settings_sections('lianamailer_gravityforms_admin');
			submit_button();
			?>
		</form>
		</div>
        <?php
    }

    /**
     * Init a Contact Form 7 admin page
     *
     * @return null
     */
	public function lianaMailerGravityFormsPageInit() {

		$page = 'lianamailer_gravityforms_admin';
		$section = 'lianamailer_gravityforms_section';

		// LianaMailer
		register_setting(
            'lianamailer_gravityforms_option_group', // option_group
            'lianamailer_gravityforms_options', // option_name
            [
                $this,
                'lianaMailerGravityFormsSanitize'
            ] // sanitize_callback
        );

		add_settings_section(
            $section, // id
            '', // empty section title text
            [ $this, 'lianMailerGravityFormsSectionInfo' ], // callback
            $page // page
        );

		$inputs = [
			// API UserID
			[
				'name' => 'lianamailer_gravityforms_userid',
				'title' => 'LianaMailer API UserID',
				'callback' => [ $this, 'lianaMailerUserIDCallback' ],
				'page' => $page,
				'section' => $section
			],
			// API Secret key
			[
				'name' => 'lianamailer_gravityforms_secret',
				'title' => 'LianaMailer API Secret key',
				'callback' => [ $this, 'lianaMailerSecretKeyCallback' ],
				'page' => $page,
				'section' => $section
			],
			// API URL
			[
				'name' => 'lianamailer_gravityforms_url',
				'title' => 'LianaMailer API URL',
				'callback' => [ $this, 'lianaMailerURLCallback' ],
				'page' => $page,
				'section' => $section,
			],
			// API Realm
			[
				'name' => 'lianamailer_gravityforms_realm',
				'title' => 'LianaMailer API Realm',
				'callback' => [ $this, 'lianaMailerRealmCallback' ],
				'page' => $page,
				'section' => $section,
			],
			// Status check
			[
				'name' => 'lianamailer_gravityforms_status_check',
				'title' => 'LianaMailer Connection Check',
				'callback' => [ $this, 'lianaMailerConnectionCheckCallback' ],
				'page' => $page,
				'section' => $section
			]
		];

		$this->addInputs($inputs);

	}

	private function addInputs($inputs) {
		if(empty($inputs))
			return;

		foreach($inputs as $input) {
			try {
				add_settings_field(
					$input['name'], // id
					$input['title'], // title
					$input['callback'], // callback
					$input['page'], // page
					$input['section'], // section
					(!empty($input['options']) ? $input['options'] : null)
				);
			}
			catch (\Exception $e) {
				$this->error_messages[] = 'Oops, something went wrong: '.$e->getMessage();
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
    public function lianaMailerGravityFormsSanitize($input) {
        $sanitary_values = [];

		// for LianaMailer inputs
		if (isset($input['lianamailer_userid'])) {
            $sanitary_values['lianamailer_userid']
                = sanitize_text_field($input['lianamailer_userid']);
        }
		if (isset($input['lianamailer_secret_key'])) {
            $sanitary_values['lianamailer_secret_key']
                = sanitize_text_field($input['lianamailer_secret_key']);
        }
		if (isset($input['lianamailer_realm'])) {
            $sanitary_values['lianamailer_realm']
                = sanitize_text_field($input['lianamailer_realm']);
        }
		if (isset($input['lianamailer_url'])) {
            $sanitary_values['lianamailer_url']
                = sanitize_text_field($input['lianamailer_url']);
        }
        return $sanitary_values;
    }

    /**
     * Empty section info
     *
     * @return null
     */
    public function lianMailerGravityFormsSectionInfo($arg) {
        // Intentionally empty section here.
        // Could be used to generate info text.
    }

	/**
     * LianaMailer API UserID
     *
     * @return null
     */
    public function lianaMailerUserIDCallback() {
        printf(
            '<input class="regular-text" type="text" '
            .'name="lianamailer_gravityforms_options[lianamailer_userid]" '
            .'id="lianamailer_userid" value="%s">',
            isset($this->lianamailer_gravityforms_options['lianamailer_userid']) ? esc_attr($this->lianamailer_gravityforms_options['lianamailer_userid']) : ''
        );
    }

		/**
     * LianaMailer API UserID
     *
     * @return null
     */
    public function lianaMailerSecretKeyCallback() {
        printf(
            '<input class="regular-text" type="text" '
            .'name="lianamailer_gravityforms_options[lianamailer_secret_key]" '
            .'id="lianamailer_secret_key" value="%s">',
			isset($this->lianamailer_gravityforms_options['lianamailer_secret_key']) ? esc_attr($this->lianamailer_gravityforms_options['lianamailer_secret_key']) : ''
        );
    }


	/**
     * LianaMailer API URL
     *
     * @return null
     */
    public function lianaMailerURLCallback() {

		printf(
            '<input class="regular-text" type="text" '
            .'name="lianamailer_gravityforms_options[lianamailer_url]" '
            .'id="lianamailer_url" value="%s">',
			isset($this->lianamailer_gravityforms_options['lianamailer_url']) ? esc_attr($this->lianamailer_gravityforms_options['lianamailer_url']) : ''
        );
    }
	/**
     * LianaMailer API Realm
     *
     * @return null
     */
    public function lianaMailerRealmCallback() {
		// https://app.lianamailer.com
		printf(
            '<input class="regular-text" type="text" '
            .'name="lianamailer_gravityforms_options[lianamailer_realm]" '
            .'id="lianamailer_realm" value="%s">',
			isset($this->lianamailer_gravityforms_options['lianamailer_realm']) ? esc_attr($this->lianamailer_gravityforms_options['lianamailer_realm']) : ''
        );
    }

	/**
     * LianaMailer Status check
     *
     * @return string HTML
     */
    public function lianaMailerConnectionCheckCallback() {

		$return = '💥Fail';

		if(!empty($this->lianamailer_gravityforms_options['lianamailer_userid']) || !empty($this->lianamailer_gravityforms_options['lianamailer_secret_key']) || !empty($this->lianamailer_gravityforms_options['lianamailer_realm'])) {
			$rest = new Rest(
				$this->lianamailer_gravityforms_options['lianamailer_userid'],		// userid
				$this->lianamailer_gravityforms_options['lianamailer_secret_key'],	// user secret
				$this->lianamailer_gravityforms_options['lianamailer_realm'],
				$this->lianamailer_gravityforms_options['lianamailer_url']
			);

			$status = $rest->getStatus();
			if($status) {
				$return = '💚 OK';
			}
		}

		echo $return;

    }
}
if (is_admin()) {
    $lianaMailerGravityForms = new LianaMailerGravityForms();
}

