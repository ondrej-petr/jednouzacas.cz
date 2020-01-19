<?php

class ActionController extends OPLib_Controller_Action
{

	//==========================================================================
	public function indexAction()
	{
		$this->view->headScript()->appendFile($this->view->baseUrl('js/daterangepicker/moment.js'));
		$this->view->headScript()->appendFile($this->view->baseUrl('js/fullcalendar/fullcalendar.min.js'));
		$this->view->headScript()->appendFile($this->view->baseUrl('js/fullcalendar/locale/cs.js'));
		$this->view->headLink()->appendStylesheet($this->view->baseUrl('js/fullcalendar/fullcalendar.min.css'));

		$this->view->myActions = Zend_Registry::get('user')->getAllActions('eventDate');

		// TODO
		$this->view->publicActions = array();

		$this->_writeDownFlashMessages();
	}

	//==========================================================================
	public function detailAction()
	{
		$action = new App_Model_Action();
		if (!$action->findById($this->getParam('actionId'))) {
			throw new Zend_Controller_Action_Exception('Akce nebyla nalezena.', 404);
		}

		$this->view->headScript()->appendFile($this->view->baseUrl('js/daterangepicker/moment.js'));
		$this->view->headScript()->appendFile($this->view->baseUrl('js/daterangepicker/daterangepicker.js'));
		$this->view->headLink()->appendStylesheet($this->view->baseUrl('js/daterangepicker/daterangepicker.css'));

		$this->view->headScript()->appendFile($this->view->baseUrl('js/tokenfield/bootstrap-tokenfield.min.js'));
		$this->view->headScript()->appendFile($this->view->baseUrl('js/tokenfield/typeahead.bundle.min.js'));
		$this->view->headLink()->appendStylesheet($this->view->baseUrl('js/tokenfield/css/tokenfield-typeahead.min.css'));
		$this->view->headLink()->appendStylesheet($this->view->baseUrl('js/tokenfield/css/bootstrap-tokenfield.min.css'));

		$this->view->headScript()->appendFile(
			'http://maps.googleapis.com/maps/api/js?libraries=places&sensor=false&key=AIzaSyAiZHxJHnwX8dUDZ_etYMSyFitKtmm9JeM'
		);
		$this->view->headScript()->appendFile($this->view->baseUrl('js/locationpicker.jquery.min.js'));

		$this->view->action = $action;
		$this->view->userIsAdmin = ($user = Zend_Registry::get('user'))
			&& $user->isAdminForAction($action->getActionId());

		// pripravim formular pro zadani nove udalosti
		$form = new App_Form_Action();
		$form->getEventForm();
		$form->getElement('actionId')->setValue($action->getActionId());
		// navrhnu datum dle zadaneho intervalu akce
		$form->getElement('dates')->setValue($action->proposeNextEventDate());
		$this->view->eventForm = $form;

		// formular pro editaci akce (pokud akce patri prihlasenemu uzivateli)
		if ($this->view->userIsAdmin) {
			$form = new App_Form_Action();
			$form->getActionForm();
			$form->populate($action->toArray());
			$this->view->actionForm = $form;
		}

		// formular pro pozvani novych lidi
		$form = new App_Form_Action_Invitation();
		$form->setAction('/akce/' . $action->getActionId() . '/pozvani');
		$form->removeElement('btnBack');
		$this->view->invitationForm = $form;

		if ($this->getRequest()->isPost()) {
			$postData = $this->_request->getPost();
			$postData['userId'] = Zend_Registry::get('user')->getUserId();

			// byl odeslan formular s novou udalosti
			if (isset($postData['dates'])) {
				if ($this->view->eventForm->isValid($postData)) {
					unset($postData['submit']);

					// ulozim udalost
					$event = $action->createNewEvent(false, $this->view->eventForm->getValues());

					$this->_addFlashMessage('Nová událost byla vytvořena.', 'success');

					$this->redirect(
						'/' . $this->_translate('akce') . '/' . $action->getActionId() . '/' . $event->getEventNo()
					);
				}
				// formular nebyl spravne vyplnen
				else {
					$this->_addFlashMessage('Vyplň, prosím, správně všechny povinnné položky.', 'danger');
					$this->view->eventForm->populate($postData);
				}
			}
			// byl odeslan formular s upravou akce
			elseif (isset($postData['title'])) {
				if ($this->view->actionForm->isValid($postData)) {
					$action->setTitle($this->view->actionForm->getValue('title'));
					$action->setDescription($this->view->actionForm->getValue('description'));
					$action->setIntervalValue($this->view->actionForm->getValue('intervalValue'));
					$action->setIntervalUnit($this->view->actionForm->getValue('intervalUnit'));
					$action->setIsPaused((int) $this->view->actionForm->getValue('isPaused'));

					$action->update();

					$this->_addFlashMessage('Akce byla upravena.', 'success');

					$this->redirect('/' . $this->_translate('akce') . '/' . $action->getActionId());
				}
				// formular nebyl spravne vyplnen
				else {
					$this->_addFlashMessage('Vyplň, prosím, správně všechny povinnné položky.', 'danger');
					$this->view->actionForm->populate($postData);
				}
			}
			// nejaka chyba
			else {

			}
		}

		$this->view->events = array_reverse($action->getAllEvents());
		$lastEvent = end($this->view->events);
		$this->view->allowCreateNewEvent = (
			$lastEvent->getChosenProposal()
			|| $lastEvent->getState() != 'active'
			|| $lastEvent->getIsClosed()
		);

		$this->_writeDownFlashMessages();
	}

