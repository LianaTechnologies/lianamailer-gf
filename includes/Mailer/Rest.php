<?php

namespace GF_LianaMailer;

class Rest {

	protected $api_key;
	protected $api_user;
	protected $api_url;
	protected $api_version = 1;
	protected $api_realm;


	/**
	 * change api version
	 *
	 * @param integer $v LianaMailer API version 1|2|3
	 */
	public function setApiVersion(int $v) {
		$available_apis = [1, 2, 3];
		if (in_array($v, $available_apis)) {
			$this->api_version = $v;
		}
		return $this->api_version;
	}

	public function __construct($api_user, $api_key, $api_realm = 'PV', $api_url = 'https://rest.lianamailer.com', $api_version = null) {
		$this->api_user = $api_user;
		$this->api_key = $api_key;
		$this->api_url = $api_url;
		$this->api_version = empty($api_version) ? $this->api_version : intval($api_version);
		$this->api_realm = $api_realm;
	}

	public function call($method, $args = array()) {
		return $this->request($method, $args);
	}

	public function getStatus() {
		try {
			$response = $this->call('echoMessage', 'hello');
			return $response == 'hello' ? true : false;
		} catch (RestClientAuthorizationException $ex) {
			return false;
		}
	}

	protected function sign(array $message) {
		return hash_hmac('sha256', implode("\n", $message), $this->api_key);
	}

	protected function request($method, $args = array()) {
		$contents = json_encode($args);
		$md5 = md5($contents);

		$datetime = new \DateTime(null, new \DateTimeZone('Europe/Helsinki'));
		$timestamp = $datetime->format('c');
		$type = 'POST';
		$url = $this->api_url . '/api/v' . $this->api_version . '/' . $method;
		$message = array(
			$type,
			$md5,
			'application/json',
			$timestamp,
			$contents,
			'/api/v' . $this->api_version . '/' . $method
		);
		$signature = $this->sign($message);
		$user_id = $this->api_user;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			"Content-MD5: {$md5}",
			"Date: {$timestamp}",
			"Authorization: {$this->api_realm} {$user_id}:{$signature}"
		));
		curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-LMAPI');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_POST, $type == 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $contents);
		$result = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		switch ($http_code) {
			case 401:
				throw new RestClientAuthorizationException;
		}

		if ($result) {
			$result = json_decode($result, true);
			// Every response might not have "succeed" key
			if (!isset($result['succeed']) || $result['succeed'] !== true) {
				if (!empty($result['message'])) {
					throw new RestClientAuthorizationException($result['message']);
				} else {
					throw new RestClientAuthorizationException('Record not found');
				}
			}

			return $result['result'];
		}

		return false;
	}

}

class RestClientAuthorizationException extends \Exception {

}
