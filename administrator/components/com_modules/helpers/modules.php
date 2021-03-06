<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_modules
 *
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Modules component helper.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_modules
 * @since       1.6
 */
abstract class ModulesHelper
{
	/**
	 * Configure the Linkbar.
	 *
	 * @param   string  $vName  The name of the active view.
	 */
	public static function addSubmenu($vName)
	{
		// Not used in this component.
	}

	/**
	 * Gets a list of the actions that can be performed.
	 *
	 * @return	JObject
	 */
	public static function getActions()
	{
		$user	= JFactory::getUser();
		$result	= new JObject;

		$actions = JAccess::getActions('com_modules');

		foreach ($actions as $action)
		{
			$result->set($action->name, $user->authorise($action->name, 'com_modules'));
		}

		return $result;
	}

	/**
	 * Get a list of filter options for the state of a module.
	 *
	 * @return	array	An array of JHtmlOption elements.
	 */
	public static function getStateOptions()
	{
		// Build the filter options.
		$options	= array();
		$options[]	= JHtml::_('select.option',	'1',	JText::_('JPUBLISHED'));
		$options[]	= JHtml::_('select.option',	'0',	JText::_('JUNPUBLISHED'));
		$options[]	= JHtml::_('select.option',	'-2',	JText::_('JTRASHED'));
		return $options;
	}

	/**
	 * Get a list of filter options for the application clients.
	 *
	 * @return	array	An array of JHtmlOption elements.
	 */
	public static function getClientOptions()
	{
		// Build the filter options.
		$options	= array();
		$options[]	= JHtml::_('select.option', '0', JText::_('JSITE'));
		$options[]	= JHtml::_('select.option', '1', JText::_('JADMINISTRATOR'));
		return $options;
	}

	/**
	 * Get a list of modules positions
	 *
	 * @param   integer  $clientId  Client ID
	 *
	 * @return  array  A list of positions
	 */
	public static function getPositions($clientId, $editPositions = false)
	{
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);

		$query->select('DISTINCT(position)');
		$query->from('#__modules');
		$query->where($db->quoteName('client_id') . ' = ' . (int) $clientId);
		$query->order('position');

		$db->setQuery($query);

		try
		{
			$positions = $db->loadColumn();
			$positions = is_array($positions) ? $positions : array();
		}
		catch (RuntimeException $e)
		{
			JError::raiseWarning(500, $e->getMessage());
			return;
		}

