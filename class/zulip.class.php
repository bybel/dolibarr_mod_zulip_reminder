<?php

class ZulipClient
{
	private $server_url;
	private $bot_email;
	private $bot_api_key;
	private $error;

	public function __construct()
	{
		global $conf;
		$this->server_url = getDolGlobalString('ZULIP_SERVER_URL');
		$this->bot_email = getDolGlobalString('ZULIP_BOT_EMAIL');
		$this->bot_api_key = getDolGlobalString('ZULIP_BOT_API_KEY');

		// ensure clean Server URL
		$this->server_url = rtrim($this->server_url, '/');
	}

	/**
	 * Send a message to a stream (channel)
	 * 
	 * @param string $stream Stream name
	 * @param string $topic Topic name
	 * @param string $content Message content
	 * @return bool true on success, false on failure
	 */
	public function sendStreamMessage($stream, $topic, $content)
	{
		if (empty($this->server_url) || empty($this->bot_email) || empty($this->bot_api_key)) {
			$this->error = "Zulip API credentials are not configured.";
			dol_syslog('ZulipClient: ' . $this->error, LOG_ERR);
			return false;
		}

		if (empty($stream)) {
			$this->error = "ZulipClient: No destination stream provided.";
			dol_syslog($this->error, LOG_ERR);
			return false;
		}

		$endpoint = $this->server_url . "/api/v1/messages";

		$data = array(
			'type' => 'stream',
			'to' => $stream,
			'topic' => $topic,
			'content' => $content
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERPWD, $this->bot_email . ":" . $this->bot_api_key);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if ($httpCode == 200) {
			dol_syslog('ZulipClient: Message successfully sent to stream ' . $stream);
			return true;
		} else {
			$this->error = "Zulip API error HTTP " . $httpCode . ", Response: " . $response . ", cURL Error: " . $curlError;
			dol_syslog('ZulipClient: ' . $this->error, LOG_ERR);
			return false;
		}
	}

	/**
	 * Send a direct message to a user or users
	 * 
	 * @param array|string $to_emails Array of emails or a single email string
	 * @param string $content Message content
	 * @return bool true on success, false on failure
	 */
	public function sendPrivateMessage($to_emails, $content)
	{
		if (empty($this->server_url) || empty($this->bot_email) || empty($this->bot_api_key)) {
			$this->error = "Zulip API credentials are not configured.";
			dol_syslog('ZulipClient: ' . $this->error, LOG_ERR);
			return false;
		}

		if (empty($to_emails)) {
			$this->error = "ZulipClient: No destination provided.";
			dol_syslog($this->error, LOG_ERR);
			return false;
		}

		$endpoint = $this->server_url . "/api/v1/messages";

		$to_array = is_array($to_emails) ? $to_emails : array($to_emails);
		foreach ($to_array as &$item) {
			if (is_numeric($item)) $item = (int)$item;
		}

		$data = array(
			'type' => 'private',
			'to' => json_encode(array_values($to_array)),
			'content' => $content
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERPWD, $this->bot_email . ":" . $this->bot_api_key);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if ($httpCode == 200) {
			dol_syslog('ZulipClient: Message successfully sent as private message. Response: ' . $response, LOG_ERR);
			return true;
		} else {
			$this->error = "Zulip API error HTTP " . $httpCode . ", Response: " . $response . ", cURL Error: " . $curlError;
			dol_syslog('ZulipClient: ' . $this->error, LOG_ERR);
			return false;
		}
	}

	/**
	 * Get user details by email
	 * 
	 * @param string $email User email
	 * @return array|bool Array with user details (user_id, full_name) or false on failure
	 */
	public function getUserByEmail($email)
	{
		if (empty($this->server_url) || empty($this->bot_email) || empty($this->bot_api_key)) {
			return false;
		}

		$endpoint = $this->server_url . "/api/v1/users/" . urlencode($email);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERPWD, $this->bot_email . ":" . $this->bot_api_key);
		
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode == 200) {
			$data = json_decode($response, true);
			if (isset($data['result']) && $data['result'] == 'success' && isset($data['user'])) {
				return $data['user'];
			}
		}
		return false;
	}

	public function getLastError()
	{
		return $this->error;
	}
}
