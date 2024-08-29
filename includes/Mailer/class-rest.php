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
 * REST class for Gravity Forms plugin
 *
 * PHP Version 7.4
 *
 * @package  LianaMailer
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */
class Rest {

	/**
	 * REST API key
	 *
	 * @var api_key string
	 */
	protected $api_key;

	/**
	 * REST API user
	 *
	 * @var api_user string
	 */
	protected $api_user;

	/**
	 * REST API url
	 *
	 * @var api_url string
	 */
	protected $api_url;

	/**
	 * REST API version
	 *
	 * @var api_version int
	 */
	protected $api_version = 1;

	/**
	 * REST API realm
	 *
	 * @var api_realm string
	 */
	protected $api_realm;


	/**
	 * Change api version
	 *
	 * @param integer $v LianaMailer API version 1|2|3.
	 */
	public function set_api_version( int $v ) {
		$available_apis = array( 1, 2, 3 );
		if ( in_array( $v, $available_apis, true ) ) {
			$this->api_version = $v;
		}
		return $this->api_version;
	}

	/**
	 * Constructor
	 *
	 * @param string $api_user API user id.
	 * @param string $api_key API key.
	 * @param string $api_realm API realm.
	 * @param string $api_url API url.
	 * @param string $api_version API version used.
	 */
	public function __construct( $api_user, $api_key, $api_realm = 'PV', $api_url = 'https://rest.lianamailer.com', $api_version = null ) {
		$this->api_user    = $api_user;
		$this->api_key     = $api_key;
		$this->api_url     = $api_url;
		$this->api_version = empty( $api_version ) ? $this->api_version : intval( $api_version );
		$this->api_realm   = $api_realm;
	}

	/**
	 * Call function for request
	 *
	 * @param string $method Method to call.
	 * @param array  $args Arguments for the call.
	 */
	public function call( $method, $args = array() ) {
		return $this->request( $method, $args );
	}

	/**
	 * Function tests if API connection works.
	 */
	public function get_status() {
		try {
			$response = $this->call( 'echoMessage', 'hello' );
			return 'hello' === $response ? true : false;
		} catch ( \Exception $ex ) {
			return false;
		}
	}

	/**
	 * Sign REST API call
	 *
	 * @param array $message Message body.
	 */
	protected function sign( array $message ) {
		return hash_hmac( 'sha256', implode( "\n", $message ), $this->api_key );
	}

	/**
	 * Make a API Call
	 *
	 * @param string $method Method to call.
	 * @param array  $args Arguments for call.
	 * @throws \Exception If API call fails.
	 */
	protected function request( $method, $args = array() ) {
		$contents = wp_json_encode( $args );
		$md5      = md5( $contents );

		$datetime  = new \DateTime( 'now', new \DateTimeZone( 'Europe/Helsinki' ) );
		$timestamp = $datetime->format( 'c' );
		$type      = 'POST';
		$url       = $this->api_url . '/api/v' . $this->api_version . '/' . $method;
		$message   = array(
			$type,
			$md5,
			'application/json',
			$timestamp,
			$contents,
			'/api/v' . $this->api_version . '/' . $method,
		);
		$signature = $this->sign( $message );
		$user_id   = $this->api_user;

		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Content-MD5'   => $md5,
				'Date'          => $timestamp,
				'Authorization' => $this->api_realm . ' ' . $user_id . ':' . $signature,
			),
			'body'    => $contents,
			'method'  => $type,
		);

		$wp_remote = wp_remote_get( $url, $args );
		$result    = wp_remote_retrieve_body( $wp_remote );
		$http_code = wp_remote_retrieve_response_code( $wp_remote );

		switch ( $http_code ) {
			case 401:
				throw new \Exception();
		}

		if ( $result ) {
			$result = json_decode( $result, true );
			// Every response might not have "succeed" key.
			if ( ! isset( $result['succeed'] ) || true !== $result['succeed'] ) {
				if ( ! empty( $result['message'] ) ) {
					throw new \Exception( $result['message'] );
				} else {
					throw new \Exception( 'Record not found' );
				}
			}

			return $result['result'];
		}

		return false;
	}

}
