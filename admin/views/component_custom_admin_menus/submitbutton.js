/**
 * @package    Joomla.Component.Builder
 *
 * @created    30th April, 2015
 * @author     Llewellyn van der Merwe <https://dev.vdm.io>
 * @git        Joomla Component Builder <https://git.vdm.dev/joomla/Component-Builder>
 * @copyright  Copyright (C) 2015 Vast Development Method. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

Joomla.submitbutton = function(task)
{
	if (task == ''){
		return false;
	} else { 
		var action = task.split('.');
		if (action[1] == 'cancel' || action[1] == 'close' || document.formvalidator.isValid(document.getElementById("adminForm"))){
			Joomla.submitform(task, document.getElementById("adminForm"));
			return true;
		} else {
			alert(Joomla.JText._('component_custom_admin_menus, some values are not acceptable.','Some values are unacceptable'));
			return false;
		}
	}
}