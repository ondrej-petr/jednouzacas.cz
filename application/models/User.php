<?php

class App_Model_User extends OPLib_Model_Db_Abstract
{

	const UPLOAD_PATH = '/profile/';

	protected $_tableClass = 'App_Model_User_Table';

	protected $_userId;
	protected $_firstName;
	protected $_lastName;
	protected $_mail;
	protected $_phone;
	protected $_password;
	protected $_isActive = 0;
	protected $_isAnonym = 0;
	protected $_facebookId;
	protected $_googleId;
	protected $_twitterId;
	protected $_refreshToken;
	protected $_notificationSettings = '{"mailDiscussion":"1","mailEvent":"1","smsDiscussion":"1","smsEvent":"1","smsAlarm":"1"}';
	protected $_dateCreated;
	protected $_dateUpdated;

	//==========================================================================
	public function setPhone($value)
	{
		$this->_phone = str_replace(' ', '', $value);
	}

	//==========================================================================
	public function setPassword($value)
	{
		$this->_password = md5($value);
	}

	//==========================================================================
	public function getName()
	{
		return $this->_firstName . ' ' . $this->_lastName;
	}

	//==========================================================================
	/**
	 *
	 * @return App_Model_User_Notification_Settings
	 */
	public function getNotificationSettings()
	{
		$settings = new App_Model_User_Notification_Settings();

		if (!empty($this->_notificationSettings)) {
			$settings->setPropsFromArray(Zend_Json::decode($this->_notificationSettings));
		}

		return $settings;
	}

	//==========================================================================
	public function setNotificationSettings($value)
	{
		if (is_array($value)) {
			$settings = $this->getNotificationSettings();
			$settings->setPropsFromArray($value);
			$this->_notificationSettings = Zend_Json::encode($settings->toArray());
		}
		elseif (is_string($value)) {
			$this->_notificationSettings = $value;
		}
	}

	//==========================================================================
	public function delete()
	{
		parent::delete();

		$this->deleteProfileImage();
	}

	//==========================================================================
	/**
	 * Nalezeni uzivatele dle jeho mailove adresy
	 * Pokud bude nalezen, naplnim daty tuto instanci a vratim true, jinak false
	 *
	 * @param string $mail
	 * @return boolean
	 */
	public function findByMail($mail)
	{
		$row = $this->getTable()->fetchRow("mail = '$mail'");

		if (isset($row)) {
			$this->_setProperties($row);
			return true;
		} else {
			return false;
		}
	}

	//==========================================================================
	public function isItMe()
	{
		return (Zend_Auth::getInstance()->hasIdentity() &&
				Zend_Auth::getInstance()->getIdentity()->userId == $this->_userId);
	}

	//==========================================================================
	/**
	 * Automaticke zalogovani tohoto uzivatele, napr. po aktivaci uctu.
	 */
	public function logIn()
	{
		$auth = Zend_Auth::getInstance();
		$auth->clearIdentity();
		$auth->getStorage()->write((object) $this->toArray());
	}

	//==========================================================================
	/**
	 * Vrati true, pokud je tento uzivatel adminem pro danou akci.
	 *
	 * @param int $actionId
	 * @return boolean
	 */
	public function isAdminForAction($actionId)
	{
		return $this->getTable()->isAdminForAction($this->_userId, $actionId) ? true : false;
	}

	//==========================================================================
	/**
	 * Vrati true, pokud je tento uzivatel adminem pro danou udalost.
	 * To znamena, ze bud je adminem pro akci, nebo vytvoril danou udalost.
	 *
	 * @param int $eventId
	 * @return boolean
	 */
	public function isAdminForEvent($eventId)
	{
		return $this->getTable()->isAdminForEvent($this->_userId, $eventId) ? true : false;
	}

	//==========================================================================
	/**
	 * Vrati profilovy obrazek
	 *
	 * @return App_Model_Image
	 */
	public function getProfileImage()
	{
		$image = new App_Model_Image('/upload/' . self::UPLOAD_PATH . $this->_userId);
		if (!$image->getFilename()) {
			$image = new App_Model_Image('/img/anonym.png');
		}

		return $image;
	}

