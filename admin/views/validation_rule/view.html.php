<?php
/**
 * @package    Joomla.Component.Builder
 *
 * @created    30th April, 2015
 * @author     Llewellyn van der Merwe <http://www.joomlacomponentbuilder.com>
 * @github     Joomla Component Builder <https://github.com/vdm-io/Joomla-Component-Builder>
 * @copyright  Copyright (C) 2015 - 2020 Vast Development Method. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * Validation_rule View class
 */
class ComponentbuilderViewValidation_rule extends JViewLegacy
{
	/**
	 * display method of View
	 * @return void
	 */
	public function display($tpl = null)
	{
		// set params
		$this->params = JComponentHelper::getParams('com_componentbuilder');
		// Assign the variables
		$this->form = $this->get('Form');
		$this->item = $this->get('Item');
		$this->script = $this->get('Script');
		$this->state = $this->get('State');
		// get action permissions
		$this->canDo = ComponentbuilderHelper::getActions('validation_rule', $this->item);
		// get input
		$jinput = JFactory::getApplication()->input;
		$this->ref = $jinput->get('ref', 0, 'word');
		$this->refid = $jinput->get('refid', 0, 'int');
		$return = $jinput->get('return', null, 'base64');
		// set the referral string
		$this->referral = '';
		if ($this->refid && $this->ref)
		{
			// return to the item that referred to this item
			$this->referral = '&ref=' . (string)$this->ref . '&refid=' . (int)$this->refid;
		}
		elseif($this->ref)
		{
			// return to the list view that referred to this item
			$this->referral = '&ref=' . (string)$this->ref;
		}
		// check return value
		if (!is_null($return))
		{
			// add the return value
			$this->referral .= '&return=' . (string)$return;
		}

		// Set the toolbar
		$this->addToolBar();
		
		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new Exception(implode("\n", $errors), 500);
		}

		// Display the template
		parent::display($tpl);

