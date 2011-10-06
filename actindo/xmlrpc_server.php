<?php

/**
 * xmlrpc server
 * 
 * actindo Faktura/WWS connector
 *
 *
 * @package actindo
 * @author  Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 376 $
 * @copyright Copyright (c) 2007, Patrick Prasse (Schneebeerenweg 26, D-85551 Kirchheim, GERMANY, pprasse@actindo.de)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author  Holger Ronecker
 * @link    http://artdevil.de/ShopConnector ShopConnector Seite auf ArtDevil.de
 * @copyright Copyright (c) 2011, Holger Ronecker, devil@artdevil.de
 */
define('ACTINDO_TRANSPORT_CHARSET', 'ISO8859-1');

/**
 * generic error reporter 
 *
 * error reporter for errors occuring during init stage
 * not used anymore after we initialized xmlrpc_server
 *
 */
function _actindo_report_init_error($faultCode, $faultString)
{
	$faultCode = (int)$faultCode;
//  $faultString = 
	header("Content-Type: text/xml");
	$errstr = <<<END
<?xml version="1.0"?>
<methodResponse>
<fault>
<value>
<struct>
<member><name>faultCode</name>
<value><int>{$faultCode}</int></value>
</member>
<member>
<name>faultString</name>
<value><string>{$faultString}</string></value>
</member>
</struct>
</value>
</fault>
</methodResponse>
END;
	echo $errstr;
	exit();
}
/* initialize error handling */
$GLOBALS['actindo_occured_errors'] = array();
ini_set('display_errors', "1");
error_reporting(E_ALL&~E_NOTICE);
set_error_handler('actindo_error_handler');

require_once( 'error.php' );
require_once( 'util.php' );
require_once( 'interface.php' );

if(!is_readable($f = 'xmlrpc/xmlrpc.inc'))
	_actindo_report_init_error(14, 'file '.$f.' does not exist');
require_once( $f );

if(!is_readable($f = 'xmlrpc/xmlrpcs.inc'))
	_actindo_report_init_error(14, 'file '.$f.' does not exist');
require_once( $f );

if(!is_dir($d = ACTINDO_CONNECTOR_SHOP_DIR))
	_actindo_report_init_error(14, 'directory '.$d.' does not exist');

if(!is_readable($f = ACTINDO_CONNECTOR_SHOP_DIR.'actindo.php'))
	_actindo_report_init_error(14, 'file '.$f.' does not exist');
require_once( $f );


ini_set('display_errors', "1");
error_reporting(E_ALL&~E_NOTICE);
set_error_handler('actindo_error_handler');

if(!defined('ACTINDO_SHOP_CHARSET'))
	define('ACTINDO_SHOP_CHARSET', ACTINDO_TRANSPORT_CHARSET);


/* xmlrpc server */
$arr = array(
	'product.count' => array('function' => 'product_count'),
	'product.get' => array('function' => 'product_get'),
	'product.create_update' => array('function' => 'product_create_update'),
	'product.update_stock' => array('function' => 'product_update_stock'),
	'product.delete' => array('function' => 'product_delete'),
	'settings.get' => array('function' => 'settings_get'),
	'category.get' => array('function' => 'categories_get'),
	'category.action' => array('function' => 'category_action'),
	'orders.count' => array('function' => 'orders_count'),
	'orders.list' => array('function' => 'orders_list'),
	'orders.list_positions' => array('function' => 'orders_list_positions'),
	'orders.set_status' => array('function' => 'orders_set_status'),
	'orders.set_trackingcode' => array('function' => 'orders_set_trackingcode'),
	'customer.set_deb_kred_id' => array('function' => 'customer_set_deb_kred_id'),
	'customers.count' => array('function' => 'customers_count'),
	'customers.list' => array('function' => 'customers_list'),
	'actindo.get_connector_version' => array('function' => 'actindo_get_connector_version'),
	'actindo.set_token' => array('function' => 'actindo_set_token'),
	'actindo.get_time' => array('function' => 'actindo_get_time'),
	'actindo.ping' => array('function' => 'actindo_ping'),
	'actindo.checksums' => array('function' => 'actindo_checksums'),
);


if(function_exists('actindo_get_cryptmode')&&isset($_REQUEST['get_cryptmode'])) {
	$str = actindo_get_cryptmode();
	$str .= (strlen($str) ? '&' : '').'&connector_type=XMLRPC';
	echo $str;
	return;
}

// ob_start();

if(!isset($HTTP_RAW_POST_DATA))
	$HTTP_RAW_POST_DATA = file_get_contents("php://input");

