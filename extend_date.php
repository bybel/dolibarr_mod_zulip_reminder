<?php
/**
 * Quick action to extend the expiry/delivery/due date of a Dolibarr object by N days.
 * Called from Zulip reminder links.
 *
 * Parameters:
 *   element  - Object type: propal, commande, order_supplier, facture, invoice_supplier, project
 *   id       - Object rowid
 *   days     - Number of days to extend (default: 30)
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

$element = GETPOST('element', 'alpha');
if (empty($element) && !empty($_GET['element'])) {
	$element = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['element']);
}
$id = GETPOSTINT('id');
if (empty($id) && !empty($_GET['id'])) {
	$id = (int) $_GET['id'];
}
$days = GETPOSTINT('days');
if (empty($days) && !empty($_GET['days'])) {
	$days = (int) $_GET['days'];
}
if ($days <= 0) {
	$days = 30; // Default: extend by 30 days
}

dol_syslog("extend_date.php called with element=".$element.", id=".$id.", days=".$days, LOG_DEBUG);

if (empty($element) || empty($id)) {
	setEventMessages('Missing parameters (element='.$element.', id='.$id.'). Check the URL.', null, 'errors');
	header('Location: '.DOL_URL_ROOT.'/index.php');
	exit;
}

$error = 0;
$redirect_url = '';
$object = null;
$new_date = null;
$field_label = '';

switch ($element) {
	case 'propal':
		require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
		$object = new Propal($db);
		$object->fetch($id);
		$new_date = dol_time_plus_duree($object->fin_validite, $days, 'd');
		$result = $object->set_echeance($user, $new_date);
		$field_label = 'end of validity';
		$redirect_url = DOL_URL_ROOT.'/comm/propal/card.php?id='.$id;
		break;

	case 'commande':
		require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
		$object = new Commande($db);
		$object->fetch($id);
		$new_date = dol_time_plus_duree($object->delivery_date, $days, 'd');
		$result = $object->setDeliveryDate($user, $new_date);
		$field_label = 'delivery date';
		$redirect_url = DOL_URL_ROOT.'/commande/card.php?id='.$id;
		break;

	case 'order_supplier':
		require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
		$object = new CommandeFournisseur($db);
		$object->fetch($id);
		$new_date = dol_time_plus_duree($object->delivery_date, $days, 'd');
		$result = $object->setDeliveryDate($user, $new_date);
		$field_label = 'delivery date';
		$redirect_url = DOL_URL_ROOT.'/fourn/commande/card.php?id='.$id;
		break;

	case 'facture':
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$object = new Facture($db);
		$object->fetch($id);
		// For invoices, extend the payment due date
		$new_date = dol_time_plus_duree($object->date_lim_reglement, $days, 'd');
		$sql = "UPDATE ".MAIN_DB_PREFIX."facture SET date_lim_reglement = '".$db->idate($new_date)."' WHERE rowid = ".((int)$id);
		$result = $db->query($sql) ? 1 : -1;
		$field_label = 'payment due date';
		$redirect_url = DOL_URL_ROOT.'/compta/facture/card.php?facid='.$id;
		break;

	case 'invoice_supplier':
		require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
		$object = new FactureFournisseur($db);
		$object->fetch($id);
		// For supplier invoices, extend the payment due date
		$new_date = dol_time_plus_duree($object->date_lim_reglement, $days, 'd');
		$sql = "UPDATE ".MAIN_DB_PREFIX."facture_fourn SET date_lim_reglement = '".$db->idate($new_date)."' WHERE rowid = ".((int)$id);
		$result = $db->query($sql) ? 1 : -1;
		$field_label = 'payment due date';
		$redirect_url = DOL_URL_ROOT.'/fourn/facture/card.php?id='.$id;
		break;

	case 'project':
		require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
		$object = new Project($db);
		$object->fetch($id);
		$new_date = dol_time_plus_duree($object->date_end, $days, 'd');
		$object->date_end = $new_date;
		$result = $object->update($user);
		$field_label = 'end date';
		$redirect_url = DOL_URL_ROOT.'/projet/card.php?id='.$id;
		break;

	default:
		setEventMessages('Unknown element type: '.$element, null, 'errors');
		header('Location: '.DOL_URL_ROOT.'/index.php');
		exit;
}

if ($result > 0 && $new_date) {
	setEventMessages('Extended '.$field_label.' by '.$days.' days (new date: '.dol_print_date($new_date, 'day').')', null, 'mesgs');
} else {
	setEventMessages('Error extending '.$field_label.': '.($object ? $object->error : $db->lasterror()), null, 'errors');
}

header('Location: '.$redirect_url);
exit;
