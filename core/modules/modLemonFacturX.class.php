<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Module descriptor for LemonFacturX
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modLemonFacturX extends DolibarrModules
{
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;
		$this->numero = 500200;
		$this->rights_class = 'lemonfacturx';
		$this->family = "financial";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Génération automatique de factures Factur-X EN16931";
		$this->descriptionlong = "Injecte un XML CrossIndustryInvoice EN16931 dans chaque PDF facture client généré, pour conformité Factur-X.";
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'bill';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'theme' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'hooks' => array(
				'pdfgeneration',
			),
		);

		$this->dirs = array();
		$this->config_page_url = array('setup.php@lemonfacturx');

		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("lemonfacturx@lemonfacturx");

		$this->const = array(
			array('LEMONFACTURX_ENABLED', 'int', '1', 'Activer la conversion Factur-X', 1, 'current', 0),
			array('LEMONFACTURX_BANK_ACCOUNT', 'int', '0', 'ID du compte bancaire pour IBAN/BIC', 1, 'current', 0),
			array('LEMONFACTURX_PAYMENT_MEANS', 'chaine', '30', 'Code moyen de paiement (30=virement, 58=SEPA)', 1, 'current', 0),
		);

		if (!isset($conf->lemonfacturx) || !isset($conf->lemonfacturx->enabled)) {
			$conf->lemonfacturx = new stdClass();
			$conf->lemonfacturx->enabled = 0;
		}

		$this->rights = array();
		$this->menu = array();
	}
}
