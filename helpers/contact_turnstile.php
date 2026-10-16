<?php
/** Contact-only Turnstile integration; credentials belong in private setup.php. */
class TadlContactTurnstile {
	public function __construct(private string $siteKey = '', private string $secretKey = '', private array $hostnames = []) {}

	public static function fromSetup(): self {
		return new self(
			defined('__TADL_TURNSTILE_SITE_KEY__') ? (string)__TADL_TURNSTILE_SITE_KEY__ : '',
			defined('__TADL_TURNSTILE_SECRET_KEY__') ? (string)__TADL_TURNSTILE_SECRET_KEY__ : '',
			defined('__TADL_TURNSTILE_HOSTNAMES__') && is_array(__TADL_TURNSTILE_HOSTNAMES__) ? __TADL_TURNSTILE_HOSTNAMES__ : []
		);
	}

	public function enabled(): bool { return $this->siteKey !== '' || $this->secretKey !== ''; }
	public function siteKey(): string { return $this->siteKey; }

	public function ready(): bool {
		if (trim($this->siteKey) === '' || trim($this->secretKey) === '' || !$this->hostnames) { return false; }
		foreach ($this->hostnames as $hostname) {
			if (!is_string($hostname) || !filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) { return false; }
		}
		return true;
	}

	public function verify($token): bool {
		if (!$this->ready() || !is_string($token) || trim($token) === '' || strlen($token) > 2048) { return false; }
		$response = $this->requestVerification($token);
		if (!$response || $response['status'] !== 200) { return false; }
		$result = json_decode($response['body'], true);
		return is_array($result) && ($result['success'] ?? false) === true
			&& ($result['action'] ?? null) === 'contact'
			&& is_string($result['hostname'] ?? null)
			&& in_array(strtolower($result['hostname']), array_map('strtolower', $this->hostnames), true);
	}

	/** Only the token/secret leave the server; form fields and visitor IP are omitted. */
	protected function requestVerification(string $token): ?array {
		if (!function_exists('curl_init')) { return null; }
		$handle = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
		if ($handle === false) { return null; }
		curl_setopt_array($handle, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query(['secret' => $this->secretKey, 'response' => $token]),
			CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_TIMEOUT => 8,
		]);
		$body = curl_exec($handle);
		$status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
		curl_close($handle);
		return is_string($body) ? ['status' => $status, 'body' => $body] : null;
	}
}
