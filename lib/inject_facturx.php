<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Script standalone pour injecter le XML Factur-X dans un PDF
 *
 * Usage: php inject_facturx.php <pdf_path> <xml_path>
 *
 * Ce script s'exécute dans un process PHP séparé, sans Dolibarr chargé,
 * pour éviter les conflits entre FPDF (utilisé par atgp/factur-x) et
 * TCPDF (utilisé par Dolibarr).
 */

if ($argc < 3) {
	fwrite(STDERR, "Usage: php inject_facturx.php <pdf_path> <xml_path>\n");
	exit(1);
}

$pdfPath = $argv[1];
$xmlPath = $argv[2];

if (!file_exists($pdfPath)) {
	fwrite(STDERR, "PDF not found: $pdfPath\n");
	exit(1);
}
if (!file_exists($xmlPath)) {
	fwrite(STDERR, "XML not found: $xmlPath\n");
	exit(1);
}

require_once __DIR__.'/../vendor/autoload.php';

try {
	$pdfContent = file_get_contents($pdfPath);
	$xmlContent = file_get_contents($xmlPath);

	$writer = new \Atgp\FacturX\Writer();
	$facturxPdf = $writer->generate(
		$pdfContent,
		$xmlContent,
		'en16931',
		false
	);

	file_put_contents($pdfPath, $facturxPdf);
	echo "OK\n";
	exit(0);

} catch (Exception $e) {
	fwrite(STDERR, "Error: ".$e->getMessage()."\n");
	exit(1);
}
