<?php
require_once(__CA_LIB_DIR__.'/pawtucket/BasePawtucketController.php');

class MediaPreferenceController extends BasePawtucketController {
	public function Set() {
		if ($this->request->getRequestMethod() !== 'POST') {
			$this->response->addHeader('Allow', 'POST');
			$this->response->setHTTPResponseCode(405, 'Method Not Allowed');
			return;
		}

		$params = $this->request->getParameters(array('POST'));
		$mode = $params['tadlMediaPreference'] ?? null;
		if (!in_array($mode, array('only', 'all'), true)) {
			$this->response->setHTTPResponseCode(400, 'Bad Request');
			return;
		}
		$token = $params['csrfToken'] ?? null;
		if (!is_string($token) || !$token || !caValidateCSRFToken($this->request, $token)) {
			$this->response->setHTTPResponseCode(403, 'Forbidden');
			return;
		}

		if (tadlSetMediaPreference($this->request, $mode) === false) {
			$this->response->setHTTPResponseCode(500, 'Internal Server Error');
			return;
		}
		$this->response->setRedirect($this->localReturnUrl($params['returnTo'] ?? null), 303);
	}

	/** Only redirect within this installation; form targets are still user input. */
	private function localReturnUrl($value) {
		$fallback = caNavUrl($this->request, '', 'Front', 'Index');
		if (!is_string($value) || !$value || $value[0] !== '/' || substr($value, 0, 2) === '//'
			|| preg_match('/[\\\\\x00-\x1f\x7f]/', $value)) {
			return $fallback;
		}
		$parts = parse_url($value);
		if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host']) || empty($parts['path'])) {
			return $fallback;
		}

		$path = $parts['path'];
		$decodedPath = rawurldecode($path);
		// Reject ambiguous encodings before browsers or proxies normalize the path.
		if (substr($decodedPath, 0, 2) === '//' || preg_match('/[\\\\\x00-\x1f\x7f]/', $decodedPath)
			|| preg_match('!/(?:\.|\.\.)(?:/|$)!', $decodedPath)
			|| preg_match('/%(?:25)*(?:2e|2f|5c|0[0-9a-f]|1[0-9a-f]|7f)/i', $decodedPath)) {
			return $fallback;
		}
		$base = rtrim($this->request->getBaseUrlPath(), '/');
		if ($base && ($path !== $base) && (strpos($path, $base.'/') !== 0)) {
			return $fallback;
		}
		return $value;
	}
}
