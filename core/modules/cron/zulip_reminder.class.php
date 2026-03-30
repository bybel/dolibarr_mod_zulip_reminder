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
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, c.fk_soc, c.fk_projet, c.fk_statut, c.total_ht, c.multicurrency_total_ht, c.multicurrency_code, s.nom as client_name FROM ".MAIN_DB_PREFIX."facture as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut = 1 AND c.paye = 0 AND c.date_lim_reglement < NOW()",
				'element' => 'facture',
				'stream_var' => 'ZULIP_STREAM_FA',
				'url_path' => '/compta/facture/card.php?id=',
				'actions' => array(
					'Dept compensation' => '/custom/clientpayfourn/clientpayfournindex.php?id=__ID__',
					'modify' => '/compta/facture/card.php?facid=__ID__&action=edit',
					'send email' => '/compta/facture/card.php?facid=__ID__&action=presend&mode=init#formmailbeforetitle',
					'enter payment' => '/compta/paiement.php?facid=__ID__&action=create',
					'Classify "Abandoned"' => '/compta/facture/card.php?facid=__ID__&action=canceled',
					'create credit note' => '/compta/facture/card.php?socid=__SOCID__&fac_avoir=__ID__&action=create&type=2',
					'Clone' => '/compta/facture/card.php?facid=__ID__&action=clone&object=invoice',
					'delete' => '/compta/facture/card.php?facid=__ID__&action=delete'
				)
			),
			// Commercial Proposals (PR)
			'Propal' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, c.fk_soc, c.fk_projet, c.fk_statut, c.total_ht, c.multicurrency_total_ht, c.multicurrency_code, s.nom as client_name FROM ".MAIN_DB_PREFIX."propal as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut IN (0, 1) AND c.fin_validite < NOW()",
				'element' => 'propal',
				'stream_var' => 'ZULIP_STREAM_PR',
				'url_path' => '/comm/propal/card.php?id=',
				'actions_by_status' => array(
					0 => array( // Draft
						'Validate' => '/comm/propal/card.php?id=__ID__&action=validate',
						'send email' => '/comm/propal/card.php?id=__ID__&action=presend&mode=init#formmailbeforetitle',
						'clone' => '/comm/propal/card.php?id=__ID__&socid=__SOCID__&action=clone&object=propal',
						'Delete' => '/comm/propal/card.php?id=__ID__&action=delete'
					),
					1 => array( // Validated
						'Modify' => '/comm/propal/card.php?id=__ID__&action=modif',
						'send email' => '/comm/propal/card.php?id=__ID__&action=presend&mode=init#formmailbeforetitle',
						'set Accepted/refused' => '/comm/propal/card.php?id=__ID__&action=closeas',
						'cancel' => '/comm/propal/card.php?id=__ID__&action=cancel',
						'clone' => '/comm/propal/card.php?id=__ID__&socid=__SOCID__&action=clone&object=propal',
						'Delete' => '/comm/propal/card.php?id=__ID__&action=delete'
					)
				)
			),
			// Purchase Orders (PO)
			'CommandeFournisseur' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, c.fk_soc, c.fk_projet, c.fk_statut, c.billed, c.total_ht, c.multicurrency_total_ht, c.multicurrency_code, s.nom as client_name FROM ".MAIN_DB_PREFIX."commande_fournisseur as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut IN (1,2,3,4) AND c.date_livraison < NOW()",
				'element' => 'order_supplier',
				'stream_var' => 'ZULIP_STREAM_PO',
				'url_path' => '/fourn/commande/card.php?id=',
				'actions' => array(
					'Scan invoice' => '/custom/scaninvoices/importinvoice.php?step=2&origin=order_supplier&fournID=__SOCID__&orderID=__ID__',
					'send email' => '/fourn/commande/card.php?id=__ID__&action=presend&mode=init#formmailbeforetitle',
					'Re open' => '/fourn/commande/card.php?id=__ID__&action=reopen',
					'classify received' => '/fourn/commande/card.php?id=__ID__&action=classifyreception#classifyreception',
					'Create Invoice' => '/fourn/facture/card.php?action=create&origin=order_supplier&originid=__ID__&socid=__SOCID__',
					'Classify billed' => '/fourn/commande/card.php?id=__ID__&action=classifybilled',
					'Classify unbilled' => '/fourn/commande/card.php?id=__ID__&action=classifyunbilled',
					'Clone' => '/fourn/commande/card.php?id=__ID__&socid=__SOCID__&action=clone&object=order',
					'delete' => '/fourn/commande/card.php?id=__ID__&action=delete'
				)
			),
			// Customer Orders (CO)
			'Commande' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, c.fk_soc, c.fk_projet, c.fk_statut, c.facture as billed, c.total_ht, c.multicurrency_total_ht, c.multicurrency_code, s.nom as client_name FROM ".MAIN_DB_PREFIX."commande as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut IN (1,2) AND c.date_livraison < NOW()",
				'element' => 'commande',
				'stream_var' => 'ZULIP_STREAM_CO',
				'url_path' => '/commande/card.php?id=',
				'actions' => array(
					'send email' => '/commande/card.php?id=__ID__&action=presend&mode=init#formmailbeforetitle',
					'modify' => '/commande/card.php?id=__ID__&action=modif',
					'Create Purchase order' => '/fourn/commande/card.php?action=create&origin=commande&originid=__ID__',
					'Create contract' => '/contrat/card.php?action=create&origin=commande&originid=__ID__&socid=__SOCID__',
					'Create Invoice' => '/compta/facture/card.php?action=create&origin=commande&originid=__ID__&socid=__SOCID__',
					'Classify billed' => '/commande/card.php?id=__ID__&action=classifybilled',
					'Classify unbilled' => '/commande/card.php?id=__ID__&action=classifyunbilled',
					'Classify Delivered' => '/commande/card.php?id=__ID__&action=shipped',
					'clone' => '/commande/card.php?id=__ID__&socid=__SOCID__&action=clone',
					'cancel order' => '/commande/card.php?id=__ID__&action=cancel',
					'Delete' => '/commande/card.php?id=__ID__&action=delete'
				)
			),
			// Supplier Invoices (SI)
			'FactureFournisseur' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_author, c.fk_soc, c.fk_projet, c.fk_statut, c.total_ht, c.multicurrency_total_ht, c.multicurrency_code, s.nom as client_name FROM ".MAIN_DB_PREFIX."facture_fourn as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut = 1 AND c.paye = 0 AND c.date_lim_reglement < NOW()",
				'element' => 'invoice_supplier',
				'stream_var' => 'ZULIP_STREAM_SI',
				'url_path' => '/fourn/facture/card.php?id=',
				'actions' => array(
					'modify' => '/fourn/facture/card.php?id=__ID__&action=edit',
					'send email' => '/fourn/facture/card.php?id=__ID__&action=presend&mode=init#formmailbeforetitle',
					'enter payment' => '/fourn/facture/paiement.php?facid=__ID__&action=create',
					'classify "Abandoned"' => '/fourn/facture/card.php?id=__ID__&action=canceled',
					'create credit note' => '/fourn/facture/card.php?socid=__SOCID__&fac_avoir=__ID__&action=create&type=2',
					'clone' => '/fourn/facture/card.php?id=__ID__&action=clone&socid=__SOCID__',
					'Delete' => '/fourn/facture/card.php?id=__ID__&action=delete'
				)
			),
			// Projects (PJ)
			'Project' => array(
				'sql' => "SELECT c.rowid, c.ref, c.fk_user_creat as fk_user_author, c.fk_soc, c.fk_statut, s.nom as client_name FROM ".MAIN_DB_PREFIX."projet as c LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid WHERE c.fk_statut = 1 AND c.datee < NOW()",
				'element' => 'project',
				'stream_var' => 'ZULIP_STREAM_PJ',
				'url_path' => '/projet/card.php?id=',
				'actions' => array(
					'send email' => '/projet/card.php?id=__ID__&action=presend&mode=init#formmailbeforetitle',
					'back to draft' => '/projet/card.php?id=__ID__&action=confirm_setdraft&confirm=yes',
					'Validate' => '/projet/card.php?id=__ID__&action=validate',
					'Modify' => '/projet/card.php?id=__ID__&action=edit',
					'Close' => '/projet/card.php?id=__ID__&action=close',
					'Create order' => '/commande/card.php?action=create&projectid=__ID__&socid=__SOCID__',
					'Create Proposal' => '/comm/propal/card.php?action=create&projectid=__ID__&socid=__SOCID__',
					'Create invoice' => '/compta/facture/card.php?action=create&projectid=__ID__&socid=__SOCID__',
					'Create purchase order' => '/fourn/commande/card.php?action=create&projectid=__ID__',
					'Create vendor invoice' => '/fourn/facture/card.php?action=create&projectid=__ID__',
					'Create contract' => '/contrat/card.php?action=create&projectid=__ID__&socid=__SOCID__',
					'Create expense report' => '/expensereport/card.php?action=create&projectid=__ID__&socid=__SOCID__',
					'Clone' => '/projet/card.php?id=__ID__&action=clone',
					'Delete' => '/projet/card.php?id=__ID__&action=delete'
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
					$replacements = array(
						'__ID__' => $obj->rowid,
						'__SOCID__' => isset($obj->fk_soc) && $obj->fk_soc > 0 ? $obj->fk_soc : '',
						'__PROJECTID__' => isset($obj->fk_projet) && $obj->fk_projet > 0 ? $obj->fk_projet : '',
					);
					
					// Determine which actions to use: status-specific or generic
					$current_actions = array();
					if (!empty($data['actions_by_status']) && isset($obj->fk_statut) && isset($data['actions_by_status'][$obj->fk_statut])) {
						$current_actions = $data['actions_by_status'][$obj->fk_statut];
					} elseif (!empty($data['actions'])) {
						$current_actions = $data['actions'];
					}
					if (!empty($current_actions)) {
						// Determine billed status for conditional actions
						$is_billed = isset($obj->billed) ? (int) $obj->billed : 0;

						foreach ($current_actions as $act_name => $act_url_format) {
							// Annotate billed/unbilled actions with current state
							$display_name = $act_name;
							if ($act_name === 'Classify billed' && $is_billed) $display_name = 'Classify billed ✓';
							if ($act_name === 'Classify unbilled' && !$is_billed) $display_name = 'Classify unbilled ✓';

							$act_full_url = constant('DOL_MAIN_URL_ROOT') . str_replace(array_keys($replacements), array_values($replacements), $act_url_format);
							$action_links[] = "[" . $display_name . "](" . $act_full_url . ")";
						}
					}
					$actions_text = !empty($action_links) ? "\n  * Actions: " . implode(" | ", $action_links) : "";

					// Build extend date sub-bullet with multiple time options
					$extend_base = '/custom/zulipreminder/extend_date.php?element=' . $data['element'] . '&id=' . $obj->rowid . '&days=';
					$extend_options = array(
						'1 week' => 7,
						'2 weeks' => 14,
						'30 days' => 30,
						'60 days' => 60
					);
					$extend_links = array();
					foreach ($extend_options as $label => $d) {
						$extend_links[] = '[' . $label . '](' . constant('DOL_MAIN_URL_ROOT') . $extend_base . $d . ')';
					}
					$extend_text = "\n  * Extend date (today+): " . implode(' | ', $extend_links);

					$amount_text = "";
					if (!empty($obj->multicurrency_code)) {
						$amount_text = " - " . price($obj->multicurrency_total_ht, 0, $langs) . " " . $obj->multicurrency_code;
					} elseif (isset($obj->total_ht)) {
						$currency_suffix = (!empty($conf->currency) ? $conf->currency : "HT");
						$amount_text = " - " . price($obj->total_ht, 0, $langs) . " " . $currency_suffix;
					}

					$obj_item = "- " . $obj->ref . $client_suffix . $amount_text . ": [View](" . $obj_url . ")" . $actions_text . $extend_text;

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

		$max_per_type = 5;

		foreach ($user_reminders as $uid => $types) {
			$user_email = $this->getUserEmail($uid);
			if (empty($user_email)) continue;
			
			$content = $explanation;
			foreach ($types as $type => $objects) {
				$total = count($objects);
				$content .= "\n## " . $type . " (" . $total . ")\n";
				$displayed = array_slice($objects, 0, $max_per_type);
				$content .= implode("\n", $displayed) . "\n";
				if ($total > $max_per_type) {
					$remaining = $total - $max_per_type;
					$content .= "\n*... and " . $remaining . " more late " . $type . "*\n";
				}
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
