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

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\Utilities\ArrayHelper;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Componentbuilder Import_language_translations Base Database Model
 */
class ComponentbuilderModelImport_language_translations extends BaseDatabaseModel
{
	// set uploading values
	protected $use_streams = false;
	protected $allow_unsafe = false;
	protected $safeFileOptions = array();
	
	/**
	 * @var object JTable object
	 */
	protected $_table = null;

	/**
	 * @var object JTable object
	 */
	protected $_url = null;

	/**
	 * Model context string.
	 *
	 * @var        string
	 */
	protected $_context = 'com_componentbuilder.import_language_translations';
	
	/**
	 * Import Settings
	 */
	protected $getType = NULL;
	protected $dataType = NULL;
	
	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @return  void
	 *
	 */
	protected function populateState()
	{
		$app = JFactory::getApplication('administrator');

		$this->setState('message', $app->getUserState('com_componentbuilder.message'));
		$app->setUserState('com_componentbuilder.message', '');

		// Recall the 'Import from Directory' path.
		$path = $app->getUserStateFromRequest($this->_context . '.import_directory', 'import_directory', $app->get('tmp_path'));
		$this->setState('import.directory', $path);
		parent::populateState();
	}
	
	/**
	 * Import an spreadsheet from either folder, url or upload.
	 *
	 * @return  boolean result of import
	 *
	 */
	public function import()
	{
		$this->setState('action', 'import');
		$app = JFactory::getApplication();
		$session = JFactory::getSession();
		$package = null;
		$continue = false;
		// get import type
		$this->getType = $app->input->getString('gettype', NULL);
		// get import type
		$this->dataType = $session->get('dataType_VDM_IMPORTINTO', NULL);

		if ($package === null)
		{
			switch ($this->getType)
			{
				case 'folder':
					// Remember the 'Import from Directory' path.
					$app->getUserStateFromRequest($this->_context . '.import_directory', 'import_directory');
					$package = $this->_getPackageFromFolder();
					break;

				case 'upload':
					$package = $this->_getPackageFromUpload();
					break;

				case 'url':
					$package = $this->_getPackageFromUrl();
					break;

				case 'continue':
					$continue 	= true;
					$package	= $session->get('package', null);
					$package	= json_decode($package, true);
					// clear session
					$session->clear('package');
					$session->clear('dataType');
					$session->clear('hasPackage');
					break;

				default:
					$app->setUserState('com_componentbuilder.message', JText::_('COM_COMPONENTBUILDER_IMPORT_NO_IMPORT_TYPE_FOUND'));

					return false;
					break;
			}
		}
		// Was the package valid?
		if (!$package || !$package['type'])
		{
			if (in_array($this->getType, array('upload', 'url')))
			{
				$this->remove($package['packagename']);
			}

			$app->setUserState('com_componentbuilder.message', JText::_('COM_COMPONENTBUILDER_IMPORT_UNABLE_TO_FIND_IMPORT_PACKAGE'));
			return false;
		}
		
		// first link data to table headers
		if(!$continue){
			$package	= json_encode($package);
			$session->set('package', $package);
			$session->set('dataType', $this->dataType);
			$session->set('hasPackage', true);
			return true;
		}
		// set the data
		$headerList = json_decode($session->get($this->dataType.'_VDM_IMPORTHEADERS', false), true);
		if (!$this->setData($package,$this->dataType,$headerList))
		{
			// There was an error importing the package
			$msg = JText::_('COM_COMPONENTBUILDER_IMPORT_ERROR');
			$back = $session->get('backto_VDM_IMPORT', NULL);
			if ($back)
			{
				$app->setUserState('com_componentbuilder.redirect_url', 'index.php?option=com_componentbuilder&view='.$back);
				$session->clear('backto_VDM_IMPORT');
			}
			$result = false;
		}
		else
		{
			// Package imported sucessfully
			$msg = JText::sprintf('COM_COMPONENTBUILDER_IMPORT_SUCCESS', $package['packagename']);
			$back = $session->get('backto_VDM_IMPORT', NULL);
			if ($back)
			{
			    $app->setUserState('com_componentbuilder.redirect_url', 'index.php?option=com_componentbuilder&view='.$back);
			    $session->clear('backto_VDM_IMPORT');
			}
			$result = true;
		}

		// Set some model state values
		$app->enqueueMessage($msg);

		// remove file after import
		$this->remove($package['packagename']);
		$session->clear($this->getType.'_VDM_IMPORTHEADERS');
		return $result;
	} 

