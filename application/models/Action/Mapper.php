<?php

class App_Model_Action_Mapper extends OPLib_Model_Mapper_Abstract
{

	protected $_tableClass = 'App_Model_Action_Table';

	protected $_modelClass = 'App_Model_Action';

	//==========================================================================
	/**
	 * Vrati vsechny akce, ve kterych je dany uzivatel
	 *
	 * @param int $userId
	 * @param string $order
	 * @return App_Model_Action[]
	 */
	public function getAllActionsForUser($userId, $order = null)
	{
		$rows = $this->getTable()->getAllActionsForUser($userId);
		$actions = $this->_getSet($rows);

		switch ($order) {
			case 'eventDate':
				usort($actions, function($a, $b) use ($actions) {
					if (!$a->getNextEvent() || !$b->getNextEvent()) {
						return !$a->getNextEvent() ? 1 : -1;
					} elseif ($a->getNextEvent()->getDate(false) == $b->getNextEvent()->getDate(false)) {
						return 0;
					} else {
						return $a->getNextEvent()->getDate(false) < $b->getNextEvent()->getDate(false) ? -1 : 1;
					}
				});
				break;
		}

		return $actions;
	}

}
