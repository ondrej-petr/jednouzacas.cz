<?php

class ActionControllerTest extends ControllerTestCase
{

	private $_action;

	private $_event;

	private $_user;

	//==========================================================================
	public function setUp()
	{
		parent::setUp();

		$this->_action = new App_Model_Action();
		$this->_event = new App_Model_Event();

		$this->_loginLastInsertedUser();
	}

	//==========================================================================
	public function testCreateAction()
	{
		$postData = array(
			'actionId' => '',
			'title' => 'Testovací akce',
			'description' => 'vytvoření testovací akce a události',
			'place' => 'Praha',
			'dates' => '01.08.2016 - 01.08.2016',
			'intervalValue' => '2',
			'intervalUnit' => 'week',
			'isPublic' => '0',
			'isVisible' => '0'
		);

		$form = new App_Form_Action();

		$this->assertTrue($form->isValid($postData));

		$this->request->setMethod('POST')
					->setPost($postData);
		$this->dispatch('/akce/nova');

		$action = new App_Model_Action();
		$arr = $action->getAll();
		$action = array_pop($arr);
		$this->assertRedirectTo('/akce/' . $action->getActionId() . '/pozvani');

		return $action->getActionId();
	}

	//==========================================================================
	/**
	 * @depends testCreateAction
	 */
	public function testSendInventionCards($actionId)
	{
		// chybne adresy
		$postData = array(
			'invitations' => 'aaa@aaa.cz djen,bbb@vvv.cz'
		);
		$this->request->setMethod('POST')
					->setPost($postData);
		$this->dispatch('/akce/' . $actionId . '/pozvani');
		$this->assertQueryCount('div.alert-danger', 1);
		$this->assertRoute('actionInvitation');

		// spravne adresy
		$postData = array(
			'invitations' => 'aaa@aaa.cz, bbb@vvv.cz'
		);
		$this->resetRequest()
			->resetResponse();
		$this->request->setMethod('POST')
			->setPost($postData);
		$this->dispatch('/akce/' . $actionId . '/pozvani');
		$this->assertRedirectTo('/akce/' . $actionId);

		// spravne adresy
		$postData = array(
			'invitations' => 'aaa@aaa.cz bbb@vvv.cz; ccc@ccc.com'
		);
		$this->resetRequest()
			->resetResponse();
		$this->request->setMethod('POST')
			->setPost($postData);
		$this->dispatch('/akce/' . $actionId . '/pozvani');
		$this->assertRedirectTo('/akce/' . $actionId);

		return $actionId;
	}

	//==========================================================================
	/**
	 * Otestuje, zda je pozvanka platna
	 *
	 * @depends testCreateAction
	 */
	public function testAcceptInvitationCard($actionId)
	{
		$action = new App_Model_Action();
		$action->findById($actionId);
		$auth = $this->_loginLastInsertedUser();

		// vyzkousim pozvanku na prihlasenem uzivateli
		$action->removeUser($auth->getUserId());
		$hash = OPLib_EasyCrypt::encrypt($actionId . '|' . $auth->getUserId() . '|' . $auth->getMail());
		$this->dispatch('/pozvanka/' . $hash);
		$this->assertRedirectTo('/akce/' . $actionId);

		// vyzkousim pozvanku na nezalogovanem uzivateli
		$action->removeUser($auth->getUserId());
		Zend_Auth::getInstance()->clearIdentity();
		$this->resetRequest()
			->resetResponse();
		$this->dispatch('/pozvanka/' . $hash);
		$this->assertRedirectTo('/akce/' . $actionId);

		// vyzkousim pozvanku na neexistujicim uzivateli
		$hash = OPLib_EasyCrypt::encrypt($actionId . '|' . $auth->getUserId() . '|' . 'neexistujici@user.cz');
		$this->resetRequest()
			->resetResponse();
		$this->dispatch('/pozvanka/' . $hash);
		$this->assertRedirectTo('/vytvoreni-uctu?hash=' . $hash);
	}

	//==========================================================================
	/**
	 * Otestuje, zda byla automaticky vytvorena prvni udalost
	 *
	 * @depends testCreateAction
	 */
	public function testFirstEventHasBeenCreated($actionId)
	{
		$action = new App_Model_Action();
		$this->assertTrue($action->findById($actionId));

		$event = $action->getEventByNo(1);
		$this->assertTrue($event instanceof App_Model_Event);

		return $event->getEventId();
	}

	//==========================================================================
	/**
	 * Otestuje, zda byl automaticky vytvoren prvni navrh terminu
	 *
	 * @depends testCreateAction
	 */
	public function testFirstProposalHasBeenCreated($actionId)
	{
		$event = new App_Model_Event();
		$this->assertTrue($event->findByActionIdAndEventNo($actionId, 1));

		$proposals = $event->getProposals();
		$this->assertEquals(count($proposals), 1);

		$proposal = array_shift($proposals);
		$this->assertTrue($proposal instanceof App_Model_Proposal);

		$this->assertEquals($proposal->getStartDate(), '2016-08-01');
	}

	//==========================================================================
	/**
	 * @depends testCreateAction
	 */
	public function testActionDetail($actionId)
	{
		$action = new App_Model_Action();
		$this->assertTrue($action->findById($actionId));

		$this->dispatch('/akce/' . $actionId);
		$this->assertQueryContentContains('h1', $action->getTitle());
	}

	//==========================================================================
	/**
	 * @depends testCreateAction
	 */
	public function testDeleteAction($actionId)
	{
		$this->dispatch('/akce/' . $actionId . '/odebrat');
		$this->assertRoute('actionRemove');

		// akci se nepodari smazat, protoze jsou na ni prihlaseni lidi
		$action = new App_Model_Action();
		$this->assertFalse($action->findById($actionId));

/* 		// odeberu lidi a smazu znova
		foreach ($action->getAllUsers() as $user) {
			$action->removeUser($user->getUserId());
		}
		$this->dispatch('/akce/' . $actionId . '/odebrat');
		$this->assertFalse($action->findById($actionId));

 */		$event = new App_Model_Event();
		$this->assertEquals(0, count($event->getAll(false, "actionId = $actionId")));
	}

}
