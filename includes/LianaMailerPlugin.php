<?php

namespace GF_LianaMailer;

defined( 'ABSPATH' ) or exit;

class_exists( 'GFForms' ) or die();

use Gravity_Forms\Gravity_Forms\Settings\Settings;

class LianaMailerPlugin {

	/**
	 * Stores the current instance of the Settings renderer.
	 *
	 * @since 2.5
	 *
	 * @var false|Gravity_Forms\Gravity_Forms\Settings\Settings
	 */
	private static $_settings_renderer = false;

	private $post_data;
	private static $lianaMailerConnection;
	private static $site_data = [];
	private static $is_connection_valid = false;

	private static $_form;

	public function __construct() {
		self::$lianaMailerConnection = new LianaMailerConnection();
	}

	public function add_hooks() {
		// include plugin JS and CSS
		add_action( 'admin_enqueue_scripts', [ $this, 'addLianaMailerPluginScripts' ], 10, 1 );
		// AJAX callback for LianaMailer plugin settings change
		add_action( 'wp_ajax_getSiteDataForGFSettings', [ $this, 'getSiteDataForGFSettings'], 10, 1);


		// displaying content for custom tab when selected
		add_action( 'gform_form_settings_page_lianaMailerSettings', [$this, 'lianaMailerSettings'] );
		// add a custom menu item to the Form Settings page menu
		add_filter( 'gform_form_settings_menu', [$this, 'addLianaMailerSettings']);

		// Register GF_Field_LianaMailer custom field when Gravity Forms is loaded
		add_action('gform_loaded',  [$this, 'initPlugin']);

		// add a content for custom "lianamailer" field
		add_action( 'gform_field_standard_settings', [$this, 'addLianaMailerField'], 10, 2);
		// filter LianaMailer field tooltip(s)
		add_filter( 'gform_tooltips', [ $this, 'setLianaMailerFieldTooltips' ] );
		// Filter integration settings for custom field options
		add_filter( 'gform_lianamailer_get_integration_options', [$this, 'getIntegrationOptions'], 10, 2);

		// Do newsletter subscription
		add_action( 'gform_after_submission', [$this, 'subscribeNewsletter'], 10, 2 );

	}

