<?php

class ExpConf {

	public $debug = false;

	public $revisaoAtual = null;

	public $revisaoFinal = null;

	public $exportar = false;

	public $stripJsComents = false;

	public $stripPhpComents = false;

	public $listar = true;

	public $svn;

	public $svnlook;

	public $php;

	public $repoPath;

	public $repoUrl;

	public $pathSql;

	public $pathTmp;

	public function getRevisaoAtual() {
		return ExpModel::_exec($this->svnlook . ' youngest ' . $this->repoPath, $this);
	}

	public function __construct($sPathIni) {
		try {
			if (!is_file($sPathIni))
				throw new Exception('Arquivo "' . $sPathIni . '" n�o encontrado.');
		} catch (Exception $e) {
			throw new Exception('N�o foi possivel carregar a configura��o. ' . $e->getMessage());
		}
		
		//$SVN
		$this->svn = 'svn';
		
		//$SVNLOOK
		$this->svnlook = 'svnlook';
		
		//$PHP_PATH 
		$this->php = '/usr/local/bin/php';
		
		//$REPOSITORY_PATH 
		$this->repoPath = '/sig/svn/';
		
		//$REPOSITORY_URL
		$this->repoUrl = 'svn://127.0.0.1/';
		
		//$PATH_SQL  
		$this->pathSql = '/revisao_sql/';
		
		//$EXPORT_PATH 
		//$TMP_PATH = 
		$this->pathTmp = dirname(__FILE__) . '/tmp/';
	}
}

class ExpModel {

	/**
	 * Arquivos SQL
	 * @var array
	 */
	public $aSql = array();

	/**
	 * Arquivos atualizados
	 * @var array
	 */
	public $aAlteracao = array();

	/**
	 * Configura��o
	 * @var ExpConf
	 */
	private $oConf;

	public $sFileName;

	public $sFileWWW;

	public function __construct(ExpConf $oConf) {
		$this->oConf = $oConf;
	}

	public static function _exec($sCommand, ExpConf $oConf) {
		/*debug do comando*/
		if ($oConf->debug)
			echo "<div class=\"debug\">Debug: `$sCommand`</div>";
		
		$iStatus = 0;
		$aOutput = array();
		exec($sCommand, $aOutput, $iStatus);
		$sResult = trim(join("\n", $aOutput));
		
		/*se o status de retorno for diferente de 0 executou com erro*/
		if ($iStatus)
			throw new Exception("Falha ao executar comando shell. \nRetorno: " . $sResult . "\nComando: " . $sCommand);
		
		return $sResult;
	}

