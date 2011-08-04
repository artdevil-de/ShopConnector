<?php

/**
 * various utilities
 *
 * actindo Faktura/WWS connector
 *
 * @package actindo
 * @author  Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 293 $
 * @copyright Copyright (c) 2007, Patrick Prasse (Schneebeerenweg 26, D-85551 Kirchheim, GERMANY, pprasse@actindo.de)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */
define('SHOP_TYPE_XTCOMMERCE', 'xtcommerce');
define('SHOP_TYPE_GAMBIOGX', 'gambiogx');
define('SHOP_TYPE_XTC4', 'xtcommerce4');
define('SHOP_TYPE_OSCOMMERCE', 'oscommerce');
define('SHOP_TYPE_SHOPWARE', 'shopware');
define('SHOP_TYPE_MAGENTO', 'magentocommerce');
define('SHOP_TYPE_OXID4', 'oxid4');

// act_get_shop_type in <shoptype>/util.php
// act_shop_get_capabilities in <shoptype>/util.php

function act_shop_is($type)
{
	return act_get_shop_type()==$type;
}
if(!function_exists('file_put_contents')) {

	function file_put_contents($filename, $data)
	{
		$fp = fopen($filename, 'w');
		if(!$fp)
			return false;
		$n = fwrite($fp, $data);
		fclose($fp);
		return $n;
	}
}

function _actindo_generic_mapper(&$input, &$map)
{
	$output = array();

	foreach ($input as $_key => $_val) {
		$_newkey = $map[$_key];
		if(!isset($_newkey)) {
			$varname = "['xtcshop']['{$_key}']";
		} else {
			parse_str($_newkey.'=1', $arr);
			$varname = _function_do_get_var($arr);
		}
		eval("\$output{$varname} = \$_val;");
	}

	return $output;
}

function _function_do_get_var($arr, $str='')
{
	list( $key, $newarr ) = each($arr);
	if(is_string($key)||is_float($key))
		$str .= sprintf("['%s']", $key);
	else
		$str .= sprintf("[%d]", $key);
	if(is_array($newarr))
		$str .= _function_do_get_var($newarr);
	return $str;
}

function var_dump_string($var)
{
	ob_start();
	var_dump($var);
	return ob_get_clean();
}

function encode_all_base64($data)
{
	if(is_array($data)) {
		if(count($data)) {
			foreach ($data as $idx => $val)
				$ret[$idx] = encode_all_base64($val);
		}
		else
			$ret = array();
	}
	else {
		if(is_numeric($data)||is_object($data))   // save overhead with numbers
			$ret = $data;
		else
			$ret = new xmlrpcval($data, $GLOBALS["xmlrpcBase64"]);
	}

	return $ret;
}

/**
 * @author derernst at gmx dot ch
 */
function decode_entities($text, $quote_style = ENT_COMPAT)
{
	if(function_exists('html_entity_decode')) {
		$text = html_entity_decode($text, $quote_style, 'ISO-8859-1'); // NOTE: UTF-8 does not work!
	} else {
		$trans_tbl = get_html_translation_table(HTML_ENTITIES, $quote_style);
		$trans_tbl = array_flip($trans_tbl);
		$text = strtr($text, $trans_tbl);
	}
	$text = preg_replace('/&#x([0-9a-f]+);/ei', 'chr(hexdec("\\1"))', $text);
	$text = preg_replace('/&#([0-9]+);/e', 'chr("\\1")', $text);
	return $text;
}

function get_col($mapping)
{
	return(!is_array($mapping) ? $mapping : '`'.$mapping[0].'`.`'.esc($mapping[1]).'`' );
}

function create_query_from_filter($request, $mapping, $default_order=null)
{
	$q_search = '1';
	$order = null;
	$limit = '0';

	if(isset($request[0]['field']))	  // just filter, no limit / order
		$filter = $request;
	else {
		if(isset($request['start'])&&isset($request['limit'])) {
			$limit = sprintf("%d OFFSET %d", $request['limit'], $request['start']);
		} else {
			$limit = '0';
		}

		if(is_null($default_order)) {
			$m = array_keys($mapping);
			$default_order = get_col($mapping[$m[0]]);
		}
		if(isset($request['sortColName'])&&!empty($request['sortColName'])) {
			$order_col = $request['sortColName'];
			if(!isset($mapping[$order_col])) {
				$order_col = $default_order;
			} else {
				$order_col = get_col($mapping[$order_col]);
			}
			$order_dir = ($request['sortOrder']<0||$request['sortOrder']==='ASC' ? 'ASC' : 'DESC');
		} else {
			$order_col = $default_order;
			$order_dir = 'DESC';
		}
		$order = trim($order_col.' '.$order_dir);

		$filter = $request['filter'];
	}


	if(isset($filter)&&is_array($filter)&&count($filter)) {
		$op = array(
			'lt' => '<',
			'gt' => '>',
			'eq' => '='
		);
		$q_filter = array();
		foreach ($filter as $_fi => $filter) {
			$fld = $filter['field'];
			$filter = $filter['data'];

			if(!isset($mapping[$fld]))
				return FALSE;

			list( $tablealias, $fld, $forcetype ) = $mapping[$fld];
			if(isset($forcetype)&&!empty($forcetype))
				$filter['type'] = $forcetype;

			if($filter['type']=='date') {
				$q = '`'.$tablealias.'`.`'.esc($fld).'`';
				$q .= $op[$filter['comparison']];
				$q .= "'".esc($filter['value'])."'";
			} else if($filter['type']=='list') {
				$vals = split(',', $filter['value']);
				$args = array();
				foreach ($vals as $_val) {
					$args[] = "'".esc($_val)."'";
				}
				$q = 'FIELD(`'.$tablealias.'`.`'.esc($fld).'`, '.join(', ', $args).')<>0';
			} else if($filter['type']=='boolean') {
				if($filter['value']==='false')
					$filter['value'] = 0;
				elseif($filter['value']==='true')
					$filter['value'] = 1;
				else
					$filter['value'] = (int)$filter['value'];

				$f1 = '`'.$tablealias.'`.`'.esc($fld).'`';
				$q = 'IFNULL('.$f1.',0)'.($filter['value'] ? '<>0' : '=0');
			}
			else if($filter['type']=='string') {
				$q = '`'.$tablealias.'`.`'.esc($fld).'` LIKE \'%'.esc($filter['value']).'%\'';
			} else if($filter['type']=='numeric') {
				$q = '`'.$tablealias.'`.`'.esc($fld).'`';
				$q .= $op[$filter['comparison']];
				$q .= sprintf("%f", (float)$filter['value']);
			}
			$q_filter[] = $q;
		}
		if(count($q_filter))
			$q_search = '('.join(' AND ', $q_filter).')';
	}

	$ret = compact('q_search');
	if(!is_null($order))
		$ret['order'] = $order;
	if(!is_null($limit))
		$ret['limit'] = $limit;
	else
		$ret['limit'] = "2147483647 OFFSET 0";

	return $ret;
}
if(!function_exists('stripos')) {

	function stripos($haystack, $needle)
	{
		return strpos(strtolower($haystack), strtolower($needle));
	}
}

/**
 * Add last slash to a directory if last char is not a slash
 *
 * @param string $path Path to add last slash to
 * @returns string Path with last slash added
 */
function add_last_slash($path)
{
	if(substr($path, strlen($path)-1, 1)!='/')
		$path .= '/';
	return $path;
}

?>