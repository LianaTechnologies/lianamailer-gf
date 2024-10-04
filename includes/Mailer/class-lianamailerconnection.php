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

/**
 * LianaMailer connection class for Gravity Forms plugin
 *
 * PHP Version 7.4
 *
 * @package  LianaMailer
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */
class LianaMailerConnection {

	/**
	 * REST object
	 *
	 * @var rest object
	 */
	private $rest;

	/**
	 * Recipient id
	 *
	 * @var recipient_id string
	 */
	private $recipient_id;

	/**
	 * Recipient consents
	 *
	 * @var recipient_consents array
	 */
	private $recipient_consents;

	/**
	 * Recipient properties
	 *
	 * @var recipient_properties array
	 */
	private $recipient_properties;

	/**
	 * Constructor
	 */
	public function __construct() {

		$lianamailer_settings = get_option( 'lianamailer_gf_options' );

		$user_id    = null;
		$secret_key = null;
		$realm      = null;
		$url        = null;

		if ( ! empty( $lianamailer_settings['lianamailer_userid'] ) ) {
			$user_id = $lianamailer_settings['lianamailer_userid'];
		}
		if ( ! empty( $lianamailer_settings['lianamailer_secret_key'] ) ) {
			$secret_key = $lianamailer_settings['lianamailer_secret_key'];
		}
		if ( ! empty( $lianamailer_settings['lianamailer_realm'] ) ) {
			$realm = $lianamailer_settings['lianamailer_realm'];
		}
		if ( ! empty( $lianamailer_settings['lianamailer_url'] ) ) {
			$url = $lianamailer_settings['lianamailer_url'];
		}

		$this->rest = new Rest(
			$user_id,
			$secret_key,
			$realm,
			$url
		);
	}

	/**
	 * Getting global WordPress request object
	 *
	 * @return object global WordPress object
	 */
	private function get_wordpress_request() {
		global $wp;
		return $wp->request;
	}

	/**
	 * Test REST API
	 *
	 * @return string $status
	 */
	public function get_status() {
		try {
			$status = $this->rest->call( 'echoMessage', 'hello' );
		} catch ( \Exception $e ) {
			$status = null;
		}
		return $status;
	}

	/**
	 * Get LianaMailer account sites
	 * Ref: https://rest.lianamailer.com/docs/#operation/v1-post-sites
	 */
	public function get_account_sites() {
		try {
			$account_sites = $this->rest->call(
				'sites',
				array(
					array(
						'properties'    => true,
						'lists'         => true,
						'layout'        => false,
						'marketing'     => false,
						'parents'       => false,
						'children'      => false,
						'authorization' => false,
					),
					// If account does not have multiple list subscription enabled, this ensures default list is returned.
					array(
						'all_lists' => true,
					),
				)
			);
		} catch ( \Exception $e ) {
			$account_sites = array();
		}
		if ( ! $account_sites ) {
			return array();
		}
		return $account_sites;
	}

	/**
	 * Get specific LianaMailer site consents
	 * Ref: https://rest.lianamailer.com/docs/#operation/v1-post-getConsentTypesBySite
	 *
	 * @param string $domain LianaMailer site domain.
	 */
	public function get_site_consents( $domain ) {
		try {
			$site_consents = $this->rest->call( 'getConsentTypesBySite', array( $domain ) );
		} catch ( \Exception $e ) {
			$site_consents = array();
		}

		return $site_consents;
	}

	/**
	 * Get recipient data by email
	 * Ref: https://rest.lianamailer.com/docs/#tag/Members/paths/~1v1~1getRecipientByEmail/post
	 *
	 * @param string $email Submitters email.
	 *
	 * @return array $result Recipient data.
	 */
	public function get_recipient_by_email( $email ) {
		try {
			$result = $this->rest->call( 'getRecipientByEmail', array( $email, true ) );
			if ( isset( $result['recipient']['id'] ) && intval( $result['recipient']['id'] ) ) {
				$this->recipient_id = $result['recipient']['id'];
			}

			if ( isset( $result['consents'] ) && ! empty( $result['consents'] ) ) {
				$this->recipient_consents = $result['consents'];
			}
			return $result;
		} catch ( \Exception $e ) {
			return;
		}

	}

	/**
	 * Get recipient data by SMS.
	 * Ref: https://rest.lianamailer.com/docs/#operation/v1-post-getRecipientBySMS
	 *
	 * @param string $sms Submitters SMS.
	 *
	 * @return array $result Recipient data.
	 */
	public function get_recipient_by_sms( $sms ) {
		try {
			$result = $this->rest->call( 'getRecipientBySMS', array( $sms, true ) );
			if ( isset( $result['recipient']['id'] ) && intval( $result['recipient']['id'] ) ) {
				$this->recipient_id = $result['recipient']['id'];
			}

			if ( isset( $result['consents'] ) && ! empty( $result['consents'] ) ) {
				$this->recipient_consents = $result['consents'];
			}
			return $result;
		} catch ( \Exception $e ) {
			return;
		}

	}