	//==========================================================================
	public function saveProfileImage()
	{
		$path = APPLICATION_PATH . '/../public/upload' . self::UPLOAD_PATH;

		$upload = new Zend_File_Transfer_Adapter_Http();
		$upload->setDestination(realpath($path));

		if (!$upload->isUploaded()) return false;

		try {
			if (!$upload->receive()) return false;
		} catch (Zend_File_Transfer_Exception $e) {
			$e->getMessage();
		}

		$filename = $upload->getFileName('img');
		$dstFilePath = $path . $this->getUserId() . '.' . pathinfo($filename, PATHINFO_EXTENSION);

		$filterFileRename = new Zend_Filter_File_Rename(array('target' => $dstFilePath, 'overwrite' => true));
		$filterFileRename->filter($filename); //move uploade file to destination
	}

	//==========================================================================
	public function deleteProfileImage()
	{
		// TODO:
	}

	//==========================================================================
	public function getProfileUrl()
	{
		$t = Zend_Registry::get('Zend_Translate')->getAdapter();
		$request = Zend_Controller_Front::getInstance()->getRequest();
		$hash = rawurlencode(OPLib_EasyCrypt::encrypt($this->getUserId(), 'aes128'));

		return str_replace('//', '/', '/' . $request->getBaseUrl() . $t->translate('uzivatel') . '/' . $hash);
	}

	//==========================================================================
	/**
	 * Vrati vsechny akce, ve kterych je zapojeny tento uzivatel
	 *
	 * @param string $order [eventDate]
	 * @return App_Model_Action[]
	 */
	public function getAllActions($order = null)
	{
		return App_Model_Action_Mapper::getInstance()->getAllActionsForUser($this->_userId, $order);
	}

	//==========================================================================
	/**
	 * Poslani mailu tomuto uzivateli s odkazy na aktivaci a smazani uctu.
	 */
	public function sendRegistrationMail()
	{
		$absoluteUrlHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('AbsoluteUrl');
		$translateHelper = Zend_Registry::get('Zend_Translate');

		$linkActivate = $absoluteUrlHelper->direct("/aktivace-uctu/$this->_mail/$this->_password");
		$linkRemove = $absoluteUrlHelper->direct("/odebrani-uctu/$this->_mail/$this->_password");

		$view = new Zend_View();
		$view->setScriptPath(APPLICATION_PATH . '/views/scripts/email');
		$view->addHelperPath(APPLICATION_PATH . '/../library/OPLib/View/Helper', 'OPLib_View_Helper');
		$view->linkActivate = $linkActivate;
		$view->linkRemove = $linkRemove;
		$mailBody = $view->render('registration.phtml');

		$mail = new Zend_Mail('utf-8');
		$mail->setBodyHtml($mailBody);
		$mail->setFrom('robot@jednouzacas.cz', 'Jednouzačas.cz');
		$mail->addTo($this->_mail);
		$mail->setSubject($translateHelper->translate('Vytvoření účtu'));

		try {
			if ($mail->send()) {
				Zend_Registry::get('dbLog')
					->setEventItem('type', 'cron mail')
					->setEventItem('userId', $this->getUserId())
					->info($this->getMail() . ': Registrace');
			}
		} catch (Zend_Exception $e) {
			throw new Zend_Exception($translateHelper->translate(
				'E-mail pro aktivaci účtu se nepodařilo odeslat. Prosíme, informujte nás o této události.'
			));
		}
	}

	//==========================================================================
	/**
	 * Poslani mailu s odkazem na reset hesla.
	 */
	public function sendMailLostPassword()
	{
		$absoluteUrlHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('AbsoluteUrl');
		$translateHelper = Zend_Registry::get('Zend_Translate');

		$hash = rawurlencode(OPLib_EasyCrypt::encrypt($this->_getUserId() . '|' . time()));
		$link = $absoluteUrlHelper->direct($translateHelper->translate('reset-hesla') . '/' . $hash);

		$view = new Zend_View();
		$view->setScriptPath(APPLICATION_PATH . '/views/scripts/email');
		$view->addHelperPath(APPLICATION_PATH . '/../library/OPLib/View/Helper', 'OPLib_View_Helper');
		$view->link = $link;
		$mailBody = $view->render('lost-password.phtml');

		$mail = new Zend_Mail('utf-8');
		$mail->setBodyHtml($mailBody);
		$mail->setFrom('robot@jednouzacas.cz', 'Jednouzačas.cz');
		$mail->addTo($this->_mail);
		$mail->setSubject($translateHelper->translate('Reset hesla'));

		try {
			if ($mail->send()) {
				Zend_Registry::get('dbLog')
					->setEventItem('type', 'cron mail')
					->setEventItem('userId', $this->_userId)
					->info($this->_mail . ': heslo');
			}
		} catch (Zend_Exception $e) {
			throw new Zend_Exception($translateHelper->translate(
				'E-mail s odkazem na reset hesla se nepodařilo odeslat. Prosíme, informujte nás o této události.'
			));
		}
	}

