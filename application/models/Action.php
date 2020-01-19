<?php

class App_Model_Action extends OPLib_Model_Db_Abstract
{

	protected $_tableClass = 'App_Model_Action_Table';

	protected $_actionId;
	protected $_userId;
	protected $_title;
	protected $_description;
	protected $_intervalValue;
	protected $_intervalUnit;
	protected $_state = 'active';
	protected $_isPaused = 0;
	protected $_dateCreated;
	protected $_dateUpdated;

	/**
	 * @var App_Model_Event
	 */
	private $_nextEvent;

	//==========================================================================
	/**
	 * Pridani uzivatele k akci
	 *
	 * @param int $userId		ID uzivatele
	 * @param boolean $isAdmin	Priznak, zda je uzivatel adminem pro tuto akci
	 * @param int $inviterId	ID uzivatele, ktery ho pozval (0 - uzivatel akci
	 * 							vytvoril)
	 */
	public function addUser($userId, $isAdmin, $inviterId)
	{
		$this->getTable()->addUser($this->_actionId, $userId, $isAdmin, $inviterId);
	}

	//==========================================================================
	/**
	 * Odebrani uzivatele z akce
	 *
	 * @param int $userId		ID uzivatele
	 */
	public function removeUser($userId)
	{
		$this->getTable()->removeUser($this->_actionId, $userId);
	}

	//==========================================================================
	/**
	 * Vrati URL teto akce.
	 * Napr. "/akce/1"
	 *
	 * @return string
	 */
	public function getUrl()
	{
		return '/akce/' . $this->getActionId();
	}

	//==========================================================================
	/**
	 * Vytvoreni nove udalosti pro tutu akci
	 *
	 * @param boolean $copyLastEvent	vzit nastaveni z minule udalosti?
	 * @param array $data				nastaveni pro novou udalost
	 * @return App_Model_Event			vytvorena udalost
	 */
	public function createNewEvent($copyLastEvent = true, $data = array())
	{
		$event = new App_Model_Event();

		// pokud $copyLastEvent == true, vezmu data z minule udalosti a nastavim
		// je teto
		if ($copyLastEvent) {
			// TODO: vzit nastaveni posledni udalosti u teto akce
		}

		// pokud byla zaslana nejaka inicializacni data, nastavim je
		if (is_array($data) && count($data)) {
			$event->setPropsFromArray($data);
		}

		$event->setActionId($this->_actionId);
		$event->setEventNo(count($this->getAllEvents()) + 1);

		$event->insert();

		// vytvorim take prvni navrh terminu, pokud byl zaslan
		if (isset($data['dates']) && $data['dates'] != '') {
			$event->createNewProposal($data['dates']);
		}

		return $event;
	}

	//==========================================================================
	/**
	 * Vrati vsechny udalosti pod touto akci
	 *
	 * @todo predelat getAll do mapperu
	 * @return App_Model_Event[]
	 */
	public function getAllEvents()
	{
		$events = App_Model_Event_Mapper::getInstance()->getAll(true, "actionId = $this->_actionId", 'eventNo');

		return $events;
	}

	//==========================================================================
	/**
	 * Vrati udalost teto akce podle cisla udalosti
	 *
	 * @param int $eventNo
	 * @return App_Model_Event|boolean
	 */
	public function getEventByNo($eventNo)
	{
		$event = new App_Model_Event();
		if ($event->findByActionIdAndEventNo($this->_actionId, $eventNo)) {
			return $event;
		} else {
			return false;
		}
	}

	//==========================================================================
	/**
	 * Vrati posledni probehlou udalost pro tuto akci
	 *
	 * @return App_Model_Event|boolean
	 */
	public function getLastEvent()
	{
		return App_Model_Event_Mapper::getInstance()->getLastEventForAction($this->_actionId);
	}

	//==========================================================================
	/**
	 * Vrati prvni neprobehlou udalost pro tuto akci
	 *
	 * @return App_Model_Event|boolean
	 */
	public function getNextEvent()
	{
		if (!$this->_nextEvent || !$this->_nextEvent instanceof App_Model_Event || !$this->_nextEvent->getEventId()) {
			$this->_nextEvent = App_Model_Event_Mapper::getInstance()->getNextEventForAction($this->_actionId);
		}

		return $this->_nextEvent;
	}

