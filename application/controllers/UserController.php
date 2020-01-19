<?php

class UserController extends OPLib_Controller_Action
{

	//==========================================================================
	public function testAction()
	{

	}

	//==========================================================================
	public function indexAction()
	{
		$user = Zend_Registry::get('user');

		if (!$user instanceof App_Model_User || !$user->getUserId()) {
			throw new Zend_Controller_Action_Exception('Do dané sekce nemáte přístup', 403);
		}

		$form = new App_Form_User();
		$form->getElement('mail')->setAttrib('readonly', 'readonly');
		$form->removeElement('password');
		$form->removeElement('password2');
		$form->populate($user->toArray());

		$notificationForm = new App_Form_User_Notification();
		$notificationForm->populate($user->getNotificationSettings()->toArray());

		if ($this->_request->isPost()) {
			$postData = $this->_request->getPost();

			if (isset($postData['mailDiscussion'])) {
				$user->setNotificationSettings($postData);
				$user->update();

				$this->redirect($this->_request->getRequestUri());
			}
			else {
				if ($form->isValid($postData)) {
					$user->setPropsFromArray($postData);
					$user->update();

					$user->saveProfileImage();

					$this->_addFlashMessage('Změny byly úspěšně uloženy.', 'success');
					$this->redirect($this->_request->getRequestUri());
				} else {
					$this->_addFlashMessage('Vyplň, prosím, správně všechny povinnné položky.', 'danger');
					$form->populate($postData);
				}
			}
		}

		$this->_writeDownFlashMessages(true);

		$this->view->form = $form;
		$this->view->notificationForm = $notificationForm;
		$this->view->user = $user;

		$this->view->myActions = App_Model_Action_Mapper::getInstance()->getAllActionsForUser($user->getUserId());
	}

	//==========================================================================
	public function loginAction()
	{
		$form = new App_Form_User_Login();
		$auth = OPLib\Auth::getInstance();

		$redirectTo = $this->_request->getRequestUri();
		if ($redirectTo == '/' . $this->_translate('prihlaseni')) {
			$redirectTo = '/' . $this->_translate('moje');
		}

		// zalogovani emailem a heslem
		if ($this->_request->isPost()) {
			$postData = $this->_request->getPost();

			if ($form->isValid($postData)) {
				// login
				$user = new App_Model_User();
				$success = $user->authenticate($postData['mail'], $postData['password']);
				if ($success === true) {
					// pokud se podarilo zalogovat
					$this->redirect($redirectTo);
				} else {
					// pokud login nebyl uspesny
					$form->getElement('mail')->setValue($postData['mail'])->setAttrib('success', '');
					$form->getElement('password')->setAttrib('success', '');

					if ($success == -3) {
						$this->view->lostPassword = true;
					}
				}
			} else {
				//pokud nebyl prihlasovaci formular spravne vyplnen
				$this->_addFlashMessage('E-mail nebo heslo nebylo zadáno správně.', 'danger');
			}
		}

		// prihlasovani pres facebook, twitter, google, ...
		if ($this->hasParam('provider')) {
			$provider = $this->_getParam('provider');

			switch ($provider) {
				case "facebook":
					if ($this->hasParam('code')) {
						$adapter = new OPLib\Auth\Adapter\Facebook($this->_getParam('code'));
						$result = $auth->authenticate($adapter);
					}
					if ($this->hasParam('error')) {
						throw new Zend_Controller_Action_Exception('Facebook login failed, response is: ' .
								$this->_getParam('error'));
					}
					break;
				case "twitter":
					if ($this->hasParam('oauth_token')) {
						$adapter = new OPLib\Auth\Adapter\Twitter($_GET);
						$result = $auth->authenticate($adapter);
					}
					break;
				case "google":
					if ($this->hasParam('code')) {
						$adapter = new OPLib\Auth\Adapter\Google($this->_getParam('code'));
						$result = $auth->authenticate($adapter);
					}
					if ($this->hasParam('error')) {
						throw new Zend_Controller_Action_Exception('Google login failed, response is: ' .
								$this->_getParam('error'));
					}
					break;
			}
			// What to do when invalid
			if (isset($result) && !$result->isValid()) {
				$auth->clearIdentity($this->_getParam('provider'));
				throw new Zend_Controller_Action_Exception('Login failed');
			} else {
				$this->redirect('/' . $this->_translate('propojeni-uctu'));
			}
		}

		$session = new Zend_Session_Namespace('invitation');
//		$session->setExpirationHops(5);
		$session->redirectUrl = $redirectTo;

		$this->_writeDownFlashMessages(true);

		$this->view->form = $form;

		$this->view->googleAuthUrl = OPLib\Auth\Adapter\Google::getAuthorizationUrl();
//		$this->view->googleAuthUrlOffline = OPLib\Auth\Adapter\Google::getAuthorizationUrl(true);
		$this->view->facebookAuthUrl = OPLib\Auth\Adapter\Facebook::getAuthorizationUrl();
//		$this->view->twitterAuthUrl = OPLib\Auth\Adapter\Twitter::getAuthorizationUrl();
	}

