<?php

class App_Model_Event_Mapper extends OPLib_Model_Mapper_Abstract
{

	protected $_tableClass = 'App_Model_Event_Table';

	protected $_modelClass = 'App_Model_Event';

	//==========================================================================
	/**
	 * Vrati posledni probehlou udalost pro danou akci.
	 *
	 * @param int $actionId
	 * @return App_Model_Event|boolean
	 */
	public function getLastEventForAction($actionId)
	{
		$row = $this->getTable()->getLastEventForAction($actionId);

		if (!is_array($row) || !count($row)) {
			return false;
		}

		$event = new App_Model_Event();
		$event->setPropsFromArray($row);

		return $event;
	}

	//==========================================================================
	/**
	 * Vrati prvni neprobehlou udalost pro danou akci.
	 *
	 * @param int $actionId
	 * @return App_Model_Event|boolean
	 */
	public function getNextEventForAction($actionId)
	{
		$row = $this->getTable()->getNextEventForAction($actionId);

		if (!is_array($row) || !count($row)) {
			return false;
		}

		$event = new App_Model_Event();
		$event->setPropsFromArray($row);

		return $event;
	}

}