		// Set the document
		$this->setDocument();
	}


	/**
	 * Setting the toolbar
	 */
	protected function addToolBar()
	{
		JFactory::getApplication()->input->set('hidemainmenu', true);
		$user = JFactory::getUser();
		$userId	= $user->id;
		$isNew = $this->item->id == 0;

		JToolbarHelper::title( JText::_($isNew ? 'COM_COMPONENTBUILDER_VALIDATION_RULE_NEW' : 'COM_COMPONENTBUILDER_VALIDATION_RULE_EDIT'), 'pencil-2 article-add');
		// Built the actions for new and existing records.
		if (ComponentbuilderHelper::checkString($this->referral))
		{
			if ($this->canDo->get('validation_rule.create') && $isNew)
			{
				// We can create the record.
				JToolBarHelper::save('validation_rule.save', 'JTOOLBAR_SAVE');
			}
			elseif ($this->canDo->get('validation_rule.edit'))
			{
				// We can save the record.
				JToolBarHelper::save('validation_rule.save', 'JTOOLBAR_SAVE');
			}
			if ($isNew)
			{
				// Do not creat but cancel.
				JToolBarHelper::cancel('validation_rule.cancel', 'JTOOLBAR_CANCEL');
			}
			else
			{
				// We can close it.
				JToolBarHelper::cancel('validation_rule.cancel', 'JTOOLBAR_CLOSE');
			}
		}
		else
		{
			if ($isNew)
			{
				// For new records, check the create permission.
				if ($this->canDo->get('validation_rule.create'))
				{
					JToolBarHelper::apply('validation_rule.apply', 'JTOOLBAR_APPLY');
					JToolBarHelper::save('validation_rule.save', 'JTOOLBAR_SAVE');
					JToolBarHelper::custom('validation_rule.save2new', 'save-new.png', 'save-new_f2.png', 'JTOOLBAR_SAVE_AND_NEW', false);
				};
				JToolBarHelper::cancel('validation_rule.cancel', 'JTOOLBAR_CANCEL');
			}
			else
			{
				if ($this->canDo->get('validation_rule.edit'))
				{
					// We can save the new record
					JToolBarHelper::apply('validation_rule.apply', 'JTOOLBAR_APPLY');
					JToolBarHelper::save('validation_rule.save', 'JTOOLBAR_SAVE');
					// We can save this record, but check the create permission to see
					// if we can return to make a new one.
					if ($this->canDo->get('validation_rule.create'))
					{
						JToolBarHelper::custom('validation_rule.save2new', 'save-new.png', 'save-new_f2.png', 'JTOOLBAR_SAVE_AND_NEW', false);
					}
				}
				$canVersion = ($this->canDo->get('core.version') && $this->canDo->get('validation_rule.version'));
				if ($this->state->params->get('save_history', 1) && $this->canDo->get('validation_rule.edit') && $canVersion)
				{
					JToolbarHelper::versions('com_componentbuilder.validation_rule', $this->item->id);
				}
				if ($this->canDo->get('validation_rule.create'))
				{
					JToolBarHelper::custom('validation_rule.save2copy', 'save-copy.png', 'save-copy_f2.png', 'JTOOLBAR_SAVE_AS_COPY', false);
				}
				JToolBarHelper::cancel('validation_rule.cancel', 'JTOOLBAR_CLOSE');
			}
		}
		JToolbarHelper::divider();
		// set help url for this view if found
		$help_url = ComponentbuilderHelper::getHelpUrl('validation_rule');
		if (ComponentbuilderHelper::checkString($help_url))
		{
			JToolbarHelper::help('COM_COMPONENTBUILDER_HELP_MANAGER', false, $help_url);
		}
	}

	/**
	 * Escapes a value for output in a view script.
	 *
	 * @param   mixed  $var  The output to escape.
	 *
	 * @return  mixed  The escaped value.
	 */
	public function escape($var)
	{
		if(strlen($var) > 30)
		{
    		// use the helper htmlEscape method instead and shorten the string
			return ComponentbuilderHelper::htmlEscape($var, $this->_charset, true, 30);
		}
		// use the helper htmlEscape method instead.
		return ComponentbuilderHelper::htmlEscape($var, $this->_charset);
	}

	/**
	 * Method to set up the document properties
	 *
	 * @return void
	 */
	protected function setDocument()
	{
		$isNew = ($this->item->id < 1);
		if (!isset($this->document))
		{
			$this->document = JFactory::getDocument();
		}
		$this->document->setTitle(JText::_($isNew ? 'COM_COMPONENTBUILDER_VALIDATION_RULE_NEW' : 'COM_COMPONENTBUILDER_VALIDATION_RULE_EDIT'));
		$this->document->addStyleSheet(JURI::root() . "administrator/components/com_componentbuilder/assets/css/validation_rule.css", (ComponentbuilderHelper::jVersion()->isCompatible('3.8.0')) ? array('version' => 'auto') : 'text/css');
		// Add Ajax Token
		$this->document->addScriptDeclaration("var token = '".JSession::getFormToken()."';");
		$this->document->addScript(JURI::root() . $this->script, (ComponentbuilderHelper::jVersion()->isCompatible('3.8.0')) ? array('version' => 'auto') : 'text/javascript');
		$this->document->addScript(JURI::root() . "administrator/components/com_componentbuilder/views/validation_rule/submitbutton.js", (ComponentbuilderHelper::jVersion()->isCompatible('3.8.0')) ? array('version' => 'auto') : 'text/javascript'); 


		// add the Uikit v2 style sheets
		$this->document->addStyleSheet( JURI::root(true) .'/media/com_componentbuilder/uikit-v2/css/uikit.gradient.min.css' , (ComponentbuilderHelper::jVersion()->isCompatible('3.8.0')) ? array('version' => 'auto') : 'text/css');
		// add Uikit v2 JavaScripts
		$this->document->addScript( JURI::root(true) .'/media/com_componentbuilder/uikit-v2/js/uikit.min.js' , (ComponentbuilderHelper::jVersion()->isCompatible('3.8.0')) ? array('version' => 'auto') : 'text/javascript');

		// add the Uikit v2 extra style sheets
		$this->document->addStyleSheet( JURI::root(true) .'/media/com_componentbuilder/uikit-v2/css/components/notify.gradient.min.css' , (ComponentbuilderHelper::jVersion()->isCompatible('3.8.0')) ? array('version' => 'auto') : 'text/css');
		// add Uikit v2 extra JavaScripts
		$this->document->addScript( JURI::root(true) .'/media/com_componentbuilder/uikit-v2/js/components/lightbox.min.js', (ComponentbuilderHelper::jVersion()->isCompatible('3.8.0')) ? array('version' => 'auto') : 'text/javascript', (ComponentbuilderHelper::jVersion()->isCompatible('3.8.0')) ? array('type' => 'text/javascript', 'async' => 'async') : true);
		$this->document->addScript( JURI::root(true) .'/media/com_componentbuilder/uikit-v2/js/components/notify.min.js', (ComponentbuilderHelper::jVersion()->isCompatible('3.8.0')) ? array('version' => 'auto') : 'text/javascript', (ComponentbuilderHelper::jVersion()->isCompatible('3.8.0')) ? array('type' => 'text/javascript', 'async' => 'async') : true);
		// add var key
		$this->document->addScriptDeclaration("var vastDevMod = '" . $this->get('VDM') . "';");
		// add return_here
		$this->document->addScriptDeclaration("var return_here = '" . urlencode(base64_encode((string) JUri::getInstance())) . "';");
		// need to add some language strings
		JText::script('COM_COMPONENTBUILDER_VALIDATION_RULE_NAME_ALREADY_TAKEN_PLEASE_TRY_AGAIN');
		JText::script('COM_COMPONENTBUILDER_YOU_MUST_ADD_AN_UNIQUE_VALIDATION_RULE_NAME');
		JText::script('view not acceptable. Error');
	}
}