	public function exportar($sFileName) {
		/**
		 * Valida��es
		 */
		if (!$this->oConf->revisaoAtual || !is_numeric($this->oConf->revisaoAtual))
			throw new Exception('Par�metro "revisaoAtual" inv�lido.');
		
		if (!$this->oConf->revisaoFinal || !is_numeric($this->oConf->revisaoFinal))
			throw new Exception('Par�metro "revisaoFinal" inv�lido.');
		
		if (!($this->oConf->revisaoFinal >= $this->oConf->revisaoAtual))
			throw new Exception('O par�metro revisaoFinal deve ser maior ou igual � revisaoAtual.');
		
		/**
		 * Execu��es
		 */
		/*limpar exporta��es antigas*/
		$this->rmdirr($this->oConf->pathTmp);
		
		/*criando o diretorio de exporta��es*/
		mkdir($this->oConf->pathTmp, 0777);
		
		/*setando o nome do arquivo*/
		$this->sFileName = $sFileName;
		
		/*pesquisando as altera��es nas vers�es entre a inicial e final informada*/
		$sComentarios = '';
		$aAlteracao = array();
		for ($iRevisao = $this->oConf->revisaoAtual + 1; $iRevisao <= $this->oConf->revisaoFinal; $iRevisao++) {
			/*copiar arquivos sql*/
			$this->_copiarSQL($iRevisao);
			
			/*concatenando o comentario da atualiza��o*/
			$sComentarios .= $this->_getComentario($iRevisao);
			
			/*recupera dois arrays, dos arquivos alterados/adicionados e dos arquivos removidos*/
			list($aAdd, $aDel) = $this->_getAlterados($iRevisao);
			
			/*removendo da exporta��o os arquivos deletados*/
			if ($aDel) {
				foreach ($aDel as $sFile) {
					$key = array_search($sFile, $aAlteracao);
					if ($key !== false) {
						unset($aAlteracao[$key]);
					} else {
						/**
						 * farei uma nova procura, agora procurando pela string no come�o do nome
						 * para resolver o problema de quando se exclui a pasta e todo o conteudo
						 * Ex: 
						 * $sFile = "libs/dashboard/WindowPanel/"
						 * $aAlteracao = array(
						 * 'libs/dashboard/WindowPanel/dasboardPanel.as'
						 * )
						 */
						foreach ($aAlteracao as $key => $val)
							if (strpos($val, $sFile) !== false)
								unset($aAlteracao[$key]);
					}
				}
			}
			
			/*adiciona na exporta��o os arquivos alterados, se eles j� n�o existirem*/
			foreach ($aAdd as $sFile) {
				if (!in_array($sFile, $aAlteracao))
					$aAlteracao[] = $sFile;
			}
		}
		
		/*gravando os comentarios em um arquivo*/
		$sLogFile = $this->oConf->pathTmp . '_update/';
		$sLogFile .= date('Y-m-d_H-i') . '_' . ($this->oConf->revisaoAtual + 1) . '-' . $this->oConf->revisaoFinal . '.log';
		mkdir(dirname($sLogFile), 0777, true);
		file_put_contents($sLogFile, $sComentarios);
		
		/*exportando altera��es*/
		$this->_exportarAlteracao($aAlteracao);
		
		/*Gerar o arquivo revisao.txt*/
		file_put_contents($this->oConf->pathTmp . 'revisao.txt', $this->oConf->revisaoFinal);
		
		/*compactando o diretorio exportado*/
		$this->sFileWWW = self::compactar($sFileName, $this->oConf);
	}

	/**
	 * Compacta o diretorio de exporta��o criando um arquivo com o nome
	 * $sFileName e devolve o url do arquivo em rela��o ao diret�rio atual
	 * 
	 * @param $sFileName
	 * @param $oConf
	 * @return text/url endre�o web do arquivo compactado
	 */
	private static function compactar($sFileName, ExpConf $oConf) {
		$sFileName = preg_replace('[^A-Za-z]', '_', $sFileName);
		
		/*vai para a pasta, para n�o ficar a estrutura completa dentro do arquivo*/
		$sCommand = "cd {$oConf->pathTmp}; ";
		
		/*compacta com tar, e remove os arquivos apos a compress�o*/
		$sCommand .= "tar -cf $sFileName.tar * --remove-files;";
		
		/*permiss�o total para o arquivo criado*/
		$sCommand .= "chmod 777 *";
		
		/*executa os comandos*/
		self::_exec($sCommand, $oConf);
		
		/*devolve o endere�o do arquivo referente ao diretorio atual*/
		return '.' . str_replace(dirname(__FILE__), '', $oConf->pathTmp) . $sFileName . '.tar';
	}

	//	/**
	//	 * Exporta os todos os arquivos de uma revis�o
	//	 *
	//	 * @param integer $revisao
	//	 */
	//	function export_revision($revisao) {
	//		global $REPOSITORY_URL, $EXPORT_PATH, $SVN;
	//		
	//		exec("$SVN export $REPOSITORY_URL --force -r $revisao $EXPORT_PATH", $output, $retun);
	//		if (!$retun) {
	//			echo '<b>Falha ao exportar a revis�o:' . $revisao . '</b><br>';
	//			return false;
	//		}
	//		if (isset($_REQUEST['listar'])) {
	//			echo str_replace('A    ', '', join('<br>', $output));
	//		}
	//		return true;
	//	}
	

