<?php

class App_Model_Event extends OPLib_Model_Db_Abstract
{

	protected $_tableClass = 'App_Model_Event_Table';

	protected $_eventId;
	protected $_actionId;
	protected $_userId;
	protected $_eventNo;
	protected $_description;
	protected $_place;
	protected $_latitude;
	protected $_longtitude;
	protected $_isPublic = 0;
	protected $_isVisible = 0;
	protected $_isClosed = 0;
	protected $_state = 'active';
	protected $_dateCreated;
	protected $_dateUpdated;

	/**
	 * @var App_Model_Action
	 */
	private $_action;

	//==========================================================================
	public function findByActionIdAndEventNo($actionId, $eventNo)
	{
		$row = $this->getTable()->getByActionIdAndEventNo($actionId, $eventNo);

		if (is_array($row)) {
			$this->_setProperties($row);
			return true;
		} else {
			return false;
		}
	}

	//==========================================================================
	/**
	 * Vrati akci, pod kterou tato udalost spada
	 *
	 * @throws Zend_Exception
	 * @return App_Model_Action
	 */
	public function getAction()
	{
		if (!$this->_action instanceof App_Model_Action) {
			$action = new App_Model_Action();
			if (!$action->findById($this->_actionId)) {
				throw new Zend_Exception('K události se nepodařilo dohledat akci!');
			}
			$this->_action = $action;
		}

		return $this->_action;
	}

	//==========================================================================
	/**
	 * Vrati vsechny navrhy pro tuto udalost
	 *
	 * @param $order string 			Moznosti: 'byVotes',
	 * @return App_Model_Proposal[]
	 */
	public function getProposals($order = null)
	{
		return App_Model_Proposal_Mapper::getInstance()->getProposalsForEvent($this->_eventId, $order);
	}

	//==========================================================================
	/**
	 * Vrati vedouci navrh
	 *
	 * @return App_Model_Proposal
	 */
	public function getLeadingProposal()
	{
		$proposals = $this->getProposals('byVotes');

		foreach ($proposals as $proposal) {
			$endDate = $proposal->getEndDateTime()
				? $proposal->getEndDateTime()
				: ($proposal->getStartDateTime() . ' 23:59:59');
			if ($endDate >= date('Y-m-d H:i:s')) {
				return $proposal;
			}
		}

		// fallback
		return array_shift($proposals);
	}

	//==========================================================================
	/**
	 * Vytvori novy navrh pro tuto udalost
	 *
	 * @return App_Model_Proposal
	 */
	public function createNewProposal($dates)
	{
		if (!$user = Zend_Auth::getInstance()->getIdentity()) {
			throw new Zend_Exception('Nepřihlášený uživatel - přístup zamítnut!');
		}

		$proposal = new App_Model_Proposal();
		$proposal->setEventId($this->_eventId);
		$proposal->setUserId($user->userId);
		$proposal->setDates($dates);
		$proposal->insert();

		// pokud je navrh terminu vytvaren prihlasenym uzivatelem, rovnou pro
		// tento navrh kladne zahlasuje
		if (Zend_Auth::getInstance()->hasIdentity()) {
			$proposal->addVote(Zend_Auth::getInstance()->getIdentity()->userId, 2);
		}

		return $proposal;
	}

	//==========================================================================
	/**
	 * Vrati zvoleny navrh terminu pro tuto udalost.
	 * Pokud nebyl zadny navrh vybran, vrati false.
	 *
	 * @return App_Model_Proposal|boolean
	 */
	public function getChosenProposal()
	{
		return App_Model_Proposal_Mapper::getInstance()->getChosenProposalForEvent($this->_eventId);
	}

	//==========================================================================
	/**
	 * Vrati vsechny hlasy pro vytvorene navrhy terminu
	 *
	 * @return App_Model_Vote[]
	 */
	public function getAllVotes()
	{
		$data = array();
		$proposals = App_Model_Proposal_Mapper::getInstance()->getProposalsForEvent($this->_eventId);

		foreach ($proposals as $proposal) {
			$data[$proposal->getProposalId()] = $proposal->getVotes();
		}

		return $data;
	}

	//==========================================================================
	/**
	 * Podle stavu udalosti vratim bud datum nejvice vyhovujiciho navrhu, nebo
	 * datum, ve kterem udalost probehla.
	 *
	 * @param bool $formated Maji se data nafomatovat dle location?
	 * @return string
	 */
	public function getDate($formated = true)
	{
		$date = '';
		$proposal = $this->getChosenProposal();

		// pokud jeste neni zvolen termin, vrati nejvice vyhovujici navrh
		if (!$proposal instanceof App_Model_Proposal) {
			$proposal = $this->getLeadingProposal();
		}

		if ($proposal) {
			if ($formated) {
				$part = 'y-MM-dd HH:mm:ss';
				$startDate = new Zend_Date($proposal->getStartDateTime(), $part);
				$endDate = $proposal->getEndDateTime() ? new Zend_Date($proposal->getEndDateTime(), $part) : null;

				$date = (!$endDate || $startDate->equals($endDate))
					? $startDate->toString(Zend_Date::DATES)
					: $startDate->toString(Zend_Date::DATES) . ' - ' . $endDate->toString(Zend_Date::DATES);
			} else {
				$date = $proposal->getStartDateTime()
					. ($proposal->getEndDateTime() ? ' - ' . $proposal->getEndDateTime() : '');
			}
		}

		return $date;
	}

	//==========================================================================
	/**
	 * Vrati URL teto udalosti.
	 * Napr. "/akce/1/2"
	 *
	 * @return string
	 */
	public function getUrl()
	{
		return '/akce/' . $this->getActionId() . '/' . $this->getEventNo();
	}

	//==========================================================================
	/**
	 * Vrati true nebo false, zda prihlaseny uzivatel muze vybirat navrh terminu
	 *
	 * @return boolean
	 */
	public function canIChooseDate()
	{
		if ($this->getUserId() == Zend_Registry::get('user')->getUserId()
			|| $this->getAction()->getUserId() == Zend_Registry::get('user')->getUserId()
		) {
			return true;
		} else {
			return false;
		}
	}

	//==========================================================================
	/**
	 * Vrati diskusi pro tuto udalost
	 *
	 * @return App_Model_Discussion[]
	 */
	public function getDiscussion()
	{
		return App_Model_Discussion_Mapper::getInstance()->getAll(
			true,
			'eventId = ' . $this->_eventId,
			'date DESC'
		);
	}

	//==========================================================================
	public function getGallery()
	{
		$path = 'upload/gallery/' . $this->getActionId() . '/' . $this->getEventId();

		$images = array();
		if (file_exists($path)) {
			foreach (array_diff(scandir($path), array('..', '.')) as $file) {
				$images[] = new App_Model_Image("/$path/$file");
			}
		}

		return $images;
	}

	//==========================================================================
	public function isPast()
	{
		return ($proposal = $this->getChosenProposal()) && $proposal->isPast();
	}

}