	//==========================================================================
	public function sendMailEventConfirmation($action, $event, $proposal)
	{
		$absoluteUrlHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('AbsoluteUrl');
		$translateHelper = Zend_Registry::get('Zend_Translate');

		$view = new Zend_View();
		$view->setScriptPath(APPLICATION_PATH . '/views/scripts/email');
		$view->addHelperPath(APPLICATION_PATH . '/../library/OPLib/View/Helper', 'OPLib_View_Helper');
		$view->action = $action;
		$view->event = $event;
		$view->proposal = $proposal;
		$view->user = $this;
		$mailBody = $view->render('event-confirmation.phtml');

		$mail = new Zend_Mail('utf-8');
		$mail->setBodyHtml($mailBody);
		$mail->setFrom('robot@jednouzacas.cz', 'Jednouzačas.cz');
		$mail->addTo($this->_mail);
		$mail->setSubject($translateHelper->translate(
			'Termín akce ' . $action->getTitle() . ' #' . $event->getEventNo()
		));

		try {
			if ($mail->send()) {
				Zend_Registry::get('dbLog')
					->setEventItem('type', 'cron mail')
					->setEventItem('userId', $this->getUserId())
					->info($this->getMail() . ': Potvrzeni terminu udalosti');
			}
		} catch (Zend_Exception $e) {
			throw new Zend_Exception($translateHelper->translate(
				'E-mail s potvrzením termínu události se nepodařilo odeslat. Prosíme, informujte nás o této události.'
			));
		}
	}

	//==========================================================================
	/**
	 * Login validation
	 *
	 * @param string $mail
	 * @param string $password
	 * @return boolean
	 */
	public function authenticate($mail, $password)
	{
		$success = false;
		$messenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');

		if (!$this->findByMail($mail)) {
			// pokud neni ucet podle mailu nalezen, koncim
			$messenger->addMessage('Daná e-mailová adresa nebyla v naší databázi nalezena.', 'danger');
			$success = -1;
		}
		if (!$this->getIsActive()) {
			// pokud neni ucet aktivovan, koncim
			$messenger->addMessage('Tvůj účet není aktivní.', 'danger');
			$success = -2;
		}

		$authAdapter = $this->_getAuthAdapter();
		$authAdapter->setIdentity($mail)
					->setCredential($password);

		$auth = Zend_Auth::getInstance();
		$result = $auth->authenticate($authAdapter);

		if ($result->isValid()) {
			$data = $authAdapter->getResultRowObject(null, 'password');
			$auth->getStorage()->write($data);

			$authSession = new Zend_Session_Namespace('Zend_Auth');
			$authSession->setExpirationSeconds(Zend_Registry::get('config')->session->auth->lifetime);

			$this->update();
			$success = true;
		} else {
			// prihlasovaci udaje nejsou spravne
			$messenger->addMessage('Tvé heslo nebylo zadáno správně.', 'danger');
			$success = -3;
		}

		return $success;
	}

	//==========================================================================
	public function setAuthenticated()
	{
		$data = new stdClass();
		$data->userId = $this->getUserId();
		$data->firstName = $this->getFirstName();
		$data->lastName = $this->getLastName();
		$data->mail = $this->getMail();
		$data->isAnonym = $this->getIsAnonym();
		Zend_Auth::getInstance()->getStorage()->write($data);
	}

	//==========================================================================
	protected function _getAuthAdapter()
	{
		$dbAdapter = Zend_Db_Table::getDefaultAdapter();
		$authAdapter = new Zend_Auth_Adapter_DbTable($dbAdapter);

		$authAdapter->setTableName("user")
					->setIdentityColumn("mail")
					->setCredentialColumn("password")
					->setCredentialTreatment("MD5(?)");

		return $authAdapter;
	}

}