		// Build the list
		$options = array();
		foreach ($positions as $position)
		{
			if (!$position && !$editPositions)
			{
				$options[]	= JHtml::_('select.option', 'none', ':: ' . JText::_('JNONE') . ' ::');

			}
			else
			{
				$options[]	= JHtml::_('select.option', $position, $position);
			}
		}
		return $options;
	}

	/**
	 * Return a list of templates
	 *
	 * @param   integer  $clientId  Client ID
	 * @param   string   $state     State
	 * @param   string   $template  Template name
	 *
	 * @return  array  List of templates
	 */
	public static function getTemplates($clientId = 0, $state = '', $template = '')
	{
		$db = JFactory::getDbo();

		// Get the database object and a new query object.
		$query	= $db->getQuery(true);

		// Build the query.
		$query->select('element, name, enabled');
		$query->from('#__extensions');
		$query->where('client_id = ' . (int) $clientId);
		$query->where('type = ' . $db->quote('template'));
		if ($state != '')
		{
			$query->where('enabled = ' . $db->quote($state));
		}

		if ($template != '')
		{
			$query->where('element = ' . $db->quote($template));
		}

		// Set the query and load the templates.
		$db->setQuery($query);
		$templates = $db->loadObjectList('element');
		return $templates;
	}

	/**
	 * Get a list of the unique modules installed in the client application.
	 *
	 * @param   int  $clientId  The client id.
	 *
	 * @return  array  Array of unique modules
	 */
	public static function getModules($clientId)
	{
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);

		$query->select('element AS value, name AS text');
		$query->from('#__extensions as e');
		$query->where('e.client_id = ' . (int) $clientId);
		$query->where('type = ' . $db->quote('module'));
		$query->leftJoin('#__modules as m ON m.module=e.element AND m.client_id=e.client_id');
		$query->where('m.module IS NOT NULL');
		$query->group('element,name');

		$db->setQuery($query);
		$modules = $db->loadObjectList();
		$lang = JFactory::getLanguage();

		foreach ($modules as $i => $module)
		{
			$extension = $module->value;
			$path = $clientId ? JPATH_ADMINISTRATOR : JPATH_SITE;
			$source = $path . "/modules/$extension";
				$lang->load("$extension.sys", $path, null, false, false)
			||	$lang->load("$extension.sys", $source, null, false, false)
			||	$lang->load("$extension.sys", $path, $lang->getDefault(), false, false)
			||	$lang->load("$extension.sys", $source, $lang->getDefault(), false, false);
			$modules[$i]->text = JText::_($module->text);
		}
		JArrayHelper::sortObjects($modules, 'text', 1, true, $lang->getLocale());
		return $modules;
	}

	/**
	 * Get a list of the assignment options for modules to menus.
	 *
	 * @param   int  $clientId  The client id.
	 *
	 * @return  array
	 */
	public static function getAssignmentOptions($clientId)
	{
		$options = array();
		$options[] = JHtml::_('select.option', '0', 'COM_MODULES_OPTION_MENU_ALL');
		$options[] = JHtml::_('select.option', '-', 'COM_MODULES_OPTION_MENU_NONE');

		if ($clientId == 0)
		{
			$options[] = JHtml::_('select.option', '1', 'COM_MODULES_OPTION_MENU_INCLUDE');
			$options[] = JHtml::_('select.option', '-1', 'COM_MODULES_OPTION_MENU_EXCLUDE');
		}

		return $options;
	}

	/**
	 * Return a translated module position name
	 *
	 * @param   string  $template  Template name
	 * @param   string  $position  Position name
	 *
	 * @return  string  Return a translated position name
	 *
	 * @since   3.0
	 */
	public static function getTranslatedModulePosition($clientId, $template, $position)
	{
		// Template translation
		$lang = JFactory::getLanguage();
		$path = $clientId ? JPATH_ADMINISTRATOR : JPATH_SITE;

		$lang->load('tpl_'.$template.'.sys', $path, null, false, false)
		||	$lang->load('tpl_'.$template.'.sys', $path.'/templates/'.$template, null, false, false)
		||	$lang->load('tpl_'.$template.'.sys', $path, $lang->getDefault(), false, false)
		||	$lang->load('tpl_'.$template.'.sys', $path.'/templates/'.$template, $lang->getDefault(), false, false);

		$langKey = strtoupper('TPL_' . $template . '_POSITION_' . $position);
		$text = JText::_($langKey);

		// Avoid untranslated strings
		if (!self::isTranslatedText($langKey, $text))
		{
			// Modules component translation
			$langKey = strtoupper('COM_MODULES_POSITION_' . $position);
			$text = JText::_($langKey);

			// Avoid untranslated strings
			if (!self::isTranslatedText($langKey, $text))
			{
				// Try to humanize the position name
				$text = ucfirst(preg_replace('/^' . $template . '\-/', '', $position));
				$text = ucwords(str_replace(array('-', '_'), ' ', $text));
			}
		}

		return $text;
	}

	/**
	 * Check if the string was translated
	 *
	 * @param   string  $langKey  Language file text key
	 * @param   string  $text     The "translated" text to be checked
	 *
	 * @return  boolean  Return true for translated text
	 *
	 * @since   3.0
	 */
	public static function isTranslatedText($langKey, $text)
	{
		return $text !== $langKey;
	}

	/**
	 * Create and return a new Option
	 *
	 * @param   string  $value  The option value [optional]
	 * @param   string  $text   The option text [optional]
	 *
	 * @return  object  The option as an object (stdClass instance)
	 *
	 * @since   3.0
	 */
	public static function createOption($value = '', $text = '')
	{
		if (empty($text))
		{
			$text = $value;
		}

		$option = new stdClass;
		$option->value = $value;
		$option->text  = $text;

		return $option;
	}

	/**
	 * Create and return a new Option Group
	 *
	 * @param   string  $label    Value and label for group [optional]
	 * @param   array   $options  Array of options to insert into group [optional]
	 *
	 * @return  array  Return the new group as an array
	 *
	 * @since   3.0
	 */
	public static function createOptionGroup($label = '', $options = array())
	{
		$group = array();
		$group['value'] = $label;
		$group['text']  = $label;
		$group['items'] = $options;

		return $group;
	}
}
