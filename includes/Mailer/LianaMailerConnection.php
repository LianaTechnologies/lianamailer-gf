<?php

namespace GF_LianaMailer;

class LianaMailerConnection {

	private $rest;
	private $recipient_id;
	private $recipient_consents;
	private $recipient_properties;
	private $post_data;

	public function __construct() {

		$lianaMailerSettings = get_option('lianamailer_gravityforms_options');
		$this->rest = new Rest(
			$lianaMailerSettings['lianamailer_userid'],		// userid
			$lianaMailerSettings['lianamailer_secret_key'],	// user secret
			$lianaMailerSettings['lianamailer_realm'],		// realm
			$lianaMailerSettings['lianamailer_url']			// https://rest.lianamailer.com
		);

	}

	private function getWPRequest() {
		global $wp;
		return $wp->request;
	}

	public function getStatus() {
		try {
			$status = $this->rest->call('echoMessage', 'hello');
		} catch( \Exception $e ) {
			$status = null;
		}
		return $status;
	}

	/**
	 * Get LianaMailer account sites
	 * Ref: https://rest.lianamailer.com/docs/#operation/v1-post-sites
	 */
	public function getAccountSites() {
		try {
			$accountSites = $this->rest->call('sites',
				[
					"properties" => true,
					"lists" => true,
					"layout" => false,
					"marketing" => false,
					"parents" => false,
					"children" => false,
					"authorization" => false,
				]
			);
		} catch( \Exception $e ) {
			$accountSites = [];
		}
		return $accountSites;
	}

	/**
	 * Get specific LianaMailer site consents
	 * Ref: https://rest.lianamailer.com/docs/#operation/v1-post-getConsentTypesBySite
	 */
	public function getSiteConsents($domain) {
		try {
			$siteConsents = $this->rest->call('getConsentTypesBySite', [$domain]);
		} catch( \Exception $e ) {
			$siteConsents = [];
		}

		return $siteConsents;
	}

	/**
	 * Ref: https://rest.lianamailer.com/docs/#tag/Members/paths/~1v1~1getRecipientByEmail/post
	 */
	public function getRecipientByEmail( $email ) {
		try {
			$result = $this->rest->call( 'getRecipientByEmail', [ $email, true ] );
			if(isset($result['recipient']['id']) && intval($result['recipient']['id'])) {
				$this->recipient_id = $result['recipient']['id'];
			}

			if(isset($result['consents']) && !empty($result['consents'])) {
				$this->recipient_consents = $result['consents'];
			}
			return $result;
		} catch( \Exception $e ) {

		}
	}

	/**
	 * Ref: https://rest.lianamailer.com/docs/#tag/Members/paths/~1v1~1getRecipientByEmail/post
	 */
	public function getRecipientBySMS( $sms ) {
		try {
			$result = $this->rest->call( 'getRecipientBySMS', [ $sms, true ] );
			if(isset($result['recipient']['id']) && intval($result['recipient']['id'])) {
				$this->recipient_id = $result['recipient']['id'];
			}

			if(isset($result['consents']) && !empty($result['consents'])) {
				$this->recipient_consents = $result['consents'];
			}
			return $result;
		} catch( \Exception $e ) {

		}
	}

	/**
	 * Reactivate recipient
	 * Works only with email
	 * Ref: https://rest.lianamailer.com/docs/#tag/Members/paths/~1v1~1reactivateRecipient/post
	 */
	public function reactivateRecipient($email, $autoConfirm) {
		try {
			$data = [
				$email, 										// email
				"User",											// identity
				"Recipient filled out a form on website.", 		// reason
				$autoConfirm,									// confirm
				null, 											// rise, deprecated
				esc_url(home_url($this->getWPRequest())) 		// origin, use site domain to prevent breaking automations
			];
			$recipientID = $this->rest->call('reactivateRecipient', $data);
		}
		catch(\Exception $e) {

		}
	}

	/**
	 * Add new recipient or update existing one into mailing list. email and SMS are used to find existing recipient, one of these must be given.
	 * Ref: https://rest.lianamailer.com/docs/#operation/v1-post-createAndJoinRecipient
	 */
	public function createAndJoinRecipient($email, $sms, $list_id, $autoConfirm) {
		try {
			settype($list_id, 'array');
			$data = [
				null, 										// id
				$email,										// email
				$sms,										// sms
				$this->recipient_properties,				// recipient properties
				$autoConfirm,								// autoconfirm
				"Recipient filled out a form on website.", 	// reason
				esc_url(home_url($this->getWPRequest())), 	// origin, use site domain to prevent breaking automations
				$list_id,
			];

			$recipientID = $this->rest->call('createAndJoinRecipient', $data);
			if(intval($recipientID)) {
				$this->recipient_id = $recipientID;
			}
		}
		catch(\Exception $e) {

		}
	}

	/**
	 * Adds consent for a recipient
	 * Ref: https://rest.lianamailer.com/docs/#tag/Members/paths/~1v1~1addMemberConsent/post
	 */
	public function addRecipientConsent($consentData) {

		if(empty($consentData)) {
			return;
		}

		$addConcentToRecipient = true;
		$consent_id = $consentData['consent_id'];
		if(!empty($this->recipient_consents)) {
			foreach($this->recipient_consents as $consent) {
				if($consent['consent_id'] == $consentData['consent_id'] && $consent['consent_revision'] == $consentData['consent_revision'] && empty($consent['revoked'])) {
					$addConcentToRecipient = false;
					break;
				}
			}
		}

		// if recipient_id, consent_id is not set or recipient already had consent applied, bail out
		if(!$this->recipient_id || !$consentData['consent_id'] || !$addConcentToRecipient) {
			return;
		}


		$args = [
			'member_id' => $this->recipient_id,
			'consent_id' => $consentData['consent_id'],
			'consent_revision' => $consentData['consent_revision'],
			'lang' => $consentData['language'],
			'source' => 'api',
			'source_data' => esc_url(home_url($this->getWPRequest()))
		];

		try {
			$this->rest->call('addMemberConsent', array_values( $args));
		} catch( \Exception $e ) {

		}
		return;

	}

	/**
	 * Send welcome mail to reciepient
	 * Ref: https://rest.lianamailer.com/docs/#operation/v1-post-sendWelcomeMail
	 */
	public function sendWelcomeMail($site) {
		$args = [
			$this->recipient_id,
			$site,
		];
		try {
			$this->rest->call('sendWelcomeMail', $args);
		} catch( \Exception $e ) {

		}
	}

	/**
	 * Set recipient property data. Used in createAndJoinRecipient()
	 */
	public function setProperties($props) {
		$this->recipient_properties = $props;
	}

	/**
	 * Get all properties from LianaMailer account
	 * Ref: https://rest.lianamailer.com/docs/#operation/v1-post-getCustomerProperties
	 */
	public function getLianaMailerProperties() {
		$fields = [];
		try {
			$fields = $this->rest->call('getCustomerProperties');
		} catch( \Exception $e ) {
			$fields = [];
		}
		return $fields;
	}

	/**
	 * Get LianaMailer customer settings
	 * Ref: https://rest.lianamailer.com/docs/#operation/v1-post-getCustomer
	 */
	public function getMailerCustomer() {
		$customer = [];
		try {
			$customer = $this->rest->call('getCustomer');
		} catch( \Exception $e ) {
			$customer = [];
		}
		return $customer;
	}
}

?>
