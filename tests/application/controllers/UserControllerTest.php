<?php

class UserControllerTest extends ControllerTestCase
{

	private $_model;

	private $_adapter;

	//==========================================================================
	public function setUp()
	{
		$this->_model = new App_Model_User();

/* 		$this->_adapter = $this->_model->getTable()->getAdapter();
		$this->_adapter->query('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
		$this->_adapter->beginTransaction();
 */
		parent::setUp();
	}

	//==========================================================================
	public function testCreateAction()
	{
		$postData = array(
			'userId' => '',
			'firstName' => 'Dump',
			'lastName' => 'User',
			'mail' => 'dump@user.cz',
			'password' => 'dumpuser',
			'password2' => 'dumpuser'
		);

		$user = new App_Model_User();
		if ($user->findByMail('dump@user.cz')) {
			$user->delete();
		}

		$form = new App_Form_User();

		$this->assertTrue($form->isValid($postData));

		$this->request->setMethod('POST')
					->setPost($postData);
		$this->dispatch('/vytvoreni-uctu');

		$this->assertRedirectTo('/prihlaseni');
	}

	//==========================================================================
	/**
	 * @depends testCreateAction
	 */
	public function testActivationAction()
	{
		$this->assertTrue($this->_model->findByMail('dump@user.cz'));

		$this->assertEquals($this->_model->getIsActive(), 0);

		$this->dispatch('/aktivace-uctu/' . $this->_model->getMail() . '/' . $this->_model->getPassword());
		$this->assertRedirectTo('/moje');

		$this->_model->findByMail('dump@user.cz');
		$this->assertEquals($this->_model->getIsActive(), 1);
	}

	//==========================================================================
	public function testLoginAction()
	{
		$postData = array(
			'mail' => 'dump@user.cz',
			'password' => 'dumpuser'
		);
		$this->request->setMethod('POST')
						->setPost($postData);
		$this->dispatch('/prihlaseni');
		$this->assertRedirectTo('/moje');
	}

	//==========================================================================
	/**
	 * @depends testCreateAction
	 */
	public function testRemovalAction()
	{
		$this->_model->findByMail('dump@user.cz');
		$this->dispatch('/odebrani-uctu/' . $this->_model->getMail() . '/' . $this->_model->getPassword());
		$this->assertFalse($this->_model->findByMail('dump@user.cz'));
	}

	//==========================================================================
	public function tearDown()
	{
//		$this->_adapter->rollBack();
	}

}