	//==========================================================================
	public function newAction()
	{
		if (!Zend_Auth::getInstance()->hasIdentity()) {
			throw new Zend_Exception('Neni prihlaseny zadny uzivatel', 500);
		}

		$this->view->headScript()->appendFile($this->view->baseUrl('js/daterangepicker/moment.js'));
		$this->view->headScript()->appendFile($this->view->baseUrl('js/daterangepicker/daterangepicker.js'));
		$this->view->headLink()->appendStylesheet($this->view->baseUrl('js/daterangepicker/daterangepicker.css'));

		$this->view->headScript()->appendFile('
			http://maps.googleapis.com/maps/api/js?libraries=places&sensor=false&key=AIzaSyAiZHxJHnwX8dUDZ_etYMSyFitKtmm9JeM'
		);
		$this->view->headScript()->appendFile($this->view->baseUrl('js/locationpicker.jquery.min.js'));

		$form = new App_Form_Action();

		if ($this->_request->isPost()) {
			$postData = $this->_request->getPost();

			if ($form->isValid($postData)) {
				$data = $form->getValues();
				$data['userId'] = Zend_Registry::get('user')->getUserId();

				// ulozim akci
				$action = new App_Model_Action();
				$action->setPropsFromArray($data);
				$action->insert();

				// k akci pridam uzivatele, co ji vytvoril
				$action->addUser(Zend_Registry::get('user')->getUserId(), true, 0);

				// zbavim se dat, ktera patrila akci
				foreach (array('title', 'description', 'intervalValue', 'intervalUnit') as $key) {
					unset($data[$key]);
				}

				// vytvorim novou udalost
				$event = $action->createNewEvent(false, $data);

				$this->_addFlashMessage('Akce a její první událost byla vytvořena.', 'success');

				$this->redirect(
					'/' . $this->_translate('akce') . '/' . $action->getActionId() . '/' . $this->_translate('pozvani')
				);
			}

			// formular nebyl spravne vyplnen
			else {
				$this->_addFlashMessage('Vyplň, prosím, správně všechny povinnné položky.', 'danger');
				$form->populate($postData);
			}
		}

		$this->_writeDownFlashMessages(true);

		$this->view->form = $form;
	}

