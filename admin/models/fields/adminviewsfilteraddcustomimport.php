<?php
/**
 * @package    Joomla.Component.Builder
 *
 * @created    30th April, 2015
 * @author     Llewellyn van der Merwe <https://dev.vdm.io>
 * @git        Joomla Component Builder <https://git.vdm.dev/joomla/Component-Builder>
 * @copyright  Copyright (C) 2015 Vast Development Method. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted access');

// import the list field type
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');

/**
 * Adminviewsfilteraddcustomimport Form Field class for the Componentbuilder component
 */
class JFormFieldAdminviewsfilteraddcustomimport extends JFormFieldList
{
	/**
	 * The adminviewsfilteraddcustomimport field type.
	 *
	 * @var		string
	 */
	public $type = 'adminviewsfilteraddcustomimport';

	/**
	 * Method to get a list of options for a list input.
	 *
	 * @return	array    An array of JHtml options.
	 */
	protected function getOptions()
	{
		// Get a db connection.
		$db = JFactory::getDbo();

		// Create a new query object.
		$query = $db->getQuery(true);

		// Select the text.
		$query->select($db->quoteName('add_custom_import'));
		$query->from($db->quoteName('#__componentbuilder_admin_view'));
		$query->order($db->quoteName('add_custom_import') . ' ASC');

		// Reset the query using our newly populated query object.
		$db->setQuery($query);

		$_results = $db->loadColumn();
		$_filter = array();
		$_filter[] = JHtml::_('select.option', '', '- ' . JText::_('COM_COMPONENTBUILDER_FILTER_SELECT_ADD_CUSTOM_IMPORT') . ' -');

		if ($_results)
		{
			// get admin_viewsmodel
			$_model = ComponentbuilderHelper::getModel('admin_views');
			$_results = array_unique($_results);
			foreach ($_results as $add_custom_import)
			{
				// Translate the add_custom_import selection
				$_text = $_model->selectionTranslation($add_custom_import,'add_custom_import');
				// Now add the add_custom_import and its text to the options array
				$_filter[] = JHtml::_('select.option', $add_custom_import, JText::_($_text));
			}
		}
		return $_filter;
	}
}
