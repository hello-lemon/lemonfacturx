<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Page de configuration du module LemonFacturX
 */

// Charger l'environnement Dolibarr
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

// Sécurité
if (!$user->admin) {
	accessforbidden();
}

$langs->loadLangs(["admin", "lemonfacturx@lemonfacturx"]);

$action = GETPOST('action', 'aZ09');

// Sauvegarde des paramètres
if ($action == 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	if (GETPOST('token', 'alpha') != newToken()) {
		accessforbidden('Bad value for CSRF token');
	}
	$error = 0;

	$enabled = GETPOSTINT('LEMONFACTURX_ENABLED');
	$bankAccount = GETPOSTINT('LEMONFACTURX_BANK_ACCOUNT');
	$paymentMeans = GETPOST('LEMONFACTURX_PAYMENT_MEANS', 'alpha');

	if (dolibarr_set_const($db, 'LEMONFACTURX_ENABLED', $enabled, 'int', 0, '', $conf->entity) < 0) {
		$error++;
	}
	if (dolibarr_set_const($db, 'LEMONFACTURX_BANK_ACCOUNT', $bankAccount, 'int', 0, '', $conf->entity) < 0) {
		$error++;
	}
	if (dolibarr_set_const($db, 'LEMONFACTURX_PAYMENT_MEANS', trim($paymentMeans), 'chaine', 0, '', $conf->entity) < 0) {
		$error++;
	}

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

// Affichage
llxHeader('', $langs->trans("LemonFacturXSetup"));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("LemonFacturXSetup"), $linkback, 'title_setup');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';

// En-tête
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

// Activer/Désactiver
print '<tr class="oddeven">';
print '<td>'.$langs->trans("LemonFacturXEnabled").'</td>';
print '<td>';
print '<select name="LEMONFACTURX_ENABLED" class="flat">';
print '<option value="0"'.(!getDolGlobalInt('LEMONFACTURX_ENABLED') ? ' selected' : '').'>'.$langs->trans("No").'</option>';
print '<option value="1"'.(getDolGlobalInt('LEMONFACTURX_ENABLED') ? ' selected' : '').'>'.$langs->trans("Yes").'</option>';
print '</select>';
print '</td>';
print '</tr>';

// Compte bancaire (IBAN/BIC)
print '<tr class="oddeven">';
print '<td>'.$langs->trans("LemonFacturXBankAccount").'</td>';
print '<td>';
$currentBankAccount = getDolGlobalInt('LEMONFACTURX_BANK_ACCOUNT');
$sql = "SELECT rowid, label, iban_prefix, bic FROM ".MAIN_DB_PREFIX."bank_account WHERE clos = 0 AND entity = ".$conf->entity." ORDER BY label";
$resql = $db->query($sql);
print '<select name="LEMONFACTURX_BANK_ACCOUNT" class="flat minwidth300">';
print '<option value="0">-- '.$langs->trans("Select").' --</option>';
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$infoIban = !empty($obj->iban_prefix) ? ' ('.substr($obj->iban_prefix, 0, 4).'...'.substr($obj->iban_prefix, -4).')' : ' (pas d\'IBAN)';
		print '<option value="'.$obj->rowid.'"'.($currentBankAccount == $obj->rowid ? ' selected' : '').'>';
		print dol_escape_htmltag($obj->label.$infoIban);
		print '</option>';
	}
}
print '</select>';
print '</td>';
print '</tr>';

// Moyen de paiement
print '<tr class="oddeven">';
print '<td>'.$langs->trans("LemonFacturXPaymentMeans").'</td>';
print '<td>';
$currentMeans = getDolGlobalString('LEMONFACTURX_PAYMENT_MEANS', '30');
print '<select name="LEMONFACTURX_PAYMENT_MEANS" class="flat">';
print '<option value="30"'.($currentMeans == '30' ? ' selected' : '').'>30 - '.$langs->trans("PaymentMeans30").'</option>';
print '<option value="58"'.($currentMeans == '58' ? ' selected' : '').'>58 - '.$langs->trans("PaymentMeans58").'</option>';
print '<option value="49"'.($currentMeans == '49' ? ' selected' : '').'>49 - '.$langs->trans("PaymentMeans49").'</option>';
print '</select>';
print '</td>';
print '</tr>';

