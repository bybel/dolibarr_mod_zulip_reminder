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
				'sql' => "SELECT rowid, ref, fk_user_author FROM ".MAIN_DB_PREFIX."commande_fournisseur WHERE fk_statut IN (1,2,3,4) AND date_livraison < NOW()",
				'element' => 'order_supplier',
				'stream_var' => 'ZULIP_STREAM_PO'
			),
			// Commercial Proposals (PR)
			'Propal' => array(
				'sql' => "SELECT rowid, ref, fk_user_author FROM ".MAIN_DB_PREFIX."propal WHERE fk_statut = 1 AND fin_validite < NOW()",
				'element' => 'propal',
				'stream_var' => 'ZULIP_STREAM_PR'
			),
			// Customer Orders (CO)
			'Commande' => array(
				'sql' => "SELECT rowid, ref, fk_user_author FROM ".MAIN_DB_PREFIX."commande WHERE fk_statut IN (1,2) AND date_livraison < NOW()",
				'element' => 'commande',
				'stream_var' => 'ZULIP_STREAM_CO'
			),
			// Customer Invoices (FA)
			'Facture' => array(
				'sql' => "SELECT rowid, ref, fk_user_author FROM ".MAIN_DB_PREFIX."facture WHERE fk_statut = 1 AND paye = 0 AND date_lim_reglement < NOW()",
				'element' => 'facture',
				'stream_var' => 'ZULIP_STREAM_FA'
			),
			// Supplier Invoices (SI)
			'FactureFournisseur' => array(
				'sql' => "SELECT rowid, ref, fk_user_author FROM ".MAIN_DB_PREFIX."facture_fourn WHERE fk_statut = 1 AND paye = 0 AND date_lim_reglement < NOW()",
				'element' => 'invoice_supplier',
				'stream_var' => 'ZULIP_STREAM_SI'
			),
			// Projects (PJ)
			'Project' => array(
				'sql' => "SELECT rowid, ref, fk_user_creat as fk_user_author FROM ".MAIN_DB_PREFIX."projet WHERE fk_statut = 1 AND date_fin < NOW()",
				'element' => 'project',
				'stream_var' => 'ZULIP_STREAM_PJ'
			)
		);

		$messages_sent = 0;

		foreach ($queries as $type => $data) {
			$stream = getDolGlobalString($data['stream_var']);
			if (empty($stream)) {
				dol_syslog('ZulipReminderCron: No stream configured for ' . $type);
				continue;
			}

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
					$mentions = array();
					
					foreach ($user_ids as $uid) {
						$user_email = $this->getUserEmail($uid);
						if (!empty($user_email)) {
							$mentions[] = "@**" . $user_email . "**";
						}
					}
					
					$responsible_text = empty($mentions) ? "No assigned users" : implode(", ", $mentions);
					$content = "Reminder: **" . $type . "** with ref **" . $obj->ref . "** is currently marked as late in Dolibarr.\nResponsible: " . $responsible_text;
					$topic = "Late " . $type . ": " . $obj->ref;
					
					if ($client->sendStreamMessage($stream, $topic, $content)) {
						$messages_sent++;
					} else {
						$this->error .= "Failed to send stream message for $type $obj->ref. ";
						$error++;
					}
				}
				$this->db->free($resql);
			} else {
				dol_syslog('ZulipReminderCron: Error in query for ' . $type . ' - ' . $this->db->lasterror(), LOG_ERR);
				$error++;
			}
		}

		$this->output = "Cron job executed. $messages_sent messages sent.";
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
