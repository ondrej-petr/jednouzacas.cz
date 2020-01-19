<?php

class App_Form_Action extends Twitter_Bootstrap3_Form_Horizontal
{

	public function init()
	{
		$this->addDisplayGroupPrefixPath('Twitter_Bootstrap3_Form_', 'Twitter/Bootstrap3/Form/');

		$this->setMethod('post');

		$this->addElement('hidden', 'actionId', array(
			'value' => null
		));

		$this->addElement('text', 'title', array(
			'label' => 'Název:',
			'required' => true,
			'filters' => array('StripTags')
		));

		$this->addElement('textarea', 'description', array(
			'label' => 'Popis:',
			'attribs' => array('rows' => 5),
			'filters' => array('StripTags')
		));

/* 		$this->addElement('text', 'duration', array(
			'label' => 'Trvání:',
			'placeholder' => 'Počet dnů',
			'required' => true,
			'validators' => array('Numeric')
		));
 */
		$this->addElement('text', 'place', array(
			'label' => 'Místo konání:',
			'required' => false,
			'filters' => array('StripTags')
		));

		$this->addElement('static', 'map', array(
			'label' => '&nbsp;',
			'ignore' => true,
			'attribs' => array('style' => 'height: 30vh'),
			'decorators' => array(
				array('ViewHelper'),
				array('Addon'),
				array('FieldSize'),
				array('HtmlTag', array('class' => 'col-sm-10')),
				array('Label', array('escape' => false, 'class' => 'col-sm-2')),
				array('Container', array('style' => 'display: none')),
			)
		));

		$this->addElement('hidden', 'latitude', array(
		));

		$this->addElement('hidden', 'longtitude', array(
		));

		$this->addElement('text', 'dates', array(
			'label' => 'Termín akce:',
			'required' => true,
			'class' => 'daterange',
			'autocomplete' => 'off'
		));

		$this->addElement('text', 'intervalValue', array(
			'label' => 'Opakování:',
			'placeholder' => 'Počet dnů / týdnů / měsíců / roků mezi jednotlivými událostmi',
			'required' => true,
			'attribs' => array(
				'data-toggle' => 'tooltip',
				'title' => 'Akce se koná jednou za ...'
			),
			'decorators' => array(
				array('ViewHelper'),
				array('Addon'),
				array('Feedback_State', array(
					'renderIcon' => $this->_renderElementsStateIcons,
					'successIcon' => $this->_elementsSuccessIcon,
					'warningIcon' => $this->_elementsWarningIcon,
					'errorIcon' => $this->_elementsErrorIcon,
				)),
				array('Errors'),
				array('Description', array(
					'tag' => 'p',
					'class' => 'help-block',
				)),
				array('Container', array('tag' => 'div', 'class' => 'col-sm-6')),
				array('FieldSize'),
				array('Label', array(
					'class' => 'control-label col-sm-2',
				))
			)
		));

		$this->addElement('select', 'intervalUnit', array(
			'required' => true,
			'multiOptions' => array(
				'day' => 'dnů',
				'week' => 'týdnů',
				'month' => 'měsíců',
				'year' => 'roků'
			),
			'decorators' => array(
				array('ViewHelper'),
				array('Addon'),
				array('FieldSize'),
				array('HtmlTag', array('tag' => 'div', 'class' => 'col-sm-4'))
			),
			'value' => 'week'
		));

		$this->addDisplayGroup(array('intervalValue', 'intervalUnit'), 'interval', array(
			'decorators' => array(
				'FormElements',
				array('HtmlTag', array('tag' => 'div', 'class' => 'form-group'))
			)
		));

		$this->addElement('checkbox', 'isPublic', array(
			'label' => 'Je veřejná',
			'description' => 'Pokud je akce označena za veřejnou, může se na ni přihlásit kdokoliv.'
		));

		$this->addElement('checkbox', 'isVisible', array(
			'label' => 'Je viditelná',
			'description' => 'Pokud je akce označena za viditelnou, může si pobrobnosti zobrazit kdokoliv.'
		));

		$this->addElement('checkbox', 'isClosed', array(
			'label' => 'Je uzavřená',
			'description' => 'Pokud je akce označena za uzavřenou, není možné navrhovat další termíny událostí.'
		));

		$this->addElement('checkbox', 'isPaused', array(
			'label' => 'Je pozastavena',
			'description' => 'Pokud je akce označena za pozastavenou, nebudou se vytvářet žádná nová připomenutí.'
		));

		$this->addElement('submit', 'submit', array(
			'ignore' => true,
			'label' => 'Odeslat'
		));
	}

	public function getEventForm()
	{
		$this->removeElement('title');
		$this->removeElement('intervalValue');
		$this->removeElement('intervalUnit');
		$this->removeDisplayGroup('interval');
		$this->removeDisplayGroup('isPaused');

		return $this;
	}

	public function getActionForm()
	{
		$this->removeElement('isPublic');
		$this->removeElement('isVisible');
		$this->removeElement('isClosed');
		// pro editaci nebudu zadavat info o miste ani termin, to patri az
		// k jednotlivym udalostem
		$this->removeElement('place');
		$this->removeElement('latitude');
		$this->removeElement('longtitude');
		$this->removeElement('dates');

		return $this;
	}

}
