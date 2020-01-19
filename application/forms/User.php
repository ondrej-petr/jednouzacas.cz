<?php

class App_Form_User extends Twitter_Bootstrap3_Form_Horizontal
{

	public function init()
	{
		$this->setMethod('post')
			->setAttrib('id', 'loginForm');

		$this->addElement('text', 'firstName', array(
			'label' => 'Jméno:',
			'required' => true,
			'filters' => array('StripTags')
		));

		$this->addElement('text', 'lastName', array(
			'label' => 'Příjmení:',
			'required' => true,
			'filters' => array('StripTags')
		));

		$this->addElement('text', 'mail', array(
			'label' => 'E-mailová adresa:',
			'required' => true,
			'validators' => array('emailAddress'),
			'filters' => array('StringToLower', 'StripTags')
		));

		$this->addElement('text', 'phone', array(
			'label' => 'Telefon:',
			'required' => false,
			'validators' => array(array('regex', false, array('/^\+?[0-9 ]+$/'))),
			'errorMessages' => array('Zadané telefonní číslo není platné'),
			'attribs' => array('placeholder' => 'Slouží pro zasílání upozornění o akci formou SMS')
		));

		$this->addElement('file', 'img', array(
			'label' => 'Profilová fotka:',
			'required' => false
		));

// TODO validator na pocet znaku
		$this->addElement('password', 'password', array(
			'label' => 'Heslo:',
			'required' => true,
			'validators' => array(array('StringLength', false, array(6,20)))
		));

		$this->addElement('password', 'password2', array(
			'label' => 'Heslo znova:',
			'required' => true,
			'validators' => array(array('Identical', false, array('token' => 'password')))
		));

		$this->addElement('hidden', 'invitationHash', array());

		$this->addElement('submit', 'submit', array(
			'ignore' => true,
			'label' => 'Odeslat'
		));
	}

}