$s = new xmlrpc_server($arr);

/**
 * Error handler
 */
function actindo_error_handler($errno, $errstr, $errfile=null, $errline=null, $errcontext=null)
{
	global $actindo_occured_errors;
	if(($errno&error_reporting())==0)
		return;
	$actindo_occured_errors[] = array($errno, $errstr, $errfile, $errline);
}

function actindo_get_connector_version($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	$arr0 = array();
	shop_get_connector_version($arr0, $params);

	$revision = '$Revision: 376 $';
	$arr = array(
		'xmlrpc_server_revision' => $revision,
		// 'protocol_version' => set in shop_get_connector_version,
		'interface_type' => ACTINDO_CONNECTOR_TYPE,
		// 'shop_type' => set in shop_get_connector_version,
		// 'shop_version' => set in shop_get_connector_version
		// 'capabilities' => set in shop_get_connector_version,
		'php_version' => is_callable('phpversion') ? phpversion() : '0.0.0',
		'zend_version' => is_callable('zend_version') ? zend_version() : '0.0.0',
		'cpuinfo' => @file_get_contents('/proc/cpuinfo'),
		'meminfo' => @file_get_contents('/proc/meminfo'),
		'extensions' => array(),
	);
	foreach (get_loaded_extensions() as $_name)
		$arr['extensions'][$_name] = phpversion($_name);

	if(is_callable('phpinfo')) {
		ob_start();
		phpinfo();
		$c = ob_get_contents();
		ob_end_clean();
		$arr['phpinfo'] = new xmlrpcval($c, $GLOBALS["xmlrpcBase64"]);
	}

	$arr = array_merge($arr0, $arr);

	$default_capabilities = array(
		'artikel_vpe' => 1,
		'artikel_shippingtime' => 1,
		'artikel_properties' => 0,
		'artikel_contents' => 1,
		'wg_sync' => 0,
	);
	$arr['capabilities'] = array_merge($default_capabilities, $arr['capabilities']);

	return resp($arr);
}

function actindo_ping($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	$res = array('ok' => TRUE, 'pong' => 'pong');
	return resp($res);
}

function parse_args(&$params, &$ret)
{
	$param = $params->getParam(0);
	if(is_null($param)||!is_object($param)) {
		$ret = xmlrpc_error(ELOGINFAILED);
		return 0;
	} else {
		list( $pass, $login ) = split('\|\|\|', $param->getval());
		if(check_admin_pass($pass, $login)) {
			$params = php_xmlrpc_decode($params);
			array_shift($params);
			$params = actindo_preprocess_request($params);
			ob_start();
			return 1;
		}
	}

	$ret = xmlrpc_error(ELOGINFAILED);
	return 0;
}

function resp($arr)
{
	global $actindo_occured_errors;

	// maybe convert charsets
	$arr = actindo_preprocess_response($arr);

	if(is_array($arr)) {
		$arr['__shop_errors'] = $actindo_occured_errors;
		$arr['__shop_obcontents'] = ob_get_contents();
	}
	ob_end_clean();


	$val = php_xmlrpc_encode(encode_all_base64($arr));
	return new xmlrpcresp($val);
}

