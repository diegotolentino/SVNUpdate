<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title>Atualizador do sistema</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<style>
<!--
body {
	font-family: "Trebuchet MS", Verdana, serif;
}

fieldset {
	padding: 0 5px 0 5px;
}

h4 {
	margin: 6px 0 0px 0;
}

.debug {
	background-color: #DFDFDF;
	color: rgb(220, 20, 60);
}
-->
</style>
</head>

<body>
<?php
function KM_strToASCII($pString, $pIsUTF8 = false) {
	$aMatches = array(
					'A' => '/[ÂÀÁÄÃ]/', 
					'a' => '/[âãàáä]/', 
					'E' => '/[ÊÈÉË]/', 
					'e' => '/[êèéë]/', 
					'I' => '/[ÎÍÌÏ]/', 
					'i' => '/[îíìï]/', 
					'O' => '/[ÔÕÒÓÖ]/', 
					'o' => '/[ôõòóö]/', 
					'U' => '/[ÛÙÚÜ]/', 
					'u' => '/[ûúùü]/', 
					'C' => '/[Ç]/', 
					'c' => '/[ç]/', 
					'N' => '/[Ñ]/', 
					'n' => '/[ñ]/');
	if ($pIsUTF8) {
		$aMatches = array_map('utf8_decode', $aMatches);
	}
	return preg_replace(array_values($aMatches), array_keys($aMatches), (($pIsUTF8) ? utf8_decode($pString) : $pString));
}

error_reporting(E_ALL);
try {
	/**
	 * Arquivo contendo a classe de model
	 */
	require_once 'model.php';
	
	/**
	 * Arquivo contendo a classe de view
	 */
	require_once 'view.php';
	
	/**
	 * Load da configuração fazendo o tratamento do POST
	 */
	$oConf = new ExpConf('./sig.ini');
	$oConf->revisaoAtual = isset($_REQUEST['REVISAO']) ? $_REQUEST['REVISAO'] : $oConf->getRevisaoAtual() - 1;
	$oConf->revisaoFinal = isset($_REQUEST['REVISAO_FINAL']) ? $_REQUEST['REVISAO_FINAL'] : $oConf->getRevisaoAtual();
	
	if (isset($_REQUEST['exportar_revisao']))
		$oConf->exportar = true;
	
	if (isset($_REQUEST['retirar_comentarios_js']))
		$oConf->stripJsComents = true;
	
	if (isset($_POST['retirar_comentarios_php']))
		$oConf->stripPhpComents = true;
	
	/**
	 * Tratando as ações
	 */
	/*sempre mostra o formulario*/
	ExpView::mostrarForm($oConf);
	
	/*se postou o form, executa a função exportar*/
	if (isset($_REQUEST['posted'])) {
		$oModel = new ExpModel($oConf);
		$sFileName = date('Y-m-d_H-i-s') . '_Atualizacao_';
		if (isset($_REQUEST['EMPRESA'])) {
			$sFileName .= preg_replace('/[^a-z0-9]/i', '_', KM_strToASCII($_REQUEST['EMPRESA'])) . '_';
		}
		$sFileName .= ($oConf->revisaoAtual+1) . '_' . $oConf->revisaoFinal;
		$oModel->exportar($sFileName);
		ExpView::mostrarExportacao($oModel, $oConf);
	}
} catch (Exception $e) {
	echo '<pre>';
	print_r($oConf);
	echo '</pre>';
	ExpView::mostrarErro($e);
}
?>
</body>
</html>