	/**
	 * Works out an importation spreadsheet from a HTTP upload
	 *
	 * @return spreadsheet definition or false on failure
	 */
	protected function _getPackageFromUpload()
	{		
		// Get the uploaded file information
		$app = JFactory::getApplication();
		$input = $app->input;

		// Do not change the filter type 'raw'. We need this to let files containing PHP code to upload. See JInputFiles::get.
		$userfile = $input->files->get('import_package', null, 'raw');
		
		// Make sure that file uploads are enabled in php
		if (!(bool) ini_get('file_uploads'))
		{
			$app->enqueueMessage(JText::_('COM_COMPONENTBUILDER_IMPORT_MSG_WARNIMPORTFILE'), 'warning');
			return false;
		}

		// If there is no uploaded file, we have a problem...
		if (!is_array($userfile))
		{
			$app->enqueueMessage(JText::_('COM_COMPONENTBUILDER_IMPORT_MSG_NO_FILE_SELECTED'), 'warning');
			return false;
		}

		// Check if there was a problem uploading the file.
		if ($userfile['error'] || $userfile['size'] < 1)
		{
			$app->enqueueMessage(JText::_('COM_COMPONENTBUILDER_IMPORT_MSG_WARNIMPORTUPLOADERROR'), 'warning');
			return false;
		}

		// Build the appropriate paths
		$config = JFactory::getConfig();
		$tmp_dest = $config->get('tmp_path') . '/' . $userfile['name'];
		$tmp_src = $userfile['tmp_name'];

		// Move uploaded file
		$p_file = File::upload($tmp_src, $tmp_dest, $this->use_streams, $this->allow_unsafe, $this->safeFileOptions);

		// Was the package downloaded?
		if (!$p_file)
		{
			$session = JFactory::getSession();
			$session->clear('package');
			$session->clear('dataType');
			$session->clear('hasPackage');
			// was not uploaded
			return false;
		}

		// check that this is a valid spreadsheet
		$package = $this->check($userfile['name']);

		return $package;
	}

	/**
	 * Import an spreadsheet from a directory
	 *
	 * @return  array  Spreadsheet details or false on failure
	 *
	 */
	protected function _getPackageFromFolder()
	{
		$app = JFactory::getApplication();
		$input = $app->input;

		// Get the path to the package to import
		$p_dir = $input->getString('import_directory');
		$p_dir = JPath::clean($p_dir);
		// Did you give us a valid path?
		if (!file_exists($p_dir))
		{
			$app->enqueueMessage(JText::_('COM_COMPONENTBUILDER_IMPORT_MSG_PLEASE_ENTER_A_PACKAGE_DIRECTORY'), 'warning');
			return false;
		}

		// Detect the package type
		$type = $this->getType;

		// Did you give us a valid package?
		if (!$type)
		{
			$app->enqueueMessage(JText::_('COM_COMPONENTBUILDER_IMPORT_MSG_PATH_DOES_NOT_HAVE_A_VALID_PACKAGE'), 'warning');
		}
		
		// check the extention
		if(!$this->checkExtension($p_dir))
		{
			// set error message
			$app->enqueueMessage(JText::_('COM_COMPONENTBUILDER_IMPORT_MSG_DOES_NOT_HAVE_A_VALID_FILE_TYPE'), 'warning');
			return false;
		}
		
		$package['packagename'] = null;
		$package['dir'] = $p_dir;
		$package['type'] = $type;

		return $package;
	}

	/**
	 * Import an spreadsheet from a URL
	 *
	 * @return  Package details or false on failure
	 *
	 */
	protected function _getPackageFromUrl()
	{
		$app = JFactory::getApplication();
		$input = $app->input;

		// Get the URL of the package to import
		$url = $input->getString('import_url');

		// Did you give us a URL?
		if (!$url)
		{
			$app->enqueueMessage(JText::_('COM_COMPONENTBUILDER_IMPORT_MSG_ENTER_A_URL'), 'warning');
			return false;
		}

		// Download the package at the URL given
		$p_file = JInstallerHelper::downloadPackage($url);

		// Was the package downloaded?
		if (!$p_file)
		{
			$app->enqueueMessage(JText::_('COM_COMPONENTBUILDER_IMPORT_MSG_INVALID_URL'), 'warning');
			return false;
		}

		// check that this is a valid spreadsheet
		$package = $this->check($p_file);

		return $package;
	}
	