print '</table>';

print '<br>';
print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

// Info
print '<br>';
print '<div class="info">';
print $langs->trans("LemonFacturXInfo");
print '</div>';

// === Diagnostic des infos obligatoires ===
print '<br>';
print load_fiche_titre($langs->trans("LemonFacturXDiagTitle"), '', '');

$diagErrors = [];
$diagOk = [];

// Société émettrice
if (empty($mysoc->name)) {
	$diagErrors[] = $langs->trans("LemonFacturXDiagSellerName");
} else {
	$diagOk[] = $langs->trans("LemonFacturXDiagSellerName").' : '.dol_escape_htmltag($mysoc->name);
}

if (empty($mysoc->address) || empty($mysoc->zip) || empty($mysoc->town)) {
	$diagErrors[] = $langs->trans("LemonFacturXDiagSellerAddress");
} else {
	$diagOk[] = $langs->trans("LemonFacturXDiagSellerAddress").' : '.dol_escape_htmltag($mysoc->zip).' '.dol_escape_htmltag($mysoc->town);
}

if (empty($mysoc->tva_intra)) {
	$diagErrors[] = $langs->trans("LemonFacturXDiagSellerVAT");
} else {
	$diagOk[] = $langs->trans("LemonFacturXDiagSellerVAT").' : '.dol_escape_htmltag($mysoc->tva_intra);
}

if (empty($mysoc->idprof2)) {
	$diagErrors[] = $langs->trans("LemonFacturXDiagSellerSIRET").' (BR-FR-10)';
} else {
	$siren = substr(preg_replace('/[^0-9]/', '', $mysoc->idprof2), 0, 9);
	$diagOk[] = $langs->trans("LemonFacturXDiagSellerSIRET").' : SIREN '.dol_escape_htmltag($siren).' (SIRET '.dol_escape_htmltag($mysoc->idprof2).')';
}

if (empty($mysoc->email)) {
	$diagErrors[] = $langs->trans("LemonFacturXDiagSellerEmail").' (BR-FR-13 / BT-34)';
} else {
	$diagOk[] = $langs->trans("LemonFacturXDiagSellerEmail").' : '.dol_escape_htmltag($mysoc->email);
}

// Compte bancaire
$bankId = getDolGlobalInt('LEMONFACTURX_BANK_ACCOUNT');
if ($bankId > 0) {
	$bankCheck = new Account($db);
	if ($bankCheck->fetch($bankId) > 0) {
		if (empty($bankCheck->iban)) {
			$diagErrors[] = $langs->trans("LemonFacturXDiagIBAN");
		} else {
			$diagOk[] = $langs->trans("LemonFacturXDiagIBAN").' : '.substr($bankCheck->iban, 0, 4).'...'.substr($bankCheck->iban, -4);
		}
		if (empty($bankCheck->bic)) {
			$diagErrors[] = $langs->trans("LemonFacturXDiagBIC");
		} else {
			$diagOk[] = $langs->trans("LemonFacturXDiagBIC").' : '.$bankCheck->bic;
		}
	} else {
		$diagErrors[] = $langs->trans("LemonFacturXDiagBankNotFound");
	}
} else {
	$diagErrors[] = $langs->trans("LemonFacturXDiagBankNotSet");
}

// Affichage diagnostic
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("LemonFacturXDiagResults").'</td></tr>';

foreach ($diagOk as $ok) {
	print '<tr class="oddeven"><td><span style="color: green;">&#10004;</span> '.$ok.'</td><td></td></tr>';
}
foreach ($diagErrors as $err) {
	print '<tr class="oddeven"><td><span style="color: red;">&#10008;</span> <strong>'.$err.'</strong></td>';
	print '<td><a href="'.DOL_URL_ROOT.'/admin/company.php">'.$langs->trans("LemonFacturXDiagFixLink").'</a></td></tr>';
}

if (empty($diagErrors)) {
	print '<tr class="oddeven"><td colspan="2"><span style="color: green;"><strong>'.$langs->trans("LemonFacturXDiagAllOk").'</strong></span></td></tr>';
}

print '</table>';

llxFooter();
$db->close();