	//==========================================================================
	/**
	 * Navrhne termin dalsi udalosti dle zadaneho intervalu a posledni udalosti
	 * teto akce.
	 *
	 * @param bool $formated Maji se data nafomatovat dle location?
	 * @return string
	 */
	public function proposeNextEventDate($formated = true)
	{
		$date = $this->getLastEvent()->getDate(false);

		$date = explode(' - ', $date);
		$startDate = trim(reset($date));
		$endDate = trim(end($date));

		$str = ' +' . $this->getIntervalValue() . ' ' . $this->getIntervalUnit();
		$startDate = new Zend_Date(strtotime($startDate . $str));
		$endDate = new Zend_Date(strtotime($endDate . $str));


		while ($startDate->isEarlier(date('Y-m-d'))) {
			$intervalInDays = new Zend_Date(
				strtotime(date('Y-m-d') . ' +' . $this->_intervalValue . ' ' . $this->_intervalUnit)
			);
			$intervalInDays = $intervalInDays->sub(time());
			$measure = new Zend_Measure_Time($intervalInDays->toValue(), Zend_Measure_Time::SECOND);
			$measure->convertTo(Zend_Measure_Time::DAY);
			$intervalInDays = ceil($measure->getValue(0) / 2);

			$diff = $endDate->sub($startDate)->toValue();
			$startDate->addDay($intervalInDays);
			$endDate = clone $startDate;
			$endDate->addDay($diff);
		}

		$format = $formated ? Zend_Date::DATES : 'YYYY-MM-dd';
		$date = $startDate->equals($endDate)
			? $startDate->toString($format)
			: ($startDate->toString($format) . ' - ' . $endDate->toString($format));

		return $date;
	}

	//==========================================================================
	/**
	 * Vrati datum, kdy by se melo poslat upozorneni na nasledujici udalost
	 *
	 * @param int $number Kolikate upozorneni to je
	 * @return string
	 */
	public function getNotificationDate($number)
	{
		if ($nextEvent = $this->getNextEvent()) {
			$nextEventDate = @array_shift(explode(' - ', $nextEvent->getDate(false)));
		} else {
			$nextEventDate = @array_shift(explode(' - ', $this->proposeNextEventDate(false)));
		}

		$interval = (strtotime($this->getIntervalValue() . ' ' . $this->getIntervalUnit()) - time()) / 60 / 60 / 24;
		$minus = round(pow(5 * $interval, 1/2) / (2 * $number));

		return date('Y-m-d', strtotime($nextEventDate . ' -' . $minus . ' days'));
	}

	//==========================================================================
	/**
	 * Vrati vsechny uzivatele, kteri patri do teto akce
	 *
	 * @param boolean $withAnonyms
	 * @return App_Model_User[]
	 */
	public function getAllUsers($withAnonyms = true)
	{
		$users = App_Model_User_Mapper::getInstance()->getAllUsersForAction($this->_actionId, $withAnonyms);

		return $users;
	}

	//==========================================================================
	/**
	 * Zkontroluje, zda ma prihlaseny uzivatel pristup k teto akci
	 *
	 * @return boolean
	 */
	public function checkCurrentUser()
	{
		$user = Zend_Auth::getInstance()->getIdentity();

		if ($this->getUserId() == $user->userId) {
			return true;
		}

		$row = $this->getTable()->checkUser($this->_actionId, $user->userId);

		return $row !== false;
	}

	//==========================================================================
	/**
	 * Poslani pozvanky na akci na danou mailovou adresu.
	 *
	 * @param $mailAddress		Mailova adresa pozvaneho uzivatele
	 */
	public function sendInvitationMail($mailAddress)
	{
		$absoluteUrlHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('AbsoluteUrl');
		$translateHelper = Zend_Registry::get('Zend_Translate');

		$authUser = Zend_Registry::get('user');

		// hash: ID akce-ID prihlaseneho uzivatele
		$hash = OPLib_EasyCrypt::encrypt(
			$this->_actionId . '|' . $authUser->getUserId() . '|' . $mailAddress
		);

		$link = $absoluteUrlHelper->direct('/' . $translateHelper->translate('pozvanka') . '/' . $hash);

		$view = new Zend_View();
		$view->setScriptPath(APPLICATION_PATH . '/views/scripts/email');
		$view->addHelperPath(APPLICATION_PATH . '/../library/OPLib/View/Helper', 'OPLib_View_Helper');
		$view->action = $this;
		$view->link = $link;
		$view->user = $authUser;
		$mailBody = $view->render('invitation.phtml');

		$mail = new Zend_Mail('utf-8');
		$mail->setBodyHtml($mailBody);
		$mail->setFrom('robot@jednouzacas.cz', 'Jednouzačas.cz');
		$mail->addTo($mailAddress);
		$mail->setSubject($translateHelper->translate('Pozvánka na akci'));

		try {
			if ($mail->send()) {
				Zend_Registry::get('dbLog')
					->setEventItem('type', 'cron mail')
					->info($mailAddress . ': Pozvánka');
			}
		} catch (Zend_Exception $e) {
			throw new Zend_Exception($translateHelper->translate(
				'E-mail pozvánky na akci se nepodařilo odeslat. Prosíme, informujte nás o této události.'
			));
		}
	}

}
