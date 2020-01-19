<?php

class App_Model_User_Table extends OPLib_Db_Table
{

	/**
	 * @var string Table name
	 */
	protected $_name = 'user';

	/**
	 * @var string Primary key
	 */
	protected $_primary = 'userId';

	//==========================================================================
	/**
	 * Vrati vsechny uzivatele, kteri patri do dane akce
	 *
	 * @param boolean $withAnonyms
	 * @return App_Model_User[]
	 */
	public function getAllUsersForAction($actionId, $withAnonyms = true)
	{
		$select = $this->getAdapter()->select();
		$select->from('actionHasUser', array())
			->join('user', 'user.userId = actionHasUser.userId')
			->where('actionId = ?', $actionId);

		if ($withAnonyms == false) {
			$select->where('isAnonym = ?', 0);
		}

		return $this->getAdapter()->fetchAll($select);
	}

	//==========================================================================
	/**
	 * Vrati informaci, zda je dany uzivatel administratorem pro danou akci.
	 *
	 * @param int $userId
	 * @param int $actionId
	 * @return int
	 */
	public function isAdminForAction($userId, $actionId)
	{
		$sql = $this->getAdapter()->select();
		$sql->from('actionHasUser', 'isAdmin')
		->where('userId = ?', $userId)
		->where('actionId = ?', $actionId)
		->limit(1);

		return $this->getAdapter()->fetchOne($sql);
	}

	//==========================================================================
	/**
	 * Vrati informaci, zda je dany uzivatel administratorem pro danou udalost.
	 *
	 * @param int $userId
	 * @param int $actionId
	 * @return int
	 */
	public function isAdminForEvent($userId, $eventId)
	{
		$sql = $this->getAdapter()->select();
		$sql->from('actionHasUser', 'userId')
			->join('event', "actionHasUser.actionId = event.actionId AND eventId = $eventId", null)
			->where('actionHasUser.userId = ? AND isAdmin = 1', $userId)
			->orWhere('event.userId = ?', $userId)
			->limit(1);

		return $this->getAdapter()->fetchOne($sql);
	}

	//==========================================================================
	public function suggestUsers($query, $userId = null)
	{
		$sql = $this->getAdapter()->select();
		$sql->from(array('A' => 'user'))
			->join(array('B' => 'actionHasUser'), 'A.userId = B.userId', null)
			->where($this->quoteInto(
				'firstName LIKE "%?%" OR lastName LIKE "%?%" OR mail LIKE "%?%"',
				new Zend_Db_Expr($query),
				new Zend_Db_Expr($query),
				new Zend_Db_Expr($query)
			))
			->group('A.userId');

		if ($userId) {
			$sub = $this->getAdapter()->select();
			$sub->from('actionHasUser', 'actionId')
				->where('userId = ?', $userId);

			$sql->where('B.actionId IN (?)', new Zend_Db_Expr($sub));
		}

		return $this->getAdapter()->fetchAll($sql);
	}

	//==========================================================================
	public function getNonVotingUsersForProposal($proposalId)
	{
		$sql = $this->getAdapter()->select();
		$sql->from($this->_name)
			->join('actionHasUser', 'user.userId = actionHasUser.userId', null)
			->join('event', 'actionHasUser.actionId = event.actionId', null)
			->join('proposal', 'event.eventId = proposal.eventId', null)
			->joinLeft('vote', "actionHasUser.userId = vote.userId AND vote.proposalId = $proposalId", null)
			->where('proposal.proposalId = ?', $proposalId)
			->where('vote.vote IS NULL');

		return $this->getAdapter()->fetchAll($sql);
	}

	//==========================================================================
	public function getNonVotingUsersForEvent($eventId)
	{
		$sql = $this->getAdapter()->select();
		$sql->from($this->_name)
			->join('actionHasUser', 'user.userId = actionHasUser.userId', null)
			->join('event', 'actionHasUser.actionId = event.actionId', null)
			->join('proposal', 'event.eventId = proposal.eventId', null)
			->joinLeft('vote', "actionHasUser.userId = vote.userId AND vote.proposalId = $proposalId")
			->where('event.eventId = ?', $eventId)
			->where('vote.vote IS NULL');

		return $this->getAdapter()->fetchAll($sql);
	}

}