	//==========================================================================
	public function logoutAction()
	{
		Zend_Session::forgetMe();
		Zend_Auth::getInstance()->clearIdentity();
		OPLib\Auth::getInstance()->clearIdentity();

		$this->_redirect('/');
	}

	//==========================================================================
	public function createAction()
	{
		Zend_Auth::getInstance()->clearIdentity();

		$form = new App_Form_User();

		if ($this->_request->isPost()) {
			$postData = $this->_request->getPost();

			if ($form->isValid($postData)) {
				unset($postData['password2']);
				$postData['isAnonym'] = 0;

				$user = new App_Model_User();

				if ($user->findByMail($postData['mail']) && !$user->getIsAnonym()) {
					$form->getElement('mail')->addError('Daná e-mailová adresa je již použita.');
				} else {
					$isAnonym = $user->getIsAnonym();
					$user->setPropsFromArray($postData);

					$isAnonym ? $user->update() : $user->insert();

					$user->sendRegistrationMail();

					// ulozim obrazek
					$user->saveProfileImage();

					$this->_addFlashMessage(
						'Tvůj účet byl vytvořen. Aktivuj si ho, prosím, přes odkaz, který ti byl odeslán na e-mail.',
						'success'
					);

					// pokud byl uzivatel pozvan, priradim ho k dane akci
					if (isset($postData['invitationHash'])) {
						list($actionId, $inviterId, $mail) = explode(
							'|',
							OPLib_EasyCrypt::decrypt($this->getParam('hash'))
						);

						$action = new App_Model_Action();
						if ($action->findById($actionId)) {
							$action->addUser($user->getUserId(), false, $inviterId);
						}

						$this->redirect($this->_translate('akce') . '/' . $action->getActionId());
					} else {
						$this->redirect('/' . $this->_translate('prihlaseni'));
					}
				}

				// pokud nebyl prihlasovaci formular spravne vyplnen (nebyl jsem
				// presmerovan)
				$this->_addFlashMessage('Vyplň, prosím, správně všechny povinnné položky.', 'danger');
				$form->populate($postData);
			}
		} elseif ($this->getParam('hash')) {
			$hash = $this->getRequest()->getServer('QUERY_STRING');
			$hash = substr($hash, strpos($hash, '=') + 1);
			$form->getElement('invitationHash')->setValue($hash);
			list($actionId, $inviterId, $mail) = explode('|', OPLib_EasyCrypt::decrypt($hash));
			if (!$actionId || !$inviterId || !$mail) {
				throw new Zend_Controller_Action_Exception('Tento odkaz je neplatný.', 500);
			}
			$form->getElement('mail')->setValue($mail);

			$this->view->hash = $hash;
		}

		$this->_writeDownFlashMessages(true);

		$this->view->form = $form;

		$this->view->googleAuthUrl = OPLib\Auth\Adapter\Google::getAuthorizationUrl();
		$this->view->facebookAuthUrl = OPLib\Auth\Adapter\Facebook::getAuthorizationUrl();

	}