	/**
	 * Check a file and verifies it as a spreadsheet file
	 * Supports .csv .xlsx .xls and .ods
	 *
	 * @param   string  $p_filename  The uploaded package filename or import directory
	 *
	 * @return  array  of elements
	 *
	 */
	protected function check($archivename)
	{
		$app = JFactory::getApplication();
		// Clean the name
		$archivename = JPath::clean($archivename);
		
		// check the extention
		if(!$this->checkExtension($archivename))
		{
			// Cleanup the import files
			$this->remove($archivename);
			$app->enqueueMessage(JText::_('COM_COMPONENTBUILDER_IMPORT_MSG_DOES_NOT_HAVE_A_VALID_FILE_TYPE'), 'warning');
			return false;
		}
		
		$config = JFactory::getConfig();
		// set Package Name
		$check['packagename'] = $archivename;
		
		// set directory
		$check['dir'] = $config->get('tmp_path'). '/' .$archivename;
		
		// set type
		$check['type'] = $this->getType;
		
		return $check;
	}
	
	/**
	 * Check the extension
	 *
	 * @param   string  $file    Name of the uploaded file
	 *
	 * @return  boolean  True on success
	 *
	 */
	protected function checkExtension($file)
	{
		// check the extention
		switch(strtolower(pathinfo($file, PATHINFO_EXTENSION)))
		{
			case 'xls':
			case 'ods':
			case 'csv':
			return true;
			break;
		}
		return false;
	}

	/**
	 * Clean up temporary uploaded spreadsheet
	 *
	 * @param   string  $package    Name of the uploaded spreadsheet file
	 *
	 * @return  boolean  True on success
	 *
	 */
	protected function remove($package)
	{
		jimport('joomla.filesystem.file');
		
		$config = JFactory::getConfig();
		$package = $config->get('tmp_path'). '/' .$package;

		// Is the package file a valid file?
		if (is_file($package))
		{
			File::delete($package);
		}
		elseif (is_file(JPath::clean($package)))
		{
			// It might also be just a base filename
			File::delete(JPath::clean($package));
		}
	}
	
	/**
	* Set the data from the spreadsheet to the database
	*
	* @param string  $package Paths to the uploaded package file
	*
	* @return  boolean false on failure
	*
	**/
	protected function setData($package,$table,$target_headers)
	{
		if (ComponentbuilderHelper::checkArray($target_headers))
		{
			// make sure the file is loaded
			ComponentbuilderHelper::composerAutoload('phpspreadsheet');
			$jinput = JFactory::getApplication()->input;
			foreach($target_headers as $header)
			{
				if (($column = $jinput->getString($header, false)) !== false ||
					($column = $jinput->getString(strtolower($header), false)) !== false)
				{
					$data['target_headers'][$header] = $column;
				}
				else
				{
					$data['target_headers'][$header] = null;
				}
			}
			// set the data
			if(isset($package['dir']))
			{
				$inputFileType = IOFactory::identify($package['dir']);
				$excelReader = IOFactory::createReader($inputFileType);
				$excelReader->setReadDataOnly(true);
				$excelObj = $excelReader->load($package['dir']);
				$data['array'] = $excelObj->getActiveSheet()->toArray(null, true,true,true);
				$excelObj->disconnectWorksheets();
				unset($excelObj);
				return $this->save($data, $table);
			}
		}
		return false;
	}
	