	/**
	 * add_action( 'gform_after_submission', [$this, 'subscribeNewsletter'], 10, 2 );
	 */
	public function subscribeNewsletter($entry, $form) {

		$lianaMailerSettings	= $form['lianamailer'];
		$isPluginEnabled		= $lianaMailerSettings['lianamailer_enabled'] ?? false;
		$list_id				= $lianaMailerSettings['lianamailer_mailing_list'] ?? null;
		$consent_id				= $lianaMailerSettings['lianamailer_consent'] ?? null;
		$selectedSite			= $lianaMailerSettings['lianamailer_site'] ?? null;

		if(!intval($isPluginEnabled)) {
			return;
		}

		$fields = $form['fields'];
		// Fetch all non LianaMailer properties for settings
		foreach($fields as $key => $fieldObject) {
			if($fieldObject instanceof GF_Field_LianaMailer == false) {
				continue;
			}
			$lianaMailerField = $fieldObject;
		}

		$property_map = [];
		if(isset($lianaMailerField['lianamailer_properties'])) {
			// keys are LianaMailer properties and values GForms
			$property_map = $lianaMailerField['lianamailer_properties'];
		}

		self::getLianaMailerSiteData($selectedSite);
		// No LianaMailer site data found. Maybe issue with credentials or REST API
		if(empty(self::$site_data)) {
			return;
		}

		$fieldMapEmail	= (array_key_exists('email', $property_map) ? $property_map['email'] : null);
		$fieldMapSMS	= (array_key_exists('sms', $property_map) ? $property_map['sms'] : null);

		$email = $sms = $recipient = null;

		$postedData = [];
		foreach ( $form['fields'] as $field ) {

			$inputs = $field->get_entry_inputs();
			$value = rgar( $entry, (string) $field->id );
			$postedData[$field->id] = $value;

			if($field->id == $fieldMapEmail) {
				$email = $value;
			}
			if($field->id == $fieldMapSMS) {
				$sms = $value;
			}
		}

		$this->post_data = $postedData;
		$consentData = [];

		try {
			if(empty($list_id)) {
				throw new \Exception('No mailing lists set');
			}
			if(empty($email) && empty($sms)) {
				throw new \Exception('No email or SMS -field set');
			}

			$subscribeByEmail	= false;
			$subscribeBySMS 	= false;
			if($email) {
				$subscribeByEmail = true;
			}
			else if($sms) {
				$subscribeBySMS = true;
			}

			if( $subscribeByEmail ||  $subscribeBySMS ) {

				$customerSettings = self::$lianaMailerConnection->getMailerCustomer();
				// autoconfirm subscription if:
				// * LM site has "registration_needs_confirmation" disabled
				// * email not set
				// * LM site does not have welcome mail set
				$autoConfirm = (empty($customerSettings['registration_needs_confirmation']) || !$email || !self::$site_data['welcome']);

				$properties = $this->filterRecipientProperties($property_map);
				self::$lianaMailerConnection->setProperties($properties);

				if($subscribeByEmail) {
					$recipient = self::$lianaMailerConnection->getRecipientByEmail($email);
				}
				else {
					$recipient = self::$lianaMailerConnection->getRecipientBySMS($sms);
				}

				// if recipient found from LM and it not enabled and subscription had email set, re-enable it
				if (!is_null($recipient) && isset($recipient['recipient']['enabled']) && $recipient['recipient']['enabled'] === false && $email) {
					self::$lianaMailerConnection->reactivateRecipient($email, $autoConfirm);
				}
				self::$lianaMailerConnection->createAndJoinRecipient($email, $sms, $list_id, $autoConfirm);

				$consentKey = array_search($consent_id, array_column(self::$site_data['consents'], 'consent_id'));
				if($consentKey !== false) {
					$consentData = self::$site_data['consents'][$consentKey];
					//  Add consent to recipient
					self::$lianaMailerConnection->addRecipientConsent($consentData);
				}


				// if not existing recipient or recipient was not confirmed and site is using welcome -mail and LM account has double opt-in enabled and email address set
				if((!$recipient || !$recipient['recipient']['confirmed']) && self::$site_data['welcome'] && $customerSettings['registration_needs_confirmation'] && $email) {
					self::$lianaMailerConnection->sendWelcomeMail(self::$site_data['domain']);
				}

			}
		}
		catch(\Exception $e) {
			$failure_reason = $e->getMessage();
			error_log('Failure: '.$failure_reason);
		}
	}

	/**
	 * Filters certain propertis from posted data
	 */
	private function filterRecipientProperties($property_map = []) {

		$properties = $this->getLianaMailerProperties(false, self::$site_data['properties']);
		$props = [];
		foreach($properties as $property) {
			$propertyName = $property['name'];
			$propertyHandle = $property['handle'];
			$field_id = (isset($property_map[$propertyHandle]) ? $property_map[$propertyHandle] : null);

			// if Property value havent been posted, leave it as it is
			if( !isset( $this->post_data[$field_id] ) ) {
				continue;
			}
			// otherwise update it into LianaMailer
			$props[$propertyName] = sanitize_text_field( $this->post_data[$field_id] );
		}
		return $props;
	}

	/**
	 * // Filter integration settings for custom field options
	 * add_filter( 'gform_lianamailer_get_integration_options', [$this, 'getIntegrationOptions']);
	 */
	public function getIntegrationOptions($options, $form_id) {
		$form = self::get_form( $form_id );

		if(isset($form['lianamailer']['lianamailer_site'])) {
			$selectedSite = $form['lianamailer']['lianamailer_site'];
			self::getLianaMailerSiteData($selectedSite);
		}
		$valid = self::$is_connection_valid = self::$lianaMailerConnection->getStatus();
		if(!$valid) {
			$options['is_connection_valid'] = false;
		}

		if(isset($form['lianamailer']['lianamailer_enabled'])) {
			$options['enabled'] = $form['lianamailer']['lianamailer_enabled'];
		}

		// If GF LianaMailer settings doesnt have consent set or if LianaMailer site doesnt have any consents
		if(!isset($form['lianamailer']['lianamailer_consent']) || empty($form['lianamailer']['lianamailer_consent']) || !isset(self::$site_data['consents']) || empty(self::$site_data['consents'])) {
			$options['label'] = 'No consent found';
			$options['precheck'] = true;
			return $options;
		}

		foreach(self::$site_data['consents'] as $consent) {
			if($consent['consent_id'] == $form['lianamailer']['lianamailer_consent']) {
				$options['label'] = $consent['description'];
				$options['consent'] = $consent['consent_id'];
				break;
			}
		}

		return $options;
	}

