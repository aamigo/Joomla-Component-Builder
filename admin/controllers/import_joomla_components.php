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

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Utilities\ArrayHelper;

/**
 * Componentbuilder Import_joomla_components Base Controller
 */
class ComponentbuilderControllerImport_joomla_components extends BaseController
{
	/**
	 * Import an spreadsheet.
	 *
	 * @return  void
	 */
	public function import()
	{
		// Check for request forgeries
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$model = $this->getModel('Import_joomla_components');
		if ($model->import())
		{
			$cache = JFactory::getCache('mod_menu');
			$cache->clean();
			// TODO: Reset the users acl here as well to kill off any missing bits
		}

		$app = JFactory::getApplication();
		$redirect_url = $app->getUserState('com_componentbuilder.redirect_url');
		if (empty($redirect_url))
		{
			$redirect_url = JRoute::_('index.php?option=com_componentbuilder&view=import_joomla_components', false);
		}
		else
		{
			// wipe out the user state when we're going to redirect
			$app->setUserState('com_componentbuilder.redirect_url', '');
			$app->setUserState('com_componentbuilder.message', '');
			$app->setUserState('com_componentbuilder.extension_message', '');
		}
		$this->setRedirect($redirect_url);
	}
}
