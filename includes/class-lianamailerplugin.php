<?php
/**
 * LianaMailer - Gravity Forms plugin
 *
 * PHP Version 7.4
 *
 * @package  LianaMailer
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */

namespace GF_LianaMailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

use Gravity_Forms\Gravity_Forms\Settings\Settings;

/**
 * LianaMailer - Gravity Forms plugin class
 *
 * PHP Version 7.4
 *
 * @package  LianaMailer
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */
class LianaMailerPlugin {

	/**
	 * Stores the current instance of the Settings renderer.
	 *
	 * @since 2.5
	 *
	 * @var false|Gravity_Forms\Gravity_Forms\Settings\Settings
	 */
	private static $settings_renderer = false;

	/**
	 * Posted data
	 *
	 * @var post_data array
	 */
	private $post_data;

	/**
	 * LianaMailer connection object
	 *
	 * @var lianamailer_connection object
	 */
	private static $lianamailer_connection;

	/**
	 * Site data fetched from LianaMailer
	 *
	 * @var site_data array
	 */
	private static $site_data = array();

	/**
	 * Is REST API connection valid
	 *
	 * @var is_connection_valid boolean
	 */
	private static $is_connection_valid = false;

	/**
	 * Current Gravity Forms object
	 *
	 * @var _form object
	 */
	private static $gf_form;

	/**
	 * Constructor
	 */
	public function __construct() {
		self::$lianamailer_connection = new LianaMailerConnection();
	}

	/**
	 * Adds actions for the plugin
	 */
	public function add_hooks() {
		// include plugin JS and CSS.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_lianamailer_plugin_scripts' ), 10, 1 );
		// AJAX callback for LianaMailer plugin settings change.
		add_action( 'wp_ajax_getSiteDataForGFSettings', array( $this, 'get_site_data_for_settings' ), 10, 1 );

		// Displaying content for custom tab when selected.
		add_action( 'gform_form_settings_page_lianaMailerSettings', array( $this, 'lianamailer_settings' ) );
		// Add a custom menu item to the Form Settings page menu.
		add_filter( 'gform_form_settings_menu', array( $this, 'add_lianamailer_settings' ) );

		// Register GF_Field_LianaMailer custom field when Gravity Forms is loaded.
		add_action( 'gform_loaded', array( $this, 'init_plugin' ) );

		// Add a content for custom "lianamailer" field.
		add_action( 'gform_field_standard_settings', array( $this, 'add_lianamailer_field' ), 10, 2 );
		// Filter LianaMailer field tooltip(s).
		add_filter( 'gform_tooltips', array( $this, 'set_lianamailer_field_tooltips' ) );
		// Filter integration settings for custom field options.
		add_filter( 'gform_lianamailer_get_integration_options', array( $this, 'get_integration_options' ), 10, 2 );

		// Do newsletter subscription.
		add_action( 'gform_after_submission', array( $this, 'do_newsletter_subscription' ), 10, 2 );
	}