	/**
	* Save the data from the file to the database
	*
	* @param string  $package Paths to the uploaded package file
	*
	* @return  boolean false on failure
	*
	**/
	protected function save($data, $table)
	{
		// import the data if there is any
		if(ComponentbuilderHelper::checkArray($data['array']))
		{
			// get user object
			$user = JFactory::getUser();
			// remove header if it has headers
			$id_key = $data['target_headers']['id'];
			if (isset($data['target_headers']['Source']))
			{
				$source_key = $data['target_headers']['Source'];
			}
			elseif (isset($data['target_headers']['English']))
			{
				$source_key = $data['target_headers']['English'];
			}
			else
			{
				$source_key = null;
			}
			// get the first array set
			$firstSet = reset($data['array']);
			// check if first array is a header array and remove if true
			if($firstSet[$id_key] == 'id' || $firstSet[$source_key] == 'Source' || $firstSet[$source_key] == 'English')
			{
				array_shift($data['array']);
			}
			// make sure there is still values in array and that it was not only headers
			if(ComponentbuilderHelper::checkArray($data['array']) && $user->authorise($table.'.import', 'com_componentbuilder') && $user->authorise('core.import', 'com_componentbuilder'))
			{
				// set target.
				$target = array_flip($data['target_headers']);
				// Get a db connection.
				$db = JFactory::getDbo();
				// set some defaults
				$todayDate = JFactory::getDate()->toSql();
				// get global action permissions
				$canDo = ComponentbuilderHelper::getActions($table);
				$canEdit = $canDo->get('core.edit');
				$canCreate = $canDo->get('core.create');
				$canState = $canDo->get('core.edit.state');
				// get languages
				$languages = ComponentbuilderHelper::getVars('language', 1, 'published', 'langtag');
				// process the data
				foreach($data['array'] as $row)
				{
					$found = false;
					$has_id = false;
					// check that we have a string or a number<-(which is weird... but happens at times)
					if ($canEdit && isset($row[$source_key]) && (ComponentbuilderHelper::checkString($row[$source_key]) || is_numeric($row[$source_key])))
					{
						// raw items import & update!
						$query = $db->getQuery(true);
						$query->select($db->quoteName(array('id', 'version', 'translation')));
						$query->from($db->quoteName('#__componentbuilder_'.$table));
						if (isset($row[$id_key]) && is_numeric($row[$id_key]) && $row[$id_key] > 0)
						{
							$query->where($db->quoteName('id') . ' = '. $db->quote($row[$id_key]));
							$has_id = true;
						}
						$query->where($db->quoteName('source') . ' = '. $db->quote($row[$source_key]));
						// Reset the query using our newly populated query object.
						$db->setQuery($query);
						$db->execute();
						$found = $db->getNumRows();
						// check one more time, just with source
						if(!$found && $has_id)
						{
							// raw items import & update!
							$query = $db->getQuery(true);
							$query->select($db->quoteName(array('id', 'version', 'translation')));
							$query->from($db->quoteName('#__componentbuilder_'.$table));
							$query->where($db->quoteName('source') . ' = '. $db->quote($row[$source_key]));
							// Reset the query using our newly populated query object.
							$db->setQuery($query);
							$db->execute();
							$found = $db->getNumRows();
						}
					}
					// check if we found the string
					if($found && $canEdit)
					{
						// update item
						$item = $db->loadObject();
						$id = $item->id;
						// load previous translation strings
						if (ComponentbuilderHelper::checkJson($item->translation))
						{
							$translations = json_decode($item->translation, true);
							$counter = count($translations) + 2;
							$pre = true;
						}
						else
						{
							$translations = array();
							$counter = 0;
							$pre = false;
						}
						// reset all buckets
						$query = $db->getQuery(true);
						$fields = array();
						// Fields to update.
						foreach($row as $key => $cell)
						{
							// ignore column
							if ('IGNORE' === $target[$key] || 'modified_by' === $target[$key] || 'modified' === $target[$key] || 'created_by' === $target[$key] || 'created' === $target[$key] || 'source' === strtolower($target[$key]) || 'english' === strtolower($target[$key]))
							{
								continue;
							}
							// update version
							if ('version' == $target[$key])
							{
								$cell = (int) $item->version + 1;
							}
							// verify publish authority
							if ('published' == $target[$key] && !$canState)
							{
								continue;
							}
							// set to translations
							if(in_array($target[$key], $languages))
							{
								// only add if it has a string
								if (ComponentbuilderHelper::checkString($cell))
								{
									// remove if was set previously
									if ($pre)
									{
										$tag = $target[$key];
										$translations = array_filter(
											$translations,
											function($trans) use($tag) {
												if ($trans['language'] !== $tag)
												{
													return true;
												}
												return false;
											});
									}
									$translations['translation'.$counter] = array('language' => $target[$key], 'translation' => $cell);
									$counter++;
								}
							}
							// set to update array
							elseif(in_array($key, $data['target_headers']) && is_numeric($cell))
							{
								$fields[] = $db->quoteName($target[$key]) . ' = ' . $cell;
							}
							elseif(in_array($key, $data['target_headers']) && is_string($cell))
							{
								$fields[] = $db->quoteName($target[$key]) . ' = ' . $db->quote($cell);
							}
							elseif(in_array($key, $data['target_headers']) && is_null($cell))
							{
								// if import data is null then set empty
								$fields[] = $db->quoteName($target[$key]) . " = ''";
							}
						}
						// set the translation
						if (ComponentbuilderHelper::checkArray($translations))
						{
							$fields[] = $db->quoteName('translation') . ' = ' . $db->quote(json_encode($translations, JSON_FORCE_OBJECT));
							// load the defaults
							$fields[]	= $db->quoteName('modified_by') . ' = ' . (int) $user->id;
							$fields[]	= $db->quoteName('modified') . ' = ' . $db->quote($todayDate);
							// Conditions for which records should be updated.
							$conditions = array(
								$db->quoteName('id') . ' = ' . $id
							);
							// update the local translation
							$query->update($db->quoteName('#__componentbuilder_'.$table))->set($fields)->where($conditions);
							$db->setQuery($query);
							$db->execute();
						}
					}
					elseif (!$found && $canCreate)
					{
						$translations = array();
						$counter = 0;
						// reset all buckets
						$query = $db->getQuery(true);
						$values = array();
						$columns = array();
						// Fields to update.
						foreach($row as $key => $cell)
						{
							// ignore column
							if ('IGNORE' === $target[$key] || 'modified_by' === $target[$key] || 'modified' === $target[$key] || 'created_by' === $target[$key] || 'created' === $target[$key] || 'version' == $target[$key])
							{
								continue;
							}
							// verify publish authority
							if ('published' == $target[$key] && !$canState)
							{
								continue;
							}
							// set to translations
							if(in_array($target[$key], $languages))
							{
								// only add if it has a string
								if (ComponentbuilderHelper::checkString($cell))
								{
									$translations['translation'.$counter] = array('language' => $target[$key], 'translation' => $cell);
									$counter++;
								}
							}
							// load the source
							elseif ('source' === strtolower($target[$key]) || 'english' === strtolower($target[$key]))
							{
								$columns[] = 'source';
								$values[] = $db->quote($cell);
							}
							// set to update array
							elseif(in_array($key, $data['target_headers']) && is_numeric($cell))
							{
								$columns[] = $target[$key];
								$values[] = $cell;
							}
							elseif(in_array($key, $data['target_headers']) && is_string($cell))
							{
								$columns[] = $target[$key];
								$values[] = $db->quote($cell);
							}
							elseif(in_array($key, $data['target_headers']) && is_null($cell))
							{
								// if import data is null then set empty
								$columns[] = $target[$key];
								$values[] = '';
							}
						}
						// set the translation
						if (ComponentbuilderHelper::checkArray($translations))
						{
							$columns[] = 'translation';
							$values[] = $db->quote(json_encode($translations, JSON_FORCE_OBJECT));
						}
						// check if we have values
						if (ComponentbuilderHelper::checkArray($values))
						{
							// load the defaults
							$columns[] = 'created_by';
							$values[] = (int) $user->id;
							$columns[] = 'created';
							$values[] = $db->quote($todayDate);
							$columns[] = 'version';
							$values[] = 1;
							// update the local translation
							$query->insert($db->quoteName('#__componentbuilder_'.$table))->columns($db->quoteName($columns))->values(implode(',', $values));
							$db->setQuery($query);
							$db->execute();
						}
					}
				}
				return true;
			}
		}
		return false;
	}

