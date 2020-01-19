<?php

class App_Model_Action_Table extends OPLib_Db_Table
{

	/**
	 * @var string Table name
	 */
	protected $_name = 'action';

	/**
	 * @var string Primary key
	 */
	protected $_primary = 'actionId';

	//==========================================================================
	public function getAllActionsForUser($userId)
	{
		$select = $this->getAdapter()->select();
		$select->from(array('A' => 'action'))
			->join(array('B' => 'actionHasUser'), 'A.actionId = B.actionId', array())
			->where('B.userId = ?', $userId)
			->order('actionId DESC');

		return $select->query()->fetchAll();
	}

	//==========================================================================
	public function addUser($actionId, $userId, $isAdmin, $inviterId)
	{
		try {
			$this->getAdapter()->insert('actionHasUser', array(
				'actionId' => $actionId,
				'userId' => $userId,
				'isAdmin' => $isAdmin,
				'inviterId' => $inviterId
			));
		} catch (Zend_Db_Exception $e) {

		}
	}

	//==========================================================================
	public function removeUser($actionId, $userId)
	{
		$this->getAdapter()->delete('actionHasUser', "actionId = $actionId AND userId = $userId");
	}

	//==========================================================================
	public function checkUser($actionId, $userId)
	{
		$select = $this->getAdapter()->select();
		$select->from('actionHasUser')
			->where('actionId = ?', $actionId)
			->where('userId = ?', $userId);

		return $this->getAdapter()->fetchRow($select);
	}

}
