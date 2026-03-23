<?php

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

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";

$langs->loadLangs(array("admin", "zulipreminder@zulipreminder"));

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

if (!$user->admin) {
	accessforbidden();
}

$useFormSetup = 1;

if (!class_exists('FormSetup')) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
}
$formSetup = new FormSetup($db);

$item = $formSetup->newItem('ZULIP_SERVER_URL');
$item->fieldParams['isMandatory'] = 1;
$item->fieldAttr['placeholder'] = 'https://your-domain.zulipchat.com';
$item->cssClass = 'minwidth500';

$item = $formSetup->newItem('ZULIP_BOT_EMAIL');
$item->fieldParams['isMandatory'] = 1;
$item->fieldAttr['placeholder'] = 'bot-email@your-domain.zulipchat.com';
$item->cssClass = 'minwidth500';

$item = $formSetup->newItem('ZULIP_BOT_API_KEY');
$item->fieldParams['isMandatory'] = 1;
$item->fieldAttr['placeholder'] = 'Secret API Key';
$item->cssClass = 'minwidth500';

$item = $formSetup->newItem('ZULIP_STREAM_PO');
$item->fieldAttr['placeholder'] = 'Purchasing';
$item->cssClass = 'minwidth500';

$item = $formSetup->newItem('ZULIP_STREAM_PR');
$item->fieldAttr['placeholder'] = 'Sales';
$item->cssClass = 'minwidth500';

$item = $formSetup->newItem('ZULIP_STREAM_CO');
$item->fieldAttr['placeholder'] = 'Sales';
$item->cssClass = 'minwidth500';

$item = $formSetup->newItem('ZULIP_STREAM_FA');
$item->fieldAttr['placeholder'] = 'Accounting';
$item->cssClass = 'minwidth500';

$item = $formSetup->newItem('ZULIP_STREAM_SI');
$item->fieldAttr['placeholder'] = 'Accounting';
$item->cssClass = 'minwidth500';

$item = $formSetup->newItem('ZULIP_STREAM_PJ');
$item->fieldAttr['placeholder'] = 'Projects';
$item->cssClass = 'minwidth500';

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

$title = "Zulip Reminder Setup";

llxHeader('', $langs->trans($title));

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.img_picto($langs->trans("BackToModuleList"), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans("BackToModuleList").'</span></a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

if (!empty($formSetup->items)) {
	print $formSetup->generateOutput(true);
	print '<br>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