	/**
	 * Make newsletter subscription on form submit
	 *
	 * @param array  $entry Submitted data.
	 * @param object $form Gravity Form instance.
	 * @throws \Exception If subscription failed.
	 *
	 * @return void
	 */
	public function do_newsletter_subscription( $entry, $form ) {

		try {

			$lianamailer_settings = $form['lianamailer'];
			$is_plugin_enabled    = $lianamailer_settings['lianamailer_enabled'] ?? false;
			$list_id              = intval( $lianamailer_settings['lianamailer_mailing_list'] ) ?? null;
			$consent_id           = intval( $lianamailer_settings['lianamailer_consent'] ) ?? null;
			$selected_site        = $lianamailer_settings['lianamailer_site'] ?? null;

			if ( ! intval( $is_plugin_enabled ) ) {
				throw new \Exception( 'Plugin is not enabled' );
			}

			$fields               = $form['fields'];
			$lianamailer_field_id = null;
			// Fetch LianaMailer properties for settings.
			$lianamailer_field = $this->get_lianamailer_field_from_form( $form );

			if ( false !== $lianamailer_field instanceof GF_LianaMailer\GF_Field_LianaMailer ) {
				throw new \Exception( 'LianaMailer field could not found on form' );
			}

			$lianamailer_field_id = $lianamailer_field['id'];
			$is_opt_in_enabled    = $lianamailer_field['lianamailer_opt_in'];
			$opt_in_label         = $lianamailer_field['label'];

			// If opt-in was enabled but without label and consent.
			if ( $is_opt_in_enabled && ! $opt_in_label && ! $consent_id ) {
				throw new \Exception( 'Opt-in was enabled without label and consent' );
			}
			// If consent is not set and opt-in is not enabled.
			if ( ! $consent_id && ! $is_opt_in_enabled ) {
				throw new \Exception( 'No consent set and opt-in was disabled' );
			}

			// If LianaMailer field was not posted, bail out.
			if ( ! array_key_exists( $lianamailer_field_id, $entry ) || empty( $entry[ $lianamailer_field_id ] ) ) {
				throw new \Exception( 'LianaMailer field was not posted' );
			}

			$property_map = array();
			if ( isset( $lianamailer_field['lianamailer_properties'] ) ) {
				// keys are LianaMailer properties and values GForms.
				$property_map = $lianamailer_field['lianamailer_properties'];
			}

			self::get_lianamailer_site_data( $selected_site );
			// No LianaMailer site data found. Maybe issue with credentials or REST API.
			if ( empty( self::$site_data ) ) {
				throw new \Exception( 'LianaMailer site data could not be fetched. Check REST API credentials.' );
			}

			// if mailing list was saved in settings but do not exists anymore on LianaMailers subscription page, null the value.
			if ( $list_id ) {
				$key = array_search( $list_id, array_column( self::$site_data['lists'], 'id' ), true );
				// if selected list is not found anymore from LianaMailer subscription page, do not allow subscription.
				if ( false === $key ) {
					$list_id = null;
				}
			}

			if ( ! $list_id ) {
				throw new \Exception( 'Mailing list was not selected' );
			}

			$field_map_email = ( array_key_exists( 'email', $property_map ) ? intval( $property_map['email'] ) : null );
			$field_map_sms   = ( array_key_exists( 'sms', $property_map ) ? intval( $property_map['sms'] ) : null );

			$email     = null;
			$sms       = null;
			$recipient = null;

			$posted_data = array();
			foreach ( $form['fields'] as $field ) {

				$inputs = $field->get_entry_inputs();
				// If multivalued input, eg. choices. Fetch values as string imploded with ", ".
				if ( is_array( $inputs ) ) {
					$tmp_values = array();
					foreach ( $inputs as $input ) {
						$value = rgar( $entry, (string) $input['id'] );
						if ( ! empty( $value ) ) {
							$tmp_values[] = $value;
						}
					}
					if ( ! empty( $tmp_values ) ) {
						$posted_data[ $field->id ] = implode( ', ', $tmp_values );
					}
				} else {
					$value                     = rgar( $entry, (string) $field->id );
					$posted_data[ $field->id ] = $value;

					if ( $field->id === $field_map_email ) {
						$email = $value;
					}
					if ( $field->id === $field_map_sms ) {
						$sms = $value;
					}
				}
			}

			$this->post_data = $posted_data;
			$consent_data    = array();

			if ( empty( $email ) && empty( $sms ) ) {
				throw new \Exception( 'No email or SMS -field set' );
			}

			$subscribe_by_email = false;
			$subscribe_by_sms   = false;
			if ( $email ) {
				$subscribe_by_email = true;
			} elseif ( $sms ) {
				$subscribe_by_sms = true;
			}

			if ( $subscribe_by_email || $subscribe_by_sms ) {

				$customer_settings = self::$lianamailer_connection->get_lianamailer_customer();
				/**
				 * Autoconfirm subscription if:
				 * LM site has "registration_needs_confirmation" disabled
				 * email is not set
				 * LM site doesn't have welcome mail set
				 */
				$auto_confirm = ( empty( $customer_settings['registration_needs_confirmation'] ) || ! $email || ! self::$site_data['welcome'] );

				$properties = $this->filter_recipient_properties( $property_map );
				self::$lianamailer_connection->set_properties( $properties );

				$recipient = array();
				if ( $subscribe_by_email ) {
					$recipient = self::$lianamailer_connection->get_recipient_by_email( $email );
				} else {
					$recipient = self::$lianamailer_connection->get_recipient_by_sms( $sms );
				}

				// if recipient found from LM and it not enabled and subscription had email set, re-enable it.
				if ( ! is_null( $recipient ) && isset( $recipient['recipient']['enabled'] ) && false === $recipient['recipient']['enabled'] && $email ) {
					self::$lianamailer_connection->reactivate_recipient( $email, $auto_confirm );
				}
				self::$lianamailer_connection->create_and_join_recipient( $recipient, $email, $sms, $list_id, $auto_confirm );

				$consent_key = array_search( $consent_id, array_column( self::$site_data['consents'], 'consent_id' ), true );
				if ( false !== $consent_key ) {
					$consent_data = self::$site_data['consents'][ $consent_key ];
					// Add consent to recipient.
					self::$lianamailer_connection->add_recipient_consent( $consent_data );
				}

				// send welcome mail if:
				// not existing recipient OR recipient was not previously enabled OR registration needs confirmation is enabled
				// and site is using welcome -mail and LM account has double opt-in enabled and email address set.
				if ( ( ! $recipient || ! $recipient['recipient']['enabled'] || $customer_settings['registration_needs_confirmation'] ) && self::$site_data['welcome'] && $email ) {
					self::$lianamailer_connection->send_welcome_mail( self::$site_data['domain'] );
				}
			}
		} catch ( \Exception $e ) {
			$failure_reason = $e->getMessage();
		}
	}

