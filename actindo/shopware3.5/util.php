<?php

/**
 * various utilities forshopware
 *
 * actindo Faktura/WWS connector
 *
 * @package actindo
 * @author  Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 442 $
 * @copyright Copyright (c) 2008, Patrick Prasse (Schneebeerenweg 26, D-85551 Kirchheim, GERMANY, pprasse@actindo.de)
 * @author  Holger Ronecker
 * @link    http://artdevil.de/ShopConnector ShopConnector Seite auf ArtDevil.de
 * @copyright Copyright (c) 2011, Holger Ronecker, devil@artdevil.de
 */

/**
 * @done
 */
function default_lang()
{
	$langs = actindo_get_languages();
	foreach ($langs as $_langid => $_lang)
		if($_lang['is_default'])
			return (int)$_langid;

	return 1;
}

function esc($text)
{
	global $export, $import;
	if(is_object($export))
		$db = $export->sDB;
	else if(is_object($import))
		$db = $import->sDB;

	$str = $db->Quote($text);
	if($str{0}=="'"||$str{0}=='"')
		$str = substr($str, 1, strlen($str)-2);
	return $str;
}

function shopware_get_version()
{
	global $export;
	$v = isset($export->sSystem->sCONFIG['sVERSION']) ? $export->sSystem->sCONFIG['sVERSION'] : '2.0.3';
	return $v;
}

function is_shopware3()
{
	$v = split('#', shopware_get_version());
	return version_compare($v[0], '3.0.0', 'ge');
}

function act_have_table($name)
{
	global $export, $import;
	if(is_object($export))
		$db = $export->sDB;
	else if(is_object($import))
		$db = $import->sDB;
	else
		return FALSE;

	global $act_have_table_cache;
	is_array($act_have_table_cache)or$act_have_table_cache = array();
	if(isset($act_have_table_cache[$name]))
		return $act_have_table_cache[$name];

	$have = FALSE;
	$res = $db->Execute("SHOW TABLES LIKE '".esc($name)."'");
	while ($n = $res->FetchRow()) { // get mixed case here, therefore check again
		reset($n);
		$n = current($n);
		if(!strcmp($n, $name)) {
			$have = TRUE;
			break;
		}
	}
	$res->Close();
	$act_have_table_cache[$name] = $have;
	return $have;
}

function actindo_get_table_fields($table)
{
	global $export;

	$cols = array();
	$result = $export->sDB->GetAll("DESCRIBE $table");
	while($row = array_shift($result)) {
		$cols[] = array_shift($row);
	}
	return $cols;
}

function actindo_attribute_translation_table()
{
	return array(
		"attr10" => "fsk18",
		"attr6" => "products_ean",
	);
}

function actindo_get_salutation_map()
{
	return array(
		'mr' => 'Herr',
		'mrs' => 'Frau',
		'ms' => 'Frau',
		'company' => 'Firma',
	);
}

function actindo_get_paymentmeans()
{
	global $export;
	$rows = array();
	$sql = "SELECT * FROM `s_core_paymentmeans` WHERE 1";
	if(is_object($result = $export->sDB->Execute($sql))) {
		while ($row = $result->FetchRow())
			$rows[(int)$row["id"]] = $row;
	}
	return $rows;
}

/**
 * @done
 */
function actindo_get_languages()
{
	global $export, $__actindo_lang_cache;

	if(!is_array($__actindo_lang_cache)||!count($__actindo_lang_cache)) {
		$langs = array();

		$sql = "SELECT id AS language_id, isocode AS language_name, isocode AS language_code, locale, isocode as _shopware_code FROM `s_core_multilanguage` ORDER BY `default` DESC, `id` ASC";
		$res = act_db_query($sql);
		while ($row = act_db_fetch_assoc($res)) {
			if(is_numeric($row['isocode'])||empty($row['isocode'])) {
				$locale = act_get_row("SELECT `locale` FROM `s_core_locales` WHERE `id`=".(int)$row['locale']);
				$locale = split('_', $locale['locale']);
				$row['language_code'] = $row['language_name'] = $locale[0];
			}
			unset($row['locale']);
			$row['language_id'] = (int)$row['language_id'];
			$langs[$row['language_id']] = $row;
		}
		act_db_free($res);

		if(!count($langs)) {
			$langs = array(1 => array('language_id' => 1, 'language_name' => 'Standardsprache', 'language_code' => 'de', '_shopware_code' => 'de', 'is_default' => 1));
		} else {
			// set first language default
			reset($langs);
			$k = key($langs);
			$langs[$k]['is_default'] = 1;
		}
		$__actindo_lang_cache = $langs;
	}

	return $__actindo_lang_cache;
}

