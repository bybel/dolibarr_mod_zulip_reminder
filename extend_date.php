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
$action = GETPOST('action', 'aZ09');

if ($action == 'custom') {
	llxHeader('', 'Extend Date');
	print load_fiche_titre('Custom Date Extension');
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="set_custom_date">';
	print '<input type="hidden" name="element" value="'.$element.'">';
	print '<input type="hidden" name="id" value="'.$id.'">';
	
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
	$form = new Form($db);
	print '<table class="border" width="100%"><tr><td class="titlefield">Select new date</td><td>';
	print $form->selectDate('', 'custom_date', 0, 0, 0, "custom_form", 1, 1);
	print '</td></tr></table>';
	
	print '<div class="center"><br><input type="submit" class="button" value="Extend Date"></div>';
	print '</form>';
	llxFooter();
	exit;
}

$new_date = null;
if ($action == 'set_custom_date') {
	$new_date = dol_mktime(12, 0, 0, GETPOSTINT('custom_datemonth'), GETPOSTINT('custom_dateday'), GETPOSTINT('custom_dateyear'));
	if (empty($new_date)) {
		setEventMessages('Invalid date', null, 'errors');
		header('Location: '.$_SERVER["PHP_SELF"].'?action=custom&element='.$element.'&id='.$id);
		exit;
	}
} else {
	if ($days <= 0) $days = 30; // Default: extend by 30 days
	$new_date = dol_time_plus_duree(dol_now(), $days, 'd');
}

$error = 0;
$redirect_url = '';
$object = null;
$field_label = '';

switch ($element) {
	case 'propal':
		require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
		$object = new Propal($db);
		$object->fetch($id);
		$result = $object->set_echeance($user, $new_date);
		$field_label = 'end of validity';
		$redirect_url = DOL_URL_ROOT.'/comm/propal/card.php?id='.$id;
		break;

	case 'commande':
		require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
		$object = new Commande($db);
		$object->fetch($id);
		$result = $object->setDeliveryDate($user, $new_date);
		$field_label = 'delivery date';
		$redirect_url = DOL_URL_ROOT.'/commande/card.php?id='.$id;
		break;

	case 'order_supplier':
		require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
		$object = new CommandeFournisseur($db);
		$object->fetch($id);
		$result = $object->setDeliveryDate($user, $new_date);
		$field_label = 'delivery date';
		$redirect_url = DOL_URL_ROOT.'/fourn/commande/card.php?id='.$id;
		break;

	case 'facture':
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$object = new Facture($db);
		$object->fetch($id);
		$sql = "UPDATE ".MAIN_DB_PREFIX."facture SET date_lim_reglement = '".$db->idate($new_date)."' WHERE rowid = ".((int)$id);
		$result = $db->query($sql) ? 1 : -1;
		$field_label = 'payment due date';
		$redirect_url = DOL_URL_ROOT.'/compta/facture/card.php?facid='.$id;
		break;

	case 'invoice_supplier':
		require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
		$object = new FactureFournisseur($db);
		$object->fetch($id);
		$sql = "UPDATE ".MAIN_DB_PREFIX."facture_fourn SET date_lim_reglement = '".$db->idate($new_date)."' WHERE rowid = ".((int)$id);
		$result = $db->query($sql) ? 1 : -1;
		$field_label = 'payment due date';
		$redirect_url = DOL_URL_ROOT.'/fourn/facture/card.php?id='.$id;
		break;

	case 'project':
		require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
		$object = new Project($db);
		$object->fetch($id);
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