	/**
	 * Filters certain propertis from posted data
	 *
	 * @param array $property_map Property map for LianaMailer field.
	 *
	 * @return array $props
	 */
	private function filter_recipient_properties( $property_map = array() ) {

		$properties = $this->get_lianamailer_properties( false, self::$site_data['properties'] );
		$props      = array();
		foreach ( $properties as $property ) {
			$property_name   = $property['name'];
			$property_handle = $property['handle'];
			$field_id        = ( isset( $property_map[ $property_handle ] ) ? $property_map[ $property_handle ] : null );

			// if Property value havent been posted, leave it as it is.
			if ( ! isset( $this->post_data[ $field_id ] ) ) {
				continue;
			}
			// otherwise update it into LianaMailer.
			$props[ $property_name ] = sanitize_text_field( $this->post_data[ $field_id ] );
		}
		return $props;
	}

	/**
	 * Filter integration settings for custom field options.
	 *
	 * @param array $options Field options.
	 * @param int   $form_id Form ID.
	 *
	 * @return array $options
	 */
	public function get_integration_options( $options, $form_id ) {

		if ( ! absint( $form_id ) ) {
			return array();
		}
		$form              = self::get_form( $form_id );
		$lianamailer_field = $this->get_lianamailer_field_from_form( $form );

		if ( isset( $form['lianamailer']['lianamailer_site'] ) ) {
			$selected_site = $form['lianamailer']['lianamailer_site'];
			self::get_lianamailer_site_data( $selected_site );
		}
		if ( ! get_transient( 'lianamailer_is_connection_valid' ) || is_admin() ) {
			self::$is_connection_valid = self::$lianamailer_connection->get_status();
			if ( ! self::$is_connection_valid ) {
				$options['is_connection_valid'] = false;
			}
			set_transient( 'lianamailer_is_connection_valid', self::$is_connection_valid, DAY_IN_SECONDS );
		}
		// Set LianaMailer plugin state.
		if ( isset( $form['lianamailer']['lianamailer_enabled'] ) ) {
			$options['is_plugin_enabled'] = $form['lianamailer']['lianamailer_enabled'];
		}

		// Set LianaMailer mailing list.
		if ( isset( $form['lianamailer']['lianamailer_mailing_list'] ) && ! empty( $form['lianamailer']['lianamailer_mailing_list'] ) ) {
			$options['mailing_list'] = $form['lianamailer']['lianamailer_mailing_list'];
		}

		// If using opt-in functionality, newsletter subscription label is possible to edit.
		if ( isset( $lianamailer_field['lianamailer_opt_in'] ) && $lianamailer_field['lianamailer_opt_in'] ) {
			$options['opt_in'] = boolval( $lianamailer_field['lianamailer_opt_in'] );
		}
		// If using opt-in functionality, newsletter subscription label is possible to edit.
		if ( isset( $lianamailer_field['label'] ) ) {
			$options['opt_in_label'] = $lianamailer_field['label'];
		}

		// If GF LianaMailer settings doesnt have consent set or if LianaMailer site doesnt have any consents.
		if ( ! isset( $form['lianamailer']['lianamailer_consent'] ) || empty( $form['lianamailer']['lianamailer_consent'] ) || ! isset( self::$site_data['consents'] ) || empty( self::$site_data['consents'] ) ) {
			$options['consent_label'] = 'No consent found';

			// If not using opt-in or opt_in_label is not set, precheck consent checkbox in public form.
			if ( false === $options['opt_in'] || ! $options['opt_in_label'] ) {
				$options['precheck'] = true;
			}
		} else {
			foreach ( self::$site_data['consents'] as $consent ) {
				if ( intval( $form['lianamailer']['lianamailer_consent'] ) === $consent['consent_id'] ) {
					$options['consent_label'] = $consent['description'];
					$options['consent_id']    = $consent['consent_id'];
					break;
				}
			}
		}

		// Check if email or SMS fields are mapped.
		if ( isset( $lianamailer_field['lianamailer_properties']['email'] ) && $lianamailer_field['lianamailer_properties']['email'] || isset( $lianamailer_field['lianamailer_properties']['sms'] ) && $lianamailer_field['lianamailer_properties']['sms'] ) {
			$options['is_email_or_sms_mapped'] = true;
		}

		return $options;
	}