	private function _getComentario($iRevisao) {
		$oConf = &$this->oConf;
		
		/*adiciona o numero da revis�o*/
		$sComentario = utf8_encode("REVIS�O $iRevisao\n\n");
		
		/*adiciona os comentarios da revis�o*/
		$sComentario .= utf8_encode("Coment�rio: \n");
		$sComentario .= self::_exec("{$oConf ->svnlook} log {$oConf ->repoPath} -r $iRevisao", $oConf) . "\n\n";
		
		/*adiciona os arquivos alterados*/
		$sComentario .= "Arquivos alterados: \n";
		$sComentario .= self::_exec("{$oConf ->svnlook} changed {$oConf ->repoPath} -r $iRevisao", $oConf) . "\n";
		
		$sComentario .= str_pad('', 25, '_') . "\n\n";
		return $sComentario;
	}

	/**
	 * Copiando os arquivos txt|sql para serem empacotados junto com a atualiza��o
	 */
	private function _copiarSQL($iRevisao) {
		$aExtensoes = array('.txt', '.sql');
		foreach ($aExtensoes as $extensao) {
			$file = $iRevisao . $extensao;
			if (is_file($this->oConf->pathSql . $file)) {
				copy($this->oConf->pathSql . $file, $this->oConf->pathTmp . $file);
				$this->aSql[] = $file;
			}
		}
	}

	private function rmdirr($dirname) {
		/*se n�o existir o arquivo/dir informado*/
		if (!file_exists($dirname))
			trigger_error('Parametro "' . $dirname . '" n�o � um arquivo v�lido.');
		
		self::_exec("rm -rdf $dirname", $this->oConf);
	}

	/**
	 * Exporta os arquivos e cria os diretorios necessarios
	 *
	 * @param array $array_changed array com os caminhos relativos dos arquivos auterados
	 */
	private function _exportarAlteracao($aAlteracao) {
		sort($aAlteracao);
		foreach ($aAlteracao as $sFile) {
			/*definindo o endere�o que o arquivo ter� em disco*/
			$sFilePath = $this->oConf->pathTmp . $sFile;
			
			/*verificando se o diretorio necessario j� existe, sen�o cria*/
			$sDir = dirname($sFilePath);
			if (!is_dir($sDir))
				mkdir($sDir, 0777, true);
				
			/*se o que foi adicionado n�o for um diretorio, exporta*/
			if (!is_dir($sFilePath)) {
				$sComand = sprintf("%s cat %s %s -r %s > %s", $this->oConf->svnlook, $this->oConf->repoPath, $sFile, 
								$this->oConf->revisaoFinal, $sFilePath);
				$this->_exec($sComand, $this->oConf);
				$this->aAlteracao[] = $sFile;
			}
		}
	}

	/**
	 * devolve os arrays com os arquivos adicionados e removidos
	 *
	 * @param integer $iRevisao
	 * @return array
	 */
	private function _getAlterados($iRevisao) {
		
		/*array para guardar os arquivos adicionados e deletados*/
		$aAdd = array();
		$aDel = array();
		
		/*pega o nome dos arquivos modificados */
		$sAlteracao = self::_exec("{$this->oConf->svnlook} changed {$this->oConf->repoPath} -r $iRevisao", $this->oConf);
		
		/*separa a atualiza��o linha a linha*/
		$aAlteracao = explode("\n", $sAlteracao);
		
		foreach ($aAlteracao as $sLinha) {
			
			/*s�o duas colunas a serem tratadas, a��o e arquivo*/
			list($sAction, $sFile) = sscanf($sLinha, '%s %s');
			
			/*remove espa��s adicionais*/
			$sAction = trim($sAction);
			$sLinha = trim($sLinha);
			
			switch ($sAction) {
				/*deletado*/
				case 'D':
					$aDel[] = $sFile;
					break;
				
				/*adicionado/atualizado*/
				case 'A':
				case 'U':
					/*se ultima letra do nome do arquivo for diferente de "/", para ignorar diretorios*/
					if (substr($sFile, -1) != '/')
						$aAdd[] = $sFile;
					break;
				
				/*a��o n�o reconhecida*/
				default:
					$mensage = "A��o '%s' desconhecida para o arquivo '%s' revis�o: %s\n\n%s";
					throw new Exception(sprintf($mensage, $sAction, $sFile, $iRevisao, $sAlteracao));
			}
		}
		return array($aAdd, $aDel);
	}
}