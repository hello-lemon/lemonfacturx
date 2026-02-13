<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Hook afterPDFCreation — injecte un XML Factur-X EN16931 dans les PDF factures clients
 */

class ActionsLemonFacturX
{
	public $db;
	public $error = '';
	public $errors = [];
	public $resPrint = '';
	public $results = [];

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Hook afterPDFCreation — contexte pdfgeneration
	 */
	public function afterPDFCreation($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $mysoc;

		if (!getDolGlobalInt('LEMONFACTURX_ENABLED')) {
			return 0;
		}

		$invoice = $parameters['object'] ?? null;
		if (!is_object($invoice) || get_class($invoice) !== 'Facture') {
			return 0;
		}

		$file = $parameters['file'] ?? '';
		if (empty($file) || !file_exists($file)) {
			return 0;
		}

		if (empty($invoice->thirdparty) || !is_object($invoice->thirdparty)) {
			$invoice->fetch_thirdparty();
		}
		if (empty($invoice->lines)) {
			$invoice->fetch_lines();
		}

		$modulePath = dirname(__DIR__);
		require_once $modulePath.'/lib/xml_builder.php';

		// Vérifier les infos obligatoires (on continue même si incomplet)
		$warnings = lemonfacturx_check_mandatory($invoice, $mysoc);
		foreach ($warnings as $w) {
			dol_syslog('LemonFacturX WARNING: '.$w, LOG_WARNING);
			setEventMessages($w, null, 'warnings');
		}

		// Générer le XML et l'écrire dans un fichier temporaire
		$xml = lemonfacturx_build_xml($invoice, $mysoc);
		$xmlTmpFile = tempnam(sys_get_temp_dir(), 'facturx_');
		file_put_contents($xmlTmpFile, $xml);

		// Injection via process séparé pour éviter le conflit FPDF/TCPDF
		if (!function_exists('exec')) {
			$this->error = 'LemonFacturX: exec() function is disabled';
			dol_syslog($this->error, LOG_ERR);
			@unlink($xmlTmpFile);
			return 0;
		}

		$phpCmd = defined('PHP_BINARY') ? PHP_BINARY : 'php';
		$scriptPath = escapeshellarg($modulePath.'/lib/inject_facturx.php');
		$pdfArg = escapeshellarg($file);
		$xmlArg = escapeshellarg($xmlTmpFile);

		$output = [];
		$returnCode = 0;
		exec(escapeshellarg($phpCmd)." $scriptPath $pdfArg $xmlArg 2>&1", $output, $returnCode);

		@unlink($xmlTmpFile);

		if ($returnCode !== 0) {
			$this->error = 'LemonFacturX: '.implode(' ', $output);
			dol_syslog($this->error, LOG_ERR);
			return 0;
		}

		dol_syslog('LemonFacturX: PDF Factur-X généré pour '.$invoice->ref, LOG_INFO);

		return 0;
	}
}
