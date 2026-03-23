<?php

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modZulipReminder extends DolibarrModules
{
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;
		$this->numero = 500205; 
		$this->rights_class = 'zulipreminder';
		$this->family = "other";
		$this->module_position = '95';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Sends Zulip reminders for late objects (PO, PR, CO, FA, SI, PJ)";
		$this->descriptionlong = "Automates sending Zulip messages to the responsible person when items are late in Dolibarr.";
		$this->editor_name = 'Custom Module';
		$this->editor_url = '';
		$this->version = '1.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'object_bell';

		$this->module_parts = array();

		$this->dirs = array();

		// We will create a simple setup page
		$this->config_page_url = array("setup.php@zulipreminder");
		
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array();

		$this->phpmin = array(7, 2);
		$this->need_dolibarr_version = array(10, 0);

		$this->const = array();

		if (!isModEnabled("zulipreminder")) {
			$conf->zulipreminder = new stdClass();
			$conf->zulipreminder->enabled = 0;
		}

		// Cronjobs
		$this->cronjobs = array(
			0 => array(
				'label' => 'Zulip Reminder for Late Objects',
				'jobtype' => 'method',
				'class' => '/zulipreminder/core/modules/cron/zulip_reminder.class.php',
				'objectname' => 'ZulipReminderCron',
				'method' => 'doScheduledJob',
				'parameters' => '',
				'comment' => 'Checks for late PO, PR, CO, FA, SI, PJ and sends Zulip messages.',
				'frequency' => 1,
				'unitfrequency' => 86400, // Daily
				'status' => 0,
				'test' => 'isModEnabled("zulipreminder")',
				'priority' => 50,
			),
		);

		$this->rights = array();
		$this->menu = array();
	}

	public function init($options = '')
	{
		global $conf, $langs;
		
		$sql = array();
		return $this->_init($sql, $options);
	}

	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
