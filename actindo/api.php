<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))).DIRECTORY_SEPARATOR.'Shopware'.DIRECTORY_SEPARATOR.'Shopware.php');

/**
 * Shopware API
 *
 * api.php ist die Basis-Klasse, die f�r den Zugriff auf die Shopware API
 * notwendig ist.
 * 
 * <code>
 * <?php
 * require_once 'api.php';
 *
 * $api = new sAPI();
 * $data = $api->load("file://".$api->sPath."/test.xml");
 * ?>
 * </code>
 *
 * @author      shopware AG
 * @package     Shopware 3.5.0
 * @subpackage  API
 * @version		1.0.0
 * @author      Holger Ronecker
 * @link        http://artdevil.de/ShopConnector ShopConnector Seite aus ArtDevil.de
 */
class sAPI extends Shopware
{
	/**
	 * Zugriff auf adoDB-Objekt
	 * @access public
	 * @var object
	 */
	var $sDB;

	/**
	 * Enth�lt absoluten Pfad zur Shopware Installation
	 * @access public
	 * @var string
	 */
	var $sPath;

	/**
	 * ???
	 * @access public
	 * @var string
	 */
	var $sFiles;

	/**
	 * Zugriff auf Shopware System-Klasse
	 * @access public
	 * @var object
	 */
	var $sSystem;

	/**
	 * Enth�lt Fehlermeldungen
	 * @access public
	 * @var array
	 */
	var $sErrors = array();

	/**
	 * Zugriff auf verschiedene Sub-Objekte
	 * @access public
	 * @var array
	 */
	var $sResource = array();
	var $sCONFIG;

	/**
	 * Der Konstruktor l�dt Einstellungen / Datenbank-Verbindung und den Shopware - Core
	 * 
	 * @access public
	 */
	public function __construct()
	{
		parent::__construct();

		$this->Bootstrap()->loadResource('Zend');
		$this->Bootstrap()->loadResource('Cache');
		$this->Bootstrap()->loadResource('Db');

		$this->sPath = $this->DocPath();
		$this->sCONFIG = $this->Config();
		$db = new Enlight_Components_Adodb(array(
			'db' => $this->Db()
		));
		$this->sDB = $db;
		$this->sSystem = $this;

		error_reporting(0);
		ini_set("display_errors", false);
	}

	/**
	 * L�dt externe Daten und speichert diese in einem File-Cache
	 * Derzeit wird ausschlie�lich das HTTP Protokoll unterst�tzt
	 * @param string $url Der Pfad (inkl. Protokoll) zur Datei
	 * @access public
	 */
	function load($url)
	{
		$url_array = parse_url($url);
		$url_array['path'] = explode("/", $url_array['path']);
		switch ($url_array['scheme']) {
			case "ftp":
			case "http":
			case "file":
				$hash = "";
				$dir = $this->sPath."/engine/connectors/api/tmp";
				while (empty($hash)) {
					$hash = md5(uniqid(rand(), true));
					if(file_exists("$dir/$hash.tmp"))
						$hash = "";
				}
				if(!$put_handle = fopen("$dir/$hash.tmp", "w+")) {
					return false;
				}
				if(!$get_handle = fopen($url, "r")) {
					return false;
				}
				while (!feof($get_handle)) {
					fwrite($put_handle, fgets($get_handle, 4096));
				}
				fclose($get_handle);
				fclose($put_handle);
				$this->sFiles[] = $hash;
				return "$dir/$hash.tmp";
			case "post":
				//$data = $_POST;
				//$_FILES['userfile']['tmp_name']
				//is_uploaded_file
				break;
			/*
			  case "shopware":
			  switch ($url_array['host']) {
			  case "sCore":
			  if(!empty($url_array['path'][1]))
			  $classname = $url_array['path'][1];
			  else
			  return false;
			  if(!empty($url_array['path'][2]))
			  $functionname = $url_array['path'][2];
			  if(!empty($url_array['query']))
			  parse_str($url_array['query'],$params);
			  else
			  $params = array();
			  if(!file_exists($this->sPath."/engine/core/class/".$classname.".php"))
			  return false;
			  include($this->sPath."/engine/core/class/".$classname.".php");
			  if(!class_exists($classname))
			  return false;
			  if (!empty($functionname)) {
			  if(!method_exists($classname,$functionname))
			  return false;
			  $class = new $classname;
			  $class->sSYSTEM =& $this->sSystem;
			  return call_user_func_array(array(&$class, $functionname),$params);
			  }
			  break;
			  default:
			  break;
			  }
			 */
			case "mail":
			case "tcp":
			case "udp":
			case "php":
			default:
				break;
		}
	}