function xmlrpc_error($code, $string=null)
{
	ob_end_clean();
	if($code==0&&!empty($string))
		return new xmlrpcresp(0, EUNKNOWN, $string);

	return new xmlrpcresp(0, $code, !empty($string) ? $string : strerror($code));
}
$GLOBALS['cp1252_map'] = array(
	"\xc2\x80" => "\xe2\x82\xac", /* EURO SIGN */
	"\xc2\x82" => "\xe2\x80\x9a", /* SINGLE LOW-9 QUOTATION MARK */
	"\xc2\x83" => "\xc6\x92", /* LATIN SMALL LETTER F WITH HOOK */
	"\xc2\x84" => "\xe2\x80\x9e", /* DOUBLE LOW-9 QUOTATION MARK */
	"\xc2\x85" => "\xe2\x80\xa6", /* HORIZONTAL ELLIPSIS */
	"\xc2\x86" => "\xe2\x80\xa0", /* DAGGER */
	"\xc2\x87" => "\xe2\x80\xa1", /* DOUBLE DAGGER */
	"\xc2\x88" => "\xcb\x86", /* MODIFIER LETTER CIRCUMFLEX ACCENT */
	"\xc2\x89" => "\xe2\x80\xb0", /* PER MILLE SIGN */
	"\xc2\x8a" => "\xc5\xa0", /* LATIN CAPITAL LETTER S WITH CARON */
	"\xc2\x8b" => "\xe2\x80\xb9", /* SINGLE LEFT-POINTING ANGLE QUOTATION */
	"\xc2\x8c" => "\xc5\x92", /* LATIN CAPITAL LIGATURE OE */
	"\xc2\x8e" => "\xc5\xbd", /* LATIN CAPITAL LETTER Z WITH CARON */
	"\xc2\x91" => "\xe2\x80\x98", /* LEFT SINGLE QUOTATION MARK */
	"\xc2\x92" => "\xe2\x80\x99", /* RIGHT SINGLE QUOTATION MARK */
	"\xc2\x93" => "\xe2\x80\x9c", /* LEFT DOUBLE QUOTATION MARK */
	"\xc2\x94" => "\xe2\x80\x9d", /* RIGHT DOUBLE QUOTATION MARK */
	"\xc2\x95" => "\xe2\x80\xa2", /* BULLET */
	"\xc2\x96" => "\xe2\x80\x93", /* EN DASH */
	"\xc2\x97" => "\xe2\x80\x94", /* EM DASH */

	"\xc2\x98" => "\xcb\x9c", /* SMALL TILDE */
	"\xc2\x99" => "\xe2\x84\xa2", /* TRADE MARK SIGN */
	"\xc2\x9a" => "\xc5\xa1", /* LATIN SMALL LETTER S WITH CARON */
	"\xc2\x9b" => "\xe2\x80\xba", /* SINGLE RIGHT-POINTING ANGLE QUOTATION */
	"\xc2\x9c" => "\xc5\x93", /* LATIN SMALL LIGATURE OE */
	"\xc2\x9e" => "\xc5\xbe", /* LATIN SMALL LETTER Z WITH CARON */
	"\xc2\x9f" => "\xc5\xb8" /* LATIN CAPITAL LETTER Y WITH DIAERESIS */
);

/**
 * Decode utf8 to latin1 obeying special characters (like euro)
 */
function actindo_utf8_decode($str)
{
	// no worky somehow
//  return utf8_decode( strtr($str, array_flip($GLOBALS['cp1252_map'])) );
	return utf8_decode($str);
}

function actindo_utf8_encode($str)
{
	// no worky somehow
//  return utf8_decode( strtr($str, array_flip($GLOBALS['cp1252_map'])) );
	return utf8_encode($str);
}

function utf8_decode_recursive($arr)
{
	if(is_array($arr)) {
		foreach ($arr as $_key => $_val) {
			if($_key==='images')
				$arr[$_key] = $_val;
			else {
				if(is_array($_val))
					$arr[$_key] = utf8_decode_recursive($_val);
				else if(is_integer($_val))
					$arr[$_key] = $_val;
				else if(is_string($_val))
					$arr[$_key] = actindo_utf8_decode($_val);
				else
					$arr[$_key] = $_val;
			}
		}
	}
	else if(is_string($arr))
		$arr = actindo_utf8_decode($arr);
	// other types: do nada

	return $arr;
}

function utf8_encode_recursive($arr)
{
	if(is_array($arr)) {
		foreach ($arr as $_key => $_val) {
			if($_key==='images')
				$arr[$_key] = $_val;
			else {
				if(is_array($_val))
					$arr[$_key] = utf8_encode_recursive($_val);
				else if(is_string($_val))
					$arr[$_key] = actindo_utf8_encode($_val);
				else
					$arr[$_key] = $_val;
			}
		}
	}
	else if(is_string($arr))
		$arr = actindo_utf8_encode($arr);
	// other types: do nada

	return $arr;
}

function actindo_preprocess_response($val)
{
	// value in $val is utf-8, transport is iso8859-1
	if(ACTINDO_SHOP_CHARSET=='UTF-8'&&ACTINDO_TRANSPORT_CHARSET=='ISO8859-1')
		$val = utf8_decode_recursive($val);
	return $val;
}

function actindo_preprocess_request($val)
{
	// value in $val is utf-8, transport is iso8859-1
	if(ACTINDO_SHOP_CHARSET=='UTF-8'&&ACTINDO_TRANSPORT_CHARSET=='ISO8859-1') {
		$val = utf8_encode_recursive($val);
	}
	return $val;
}

function actindo_checksums($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	if(!function_exists('actindo_do_checksums'))
		return resp(array('ok' => FALSE, 'errno' => ENOSYS, 'error' => 'Function actindo_do_checksums does not exist'));

	$res = call_user_func_array('actindo_do_checksums', $params);
	return resp($res);
}

?>