	/**
	 * Gets LianaMailer field object from form fields.
	 *
	 * @param object $form Form object.
	 *
	 * @return object $lianamailer_field LianaMailer field object.
	 */
	private function get_lianamailer_field_from_form( $form ) {

		if ( ! isset( $form['fields'] ) ) {
			return array();
		}

		$fields            = $form['fields'];
		$lianamailer_field = array();
		// Fetch all non LianaMailer properties for settings.
		foreach ( $fields as $key => $field_object ) {
			if ( false === $field_object instanceof GF_Field_LianaMailer ) {
				continue;
			}
			$lianamailer_field = $field_object;
		}

		return $lianamailer_field;
	}

	/**
	 * AJAX callback for LianaMailer plugin settings change
	 * Returns selected LianaMailer site mailing lists and consents
	 */
	public function get_site_data_for_settings() {
		if ( ! class_exists( 'GFFormSettings' ) ) {
			require_once \GFCommon::get_base_path() . '/form_settings.php';
		}

		require_once \GFCommon::get_base_path() . '/form_detail.php';

		if ( ! isset( $_POST['site'] ) || ! sanitize_text_field( wp_unslash( $_POST['site'] ) ) ) {
			wp_die();
		}

		$site   = sanitize_text_field( wp_unslash( $_POST['site'] ) );
		$action = __FUNCTION__ . '-' . $site;
		$nonce  = sanitize_key( wp_create_nonce( $action ) );
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die();
		}

		$account_sites = self::$lianamailer_connection->get_account_sites();
		$selected_site = ( isset( $_POST['site'] ) ? sanitize_text_field( wp_unslash( $_POST['site'] ) ) : null );

		if ( ! $selected_site ) {
			wp_die();
		}

		$data = array(
			'lists'    => array(),
			'consents' => array(),
		);
		foreach ( $account_sites as &$site ) {
			if ( $site['domain'] === $selected_site ) {
				$data['lists']    = $site['lists'];
				$data['consents'] = ( self::$lianamailer_connection->get_site_consents( $site['domain'] ) ?? array() );
				break;
			}
		}