	/**
	 * Garbage-Collector
	 * Nach dem Beenden der API werden tempor�re Dateien gel�scht
	 * @access public
	 */
	function __destruct()
	{
		if(!empty($this->sFiles))
			foreach ($this->sFiles as $hash) {
				if(file_exists($this->sPath."/engine/connectors/api/tmp/$hash.tmp"))
					@unlink($this->sPath."/engine/connectors/api/tmp/$hash.tmp");
			}
	}

	/**
	 * Lokales Speichern von Daten - derzeit ohne Funktion
	 * @param string $url Der Pfad (inkl. Protokoll) zur Datei
	 * @access public
	 */
	function save($url)
	{
		$url_array = parse_url($url);
		$url_array['path'] = explode("/", $url_array['path']);
		switch ($url_array['scheme']) {
			case "ftp":
			case "http":
			case "file":
				break;
			case "post":
				break;
			case "shopware":
				break;
			case "mail":
			case "tcp":
			case "udp":
			case "php":
			default:
				break;
		}
	}
	protected $throwError = false;

	function sSetError($message, $code)
	{
		$this->sErrors[] = array('message' => $message, 'code' => $code);
	}

	function sGetErrors()
	{
		return $this->sErrors;
	}

	function sGetLastError()
	{
		return end($this->sErrors);
	}

	/**
	 * Einbinden von externen Klassen / Objekten
	 * <code>
	 * <?php
	 *    $api = new sAPI();
	 *    $export =& $api->export->shopware;	// L�dt Klasse /api/export/shopware.php
	 *    $xml =& $api->convert->xml;			// L�dt Klasse /api/convert/xml.php
	 *    $mapping =& $api->convert->mapping;	// L�dt Klasse /api/convert/mapping.php
	 *    $xml->sSettings['encoding'] = "ISO-8859-1";
	 * ?>
	 * </code>
	 * @param string $res Enth�lt den Pfad / Dateinamen des einzubindenen Objekts
	 * @access public
	 */
	function __get($res)
	{
		switch ($res) {
			case "sConvert":
			case "convert":
				$res = "convert";
				break;
			case "sSave":
			case "save":
			case "import":
				$res = "import";
				break;
			case "sLoad":
			case "load":
			case "export":
				$res = "export";
				break;
				break;
			default:
				return false;
		}
		if(!isset($this->sResource[$res])) {
			$this->sResource[$res] = new sClassHandler($this, $res);
		}
		return $this->sResource[$res];
	}
}

/**
 * Shopware API - Class-Loader
 *
 *
 * @author	  Heiner Lohaus <hl@shopware2.de>
 * @package	 Shopware 2.08.01-a
 * @subpackage  API - Class-Loader
 */
class sClassHandler
{
	private $sAPI = null;
	private $sType = null;
	protected $sClass = array();

	function __construct(&$sAPI, $sType)
	{
		$this->sType = $sType;
		$this->sAPI = & $sAPI;
	}

	function __get($class)
	{
		if(!isset($this->sClass[$class])) {
			if(!file_exists($this->sAPI->sPath."/engine/connectors/api/actindo/{$this->sType}/$class.php"))
				return false;
			include($this->sAPI->sPath."/engine/connectors/api/actindo/{$this->sType}/$class.php");
			$name = "s".ucfirst($class).ucfirst($this->sType);
			if(class_exists($name))
				$this->sClass[$class] = new $name;
			elseif(class_exists($class))
				$this->sClass[$class] = new $class;
			else
				return false;
			$this->sClass[$class]->sSystem = & $this->sAPI->sSystem;
			$this->sClass[$class]->sDB = & $this->sAPI->sDB;
			$this->sClass[$class]->sPath = & $this->sAPI->sPath;
			$this->sClass[$class]->sAPI = & $this->sAPI;
		}
		return $this->sClass[$class];
	}
}