	//==========================================================================
	public function invitationAction()
	{
		$this->view->headScript()->appendFile($this->view->baseUrl('js/tokenfield/bootstrap-tokenfield.min.js'));
		$this->view->headScript()->appendFile($this->view->baseUrl('js/tokenfield/typeahead.bundle.min.js'));
		$this->view->headLink()->appendStylesheet($this->view->baseUrl('js/tokenfield/css/tokenfield-typeahead.min.css'));
		$this->view->headLink()->appendStylesheet($this->view->baseUrl('js/tokenfield/css/bootstrap-tokenfield.min.css'));

		$action = new App_Model_Action();
		if (!$action->findById($this->getParam('actionId'))) {
			throw new Zend_Controller_Exception('Akce s danym ID neexistuje.', 500);
		}

		// zkontroluju, zda si prihlaseny uzivatel muze tuto stranku zobrazit
		if ($action->checkCurrentUser()) {
			$form = new App_Form_Action_Invitation();

			if ($this->_request->isPost()) {
				$postData = $this->_request->getPost();

				if ($form->isValid($postData)) {
					$mails = preg_split('/,|;| /', trim($postData['invitations']));

					$validator = new Zend_Validate_EmailAddress();
					foreach ($mails as $key => $mail) {
						// vysortuju polozky, ktere vznikly kvuli spatnemu splitu
						if ($mail == '') {
							unset($mails[$key]);
							continue;
						}
						//zvaliduju e-mailove adresy
						if (!$validator->isValid($mail)) {
							$errors[] = $mail;
						}
					}

					// pokud byla nektera adresa chybne, vypisu ji
					if (isset($errors)) {
						$form->getElement('invitations')->addError(
							'Následující e-mailové adresy jsou zadány chybně: ' . implode(', ', $errors)
						);
					}
					// pokud bylo vse v poradku, odeslu maily a presmeruju
					else {
						foreach ($mails as $mail) {
							$action->sendInvitationMail($mail);
						}

						$this->_addFlashMessage('Pozvánky byly odeslány', 'success');

						$this->redirect('/' . $this->_translate('akce') . '/' . $action->getActionId());
					}
				}

				// formular nebyl spravne vyplnen
				$this->_addFlashMessage('Vyplň, prosím, správně všechny povinnné položky.', 'danger');
				$form->populate($postData);
			}

			$this->view->form = $form;
		}
		// uzivatel nema opravneni si tuto stranku zobrazit
		else {
			$this->forward('not-allowed', 'user');
		}

		$this->view->action = $action;

		$this->_writeDownFlashMessages(true);
	}

	//==========================================================================
	public function removeAction()
	{
		$action = new App_Model_Action();
		if ($action->findById($this->getParam('actionId'))) {
			$users = $action->getAllUsers();
			// akci mohu smazat pouze pokud na ni neni nikdo prihlasen
			if (count($users) == 1
				&& $action->getUserId() == array_shift($users)->getUserId()
				&& $action->getUserId() == Zend_Registry::get('user')->getUserId()
			) {
				$action->delete();

				$this->_addFlashMessage('Akce byla smazána.', 'success');
			} else {
				$this->_addFlashMessage('Akci není možné smazat, jsou na ni přihlášení lidi.', 'info');
			}
		} else {
			$this->_addFlashMessage('Akci se nepodařilo najít a tudíž ani smazat.', 'danger');
		}

		$this->redirect('/' . $this->_translate('akce'));
	}

	//==========================================================================
	public function invitationCardAction()
	{
		// desifruju hash
		list($actionId, $inviterId, $mail) = explode('|', OPLib_EasyCrypt::decrypt($this->getParam('hash')));
		if (!$actionId || !$inviterId || !$mail) {
			throw new Zend_Controller_Action_Exception('Tato pozvánka není platná');
		}

		$session = new Zend_Session_Namespace('invitation');
//		$session->setExpirationHops(4);

		// zkusim najit uzivatele podle mailu
		$user = new App_Model_User();
		if (!$user->findByMail($mail) || $user->getIsAnonym()) {
			Zend_Auth::getInstance()->clearIdentity();

			$session->hash = $this->getParam('hash');

			$this->_addFlashMessage('Před přijetím pozvání je zapotřebí si vytvořit účet.', 'info');
			$this->redirect('/' . $this->_translate('vytvoreni-uctu') . '?hash=' . $this->getParam('hash'));
		} else {
			$action = new App_Model_Action();
			if ($action->findById($actionId)) {
				$action->addUser($user->getUserId(), false, $inviterId);
			}

			// pozvany uzivatel je uz prihlaseny
			if (Zend_Auth::getInstance()->hasIdentity() && Zend_Auth::getInstance()->getIdentity()->mail == $mail) {
				$this->_addFlashMessage('Jsi přidán(a) do této akce.', 'success');
			}
			// pozvany uzivatel neni prihlaseny
			else {
				Zend_Auth::getInstance()->clearIdentity();
				$this->_addFlashMessage('Jsi přidán(a) do nové akce. Stačí se už jen přihlásit.', 'success');
			}
			$session->redirectUrl = $this->_translate('akce') . '/' . $action->getActionId();
			$this->redirect($session->redirectUrl);
		}
	}

}