	protected function getAlias($name,$type = false)
	{
		// sanitize the name to an alias
		if (JFactory::getConfig()->get('unicodeslugs') == 1)
		{
			$alias = JFilterOutput::stringURLUnicodeSlug($name);
		}
		else
		{
			$alias = JFilterOutput::stringURLSafe($name);
		}
		// must be a uniqe alias
		if ($type)
		{
			return $this->getUniqe($alias,'alias',$type);
		}
		return $alias;
	}
	
	/**
	 * Method to generate a uniqe value.
	 *
	 * @param   string  $field name.
	 * @param   string  $value data.
	 * @param   string  $type table.
	 *
	 * @return  string  New value.
	 */
	protected function getUniqe($value,$field,$type)
	{
		// insure the filed is always uniqe
		while (isset($this->uniqeValueArray[$type][$field][$value]))
		{
			$value = JString::increment($value, 'dash');
		}
		$this->uniqeValueArray[$type][$field][$value] = $value;
		return $value;
	}
	
	protected function getAliasesUsed($table)
	{
		// Get a db connection.
		$db = JFactory::getDbo();
		// first we check if there is a alias column
		$columns = $db->getTableColumns('#__componentbuilder_'.$table);
		if(isset($columns['alias'])){
			// Create a new query object.
			$query = $db->getQuery(true);
			$query->select($db->quoteName(array('alias')));
			$query->from($db->quoteName('#__componentbuilder_'.$table));
			$db->setQuery($query);
			$db->execute();
			if ($db->getNumRows())
			{
				$aliases = $db->loadColumn();
				foreach($aliases as $alias)
				{
					$this->uniqeValueArray[$table]['alias'][$alias] = $alias;
				}
			}
			return true;
		}
		return false;
	}
}