	/**
	 * AJAX callback for LianaMailer plugin settings change
	 * Returns selected LianaMailer site mailing lists and consents
	 * add_action( 'wp_ajax_getSiteDataForGFSettings', [ $this, 'getSiteDataForGFSettings'], 10, 1);
	 */
	public function getSiteDataForGFSettings() {
		if ( ! class_exists( 'GFFormSettings' ) ) {
			require_once( \GFCommon::get_base_path() . '/form_settings.php' );
		}

		require_once( \GFCommon::get_base_path() . '/form_detail.php' );


		$accountSites = self::$lianaMailerConnection->getAccountSites();
		$selectedSite = $_POST['site'];
		//$form_id = intval($_POST['id']);

		$data = [
			'lists' => [],
			'consents' => []
		];
		foreach($accountSites as &$site) {
			if($site['domain'] == $selectedSite) {
				$data['lists'] = $site['lists'];
				$data['consents'] = (self::$lianaMailerConnection->getSiteConsents($site['domain']) ?? []);
				break;
			}
		}

		echo json_encode($data);
		wp_die();
	}

	/**
	 * Add a custom menu item to the Form Settings page menu
	 * add_filter( 'gform_form_settings_menu', [$this, 'addLianaMailerSettings']);
	 */
	public function addLianaMailerSettings( $menu_items ) {

		$menu_items[] = array(
			'name' => 'lianaMailerSettings',
			'label' => __( 'LianaMailer' ),
			'icon' => 'gform-icon--lianamailer'
		);

		return $menu_items;
	}


	/**
	 * Displaying content for custom tab when selected
	 * add_action( 'gform_form_settings_page_lianaMailerSettings', [$this, 'lianaMailerSettings'] );
	 */
	public function lianaMailerSettings() {

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
			require_once( GFCommon::get_base_path() . '/form_settings.php' );
		}

		require_once(\GFCommon::get_base_path() . '/form_detail.php' );

		// Get form, confirmation IDs.
		$form_id	= absint( rgget( 'id' ) );
		$form		= self::get_form( $form_id );

		// Initialize new settings renderer.
		$renderer = new Settings(
			array(
				'fields'	=> $this->getLianaMailerSettingsFields($form),
				'header'	=> array(
					'icon'  => 'fa fa-cogs',
					'title' => esc_html__( 'LianaMailer settings', 'lianamailer' ),
				),
				'initial_values' => rgar( $form, 'lianamailer' ),
				'save_callback'  => [ $this, 'process_form_settings' ],
				// JS which updates selected values
				'after_fields' => function() use ($form_id) {
					$form = self::get_form( $form_id );
					?>
					<script type="text/javascript">

						var form = <?php echo json_encode( $form ); ?>;
						//console.log('form ',form);
						( function( $ ){
							jQuery( document ).trigger( 'gform_load_lianamailer_form_settings', [ form ] );
						})( jQuery );

					</script>
					<?php

				}
			)
		);


		self::set_settings_renderer( $renderer );