	//==========================================================================
	public function activationAction()
	{
		$user = new App_Model_User();

		if ($user->findByMail($this->_getParam('mail')) &&
			$user->getPassword() == $this->_getParam('hash'))
		{
			$user->setIsActive(1);
			$user->update();

			$user->logIn();

			$this->_addFlashMessage('Gratulujeme, tvoje registrace je úspěšně dokončena.', 'success');

			$this->redirect('/moje');
		} else {
			$this->_addFlashMessage('Je nám líto, účet se nepodařilo ověřit.', 'danger');
		}

		$this->view->headTitle($this->_translate('Jednou za čas'));
		$this->view->headTitle()->setSeparator(' · ');
		$this->view->headTitle()->append($this->_translate('Aktivace účtu'));

		$this->_writeDownFlashMessages(true);
	}

	//==========================================================================
	public function removalAction()
	{
		$user = new App_Model_User();

		if ($user->findByMail($this->_getParam('mail')) &&
			$user->getPassword() == $this->_getParam('hash'))
		{
			$user->delete();

			$this->_addFlashMessage('Váš účet byl odstraněn ze systému.', 'danger');
		} else {
			$this->_addFlashMessage('Je nám líto, účet se nepodařilo ověřit.', 'danger');
		}

		$this->view->headTitle($this->_translate('Jednou za čas'));
		$this->view->headTitle()->setSeparator(' · ');
		$this->view->headTitle()->append($this->_translate('Odebrání účtu'));

		$this->_writeDownFlashMessages(true);

		$this->_helper->viewRenderer('activation');
	}

	//==========================================================================
	/**
	 * Detail neprihlaseneho uzivatele
	 *
	 * @throws Zend_Controller_Action_Exception
	 */
	public function detailAction()
	{
		$user = new App_Model_User();

		if (!$this->getParam('userId')
			|| !$user->findById(OPLib_EasyCrypt::decrypt($this->getParam('userId'), 'aes128'))
		) {
			throw new Zend_Controller_Action_Exception('Uživatel s daným ID nebyl nalezen', 404);
		}

		$this->view->user = $user;
		$this->view->usersActions = $user->getAllActions();
	}

	//==========================================================================
	public function notAuthorizedAction()
	{
		$request = $this->_request;
		$referer = $this->getRequest()->getHeader('referer');
		$actual = $request->getScheme() . '://' . $request->getHttpHost() . $request->getRequestUri();

		if ($referer != $actual) {
			$this->_addFlashMessage('Pro přístup do dané sekce se musíte přihlásit.', 'warning');
		}

		$this->_forward('login');
	}

	//==========================================================================
	public function notAllowedAction()
	{
		throw new Zend_Controller_Action_Exception('Do dané sekce nemáte přístup', 403);
	}

	//==========================================================================
	public function noRegAction()
	{
		if (!$this->getParam('hash')) {
			throw new Zend_Controller_Action_Exception('Není zadaný argument "hash"');
		}

		list($actionId, $inviterId, $mail) = explode('|', OPLib_EasyCrypt::decrypt($this->getParam('hash')));

		$user = new App_Model_User();
		if ($user->findByMail($mail)) {
			if (!$user->getIsAnonym()) {
				$this->_addFlashMessage(
					'Uživatel s tímto e-mailem už existuje. Jestli jsi to ty, tak se přihlaš ;)',
					'info'
				);
				$this->redirect($this->_translate('prihlaseni'));
			}
		} else {
			$user->setPropsFromArray(array(
				'firstName' => 'anonym',
				'lastName' => ' ',
				'mail' => $mail,
				'isAnonym' => 1
			));
			$user->insert();
		}

		$user->logIn();

		$action = new App_Model_Action();
		if (!$action->findById($actionId)) {
			throw new Zend_Controller_Action_Exception('Akce s ID ' . $actionId . ' nebyla nalezena.');
		}

		$action->addUser($user->getUserId(), false, $inviterId);

		$this->redirect($this->_translate('akce') . '/' . $actionId);
	}

