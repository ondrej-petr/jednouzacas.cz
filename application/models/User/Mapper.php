<?php

class App_Model_User_Mapper extends OPLib_Model_Mapper_Abstract
{

	protected $_tableClass = 'App_Model_User_Table';

	protected $_modelClass = 'App_Model_User';

	//==========================================================================
	/**
	 * Vrati vsechny uzivatele prirazene k dane akci
	 *
	 * @param int $actionId		ID akce
	 * @param boolean $withAnonyms
	 * @return App_Model_User[]
	 */
	public function getAllUsersForAction($actionId, $withAnonyms = true)
	{
		$rows = $this->getTable()->getAllUsersForAction($actionId, $withAnonyms);

		return $this->_getSet($rows);
	}

	//==========================================================================
	/**
	 * Vrati uzivatele nalezene dle zaslaneho stringu. Slouzi pro navrh
	 * uzivatelu nalezenych dle pocatecnich pismen.
	 *
	 * Pokud je zadany $userId, bude se hledat uzivatel, ktery existuje
	 * v nejake akci, ve ktere je i uzivatel s danym ID.
	 *
	 * @param string $query
	 * @param int $userId
	 * @return App_Model_User[]
	 */
	public function suggestUsers($query, $userId = null)
	{
		$rows = $this->getTable()->suggestUsers($query, $userId);

		return $this->_getSet($rows);
	}

	//==========================================================================
	/**
	 * Vrati uzivatele, kteri patri do akce, ve ktere se nachazi dany navrh a
	 * jeste pro dany navrh nehlasovali
	 *
	 * @param int $proposalId
	 * @return App_Model_User[]
	 */
	public function getNonVotingUsersForProposal($proposalId)
	{
		$rows = $this->getTable()->getNonVotingUsersForProposal($proposalId);

		return $this->_getSet($rows);
	}

	//==========================================================================
	/**
	 * Vrati uzivatele, kteri patri do akce, ve ktere se nachazi dana udalost a
	 * jeste pro zadny navrh nehlasovali
	 *
	 * @param int $eventId
	 * @return App_Model_User[]
	 */
	public function getNonVotingUsersForEvent($eventId)
	{
		$rows = $this->getTable()->getNonVotingUsersForEvent($eventId);

		return $this->_getSet($rows);
	}

}
