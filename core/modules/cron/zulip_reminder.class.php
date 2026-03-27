<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/zulipreminder/class/zulip.class.php';

class ZulipReminderCron extends CommonObject
{
	public $element = 'zulipreminder';

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function doScheduledJob()
	{
		global $conf, $langs;
		
		$error = 0;
		$this->output = '';
		$this->error = '';

		dol_syslog(__METHOD__." start", LOG_INFO);
		
		$client = new ZulipClient();
		
		$queries = array(
			// Purchase Orders (PO)
			'CommandeFournisseur' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, s.nom as client_name FROM ".MAIN_DB_PREFIX."commande_fournisseur as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut IN (1,2,3,4) AND c.date_livraison < NOW()",
				'element' => 'order_supplier',
				'stream_var' => 'ZULIP_STREAM_PO',
				'url_path' => '/fourn/commande/card.php?id=',
				'actions' => array(
					'Mark as delivered' => '/fourn/commande/card.php?id=%s&action=classifybilled',
					'Mark as abandoned' => '/fourn/commande/card.php?id=%s&action=cancel',
					'Extend expiry date' => '/fourn/commande/card.php?id=%s&action=edit'
				)
			),
			// Commercial Proposals (PR)
			'Propal' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, s.nom as client_name FROM ".MAIN_DB_PREFIX."propal as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut = 1 AND c.fin_validite < NOW()",
				'element' => 'propal',
				'stream_var' => 'ZULIP_STREAM_PR',
				'url_path' => '/comm/propal/card.php?id=',
				'actions' => array(
					'Extend expiration date' => '/comm/propal/card.php?id=%s&action=edit',
					'Mark as done/completed' => '/comm/propal/card.php?id=%s&action=close',
					'Mark as abandoned/rejected' => '/comm/propal/card.php?id=%s&action=close'
				)
			),
			// Customer Orders (CO)
			'Commande' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, s.nom as client_name FROM ".MAIN_DB_PREFIX."commande as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut IN (1,2) AND c.date_livraison < NOW()",
				'element' => 'commande',
				'stream_var' => 'ZULIP_STREAM_CO',
				'url_path' => '/commande/card.php?id=',
				'actions' => array(
					'Mark as done/solved' => '/commande/card.php?id=%s&action=classify',
					'Cancel/abandon' => '/commande/card.php?id=%s&action=cancel',
					'Extend expiry date' => '/commande/card.php?id=%s&action=edit'
				)
			),
			// Customer Invoices (FA)
			'Facture' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, s.nom as client_name FROM ".MAIN_DB_PREFIX."facture as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut = 1 AND c.paye = 0 AND c.date_lim_reglement < NOW()",
				'element' => 'facture',
				'stream_var' => 'ZULIP_STREAM_FA',
				'url_path' => '/compta/facture/card.php?id=',
				'actions' => array(
					'Send reminder email' => '/compta/facture/card.php?id=%s&action=presend',
					'Extend expiry date' => '/compta/facture/card.php?id=%s&action=edit',
					'Cancel FA' => '/compta/facture/card.php?id=%s&action=cancel'
				)
			),
			// Supplier Invoices (SI)
			'FactureFournisseur' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, s.nom as client_name FROM ".MAIN_DB_PREFIX."facture_fourn as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut = 1 AND c.paye = 0 AND c.date_lim_reglement < NOW()",
				'element' => 'invoice_supplier',
				'stream_var' => 'ZULIP_STREAM_SI',
				'url_path' => '/fourn/facture/card.php?id=',
				'actions' => array(
					'Reject and inform supplier' => '/fourn/facture/card.php?id=%s&action=cancel',
					'Pay' => '/fourn/facture/payment.php?facid=%s&action=create',
					'Modify the date' => '/fourn/facture/card.php?id=%s&action=edit'
				)
			),
			// Projects (PJ)
			'Project' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_creat as fk_user_author, s.nom as client_name FROM ".MAIN_DB_PREFIX."projet as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut = 1 AND c.datee < NOW()",
				'element' => 'project',
				'stream_var' => 'ZULIP_STREAM_PJ',
				'url_path' => '/projet/card.php?id=',
				'actions' => array(
					'Extend expiry date' => '/projet/card.php?id=%s&action=edit',
					'Mark as won/solved' => '/projet/card.php?id=%s&action=close',
					'Mark as lost/canceled' => '/projet/card.php?id=%s&action=cancel',
					'Cloturer le PJ' => '/projet/card.php?id=%s&action=close',
					'Send' => '/projet/card.php?id=%s&action=presend'
				)
			)
		);

		$messages_sent = 0;
		$test_mode_email = 89; // send to my DMs for testing. Set to empty '' for production.
		$test_messages_limit = 5; 
		$test_messages_count = 0;

		$user_reminders = array();

		// 1. Collect all late objects per user
		foreach ($queries as $type => $data) {
			$resql = $this->db->query($data['sql']);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$user_ids = array();
					
					// 1. Add creator
					if ($obj->fk_user_author > 0) {
						$user_ids[] = $obj->fk_user_author;
					}
					
					// 2. Add assigned internal contacts
					$sql_contacts = "SELECT ec.fk_socpeople as user_id ";
					$sql_contacts.= "FROM ".MAIN_DB_PREFIX."element_contact as ec ";
					$sql_contacts.= "JOIN ".MAIN_DB_PREFIX."c_type_contact as tc ON ec.fk_c_type_contact = tc.rowid ";
					$sql_contacts.= "WHERE ec.element_id = ".((int)$obj->rowid)." AND tc.element = '".$this->db->escape($data['element'])."' AND tc.source = 'internal'";
					
					$res_contacts = $this->db->query($sql_contacts);
					if ($res_contacts) {
						while ($contact = $this->db->fetch_object($res_contacts)) {
							if ($contact->user_id > 0) {
								$user_ids[] = $contact->user_id;
							}
						}
					}
					
					// Unique Users
					$user_ids = array_unique($user_ids);
					
					if (empty($user_ids)) continue;
					
					$client_suffix = (!empty($obj->client_name)) ? " (Client: " . $obj->client_name . ")" : "";
					$obj_url = constant('DOL_MAIN_URL_ROOT') . $data['url_path'] . $obj->rowid;
					
					$action_links = array();
					if (!empty($data['actions'])) {
						foreach ($data['actions'] as $act_name => $act_url_format) {
							$act_full_url = constant('DOL_MAIN_URL_ROOT') . sprintf($act_url_format, $obj->rowid);
							$action_links[] = "[" . $act_name . "](" . $act_full_url . ")";
						}
					}
					$actions_text = !empty($action_links) ? " | " . implode(" | ", $action_links) : "";

					$obj_item = "- **" . $type . "** " . $obj->ref . $client_suffix . ": [View](" . $obj_url . ")" . $actions_text;

					foreach ($user_ids as $uid) {
						if (!isset($user_reminders[$uid])) {
							$user_reminders[$uid] = array();
						}
						$user_reminders[$uid][] = $obj_item;
					}
				}
				$this->db->free($resql);
			} else {
				dol_syslog('ZulipReminderCron: Error in query for ' . $type . ' - ' . $this->db->lasterror(), LOG_ERR);
				$error++;
			}
		}

		// 2. Send summarized DMs to each user
		$explanation = "# Late Objects Reminder\n"
			. "You will find below the list of late (PR, PJ, ...) that are linked to you.\n\n"
			. "**What should I do with the late objects?**\n"
			. "- Check that everything is fine regarding the object\n"
			. "- If applicable, change the expiry date for the object\n\n"
			. "**Your late objects:**\n";

		foreach ($user_reminders as $uid => $objects) {
			$user_email = $this->getUserEmail($uid);
			if (empty($user_email)) continue;
			
			$content = $explanation . implode("\n", $objects);
			
			// If testing mode is enabled, route to test email
			$target_email = !empty($test_mode_email) ? $test_mode_email : $user_email;
			
			if (!empty($test_mode_email)) {
				if ($test_messages_count >= $test_messages_limit) {
					break; // Stop if we hit the limit in test mode
				}
				$content = "*(TEST MODE: Originally intended for " . $user_email . ")*\n\n" . $content;
			}
			
			// Send the message as direct message
			if ($client->sendPrivateMessage($target_email, $content)) {
				$messages_sent++;
				if (!empty($test_mode_email)) {
					$test_messages_count++;
				}
			} else {
				$this->error .= "Failed to send private message to $target_email. ";
				$error++;
			}
		}

		$this->output = "Job executed. Found " . count($user_reminders) . " users with late objects. " . $error . " API errors. " . $messages_sent . " sent.";
		dol_syslog(__METHOD__." end. " . $this->output, LOG_INFO);

		return $error;
	}

	private function getUserEmail($user_id)
	{
		$sql = "SELECT email FROM ".MAIN_DB_PREFIX."user WHERE rowid = ".((int)$user_id)." AND statut = 1";
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($obj = $this->db->fetch_object($resql)) {
				return $obj->email;
			}
		}
		return "";
	}
}