	/**
	 * Reactivate recipient.
	 * Works only with email.
	 * Ref: https://rest.lianamailer.com/docs/#tag/Members/paths/~1v1~1reactivateRecipient/post
	 *
	 * @param string  $email Submitters email.
	 * @param boolean $auto_confirm true if LianaMailer site is not using welcome mail functionality.
	 */
	public function reactivate_recipient( $email, $auto_confirm ) {

		try {
			$data         = array(
				$email,
				'User',
				'Recipient filled out a form on website.',
				$auto_confirm,
				null,
				esc_url( home_url( $this->get_wordpress_request() ) ),
			);
			$recipient_id = $this->rest->call( 'reactivateRecipient', $data );
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Add new recipient to mailinglist or update existing one. email and SMS are used to find existing recipient, one of these must be given.
	 * Ref: https://rest.lianamailer.com/docs/#operation/v1-post-createAndJoinRecipient
	 *
	 * @param array   $recipient Existing recipient data.
	 * @param string  $email Submitters email.
	 * @param string  $sms Submitters SMS.
	 * @param string  $list_id LianaMailer list id.
	 * @param boolean $auto_confirm true if LianaMailer site is not using welcome mail functionality.
	 */
	public function create_and_join_recipient( $recipient, $email, $sms, $list_id, $auto_confirm ) {
		try {
			// If email was not mapped, use recipient existing email address.
			if ( empty( $email ) && isset( $recipient['recipient']['email'] ) & ! empty( $recipient['recipient']['email'] ) ) {
				$email = $recipient['recipient']['email'];
			}
			// If sms was not mapped, use recipient existing sms.
			if ( empty( $sms ) && isset( $recipient['recipient']['sms'] ) && ! empty( $recipient['recipient']['sms'] ) ) {
				$sms = $recipient['recipient']['sms'];
			}
			settype( $list_id, 'array' );
			$data = array(
				null,
				$email,
				$sms,
				$this->recipient_properties,
				$auto_confirm,
				'Recipient filled out a form on website.',
				esc_url( home_url( $this->get_wordpress_request() ) ),
				$list_id,
			);

			$recipient_id = $this->rest->call( 'createAndJoinRecipient', $data );
			if ( intval( $recipient_id ) ) {
				$this->recipient_id = $recipient_id;
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Adds consent for a recipient
	 * Ref: https://rest.lianamailer.com/docs/#tag/Members/paths/~1v1~1addMemberConsent/post
	 *
	 * @param array $consent_data Consent related data.
	 */
	public function add_recipient_consent( $consent_data ) {

		if ( empty( $consent_data ) ) {
			return;
		}

		$add_concent_to_recipient = true;
		$consent_id               = $consent_data['consent_id'];
		if ( ! empty( $this->recipient_consents ) ) {
			foreach ( $this->recipient_consents as $consent ) {
				if ( $consent['consent_id'] === $consent_data['consent_id'] && $consent['consent_revision'] === $consent_data['consent_revision'] && empty( $consent['revoked'] ) ) {
					$add_concent_to_recipient = false;
					break;
				}
			}
		}

		// if recipient_id, consent_id is not set or recipient already had consent applied, bail out.
		if ( ! $this->recipient_id || ! $consent_data['consent_id'] || ! $add_concent_to_recipient ) {
			return;
		}

		$args = array(
			'member_id'        => $this->recipient_id,
			'consent_id'       => $consent_data['consent_id'],
			'consent_revision' => $consent_data['consent_revision'],
			'lang'             => $consent_data['language'],
			'source'           => 'api',
			'source_data'      => esc_url( home_url( $this->get_wordpress_request() ) ),
		);

		try {
			$this->rest->call( 'addMemberConsent', array_values( $args ) );
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Send welcome mail to reciepient
	 * Ref: https://rest.lianamailer.com/docs/#operation/v1-post-sendWelcomeMail
	 *
	 * @param string $domain Site domain.
	 */
	public function send_welcome_mail( $domain ) {
		$args = array(
			$this->recipient_id,
			$domain,
		);
		try {
			$this->rest->call( 'sendWelcomeMail', $args );
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Set recipient property data. Used in create_and_join_recipient()
	 *
	 * @param array $props Filtered properties for LianaMailer.
	 */
	public function set_properties( $props ) {
		$this->recipient_properties = $props;
	}

	/**
	 * Get all properties from LianaMailer account
	 * Ref: https://rest.lianamailer.com/docs/#operation/v1-post-getCustomerProperties
	 */
	public function get_lianamailer_properties() {
		try {
			$fields = $this->rest->call( 'getCustomerProperties' );
		} catch ( \Exception $e ) {
			$fields = array();
		}
		if ( ! $fields ) {
			return array();
		}
		return $fields;
	}

	/**
	 * Get LianaMailer customer settings
	 * Ref: https://rest.lianamailer.com/docs/#operation/v1-post-getCustomer
	 */
	public function get_lianamailer_customer() {
		try {
			$customer = $this->rest->call( 'getCustomer' );
		} catch ( \Exception $e ) {
			$customer = array();
		}
		if ( ! $customer ) {
			return array();
		}
		return $customer;
	}

}
