<?php
/**
 * Access point to render Fabrik component
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2015 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Helpers\Text;
use Fabrik\Helpers\StringHelper;

jimport('joomla.application.component.helper');
jimport('joomla.filesystem.file');

if (!defined('COM_FABRIK_FRONTEND'))
{
	throw new RuntimeException(Text::_('COM_FABRIK_SYSTEM_PLUGIN_NOT_ACTIVE'), 400);
}

jimport('joomla.log.log');

if (JDEBUG)
{
	// Add the logger.
	JLog::addLogger(array('text_file' => 'fabrik.log.php'));
}

require_once JPATH_COMPONENT . '/controller.php';
$app = JFactory::getApplication();
$app->set('jquery', true);
$input = $app->input;

JModelLegacy::addIncludePath(JPATH_COMPONENT . '/models');

// $$$ rob if you want to you can override any fabrik model by copying it from
// models/ to models/adaptors the copied file will overwrite (NOT extend) the original
JModelLegacy::addIncludePath(JPATH_COMPONENT . '/models/adaptors');

$controllerName = $input->getCmd('view');
$type           = '';
// Check for a plugin controller

// Call a plugin controller via the url :
// &controller=visualization.calendar

$isPlugin = false;
$cName    = $input->getCmd('controller');

if (StringHelper::strpos($cName, '.') != false)
{
	list($type, $name) = explode('.', $cName);
	$controller = StringHelper::ucfirst($name);
}
else
{
	// Its not a plugin
	// map controller to view - load if exists

	/**
	 * $$$ rob was a simple $controller = view, which was giving an error when trying to save a popup
	 * form to the calendar viz
	 * May simply be the best idea to remove main controller and have different controllers for each view
	 */

	// Hack for package
	if ($input->getCmd('view') == 'package' || $input->getCmd('view') == 'list')
	{
		$controller = $input->getCmd('view');
	}
	else
	{
		$controller = $cName === 'oai' ? $cName : $controllerName;
	}

	if (strtolower($controller) == 'list') {
		$controller = 'lizt';
	}
	$controller = StringHelper::ucfirst($controller);
}

/**
 * Create the controller if the task is in the form view.task then get
 * the specific controller for that class - otherwise use $controller to load
 * required controller class
 */
if (strpos($input->getCmd('task'), '.') !== false)
{
	$controllerTask = explode('.', $input->getCmd('task'));
	$controller     = array_shift($controllerTask);
	$task           = array_pop($controllerTask);

	// Needed to process J content plugin (form)
	$input->set('view', $controller);
	$className  = '\Fabrik\Controllers\\' . StringHelper::ucfirst($controller);
	$controller = new $className;

}
else
{
	if ($type === 'visualization')
	{
		//namespace Fabrik\Plugins\Visualization\Calendar;
		$className  = '\Fabrik\Plugins\Visualization\\' . $controller . '\Controller';
		$controller = new $className;
	}
	else
	{
		$className  = '\Fabrik\Controllers\\' . $controller;
		$controller = new $className;
	}

	$task = $input->getCmd('task');
}

if ($isPlugin)
{
	// Add in plugin view
	$controller->addViewPath(JPATH_SITE . '/plugins/fabrik_' . $type . '/' . $name . '/views');

	// Add the model path
	JModelLegacy::addIncludePath(JPATH_SITE . '/plugins/fabrik_' . $type . '/' . $name . '/models');
}

$package = $input->get('package', 'fabrik');
$app->setUserState('com_fabrik.package', $package);

$controller->execute($task);

// Redirect if set by the controller
$controller->redirect();