		echo wp_json_encode( $data );
		wp_die();
	}

	/**
	 * Add a custom menu item to the Form Settings page menu.
	 *
	 * @param array $menu_items Array of Gravity Form menu items.
	 */
	public function add_lianamailer_settings( $menu_items ) {

		$menu_items[] = array(
			'name'  => 'lianaMailerSettings',
			'label' => __( 'LianaMailer' ),
			'icon'  => 'gform-icon--lianamailer',
		);

		return $menu_items;
	}


	/**
	 * Displaying content for custom tab when selected.
	 */
	public function lianamailer_settings() {

		\GFFormSettings::page_header();

		// Initialize settings.
		if ( ! self::get_settings_renderer() ) {
			self::initialize_settings_renderer();
		}

		// Render settings.
		self::get_settings_renderer()->render();

		\GFFormSettings::page_footer();

	}

	/**
	 * Initialize LianaMailer settings as Gravity_Forms\Gravity_Forms\Settings\Settings object
	 */
	public function initialize_settings_renderer() {

		if ( ! class_exists( 'GFFormSettings' ) ) {
			require_once GFCommon::get_base_path() . '/form_settings.php';
		}

		require_once \GFCommon::get_base_path() . '/form_detail.php';

		// Get form, confirmation IDs.
		$form_id = absint( rgget( 'id' ) );
		$form    = self::get_form( $form_id );

		// Initialize new settings renderer.
		$renderer = new Settings(
			array(
				'fields'         => $this->get_lianamailer_settings_fields( $form ),
				'header'         => array(
					'icon'  => 'fa fa-cogs',
					'title' => esc_html__( 'LianaMailer settings', 'lianamailer' ),
				),
				'initial_values' => rgar( $form, 'lianamailer' ),
				'save_callback'  => array( $this, 'process_form_settings' ),
				// JS which updates selected values.
				'after_fields'   => function() use ( $form_id ) {
					$form = self::get_form( $form_id );
					?>
					<script type="text/javascript">

						var form = <?php echo wp_json_encode( $form ); ?>;
						( function( $ ){
							jQuery( document ).trigger( 'gform_load_lianamailer_form_settings', [ form ] );
						})( jQuery );

					</script>
					<?php

				},
			)
		);

		self::set_settings_renderer( $renderer );

	}

	/**
	 * Callback for saving LianaMailer settings
	 *
	 * @param array $values Form settings.
	 */
	public function process_form_settings( $values ) {
		$form = self::get_form( rgget( 'id' ) );

		// if disabling functionality, retain old values. These arent posted because inputs are disabled.
		if ( 0 === intval( $values['lianamailer_enabled'] ) ) {
			$values['lianamailer_site']         = $form['lianamailer']['lianamailer_site'];
			$values['lianamailer_mailing_list'] = $form['lianamailer']['lianamailer_mailing_list'];
			$values['lianamailer_consent']      = $form['lianamailer']['lianamailer_consent'];
		}

		// Save form.
		$form['lianamailer'] = $values;
		// Save form.
		\GFAPI::update_form( $form );

		self::$gf_form = $form;
	}

	/**
	 * Returns the form array for use in the form settings.
	 *
	 * @param int $form_id Current form id.
	 *
	 * @return GFForm object
	 */
	public static function get_form( $form_id ) {
		if ( empty( self::$gf_form ) ) {
			self::$gf_form = \GFAPI::get_form( $form_id );
		}

		return self::$gf_form;
	}

	/**
	 * Return selectable values for LianaMailer settings page
	 * lianamailer_enabled (checkbox)
	 * lianamailer_site (select)
	 * lianamailer_mailing_list (select)
	 * lianamailer_consent (select)
	 *
	 * @param object $form GF object.
	 *
	 * @return array $fields
	 */
	private function get_lianamailer_settings_fields( $form ) {

		$posted_site   = rgpost( '_gform_setting_lianamailer_site', true );
		$selected_site = null;
		if ( ! empty( $posted_site ) ) {
			$selected_site = $posted_site;
		} elseif ( isset( $form['lianamailer']['lianamailer_site'] ) ) {
			$selected_site = $form['lianamailer']['lianamailer_site'];
		}

		$site_data     = array();
		$account_sites = self::$lianamailer_connection->get_account_sites();
		// If account sites not found propably there isnt any or REST API settings arent ok.
		if ( empty( $account_sites ) ) {
			$valid  = self::$lianamailer_connection->get_status();
			$fields = array(
				array(
					'title'  => esc_html__( 'LianaMailer settings', 'lianamailer' ),
					'fields' => array(
						array(
							'name' => 'no-settings',
							'type' => 'html',
							'html' => ( ! $valid ? 'REST API connection failed. <a href="' . admin_url( 'admin.php?page=lianamailergf' ) . '" target="_blank">Check settings</a>' : 'Unable to find any LianaMailer sites.' ),
						),
					),
				),
			);
			return $fields;
		}
		foreach ( $account_sites as &$site ) {
			unset( $site['layout'] );
			unset( $site['marketing'] );

			if ( $site['domain'] === $selected_site ) {
				$site_consents = ( self::$lianamailer_connection->get_site_consents( $site['domain'] ) ?? array() );

				$site_data['lists']    = isset( $site['lists'] ) ? $site['lists'] : array();
				$site_data['consents'] = $site_consents;
				self::$site_data       = $site_data;
			}
		}

		// Initialize site choices array.
		$site_choices = array(
			array(
				'label' => 'Choose',
				'value' => '',
			),
		);

		$mailing_list_choices = $site_choices;
		$consent_list_choices = $site_choices;

		foreach ( $account_sites as $account_site ) {
			if ( $account_site['redirect'] || $account_site['replaced_by'] ) {
				continue;
			}
			$site_choices[] = array(
				'label' => $account_site['domain'],
				'value' => $account_site['domain'],
			);
		}

		if ( isset( self::$site_data['lists'] ) ) {
			foreach ( self::$site_data['lists'] as $list ) {
				$mailing_list_choices[] = array(
					'label' => $list['name'],
					'value' => $list['id'],
				);
			}
		}

		if ( isset( self::$site_data['consents'] ) ) {
			foreach ( self::$site_data['consents'] as $consent ) {
				$consent_list_choices[] = array(
					'label' => $consent['name'],
					'value' => $consent['consent_id'],
				);
			}
		}

		// Build LianaMailer plugin settings fields.
		$fields = array(
			array(
				'title'  => esc_html__( 'LianaMailer settings', 'lianamailer' ),
				'fields' => array(
					array(
						'name'    => 'lianamailer_enabled',
						'label'   => esc_html__( 'Enable LianaMailer -integration on this form', 'lianamailer' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'name'  => 'lianamailer_enabled',
								'label' => 'Enable LianaMailer -integration on this form',
							),
						),
					),
					array(
						'name'    => 'lianamailer_site',
						'label'   => esc_html__( 'Site', 'lianamailer' ),
						'type'    => 'select',
						'choices' => $site_choices,
					),
					array(
						'name'    => 'lianamailer_mailing_list',
						'label'   => esc_html__( 'Mailing list', 'lianamailer' ),
						'type'    => 'select',
						'choices' => $mailing_list_choices,
					),
					array(
						'name'    => 'lianamailer_consent',
						'label'   => esc_html__( 'Consent', 'lianamailer' ),
						'type'    => 'select',
						'choices' => $consent_list_choices,
					),
					// save button.
					array(
						'type'  => 'save',
						'value' => esc_html__( 'Save settings', 'lianamailer' ),
					),
				),
			),
		);

		return $fields;

	}

	/**
	 * Gets the current instance of Settings handling settings rendering.
	 *
	 * @return false|Gravity_Forms\Gravity_Forms\Settings\Settings
	 */
	public function get_settings_renderer() {
		return self::$settings_renderer;
	}

	/**
	 * Sets the current instance of Settings handling settings rendering.
	 *
	 * @param \Gravity_Forms\Gravity_Forms\Settings\Settings $renderer Settings renderer.
	 * @return bool|WP_Error
	 */
	public function set_settings_renderer( $renderer ) {

		// Ensure renderer is an instance of Settings.
		if ( ! is_a( $renderer, 'Gravity_Forms\Gravity_Forms\Settings\Settings' ) ) {
			return new WP_Error( 'Renderer must be an instance of Gravity_Forms\Gravity_Forms\Settings\Settings.' );
		}

		self::$settings_renderer = $renderer;

		return true;
	}

	/**
	 * Register GF_Field_LianaMailer custom field when Gravity Forms is loaded
	 */
	public function init_plugin() {
		// Register LianaMailer field.
		require_once 'class-gf-field-lianamailer.php';
		\GF_Fields::register( new GF_Field_LianaMailer() );
	}

	/**
	 * Adds custom inputs for GF_Field_LianaMailer -field
	 *
	 * @param int $position Field position.
	 * @param int $form_id Form ID.
	 *
	 * @return HTML custom input content
	 */
	public function add_lianamailer_field( $position, $form_id ) {

		if ( 0 !== $position ) {
			return;
		}

		$form                      = self::get_form( $form_id );
		self::$is_connection_valid = self::$lianamailer_connection->get_status();

		if ( isset( $form['lianamailer']['lianamailer_site'] ) ) {
			$selected_site = $form['lianamailer']['lianamailer_site'];
			self::get_lianamailer_site_data( $selected_site );
		}

		$fields = $form['fields'];

		$form_fields = array();
		// Fetch all non LianaMailer fields for settings.
		foreach ( $fields as $key => $field_object ) {

			if ( false !== $field_object instanceof GF_Field_LianaMailer ) {
				continue;
			}
			$form_fields[] = $field_object;
		}

		// Property mapping.
		$html = $this->print_property_selection( $form_fields );

		$allowed_html   = wp_kses_allowed_html( 'post' );
		$custom_allowed = array();

		$custom_allowed['input'] = array(
			'class'   => 1,
			'id'      => 1,
			'name'    => 1,
			'value'   => 1,
			'type'    => 1,
			'checked' => 1,
			'onclick' => 1,
			'onkeyup' => 1,
		);

		$custom_allowed['select'] = array(
			'class'       => 1,
			'id'          => 1,
			'name'        => 1,
			'value'       => 1,
			'type'        => 1,
			'disabled'    => 1,
			'data-handle' => 1,
			'onchange'    => 1,
		);

		$custom_allowed['option'] = array(
			'selected' => 1,
			'class'    => 1,
			'value'    => 1,
		);

		$allowed_html = array_merge( $allowed_html, $custom_allowed );
		echo wp_kses( $html, $allowed_html );
	}

	/**
	 * Get HTML for property selects into custom field
	 * If properties not found, print basic error message
	 *
	 * @param array $form_fields LianaMailer field data.
	 *
	 * @return HTML for custom field
	 */
	private function print_property_selection( $form_fields ) {

		// Get all LianaMailer site properties core fields included.
		if ( isset( self::$site_data['properties'] ) ) {
			$lianamailer_properties = $this->get_lianamailer_properties( true, self::$site_data['properties'] );
		}

		$html          = '<li class="lianamailer_properties_setting field_setting">';
			$html     .= '<label for="field_lianamailer_property" class="section_label">';
				$html .= esc_html__( 'LianaMailer Property Map', 'lianamailer-for-gf' );
				$html .= gform_tooltip( 'property_map_setting', '', true );
			$html     .= '</label>';

		if ( empty( $lianamailer_properties ) ) {
			if ( ! self::$is_connection_valid ) {
				$html .= 'REST API connection failed. Check <a href="' . admin_url( 'admin.php?page=lianamailergf' ) . '" target="_blank">settings</a>';
			} else {
				$html .= 'No properties found. Check LianaMailer <a href="' . admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=lianaMailerSettings&id=' . rgget( 'id' ) ) . '" target="_blank">settings</a>';
			}
		} else {
			foreach ( $lianamailer_properties as $property ) {
				$html .= '<div class="lm-property-wrapper">';

					$html     .= '<label for="field_lianamailer_property" class="section_label">';
						$html .= '<b>' . $property['name'] . ( isset( $property['handle'] ) && is_int( $property['handle'] ) ? ' (#' . $property['handle'] . ')' : '' ) . '</b>';
					$html     .= '</label>';
					$html     .= '<select id="field_lianamailer_property_' . $property['handle'] . '" data-handle="' . $property['handle'] . '" onchange="SetLianaMailerProperty(jQuery(this))">';
						$html .= '<option value="">' . esc_html__( 'Select form field', 'lianamailer-for-gf' ) . '</option>';
				foreach ( $form_fields as $form_field ) {
					$html .= sprintf( '<option value="%d">%s</option>', $form_field->id, $form_field->label );
				}
					$html .= '</select>';
					$html .= '</div>';
			}
		}
		$html .= '</li>';

		$html .= '<li class="lianamailer_opt_in_setting field_setting">';

			// Opt-in checkbox.
			$html     .= '<input type="checkbox" id="field_lianamailer_opt_in" onclick="SetLianaMailerOptIn(jQuery(this))" />';
			$html     .= '<label for="field_lianamailer_opt_in" class="inline">';
				$html .= esc_html__( 'Opt-in', 'lianamailer-for-gf' );
				$html .= gform_tooltip( 'opt_in_setting', '', true );
			$html     .= '</label>';

			// Opt-in label.
			$html         .= '<div class="lm-opt-in-label-wrapper hidden">';
				$html     .= '<label for="field_lianamailer_opt_in_label" class="section_label">';
					$html .= esc_html__( 'Opt-in Label', 'lianamailer-for-gf' );
				$html     .= '</label>';
				$html     .= '<input type="text" id="field_lianamailer_opt_in_label" onkeyup="SetLianaMailerOptInLabel(jQuery(this)); "/>';
			$html         .= '</div>';

		$html .= '</li>';

		return $html;
	}

	/**
	 * Get selected LianaMailer site data:
	 * domain, welcome, properties, lists and consents
	 *
	 * @param string $selected_site Selected site domain.
	 */
	private static function get_lianamailer_site_data( $selected_site = null ) {

		if ( ! empty( self::$site_data ) ) {
			return;
		}

		// if site is not selected.
		if ( ! $selected_site ) {
			return;
		}

		// use a cached dataset on the front end to avoid excessive REST calls.
		if ( ! is_admin() ) {
			$site_data = get_transient( 'lianamailer_' . $selected_site );
			if ( ! empty( $site_data ) ) {
				self::$site_data = $site_data;
				return;
			}
		}

		// Getting all sites from LianaMailer.
		$account_sites = self::$lianamailer_connection->get_account_sites();

		// Getting all properties from LianaMailer.
		$lianamailer_properties = self::$lianamailer_connection->get_lianamailer_properties();

		$site_data = array();
		foreach ( $account_sites as &$site ) {
			if ( $site['domain'] === $selected_site ) {
				$properties    = array();
				$site_consents = ( self::$lianamailer_connection->get_site_consents( $site['domain'] ) ?? array() );

				$site_data['domain']  = $site['domain'];
				$site_data['welcome'] = $site['welcome'];
				foreach ( $site['properties'] as &$prop ) {
					/**
					 * Add required and type -attributes because get_account_sites() -endpoint doesnt return these.
					 * https://rest.lianamailer.com/docs/#tag/Sites/paths/~1v1~1sites/post
					 */
					$key = array_search( $prop['handle'], array_column( $lianamailer_properties, 'handle' ), true );
					if ( false !== $key ) {
						$prop['required'] = $lianamailer_properties[ $key ]['required'];
						$prop['type']     = $lianamailer_properties[ $key ]['type'];
					}
				}

				$site_data['properties'] = $site['properties'];
				$site_data['lists']      = $site['lists'];
				$site_data['consents']   = $site_consents;
				self::$site_data         = $site_data;
				set_transient( 'lianamailer_' . $selected_site, $site_data, DAY_IN_SECONDS );
			}
		}
	}

	/**
	 * Generates array of LianaMailer properties
	 *
	 * @param boolean $core_fields Should we fetch LianaMailer core fields also. Defaults to false.
	 * @param array   $properties LianaMailer site property data as array.
	 *
	 * @return array of properties
	 */
	private function get_lianamailer_properties( $core_fields = false, $properties = array() ) {
		$fields            = array();
		$customer_settings = self::$lianamailer_connection->get_lianamailer_customer();
		// if couldnt fetch customer settings we assume something is wrong with API or credentials.
		if ( empty( $customer_settings ) ) {
			return $fields;
		}

		// append Email and SMS fields.
		if ( $core_fields ) {
			$fields[] = array(
				'name'         => 'email',
				'visible_name' => 'email',
				'handle'       => 'email',
				'required'     => true,
				'type'         => 'text',
			);
			$fields[] = array(
				'name'         => 'sms',
				'visible_name' => 'sms',
				'handle'       => 'sms',
				'required'     => false,
				'type'         => 'text',
			);
		}

		if ( ! empty( $properties ) ) {
			$properties = array_map(
				function( $field ) {
					return array(
						'name'     => $field['name'],
						'handle'   => $field['handle'],
						'required' => $field['required'],
						'type'     => $field['type'],
					);
				},
				$properties
			);

			$fields = array_merge( $fields, $properties );
		}

		return $fields;

	}

	/**
	 * Enqueue plugin CSS and JS
	 */
	public function add_lianamailer_plugin_scripts() {
		wp_enqueue_style( 'lianamailer-gravity-forms-admin-css', dirname( plugin_dir_url( __FILE__ ) ) . '/css/admin.css', array(), LMCGF_VERSION );

		$form_id = absint( rgget( 'id' ) );
		$js_vars = array(
			'url'     => admin_url( 'admin-ajax.php' ),
			'form_id' => $form_id,
		);
		wp_register_script( 'lianamailer-gravity-forms-plugin', dirname( plugin_dir_url( __FILE__ ) ) . '/js/lianamailer-plugin.js', array( 'jquery' ), LMCGF_VERSION, false );
		wp_localize_script( 'lianamailer-gravity-forms-plugin', 'lianaMailerConnection', $js_vars );
		wp_enqueue_script( 'lianamailer-gravity-forms-plugin' );
	}

	/**
	 * Add LianaMailer field tooltip
	 *
	 * @param array $tooltips Existing GF tooltips.
	 */
	public function set_lianamailer_field_tooltips( $tooltips ) {
		$lianamailer_tooltips = array(
			'property_map_setting' => sprintf( '<h6>%s</h6>%s', esc_html__( 'Property map for LianaMailer', 'lianamailer-for-gf' ), esc_html__( 'Map form fields for LianaMailer fields', 'lianamailer-for-gf' ) ),
			'opt_in_setting'       => sprintf( '<h6>%s</h6>%s', esc_html__( 'Opt-in for LianaMailer', 'lianamailer-for-gf' ), esc_html__( 'Select this if user should be able to subscribe the newsletter without giving consent', 'lianamailer-for-gf' ) ),
		);

		return array_merge( $tooltips, $lianamailer_tooltips );
	}
}

?>
