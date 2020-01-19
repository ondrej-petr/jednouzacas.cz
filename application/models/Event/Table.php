<?php

class App_Model_Event_Table extends OPLib_Db_Table
{

	/**
	 * @var string Table name
	 */
	protected $_name = 'event';

	/**
	 * @var string Primary key
	 */
	protected $_primary = 'eventId';

	//==========================================================================
	public function getByActionIdAndEventNo($actionId, $eventNo)
	{
		$sql = $this->select();
		$sql->from($this->_name)
			->where('actionId = ?', $actionId)
			->where('eventNo = ?', $eventNo);

		return $this->getAdapter()->fetchRow($sql);
	}

	//==========================================================================
	public function getLastEventForAction($actionId)
	{
		$sql = $this->getAdapter()->select();
		$sql->from($this->_name)
			->join('proposal', "event.eventId = proposal.eventId AND actionId = $actionId", null)
			->where('isChosen = ? AND startDate < DATE(NOW())', 1)
//			->orWhere('isChosen = ? AND startDate < DATE(NOW())', 0)
			->order(array('eventNo DESC', 'isChosen DESC'))
			->limit(1);

		if ($row = $this->getAdapter()->fetchRow($sql)) {
			return $row;
		}

		// neexistuje jeste zadny hlas pro zadnou udalost
		$sql = $this->getAdapter()->select();
		$sql->from($this->_name)
			->where('actionId = ?', $actionId)
			->order('eventNo DESC')
			->limit(1);

		return $this->getAdapter()->fetchRow($sql);
	}

	//==========================================================================
	public function getNextEventForAction($actionId)
	{
		$sql = $this->getAdapter()->select();
		$sql->from($this->_name)
			->join(
				'proposal',
				"event.eventId = proposal.eventId AND actionId = $actionId",
				array('isChosen', 'startDate')
			)
			->where('isChosen = ?', 1)
			->orWhere('isChosen = ? AND startDate > DATE(NOW())', 0)
			->order(array('eventNo DESC', 'isChosen DESC'))
			->limit(1);

		$row = $this->getAdapter()->fetchRow($sql);

		return ($row['startDate'] >= date('Y-m-d')) ? $row : false;
	}

}