function actindo_get_multistores() {
	global $__actindo_multistore_cache;

	if(!is_array($__actindo_multistore_cache) || empty($__actindo_multistore_cache)) {
		$result = act_db_query('
			SELECT `id`, `name`, `isocode`, `default`, `domainaliase`
			FROM `s_core_multilanguage`
			ORDER BY `default` DESC, `id` ASC
		');
		$shops = array();
		while($row = act_db_fetch_assoc($result)) {
			// id
			$id = (int) $row['id'];

			// name
			if(!empty($row['name'])) {
				$name = $row['name'];
			} else {
				if(!empty($row['default'])) {
					$name = 'Main Store';
				} else {
					$name = sprintf('%s (%d)', $row['isocode'], $row['id']);
				}
			}

			// url
			if(!empty($row['domainaliase'])) {
				list($url) = explode("\n", $row['domainaliase']);
				$urlHttp = 'http://' . trim($url);
			} else {
				$urlHttp = '';
			}

			$shops[$id] = array(
				'id' => $id,
				'name' => $name,
				'url_http' => $urlHttp,
				'active' => 1,
			);
		}
		act_db_free($result);
		$__actindo_multistore_cache = $shops;
	}

	return $__actindo_multistore_cache;
}

function get_language_id_by_code($code)
{
	global $_language_id_by_code;
	if(!is_array($_language_id_by_code)) {
		$_language_id_by_code = array();
		foreach (actindo_get_languages() as $row)
			$_language_id_by_code[$row['language_code']] = (int)$row['language_id'];
	}
	return $_language_id_by_code[$code];
}

function get_language_code_by_id($languages_id)
{
	global $_language_code_by_id;
	if(!is_array($_language_code_by_id)) {
		$_language_code_by_id = array();
		foreach (actindo_get_languages() as $row)
			$_language_code_by_id[(int)$row['language_id']] = $row['language_code'];
	}
	return $_language_code_by_id[(int)$languages_id];
}

function actindo_get_fields($attr_fields=TRUE, $filter_fields=TRUE)
{
	global $export;
	$fields = $field_sets = array();
	$xlation_fields = actindo_attribute_translation_table();

	$field_sets[] = array('id' => 0, 'name' => 'Shopware');

	if($attr_fields) {
		$sql = "SELECT `databasefield`, `required`, `position`, `domdescription`, `domtype`, `help`, `multilanguage` FROM `s_core_engine_elements` WHERE `group`=7 ORDER BY `position` DESC";
		if(is_object($result = $export->sDB->Execute($sql))) {
			$domtype_translation = array(
				'text' => 'textfield',
				'price' => 'numberfield',
				'textarea' => 'textarea',
				'select' => 'combobox',
				'boolean' => 'boolean',
				'date' => 'datefield',
				'time' => 'timefield',
			);
			while ($row = $result->FetchRow()) {
				if(isset($xlation_fields[$row['databasefield']]))  // if this field is otherwise already in use (EAN for example)
					continue;

				$type = isset($domtype_translation[$row['domtype']]) ? $domtype_translation[$row['domtype']] : 'textfield';

				$f = array(
					'field_id' => $row['databasefield'],
					'field_name' => $row['domdescription'],
					'field_i18n' => $row['multilanguage'] ? 1 : 0,
					'field_set' => 'Shopware',
					'field_set_ids' => array(0),
					'field_help' => $row['help'],
					'field_noempty' => $row['required'] ? 1 : 0,
					'field_type' => $type,
				);
				if($type=='combobox') {
					$sql = "SELECT DISTINCT description, domvalue FROM s_core_engine_values WHERE domelement='".$row['databasefield']."' ORDER BY position ASC";
					if(is_object($result_field_values = $export->sDB->Execute($sql))) {
						while ($row_field_values = $result_field_values->FetchRow()) {
							$f['field_values'][] = array('value' => $row_field_values['domvalue'], 'text' => $row_field_values['description']);
						}
					}
					//        $f['field_values'] = array( array('value'=>'1', 'text'=>'Eins'), array('value'=>'2', 'text'=>'Zwei') );
				}
				$fields[$row['databasefield']] = $f;
			}
		}
	}

	if(is_shopware3()&&$filter_fields) {
		$sql = "SELECT o.`id`, o.`name`, o.`filterable`, o.`default`, GROUP_CONCAT(r.groupID SEPARATOR '|') AS groups FROM `s_filter_options` AS o LEFT JOIN `s_filter_relations` AS r ON (r.`optionID`=o.id) WHERE 1 GROUP BY o.`id` ORDER BY o.`id` ASC";
		if(is_object($result = $export->sDB->Execute($sql))) {
			while ($row = $result->FetchRow()) {
				$field_id = 'filter'.(int)$row['id'];
				$f = array(
					'field_id' => $field_id,
					'field_name' => $row['name'],
					'field_i18n' => 0,
					'field_set' => 'Shopware-Filter',
					'field_set_ids' => !empty($row['groups']) ? array_map('intval', split('\|', $row['groups'])) : array(),
					'field_noempty' => 0,
					'field_type' => 'textfield',
				);
				$fields[$field_id] = $f;
			}
		}

		$sql = "SELECT o.`id`, o.`name`, o.`comparable`, o.`position` FROM `s_filter` AS o WHERE 1 ORDER BY o.`id` ASC";
		if(is_object($result = $export->sDB->Execute($sql))) {
			while ($row = $result->FetchRow()) {
				$field_sets[] = array('id' => (int)$row['id'], 'name' => 'Filter: '.$row['name'], 'comparable' => (int)$row['comparable'], 'position' => (int)$row['position']);
			}
		}
	}

	return compact('fields', 'field_sets');
}

function _fix_description_languages($languages, $data)
{
	$ret = array();
	foreach ($data as $_key => $_d) {
		foreach ($languages as $_langid => $_lang)
			$ret[$_key][$_langid] = $_d['description'];
	}
	return $ret;
}

function get_customergroups()
{
	global $export;
	$sql = "SELECT * FROM `s_core_customergroups`";
	$customer_groups = $export->sDB->GetAssoc($sql, false, $force_array = true);
	return $customer_groups;
}

function get_preisgruppe_translation()
{
	$customer_groups = get_customergroups();

	$customers_status = array();
	foreach ($customer_groups as $_key => $_status)
		$customers_status[(int)$_key] = $_status['groupkey'];

	return $customers_status;
}

function get_tax_rate($tax_id)
{
	global $export;

	$sql = "SELECT `tax` FROM `s_core_tax` WHERE `id`=".(int)$tax_id;
	if(is_object($result = $export->sDB->Execute($sql))) {
		$row = $result->FetchRow();
		return (float)$row['tax'];
	}
	else
		return null;
}

function check_admin_pass($pass, $login=null)
{
	global $export;
	global $connectorlogin;
	!is_null($login)or$login = 'actindo';
	$sql = "SELECT IF(`password`=".$export->sDB->Quote($pass).", 1, 0) AS okay, name FROM `s_core_auth` WHERE `username`=".$export->sDB->Quote($login);
	if(is_object($result = $export->sDB->Execute($sql))) {
		$row = $result->FetchRow();
		$connectorlogin = $row['name'];
		if($row['okay']>0)
			return TRUE;
	}

	return FALSE;
}

function act_get_shop_type()
{
	return 'shopware';
}

function actindo_create_temporary_file($data)
{
	global $import;

	$tmp_name = tempnam("/tmp", "");
	if($tmp_name===FALSE||!is_writable($tmp_name))
		$tmp_name = tempnam(ini_get('upload_tmp_dir'), "");
	if($tmp_name===FALSE||!is_writable($tmp_name))
		$tmp_name = tempnam(SHOPWARE_BASEPATH.$import->sSystem->sCONFIG['sARTICLEIMAGES'], "");   // last resort: try sArticleImages
	if($tmp_name===FALSE||!is_writable($tmp_name))
		return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Konnte keine tempöräre Datei anlegen');
	$written = file_put_contents($tmp_name, $data);
	if($written!=strlen($data)) {
		$ret = array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim schreiben des Bildes in das Dateisystem (Pfad '.var_dump_string($tmp_name).', written='.var_dump_string($written).', filesize='.var_dump_string(@filesize($tmp_name)).')');
		unlink($tmp_name);
		return $ret;
	}

	return array('ok' => TRUE, 'file' => $tmp_name);
}

function actindo_get_translation($objecttype, $objectkey)
{
	global $export;
	$langs = actindo_get_languages();
	$rows = array();

	$sql = "SELECT `objectdata`, `objectlanguage` FROM `s_core_translations` WHERE `objecttype`='{$objecttype}' AND `objectkey`=".(int)$objectkey;
	if(is_object($result = $export->sDB->Execute($sql))) {
		while ($row = $result->FetchRow()) {
			foreach ($langs as $_lang) {
				if($_lang['_shopware_code']==$row['objectlanguage'])
					$rows[$_lang['language_code']] = unserialize($row["objectdata"]);
			}
		}
	}

	return $rows;
}

/**
 * Export Preis with right brutto / netto
 */
function export_price($price_netto, $net, $tax_percent)
{
	return round(str_replace(',', '.', $price_netto), 2);
}

/**
 * Import Preis with right brutto / netto
 */
function import_price($price, $is_brutto, $tax_percent)
{
	// look ma, no rounding!
	if(!$is_brutto)
		return $price;
	else
		return $price/(1+($tax_percent/100));
}

/**
 * Date conversion from YYYY-MM-DD HH:MM:SS to unix timestamp
 *
 * @param string Date in format 'YYYY-MM-DD HH:MM:SS'
 * @returns int Unix timestamp, or -1 if out of range
 */
function datetime_to_timestamp($date)
{
	preg_match('/(\d+)-(\d+)-(\d+)\s+(\d+):(\d+)(:(\d+))/', $date, $date);
	if((!((int)$date[1])&&!((int)$date[2])&&!((int)$date[0])))
		return -1;
	return mktime((int)$date[4], (int)$date[5], (int)$date[7], (int)$date[2], (int)$date[3], (int)$date[1]);
}

function act_db_query($text)
{
	global $export;

	try {
		return $export->sDB->Execute($text);
	} catch (Exception $e) {
		trigger_error(var_dump_string($e), E_USER_NOTICE);
		return FALSE;
	}
}

function act_db_fetch_assoc(&$res)
{
	if(!is_object($res) /* || !function_exists(array(&$res, 'FetchRow')) */)
		return FALSE;
	return $res->FetchRow();
}

function act_db_free(&$res)
{
	if(!is_object($res) /* || !function_exists(array(&$res, 'Close')) */)
		return FALSE;
	return $res->Close();
}

function act_insert_id()
{
	global $export;
	return $export->sDB->Insert_ID();
}

function act_quote($str)
{
	global $export;
	return $export->sDB->qstr($str);
}

function act_get_row($sql)
{
	$res = act_db_query($sql);
	if(!is_object($res))
		return FALSE;
	$row = act_db_fetch_assoc($res);
	if($row===FALSE) // no more rows
		return null;
	act_db_free($res);
	return $row;
}

function act_get_array($sql)
{
	$res = act_db_query($sql);
	if(!is_object($res))
		return FALSE;
	$arr = array();
	while ($row = act_db_fetch_assoc($res))
		$arr[] = $row;
	act_db_free($res);
	return $arr;
}

function act_db_num_rows(&$res)
{
	if(!is_object($res))
		return FALSE;
	return $res->RecordCount();
}

function act_db_error()
{
	global $export;
	return $export->sDB->ErrorMsg();
}

function act_get_shopware_errors($errors)
{
	$errors_str = array();
	foreach ($errors as $_err) {
		$errors_str[] = sprintf("Code %d: %s", $_err['code'], $_err['message']);
	}
	return implode("\n", $errors_str);
}

function actindo_do_checksums($subdirectory='', $pattern='*', $checksum_type='MD5', $recursive=TRUE)
{
	$path = add_last_slash(ACTINDO_SHOP_BASEDIR).$subdirectory;
	if(is_file($path)) {
		$files_arr = array($subdirectory => _checksum_file($path, $checksum_type));
	} else {
		$files_arr = array();
		$files_arr_2 = _checksum_dir($path, $pattern, $checksum_type, $recursive);
		foreach ($files_arr_2 as $_fn => $_cs) {
			$_fn = substr($_fn, strlen($path));
			$files_arr[$_fn] = $_cs;
		}
	}

	$conn_relative_dir = 'engine/connectors/api/actindo/';

	foreach (array_keys($files_arr) as $fn) {
		if(strpos($fn, $conn_relative_dir)===0) {
			$fn1 = strtr($fn, array($conn_relative_dir => 'SHOPCONN-'.constant('ACTINDO_PROTOCOL_REVISION').'/'));
			$files_arr[$fn1] = $files_arr[$fn];
			unset($files_arr[$fn]);
		}
	}

	return array('ok' => TRUE, 'basedir' => $path, 'files' => $files_arr);
}

function _checksum_dir($dirname, $pattern, $checksum_type, $recursive)
{
	$dirs = array();
	$files = array();

	$dir = opendir($dirname);
	if(!is_resource($dir))
		return FALSE;

	while ($fn = readdir($dir)) {
		if($fn=='.'||$fn=='..')
			continue;

		if($fn=='templates_c')
			continue;
		if($fn=='cache')
			continue;

		$basename = $fn;
		$fn = add_last_slash($dirname).$fn;

		if(is_dir($fn))
			$dirs[] = $fn;
		else if(is_file($fn)&&(!function_exists('fnmatch')||fnmatch($pattern, $basename))) {
			$files[$fn] = _checksum_file($fn, $checksum_type);
		}
	}
	closedir($dir);

	if($recursive&&count($dirs)) {
		foreach ($dirs as $_dir) {
			$files = array_merge($files, _checksum_dir($_dir, $pattern, $checksum_type, $recursive));
		}
	}

	return $files;
}

function _checksum_file($fn, $checksum_type='MD5')
{
	if(!is_readable($fn)) {
		return 'UNREADABLE';
	}

	if(empty($checksum_type))
		return 'NO-CHECKSUM-TYPE';

	if($checksum_type=='FILESIZE')
		return filesize($fn);

	$data = file_get_contents($fn);
	if($checksum_type=='MD5') {
		$data = md5($data);
	} else if($checksum_type=='SHA1') {
		$data = sha1($data);
	} else if($checksum_type=='MD5-TRIM') {
		$data = strtr($data, array("\r" => "", "\n" => "", "\t" => "", " " => ""));
		$data = md5(trim($data));
	} else if($checksum_type=='SHA1-TRIM') {
		$data = strtr($data, array("\r" => "", "\n" => "", "\t" => "", " " => ""));
		$data = sha1(trim($data));
	} else if($checksum_type=='SIZE') {
		$data = strlen($data);
	} else if($checksum_type=='SIZE-TRIM') {
		$data = strtr($data, array("\r" => "", "\n" => "", "\t" => "", " " => ""));
		$data = strlen(trim($data));
	}
	return $data;
}

function aGetCategoryParents($categoryID, $limit = null) {
	global $import;
	$parents = array();
	while($categoryID > 1 && ($limit === null || count($parents) < $limit)) {
		$categoryID = (int) $import->sDB->GetOne(sprintf('SELECT `parent` FROM `s_categories` WHERE `id` = %d', $categoryID));
		if(!empty($categoryID)) {
			$parents[] = $categoryID;
		}
	}
	return array_reverse($parents);
}

?>