	//==========================================================================
	public function connectAction()
	{
		$auth = OPLib\Auth::getInstance();
		if (!$auth->hasIdentity()) {
			throw new Zend_Controller_Action_Exception('Not logged in!', 404);
		}

		$providers = $auth->getIdentity();
		$user = new App_Model_User();

		$session = new Zend_Session_Namespace('invitation');

		// vytvoreni uctu ze socialni site, popr. propojeni existujiciho uctu
		if ($this->getParam('provider')) {
			$api = $providers->get($this->getParam('provider'))->getApi();
			$profile = $api->getProfile();

			// uzivatel existoval, propojim ho
			if ($user->findByMail($profile['email'])) {
				$user->setPropsFromArray(array(
					$this->getParam('provider') . 'Id' => @$profile['id'] ?: $profile['id_str'],
					'refreshToken' => method_exists($api, 'getAccessToken')
						? @$api->getAccessToken()['refresh_token']
						: $user->getRefreshToken()
				));

				if ($user->getIsAnonym()) {
					$user->setPropsFromArray(array(
						'firstName' => @$profile['first_name'] ?: @$profile['given_name'] ?: @explode(' ', $profile['name'])[0],
						'lastName' => @$profile['last_name'] ?: @$profile['family_name'] ?: @explode(' ', $profile['name'])[1],
						'isActive' => true,
						'isAnonym' => false
					));
				}

				$user->update();

				$flashMsg = $this->_translate('Tvůj účet byl propojen.');
			}
			// uzivatel neexistoval, vytvorim ho
			else {
				$user->setPropsFromArray(array(
					'firstName' => @$profile['first_name'] ?: @$profile['given_name'] ?: @explode(' ', $profile['name'])[0],
					'lastName' => @$profile['last_name'] ?: @$profile['family_name'] ?: @explode(' ', $profile['name'])[1],
					'mail' => @$profile['email'] ?: @$profile['email'] ?: $profile[''],
					'isActive' => true,
					$this->getParam('provider') . 'Id' => @$profile['id'] ?: $profile['id_str'],
					'refreshToken' => method_exists($api, 'getAccessToken') ? $api->getAccessToken()['refresh_token'] : ''
				));

				$id = $user->insert();

				// kdyz si uzivatel klasicky vytvoril novy ucet (bez pozvanky),
				// poslu ho na /moje
				if (!$session->hash) {
					$flashMsg = $this->_translate(
						'Byl ti vytvořen nový účet. Teď už se stačí přidat k nějaké akci nebo ji vytvořit ;)'
					);
				}
			}

			$img = $api->getPicture();
			file_put_contents(
				APPLICATION_PATH . '/../public/upload/profile/' . $user->getUserId() . '.'
					. pathinfo(parse_url($img, PHP_URL_PATH), PATHINFO_EXTENSION),
				file_get_contents($img)
			);

			// kdyz uzivatel dostal pozvanku a vytvoril si nebo propojil ucet pres
			// socialni sit, tak pak ho po vytvoreni uctu poslu na URL z pozvanky
			if ($session->hash) {
				list($actionId, $inviterId, $mail) = explode('|', OPLib_EasyCrypt::decrypt($session->hash));
				$action = new App_Model_Action();
				if ($action->findById($actionId)) {
					$action->addUser($user->getUserId(), false, $inviterId);
				}

				$session->redirectUrl = $this->_translate('akce') . '/' . $action->getActionId();
				$flashMsg = $this->_translate(
					'Byl jsi přidán k nové akci. Teď už se stačí u této akce přidat k nějaké události ;)'
				);
			}

			$user->setAuthenticated();

			$this->_addFlashMessage($flashMsg, 'success');
			$this->redirect($session->redirectUrl ? $session->redirectUrl : ('/' . $this->_translate('moje')));
		}
		// prihlaseni pres socialni sit
		else {
			foreach ($providers as $provider) {
				// kdyz uzivatel prijde nezalogovany na nejakou stranku, prihlasi se
				// pres socialni sit, poslu ho po zalogovani na puvodni stranku
				if ($session->redirectUrl && $session->redirectUrl != '/' . $this->_translate('moje')) {
					$this->_addFlashMessage(
						$this->_translate(
							'Byl jsi přihlášen. Teď už se stačí u této akce přidat k nějaké události ;)'
						),
						'success'
					);
				}

				$profile = $provider->getApi()->getProfile();

				if (!isset($profile['email']) || empty($profile['email'])) {
					$this->_addFlashMessage(
						$this->_translate('Bohužel jsme neobdrželi informaci o tvé mailové adrese. '
							. 'Nejspíš jsi nám ji nedovolil získat a bez ní ti opravdu nemůžeme vytvořit účet.'),
						'danger'
					);
					$this->redirect($this->view->baseUrl($this->_translate('prihlaseni')));
				}

				switch ($provider->getName()) {
					case 'facebook':
						if ($user->findByMail($profile['email']) && $user->getFacebookId() == $profile['id']) {
							$user->setAuthenticated();
							$user->update();
							$this->redirect($session->redirectUrl ? $session->redirectUrl : '/' . $this->_translate('moje'));
						}
						break;
					case 'twitter':
						break;
					case 'google':
						if ($user->findByMail($profile['email']) && $user->getGoogleId() == $profile['id']) {
							$user->setAuthenticated();
							$user->update();
							$this->redirect($session->redirectUrl ? $session->redirectUrl : '/' . $this->_translate('moje'));
						}
						break;
				}
			}
		}

		// zobrazeni dotazu, zda chce ucet vytvorit, popr. provazat
		foreach ($provider as $provider) {
			$profile = $provider->getApi()->getProfile();
		}

		if ($user->findByMail($profile['email']) && !$user->getIsAnonym()) {
			$this->view->user = $user;
		}

		$this->view->providers = $providers;
	}

