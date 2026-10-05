<?php
require_once(__CA_APP_DIR__.'/controllers/LoginRegController.php');

/** Theme profile saves; login, forms and password policy remain native. */
class AccountProfileController extends LoginRegController {
	public function profileSave() {
		if ($this->request->getRequestMethod() !== 'POST') {
			$this->response->addHeader('Allow', 'POST');
			$this->response->setHTTPResponseCode(405, 'Method Not Allowed');
			return;
		}
		if (!$this->request->isLoggedIn()) {
			$this->notification->addNotification(_t('User is not logged in'), __NOTIFICATION_TYPE_ERROR__);
			$this->redirect(caNavUrl($this->request, '', 'Front', 'Index'));
			return;
		}
		if ($this->request->config->get(['dontAllowRegistrationAndLogin', 'dont_allow_registration_and_login'])) {
			$this->response->setHTTPResponseCode(403, 'Forbidden');
			return;
		}
		$params = $this->request->getParameters(['POST']);
		$token = $params['csrfToken'] ?? null;
		if (!is_string($token) || !$token || !caValidateCSRFToken($this->request, $token, ['notifications' => $this->notification])) {
			$this->response->setHTTPResponseCode(403, 'Forbidden');
			return;
		}

		// RequestHTTP::close() saves request->user even after validation fails.
		// Keep all proposed changes on a separate model until the save succeeds.
		$user = new ca_users();
		$userID = (int)$this->request->getUserID();
		if (!$userID || !$user->load($userID)) {
			$this->response->setHTTPResponseCode(403, 'Forbidden');
			return;
		}
		$user->purify(true);
		$errors = [];
		$request = $this->request;
		$readText = static function ($name) use ($params, $request, &$errors) {
			$value = $params[$name] ?? '';
			if (!is_string($value)) {
				$errors['general'] = _t('Invalid profile form data.');
				return '';
			}
			return $request->getParameter($name, pString, 'POST');
		};
		$email = trim(html_entity_decode(strip_tags($readText('email')), ENT_QUOTES, 'UTF-8'));
		$fields = ['fname' => trim(strip_tags($readText('fname'))), 'lname' => trim(strip_tags($readText('lname')))];
		$password = html_entity_decode(strip_tags($readText('password')), ENT_QUOTES, 'UTF-8');
		$password2 = html_entity_decode(strip_tags($readText('password2')), ENT_QUOTES, 'UTF-8');
		$emailChanged = ($email !== (string)$user->get('email'));
		$renameLogin = $emailChanged && ((int)$user->get('userclass') === 1);
		if (!caCheckEmailAddress($email)) {
			$errors['email'] = _t('E-mail address is not valid.');
		} elseif ($renameLogin) {
			$otherUser = new ca_users();
			if ($otherUser->load(['user_name' => $email]) && (int)$otherUser->getPrimaryKey() !== $userID) {
				$errors['email'] = _t('A user has already registered with this email address');
			}
		}
		if (!$fields['fname']) { $errors['fname'] = _t('Please enter your first name'); }
		if (!$fields['lname']) { $errors['lname'] = _t('Please enter your last name'); }
		if ($password !== $password2) { $errors['password'] = _t('Passwords do not match'); }
		if ($password !== '') {
			if (strlen($password) < 4) { $errors['password'] = _t('Password must be at least 4 characters long'); }
			$fields['password'] = $password;
		}
		if ($emailChanged) { $fields['email'] = $email; }
		if ($renameLogin) { $fields['user_name'] = $email; }

		$groupCode = trim(strip_tags($readText('group_code')));
		$group = null;
		if ($groupCode !== '') {
			// Match native invitation rules; never accept a private/admin group.
			$code = preg_replace('![^A-Za-z0-9_]+!u', '', $groupCode) ?? '';
			if ($code !== '') {
				$criteria = is_numeric($code) ? ['group_id' => (int)$code] : ['code' => $code];
				$group = ca_user_groups::find($criteria + ['for_public_use' => 1], ['returnAs' => 'firstModelInstance']);
			}
			if (!$group) { $errors['group_code'] = _t('Group code is not valid'); }
		}
		$preferences = [];
		foreach ((array)$user->getValidPreferences('profile') as $name) {
			$value = $readText('pref_'.$name);
			if (!$user->isValidPreferenceValue($name, $value, true)) {
				$errors[$name] = join('; ', $user->getErrors()) ?: _t('Profile value is not valid.');
				$user->clearErrors();
			} else {
				$preferences[$name] = $value;
			}
		}
		if ($errors) { $this->showSaveErrors($errors); return; }

		$user->setMode(ACCESS_WRITE);
		foreach ($fields as $name => $value) {
			if ($user->set($name, $value) === false || $user->numErrors()) {
				$errors[$name === 'user_name' ? 'email' : $name] = join('; ', $user->getErrors()) ?: _t('Profile value could not be set.');
				$user->clearErrors();
			}
		}
		foreach ($preferences as $name => $value) {
			if ($user->setPreference($name, $value) === false || $user->numErrors()) {
				$errors[$name] = join('; ', $user->getErrors()) ?: _t('Profile value could not be set.');
				$user->clearErrors();
			}
		}
		if ($errors) { $this->showSaveErrors($errors); return; }
		// Native update enforces configured password policy and auth adapter rules.
		if (!$user->update() || $user->numErrors()) {
			$this->showSaveErrors(['general' => join('; ', $user->getErrors()) ?: _t('Profile could not be updated.')]);
			return;
		}

		$this->notification->addNotification(_t('Updated profile'), __NOTIFICATION_TYPE_INFO__);
		if ($group && !$user->inGroup($group->getPrimaryKey())) {
			if (!$user->addToGroups($group->getPrimaryKey()) || $user->numErrors()) {
				$this->view->setVar('errors', ['group_code' => _t('Your profile was updated, but the group could not be joined.')]);
			} else {
				$this->notification->addNotification(_t('You were added to group %1', htmlspecialchars($group->get('name'), ENT_QUOTES, 'UTF-8')), __NOTIFICATION_TYPE_INFO__);
			}
		}
		// Do not let the request's stale preferences overwrite the successful save.
		$this->request->user->load($userID);
		$this->profileForm();
	}

	private function showSaveErrors(array $errors) {
		$this->notification->addNotification(_t('Your profile could not be updated. Please correct the errors and try again.'), __NOTIFICATION_TYPE_ERROR__);
		$this->view->setVar('errors', $errors);
		$this->profileForm();
	}
}
