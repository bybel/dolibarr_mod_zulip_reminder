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
			// Customer Invoices (FA)
			'Facture' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, c.total_ht, s.nom as client_name FROM ".MAIN_DB_PREFIX."facture as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut = 1 AND c.paye = 0 AND c.date_lim_reglement < NOW()",
				'element' => 'facture',
				'stream_var' => 'ZULIP_STREAM_FA',
				'url_path' => '/compta/facture/card.php?id=',
				'actions' => array(
					'Dept compensation' => '/compta/facture/card.php?id=%s',
					'modify' => '/compta/facture/card.php?id=%s&action=edit',
					'send email' => '/compta/facture/card.php?id=%s&action=presend',
					'enter payment' => '/compta/paiement/card.php?facid=%s&action=create',
					'Classify "Abandoned"' => '/compta/facture/card.php?id=%s&action=canceledit',
					'create credit note' => '/compta/facture/card.php?action=create&facid=%s&type=2',
					'Clone' => '/compta/facture/card.php?id=%s&action=clone',
					'delete' => '/compta/facture/card.php?id=%s&action=delete'
				)
			),
			// Commercial Proposals (PR)
			'Propal' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, c.total_ht, s.nom as client_name FROM ".MAIN_DB_PREFIX."propal as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut = 1 AND c.fin_validite < NOW()",
				'element' => 'propal',
				'stream_var' => 'ZULIP_STREAM_PR',
				'url_path' => '/comm/propal/card.php?id=',
				'actions' => array(
					'Modify' => '/comm/propal/card.php?id=%s&action=edit',
					'send email' => '/comm/propal/card.php?id=%s&action=presend',
					'set Accepted/refused' => '/comm/propal/card.php?id=%s&action=close',
					'cancel' => '/comm/propal/card.php?id=%s&action=cancel',
					'clone' => '/comm/propal/card.php?id=%s&action=clone',
					'Delete' => '/comm/propal/card.php?id=%s&action=delete'
				)
			),
			// Purchase Orders (PO)
			'CommandeFournisseur' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, c.total_ht, s.nom as client_name FROM ".MAIN_DB_PREFIX."commande_fournisseur as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut IN (1,2,3,4) AND c.date_livraison < NOW()",
				'element' => 'order_supplier',
				'stream_var' => 'ZULIP_STREAM_PO',
				'url_path' => '/fourn/commande/card.php?id=',
				'actions' => array(
					'Scan invoice' => '/fourn/commande/card.php?id=%s',
					'send email' => '/fourn/commande/card.php?id=%s&action=presend',
					'Re open' => '/fourn/commande/card.php?id=%s&action=reopen',
					'classify received' => '/fourn/commande/card.php?id=%s&action=classifyreceived',
					'classify unfilled' => '/fourn/commande/card.php?id=%s&action=cancel',
					'Clone' => '/fourn/commande/card.php?id=%s&action=clone',
					'delete' => '/fourn/commande/card.php?id=%s&action=delete'
				)
			),
			// Customer Orders (CO)
			'Commande' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, c.total_ht, s.nom as client_name FROM ".MAIN_DB_PREFIX."commande as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut IN (1,2) AND c.date_livraison < NOW()",
				'element' => 'commande',
				'stream_var' => 'ZULIP_STREAM_CO',
				'url_path' => '/commande/card.php?id=',
				'actions' => array(
					'send email' => '/commande/card.php?id=%s&action=presend',
					'modify' => '/commande/card.php?id=%s&action=edit',
					'Create PO' => '/commande/card.php?id=%s',
					'create contract' => '/commande/card.php?id=%s',
					'Classify unbilled' => '/commande/card.php?id=%s&action=classifyunbilled',
					'clone' => '/commande/card.php?id=%s&action=clone',
					'cancel order' => '/commande/card.php?id=%s&action=cancel',
					'Delete' => '/commande/card.php?id=%s&action=delete'
				)
			),
			// Supplier Invoices (SI)
			'FactureFournisseur' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, c.total_ht, s.nom as client_name FROM ".MAIN_DB_PREFIX."facture_fourn as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut = 1 AND c.paye = 0 AND c.date_lim_reglement < NOW()",
				'element' => 'invoice_supplier',
				'stream_var' => 'ZULIP_STREAM_SI',
				'url_path' => '/fourn/facture/card.php?id=',
				'actions' => array(
					'modify' => '/fourn/facture/card.php?id=%s&action=edit',
					'send email' => '/fourn/facture/card.php?id=%s&action=presend',
					'enter payment' => '/fourn/facture/payment.php?facid=%s&action=create',
					'classify "Abandoned"' => '/fourn/facture/card.php?id=%s&action=cancel',
					'create credit note' => '/fourn/facture/card.php?action=create&facid=%s&type=2',
					'clone' => '/fourn/facture/card.php?id=%s&action=clone',
					'Delete' => '/fourn/facture/card.php?id=%s&action=delete'
				)
			),
			// Projects (PJ)
			'Project' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_creat as fk_user_author, s.nom as client_name FROM ".MAIN_DB_PREFIX."projet as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut = 1 AND c.datee < NOW()",
				'element' => 'project',
				'stream_var' => 'ZULIP_STREAM_PJ',
				'url_path' => '/projet/card.php?id=',
				'actions' => array(
					'send email' => '/projet/card.php?id=%s&action=presend',
					'back to draft' => '/projet/card.php?id=%s&action=confirm_draft',
					'Modify' => '/projet/card.php?id=%s&action=edit',
					'Close' => '/projet/card.php?id=%s&action=close',
					'Create purchase order' => '/projet/card.php?id=%s',
					'Create vendor invoice' => '/projet/card.php?id=%s',
					'Create contract' => '/projet/card.php?id=%s',
					'Create expense report' => '/projet/card.php?id=%s',
					'Clone' => '/projet/card.php?id=%s&action=clone',
					'Delete' => '/projet/card.php?id=%s&action=delete'
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
					$actions_text = !empty($action_links) ? "\n  * " . implode(" | ", $action_links) : "";

					$amount_text = isset($obj->total_ht) ? " - " . price($obj->total_ht, 0, $langs) . " HT" : "";

					$obj_item = "- " . $obj->ref . $client_suffix . $amount_text . ": [View](" . $obj_url . ")" . $actions_text;

					foreach ($user_ids as $uid) {
						if (!isset($user_reminders[$uid])) {
							$user_reminders[$uid] = array();
						}
						if (!isset($user_reminders[$uid][$type])) {
							$user_reminders[$uid][$type] = array();
						}
						$user_reminders[$uid][$type][] = $obj_item;
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

		foreach ($user_reminders as $uid => $types) {
			$user_email = $this->getUserEmail($uid);
			if (empty($user_email)) continue;
			
			$content = $explanation;
			foreach ($types as $type => $objects) {
				$content .= "\n**" . $type . "**\n";
				$content .= implode("\n", $objects) . "\n";
			}
			
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
