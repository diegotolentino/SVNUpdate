<?php

require_once 'model.php';

class ExpView {

	public static function mostrarForm(ExpConf $oConf) {
		?>
<form name="form1" method="post" action="">
<fieldset><legend>Atualizador do sistema</legend>
<h4>Revisão a exportar</h4>
<input type="hidden" name="EMPRESA"
	value="<?php
		if (isset($_REQUEST['EMPRESA']))
			echo $_REQUEST['EMPRESA'];
		?>"> Revisão atual (exportado no arquivo revisao.txt): <input
	name="REVISAO" type="text"
	value="<?php
		echo $oConf->revisaoAtual;
		?>"> <br />

Revisão final: <input name="REVISAO_FINAL" type="text"
	value="<?php
		echo $oConf->revisaoFinal;
		?>"> Exportar final: <input type="checkbox" name="exportar_revisao"
	<?php
		echo ($oConf->exportar ? 'checked' : '');
		?>>
<h4>Opções de exportação</h4>
Retirar espaços em branco e comentarios de scripts .JS: <input
	type="checkbox" name="retirar_comentarios_js"
	<?php
		echo ($oConf->stripJsComents ? 'checked' : '');
		?>> <br />
Retirar espaços em branco e comentarios de scripts .PHP: <input
	type="checkbox" name="retirar_comentarios_php"
	<?php
		echo ($oConf->stripPhpComents ? 'checked' : '');
		?>> <br />
Listar arquivos exportados: <input type="checkbox" name="listar"
	value="listar" <?php
		echo ($oConf->listar ? 'checked' : '');
		?>>
<p><input type="hidden" name="posted" value="1"> &nbsp;<input
	type="submit" name="Submit" value="Enviar"></p>
</fieldset>
		<?php
	}

	public static function mostrarExportacao(ExpModel $oModel, ExpConf $oConf) {
		?>
		<hr width="80%" />
<h4>Diretório exportado:</h4>
		<?php
		echo $oConf->pathTmp;
		
		if ($oConf->listar) {
			?>
<h4>Atualizações SQL:</h4>
<ul>
<?php
			foreach ($oModel->aSql as $item)
				echo '<li>' . $item . '</li>';
			?>
            </ul>
<h4>Arquivos alterados:</h4>
<ul>
<?php
			foreach ($oModel->aAlteracao as $item) {
				?>
				<li>
				<?php
				echo $item;
				?>
				</li>
				<?php
			}
		
		}
		
		?>
                    </ul>
<?php
		echo count($oModel->aAlteracao) . ' arquivos alterados';
		?>
<h4>Arquivo compactado:</h4>
<b><a href="<?php
		echo $oModel->sFileWWW;
		?>">
		<?php
		echo $oModel->sFileName;
		?></a></b>
		<?php
	}

	public static function mostrarErro(Exception $e) {
		?>
		<div style="color: Red">
<h2>Erro:</h2>
 
<?php
		echo nl2br($e->getMessage()) . '<br>';
		echo '<b>Origem: </b>' . $e->getFile() . ':' . $e->getLine() . '<br>';
		echo '<b>Debug: </b><br>' . nl2br($e->getTraceAsString());
		?></div>
		<?php
	}
}
?>