	//==========================================================================
	public function lostPasswordAction()
	{
		$form = new App_Form_User_Login();
		$form->removeElement('password');

		if ($this->_request->isPost()) {
			$postData = $this->_request->getPost();

			if ($form->isValid($postData)) {
				$user = new App_Model_User();
				if ($user->findByMail($postData['mail'])) {
					$user->sendMailLostPassword();

					$this->_addFlashMessage(
						$this->_translate('Na zadaný e-mail ti byl zaslán odkaz pro reset hesla.'),
						'success'
					);
					$this->redirect($this->_translate('prihlaseni'));
				} else {
					$this->_addFlashMessage(
						$this->_translate('Zadanou e-mailovou adresu se nám nepodařilo najít.'),
						'danger'
					);
				}
			}
		}

		$this->view->form = $form;
	}

	//==========================================================================
	public function resetPasswordAction()
	{
		// desifruju hash
		list($userId, $time) = explode('|', OPLib_EasyCrypt::decrypt($this->getParam('hash')));
		if (!$userId || !$time || $time + 3600 < time()) {
			throw new Zend_Controller_Action_Exception('Tento odkaz pro reset hesla není platný');
		}

		$user = new App_Model_User();
		if (!$user->findById($userId)) {
			throw new Zend_Controller_Action_Exception('Uživatel nebyl nalezen');
		}

		$form = new App_Form_User();
		$form->getElement('firstName')->setAttrib('disabled', 'disabled');
		$form->getElement('lastName')->setAttrib('disabled', 'disabled');
		$form->getElement('mail')->setAttrib('disabled', 'disabled');
		$form->removeElement('img');

		if ($this->_request->isPost()) {
			$postData = $this->_request->getPost();

			$form->getElement('firstName')->setRequired(false);
			$form->getElement('lastName')->setRequired(false);
			$form->getElement('mail')->setRequired(false);

			if ($form->isValid($postData)) {
				$user->setPassword($postData['password']);
				$user->update();

				$this->_addFlashMessage(
					$this->_translate('Tvé heslo bylo změněno. Nyní se můžeš přihlásit.'),
					'success'
				);
				$this->redirect($this->_translate('prihlaseni'));
			}
		}

		$form->populate($user->toArray());

		$this->view->form = $form;
	}

	//==========================================================================
	public function calendarAction()
	{
		$this->view->headScript()->appendFile($this->view->baseUrl('js/daterangepicker/moment.js'));
		$this->view->headScript()->appendFile($this->view->baseUrl('js/fullcalendar/fullcalendar.min.js'));
		$this->view->headScript()->appendFile($this->view->baseUrl('js/fullcalendar/locale/cs.js'));
		$this->view->headLink()->appendStylesheet($this->view->baseUrl('js/fullcalendar/fullcalendar.min.css'));
	}

}
