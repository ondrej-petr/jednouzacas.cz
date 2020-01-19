<?php

class EventController extends OPLib_Controller_Action
{

	//==========================================================================
	public function detailAction()
	{
		$this->view->headScript()->appendFile($this->view->baseUrl('js/daterangepicker/moment.js'));
		$this->view->headScript()->appendFile($this->view->baseUrl('js/daterangepicker/daterangepicker.js'));
		$this->view->headScript()->appendFile($this->view->baseUrl('js/jquery.blueimp-gallery.min.js'));
		$this->view->headLink()->appendStylesheet($this->view->baseUrl('js/daterangepicker/daterangepicker.css'));
		$this->view->headLink()->appendStylesheet($this->view->baseUrl('css/blueimp-gallery.min.css'));

		$this->view->headLink()->appendStylesheet($this->view->baseUrl('css/awesome-bootstrap-checkbox.css'));

		$this->view->headScript()->appendFile('//cdnjs.cloudflare.com/ajax/libs/underscore.js/1.8.3/underscore-min.js');
		$this->view->headScript()->appendFile($this->view->baseUrl('js/jquery.mentionsInput.js'));
		$this->view->headLink()->appendStylesheet($this->view->baseUrl('css/jquery.mentionsInput.css'));

		$this->view->headScript()->appendFile(
			'http://maps.googleapis.com/maps/api/js?libraries=places&sensor=false&key=AIzaSyAiZHxJHnwX8dUDZ_etYMSyFitKtmm9JeM'
		);
		$this->view->headScript()->appendFile($this->view->baseUrl('js/locationpicker.jquery.min.js'));

		if (Zend_Registry::get('user')->getIsAnonym()) {
			$this->view->headLink()->appendStylesheet('//cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.0/bootstrap3-editable/css/bootstrap-editable.css');
			$this->view->headScript()->appendFile('//cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.0/bootstrap3-editable/js/bootstrap-editable.min.js');
		}

		// najdu danou akci a udalost (ID akce i udalosti zadane v URL)
		$action = new App_Model_Action();
		if (!$action->findById($this->getParam('actionId'))) {
			throw new Zend_Controller_Action_Exception(
				'Akce s ID ' . $this->getParam('actionId') . ' nebyla nalezena!', 404
			);
		}

		$event = $action->getEventByNo($this->getParam('eventNo'));
		if (!$event instanceof App_Model_Event) {
			throw new Zend_Controller_Action_Exception(
				'Udalost #' . $this->getParam('eventNo') . ' nebyla nalezena!', 404
			);
		}

		// vytvorim formular pro pridani noveho navrhu
		$proposalForm = new App_Form_Proposal();
		// nastavim do formulare ID udalosti
		$proposalForm->getElement('eventId')->setValue($event->getEventId());

		$galleryForm = new App_Form_Event_Gallery();
		$galleryForm->getElement('eventId')->setValue($event->getEventId());

		// formular pro editaci udalosti (pokud akce nebo udalost patri prihlasenemu uzivateli)
		$canEditEvent = false;
		if (in_array(Zend_Registry::get('user')->getUserId(), array($action->getUserId(), $event->getUserId()))) {
			$eventForm = new App_Form_Action();
			$eventForm->getEventForm();
			$eventForm->removeElement('dates');
			$eventForm->populate($event->toArray());

			$canEditEvent = true;
		}

		// formular pro diskusi
		$discussionForm = new App_Form_Discussion();
		$discussionForm->getElement('eventId')->setValue($event->getEventId());

		// pokud byl odeslán formulář, zvaliduji data a popr. je ulozim
		if ($this->_request->isPost()) {
			$postData = $this->_request->getPost();
			unset($postData['submit']);

			// byl odeslan formular s novym navrhem terminu
			if (isset($postData['dates'])) {
				if ($proposalForm->isValid($postData)) {
					$proposal = new App_Model_Proposal();

					// rozdelim datum na data a casy
					$dates = explode(' - ', $postData['dates']);
					@list($startDate, $startTime) = explode(' ', $dates[0]);
					@list($endDate, $endTime) = explode(' ', $dates[1]);

					$proposal->setEventId($proposalForm->getValue('eventId'));
					$proposal->setStartDate(date('Y-m-d', strtotime($startDate)));
					$proposal->setStartTime($startTime);
					if ($endDate && $endDate != '') {
						$proposal->setEndDate(date('Y-m-d', strtotime($endDate)));
						$proposal->setEndTime($endTime);
					}

					if (!$proposal->isDuplicated()) {
						// k navrhu terminu pridam uzivatele, co ho vytvoril
						if (!Zend_Auth::getInstance()->hasIdentity()) {
							throw new Zend_Exception('Neni prihlaseny zadny uzivatel', 500);
						}
						$proposal->setUserId(Zend_Auth::getInstance()->getIdentity()->userId);

						$proposal->insert();

						// pridam tohoto uzivatele k prave vytvorenemu navrhu terminu
						$proposal->addVote(Zend_Auth::getInstance()->getIdentity()->userId, 2);

						$this->_addFlashMessage('Návrh termínu byl vytvořen a byl k němu přidán tvůj hlas.', 'success');

						$this->redirect($this->getRequest()->getRequestUri());
					}
					// takovy navrh uz existuje
					else {
						$this->_addFlashMessage('Takový návrh termínu už existuje!', 'danger');
					}
				}
				// formular nebyl spravne vyplnen
				else {
					$this->_addFlashMessage('Vyplň, prosím, správně všechny povinnné položky.', 'danger');
					$proposalForm->populate($postData);
				}
			}

			// byl odeslan formular s obrazkem do galerie
			elseif (isset($_FILES['img'])) {
				if ($galleryForm->isValid($postData) && $event->getEventId() == $postData['eventId']) {
					$ds = DIRECTORY_SEPARATOR;
					if (App_Model_Image::upload(
						'img',
						'gallery' . $ds . $action->getActionId() . $ds . $event->getEventId()
					)) {
						$this->_addFlashMessage('Fotka byla přidána do galerie.', 'success');
					} else {
						$galleryForm->populate($postData);
						$this->_addFlashMessage('Fotku nebylo možné vložit do galerie.', 'danger');
					}

					$this->redirect($this->getRequest()->getRequestUri());
				}
				// formular nebyl spravne vyplnen
				else {
					$this->_addFlashMessage('Fotku se nepodařilo nahrát.', 'error');
					$galleryForm->populate($postData);
				}
			}

			// byl odeslan formular s editaci udalosti
			elseif (isset($postData['place'])) {
				if ($eventForm->isValid($postData)) {
					$event->setPropsFromArray($eventForm->getValues());
					$event->update();

					$this->_addFlashMessage('Událost byla upravena.', 'success');

					$this->redirect($this->getRequest()->getRequestUri());
				}
				// formular nebyl spravne vyplnen
				else {
					$this->_addFlashMessage('Vyplň, prosím, správně všechny povinnné položky.', 'danger');
					$eventForm->populate($postData);
				}
			}

			// byl odeslan formular s novym komentarem do diskuse
			if (isset($postData['comment'])) {
				if ($discussionForm->isValid($postData)
					&& $postData['eventId'] == $event->getEventId()
					&& Zend_Registry::get('user')->getUserId()
				) {
					$discussion = new App_Model_Discussion();
					$discussion->setUserId(Zend_Registry::get('user')->getUserId());
					$discussion->setEventId($discussionForm->getValue('eventId'));
					$discussion->setText($discussionForm->getValue('comment'));
					$discussion->setDate(date('Y-m-d H:i:s'));
					$discussion->insert();

					$this->_addFlashMessage('Komentář byl vložen do diskuse.', 'success');

					$this->redirect($this->getRequest()->getRequestUri());
				}
				// formular nebyl spravne vyplnen
				else {
					$this->_addFlashMessage('Vyplňte, prosím, správně všechny povinnné položky.', 'danger');
					$discussionForm->populate($postData);
				}
			}
		}

		$this->_writeDownFlashMessages(true);

		// vytvorim pole navrhu terminu a hlasovani pro vypis
		$votesArr = array();
		$votesByProposals = $event->getAllVotes();
		$proposalColNo = 0;

		$authUser = Zend_Registry::get('user');
		$didIVote = false;

		foreach ($votesByProposals as $proposalId => $votes) {
			foreach ($votes as $vote) {
				$user = new App_Model_User();
				if (!$user->findById($vote->getUserId())) {
					throw new Zend_Controller_Exception('Uzivatel s ID ' . $vote->getUserId() . ' nebyl nalezen!');
				}
				$votesArr[(string)$user->getUserId()]['votes'][$proposalColNo] = $vote;

				// pridam informace o danem uzivateli
				if (!isset($votesArr[(string)$user->getUserId()]['user'])) {
					$votesArr[(string)$user->getUserId()]['user'] = $user;
					$votesArr[(string)$user->getUserId()]['changeable'] = $user->getUserId() == $authUser->getUserId();
				}

				if ($user->getUserId() == $authUser->getUserId()) {
					$didIVote = true;
				}
			}
			$proposalColNo++;
		}

		// pokud jsem nehlasoval, pridam prazdny radek
		if (!$didIVote) {
			$votesArr[] = array(
				'user' => $authUser,
				'changeable' => true,
				'votes' => array()
			);
		}

		// pokud jsem admin a udalost jeste neni potvrzena, pridam radek pro
		// vyber vitezneho navrhu
		$chosenProposal = $event->getChosenProposal();
		if (Zend_Registry::get('user')->isAdminForEvent($event->getEventId())
			|| $chosenProposal instanceof App_Model_Proposal
		) {
			$votesArr[] = array(
				'user' => null,
				'changeable' => $event->canIChooseDate(),
				'chooseWinner' => true,
				'chosen' => $chosenProposal instanceof App_Model_Proposal ? $chosenProposal->getProposalId() : null,
				'votes' => array()
			);
		}

		// pokud jeste neni vybran vitezny navrh, pridam oznaceni vedouciho
		// navrhu
		if (!$event->getChosenProposal()) {
			$leadingProposal = $event->getLeadingProposal();
			$col = 0;
			$row = array();

			foreach ($votesByProposals as $proposalId => $votes) {
				if ($leadingProposal->getProposalId() == $proposalId) {
					$row[$col++] = true;
				} else {
					$row[$col++] = false;
				}
			}

			$votesArr[] = array(
				'votes' => $row
			);
		}

		$this->view->proposalForm = $proposalForm;
		if (isset($eventForm)) {
			$this->view->eventForm = $eventForm;
		}
		$this->view->discussionForm = $discussionForm;

		$this->view->galleryForm = $galleryForm;

		$this->view->event = $event;

		$this->view->votes = $votesArr;

		$this->view->canEditEvent = $canEditEvent;

		$this->view->discussion = $event->getDiscussion();
	}

}