		/*
		// Process save callback.
		if ( self::get_settings_renderer()->is_save_postback() ) {
			self::get_settings_renderer()->process_postback();
		}
		*/


	}

	/**
	 * Callback for saving LianaMailer settings
	 */
	public function process_form_settings($values) {
		$form = self::get_form( rgget( 'id' ) );

		// if disabling functionality, retain old values. These arent posted because inputs are disabled
		if($values['lianamailer_enabled'] == 0) {
			$values['lianamailer_site'] = $form['lianamailer']['lianamailer_site'];
			$values['lianamailer_mailing_list'] = $form['lianamailer']['lianamailer_mailing_list'];
			$values['lianamailer_consent'] = $form['lianamailer']['lianamailer_consent'];
		}

		// Save form.
		$form['lianamailer'] = $values;
		// Save form.
		\GFAPI::update_form( $form );

		self::$_form = $form;
	}

	/**
	 * Returns the form array for use in the form settings.
	 * @return GFForm object
	 */
	public static function get_form( $form_id ) {
		if ( empty( self::$_form ) ) {
			self::$_form = \GFAPI::get_form( $form_id );
		}

		return self::$_form;
	}

	/**
	 * Return selectable values for LianaMailer settings page
	 * lianamailer_enabled (checkbox)
	 * lianamailer_site (select)
	 * lianamailer_mailing_list (select)
	 * lianamailer_consent (select)
	 * @return array $fields
	 */
	private function getLianaMailerSettingsFields($form) {

		$postedSite		= rgpost( '_gform_setting_lianamailer_site', true );
		$selectedSite = null;
		if(!empty($postedSite)) {
			$selectedSite = $postedSite;
		}
		else if(isset($form['lianamailer']['lianamailer_site'])){
			$selectedSite = $form['lianamailer']['lianamailer_site'];
		}

		$siteData = [];

		$accountSites = self::$lianaMailerConnection->getAccountSites();
		// if account sites not found propably there isnt any or REST API settings arent ok
		if(empty($accountSites)) {
			$valid = self::$lianaMailerConnection->getStatus();
			$fields = array(
				array(
					'title'  => esc_html__( 'LianaMailer settings', 'lianamailer' ),
					'fields' => array(
						array(
							'name'     => 'no-settings',
							'type'     => 'html',
							'html'    => (!$valid ? 'REST API connection failed. <a href="'.admin_url('admin.php?page=lianamailergravityforms').'" target="_blank">Check settings</a>' : 'Unable to find any LianaMailer sites.'),
						)
					),
				),
			);
			return $fields;
		}
		foreach($accountSites as &$site) {
			unset($site['layout']);
			unset($site['marketing']);

			if($site['domain'] == $selectedSite) {
				$siteConsents = (self::$lianaMailerConnection->getSiteConsents($site['domain']) ?? []);

				$siteData['lists'] = isset($site['lists']) ? $site['lists'] : [];
				$siteData['consents'] = $siteConsents;
				self::$site_data = $siteData;
			}
		}

		// Initialize site choices array.
		$siteChoices = [
			['label' => 'Choose','value' => '']
		];
		$mailingListChoices = $consentListChoices = $siteChoices;

		foreach($accountSites as $accountSite) {
			$siteChoices[] = [
				'label' => $accountSite['domain'],
				'value' => $accountSite['domain']
			];
		}

		if(isset(self::$site_data['lists'])) {
			foreach(self::$site_data['lists'] as $list) {
				$mailingListChoices[] = [
					'label' => $list['name'],
					'value' => $list['id']
				];
			}
		}

		if(isset(self::$site_data['consents'])) {
			foreach(self::$site_data['consents'] as $consent) {
				$consentListChoices[] = [
					'label' => $consent['name'],
					'value' => $consent['consent_id']
				];
			}
		}

		// Build confirmation settings fields.
		$fields = array(
			array(
				'title'  => esc_html__( 'LianaMailer settings', 'lianamailer' ),
				'fields' => array(
					array(
						'name'     => 'lianamailer_enabled',
						'label'    => esc_html__( 'Enable LianaMailer -integration on this form', 'lianamailer' ),
						'type'     => 'checkbox',
						'choices'	=> [
							[
								'name' => 'lianamailer_enabled',
								'label' => 'Enable LianaMailer -integration on this form'
							]
						]
					),
					array(
						'name'     => 'lianamailer_site',
						'label'    => esc_html__( 'Site', 'lianamailer' ),
						'type'     => 'select',
						'choices'    => $siteChoices,
					),
					array(
						'name'     => 'lianamailer_mailing_list',
						'label'    => esc_html__( 'Mailing list', 'lianamailer' ),
						'type'     => 'select',
						'choices'    => $mailingListChoices,
					),
					array(
						'name'     => 'lianamailer_consent',
						'label'    => esc_html__( 'Consent', 'lianamailer' ),
						'type'     => 'select',
						'choices'    => $consentListChoices,
					),
					// save button
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
	 * @return false|Gravity_Forms\Gravity_Forms\Settings\Settings
	 */
	public function get_settings_renderer() {
		return self::$_settings_renderer;
	}

	/**
	 * Sets the current instance of Settings handling settings rendering.
	 * @param \Gravity_Forms\Gravity_Forms\Settings\Settings $renderer Settings renderer.
	 * @return bool|WP_Error
	 */
	public function set_settings_renderer( $renderer ) {

		// Ensure renderer is an instance of Settings
		if ( ! is_a( $renderer, 'Gravity_Forms\Gravity_Forms\Settings\Settings' ) ) {
			return new WP_Error( 'Renderer must be an instance of Gravity_Forms\Gravity_Forms\Settings\Settings.' );
		}

		self::$_settings_renderer = $renderer;

		return true;
	}

	/**
	 * Register GF_Field_LianaMailer custom field when Gravity Forms is loaded
	 * add_action('gform_loaded',  [$this, 'initPlugin']);
	 */
	public function initPlugin() {
		// LianaMailer field
		require_once('class-field-lianamailer.php');
		\GF_Fields::register( new GF_Field_LianaMailer() );

		return;
	}

	/**
	 * Adds custom inputs for GF_Field_LianaMailer -field
	 * add_action( 'gform_field_standard_settings', [$this, 'addLianaMailerField'], 10, 2);
	 * @return HTML custom input content
	 */
	public function addLianaMailerField($position, $form_id ) {

		if ( $position !== 0 ) {
			return;
		}

		$form = self::get_form( $form_id );

		if(isset($form['lianamailer']['lianamailer_site'])) {
			$selectedSite = $form['lianamailer']['lianamailer_site'];
			self::getLianaMailerSiteData($selectedSite);
		}

		$fields = $form['fields'];

		$formFields = [];
		// Fetch all non LianaMailer fields for settings
		foreach($fields as $key => $fieldObject) {

			if($fieldObject instanceof GF_Field_LianaMailer != false) {
				continue;
			}
			$formFields[] = $fieldObject;
		}

		// Property mapping
		$html = $this->printPropertySelection($formFields);

		echo $html;
	}

	/**
	 * Get HTML for property selects into custom field
	 * If properties not found, print basic error message
	 * @return HTML for custom field
	 */
	private function printPropertySelection($formFields) {

		// Get all LianaMailer site properties core fields included
		if(isset(self::$site_data['properties'])) {
			$LMproperties = $this->getLianaMailerProperties(true, self::$site_data['properties']);
		}

		$html = '<li class="lianamailer_properties_setting field_setting">';
			$html .= '<label for="field_lianamailer_property" class="section_label">';
				$html .= esc_html__( 'LianaMailer Property Map', 'lianamailer-for-wp' );
				$html .= gform_tooltip( 'property_map_setting', '', true);
			$html .= '</label>';

			if(empty($LMproperties)) {
				if(!self::$is_connection_valid) {
					$html .= 'REST API connection failed. Check <a href="'.admin_url('admin.php?page=lianamailergravityforms').'" target="_blank">settings</a>';
				}
				else {
					$html .= 'No properties found. Check LianaMailer <a href="'.$_SERVER['PHP_SELF'].'?page=gf_edit_forms&view=settings&subview=lianaMailerSettings&id='.rgget('id').'" target="_blank">settings</a>';
				}
			}
			else {
				foreach($LMproperties as $property) {
					$html .= '<div class="lm-property-wrapper">';

						$html .= '<label for="field_lianamailer_property" class="section_label">';
							$html .= '<b>'.$property['name'].(isset($property['handle']) && is_int($property['handle']) ? ' (#'.$property['handle'].')' : '').'</b>';
						$html .= '</label>';
						$html .= '<select id="field_lianamailer_property_'.$property['handle'].'" data-handle="'.$property['handle'].'" onchange="SetLianaMailerProperty(jQuery(this))">';
							$html .= '<option value="">'.esc_html__( 'Select form field', 'lianamailer-for-wp' ).'</option>';
							foreach ( $formFields as $formField ) {
								$html .= sprintf( '<option value="%d">%s</option>', $formField->id, $formField->label );
							}
						$html .= '</select>';
					$html .= '</div>';
				}
			}
		$html .= '</li>';

		return $html;
	}

	/**
	 * Get selected LianaMailer site data:
	 * domain, welcome, properties, lists and consents
	 */
	private static function getLianaMailerSiteData($selectedSite = null) {

		if(!empty(self::$site_data)) {
			return;
		}

		// if site is not selected
		if(!$selectedSite) {
			return;
		}

		// Getting all sites from LianaMailer
		$accountSites = self::$lianaMailerConnection->getAccountSites();

		// Getting all properties from LianaMailer
		$lianaMailerProperties = self::$lianaMailerConnection->getLianaMailerProperties();

		$siteData = [];
		foreach($accountSites as &$site) {
			if($site['domain'] == $selectedSite) {
				$properties = [];
				$siteConsents = (self::$lianaMailerConnection->getSiteConsents($site['domain']) ?? []);

				$siteData['domain'] = $site['domain'];
				$siteData['welcome'] = $site['welcome'];
				foreach($site['properties'] as &$prop) {
					// Add required and type -attributes because getAccountSites() -endpoint doesnt return these
					// https://rest.lianamailer.com/docs/#tag/Sites/paths/~1v1~1sites/post
					$key = array_search($prop['handle'], array_column($lianaMailerProperties, 'handle'));
					if($key !== false) {
						$prop['required'] = $lianaMailerProperties[$key]['required'];
						$prop['type'] = $lianaMailerProperties[$key]['type'];
					}
				}

				$siteData['properties'] = $site['properties'];
				$siteData['lists'] = $site['lists'];
				$siteData['consents'] = $siteConsents;
				self::$site_data = $siteData;
			}
		}
	}

	/**
	 * Generates array of LianaMailer properties
	 * @return array of properties
	 */
	private function getLianaMailerProperties($core_fields = false, $properties = []) {
		$fields = [];
		$customerSettings = self::$lianaMailerConnection->getMailerCustomer();
		// if couldnt fetch customer settings we assume something is wrong with API or credentials
		if(empty($customerSettings)) {
			return $fields;
		}

		// append Email and SMS fields
		if($core_fields) {
			$fields[] = [
				'name'         => 'email',
				'visible_name' => 'email',
				'handle'		=> 'email',
				'required'     => true,
				'type'         => 'text'
			];
			$fields[] = [
				'name'         => 'sms',
				'visible_name' => 'sms',
				'handle'		=> 'sms',
				'required'     => false,
				'type'         => 'text'
			];
		}

		if( !empty( $properties ) ) {
			$properties = array_map( function( $field ){
				return [
					'name'			=> $field[ 'name' ],
					'handle'		=> $field['handle'],
					'required'		=> $field[ 'required' ],
					'type'			=> $field[ 'type' ]
				];
			}, $properties );

			$fields = array_merge($fields, $properties);
		}

		return $fields;

	}

	/**
	 * Enqueue plugin CSS and JS
	 * add_action( 'admin_enqueue_scripts', [ $this, 'addLianaMailerPluginScripts' ], 10, 1 );
	 */
	public function addLianaMailerPluginScripts() {
		wp_enqueue_style('lianamailer-gravity-forms-admin-css', dirname( plugin_dir_url( __FILE__ ) ).'/css/admin.css');

		$form_id         = absint( rgget( 'id' ) );
		$js_vars = [
			'url' => admin_url( 'admin-ajax.php' ),
			'form_id' => $form_id
		];
		wp_register_script('lianamailer-gravity-forms-plugin',  dirname( plugin_dir_url( __FILE__ ) ) . '/js/lianamailer-plugin.js', [ 'jquery' ], false, false );
		wp_localize_script('lianamailer-gravity-forms-plugin', 'lianaMailerConnection', $js_vars );
		wp_enqueue_script('lianamailer-gravity-forms-plugin');
	}

	/**
	 * Add LianaMailer field tooltip
	 * add_filter( 'gform_tooltips', [ $this, 'setLianaMailerFieldTooltips' ] );
	 */
	public function setLianaMailerFieldTooltips($tooltips) {
		$lianaMailerTooltips = array(
			'property_map_setting' => sprintf( '<h6>%s</h6>%s', esc_html__( 'Property map for LianaMailer', 'lianamailer-for-wp' ), esc_html__( 'Map form fields for LianaMailer fields', 'lianamailer-for-wp' ) ),
		);

		return array_merge( $tooltips, $lianaMailerTooltips );
	}
}

?>
