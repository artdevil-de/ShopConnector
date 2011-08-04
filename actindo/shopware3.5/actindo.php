<?php
error_reporting(E_ALL);
/**
 * xmlrpc server
 **
 * actindo Faktura/WWS connector
 *
 * @package actindo
 * @author  Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 386 $
 * @copyright Copyright (c) 2007, Patrick Prasse (Schneebeerenweg 26, D-85551 Kirchheim, GERMANY, pprasse@actindo.de)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

if( ACTINDO_CONNECTOR_TYPE == 'shopware3.5' || ACTINDO_CONNECTOR_TYPE == 'shopware3.04' || ACTINDO_CONNECTOR_TYPE == 'shopware3.03' )     // do not use is_shopware3 here...
  require_once('api.php');
else
  require_once('../api.php');


define( 'SHOPWARE_BASEPATH', realpath(dirname($_SERVER['SCRIPT_FILENAME']).'/../../../../') );


$api = new sAPI();
$mapping =& $api->convert->mapping;
$import =& $api->import->shopware;
$export =& $api->export->shopware;

$sprache = 2;

require_once( ACTINDO_CONNECTOR_SHOP_DIR.'util.php' );

require_once( 'export_products.php' );
require_once( 'export_orders.php' );
require_once( 'export_customers.php' );
require_once( 'import_products.php' );


/**
 * @done
 */
function categories_get( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  global $export, $sprache;
  $mask = array(
    "childs" => "children",
    "id" => "categories_id",
    "parent" => "parent_id",
    "description" => "categories_name"
  );
  $cat = $export->sCategoryTree($mask);

  $cats = array();
  foreach( array_keys(actindo_get_languages()) as $_langid )
    $cats[$_langid] = $cat;

  return resp( array( 'ok' => TRUE, 'categories' => $cats ) );
}

function category_action( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  global $export;
  $default_lang = default_lang();

  list( $point, $id, $pid, $aid, $data ) = $params;

  $position = 0;
  if( ($pid || $aid) && $point != 'delete' && $point != 'textchange' )
  {
    $sql = "SELECT MAX(position) AS position FROM `s_categories` WHERE ".($aid ? "`id`=".(int)$aid : "`parent`=".(int)$pid)." GROUP BY 1=1";
    $row = act_get_row($sql);
    if( is_array($row) && count($row) )
    {
      $position = (int)$row['position'] + 1;
    }
  }

  if( $point == 'add' )
  {
    if( $position )
    {
      $sql = "UPDATE `s_categories` SET position=position+1 WHERE position>={$position} AND parent=".(int)$pid;
      $res = act_db_query($sql);
    }

    // Beim anlegen von neuen Warengruppen, standard Template setzen und Gruppe aktivieren
    if ($position < 1)
      $position = 1;

    $sql = "SELECT * FROM `s_core_config` WHERE `name` LIKE 'sCATEGORY_DEFAULT_TPL'";
    $row = act_get_row($sql);
    if(!(is_array($row)))
    {
      $row['value'] = 'article_listing_3col.tpl';
    }

    $category = array(
      'id' => 'NULL',
      'parent' => (int)$pid,
      'description' => act_quote($data['description'][$default_lang]['name']),
      'position' => (int)$position,
      'template' => $export->sDB->qstr($row['value']),
      'active' => 1,
    );

    $sql = "INSERT INTO `s_categories` (id, parent, position, description, template, active) VALUE ({$category['id']}, {$category['parent']}, {$category['position']}, {$category['description']}, {$category['template']}, {$category['active']})";
    act_db_query($sql);
    $category['id'] = act_insert_id();
    if( $category['id'] )
      return resp( array('ok' => TRUE, 'id'=>$category['id']) );
    else
      return xmlrpc_error( EIO );
  }
  else if( $point == 'delete' )
  {
    $sql = "DELETE FROM `s_categories` WHERE id=".(int)$id." OR parent=".(int)$id;
    act_db_query($sql);
    $sql = "DELETE FROM `s_articles_categories` WHERE categoryID=".(int)$id." OR categoryparentID=".(int)$id;
    act_db_query($sql);
    return resp( array('ok' => TRUE) );
  }
  else if( $point == 'above' || $point == 'below' || $point == 'append' )
  {
    return xmlrpc_error( ENOSYS, 'Verschieben von Kategorien wird in Shopware nicht unterstützt.' );
  }
  else if( $point == 'textchange' )
  {
    $sql = "UPDATE `s_categories` SET `description`=".act_quote($data['description'][$default_lang]['name'])." WHERE `id`=".(int)$id;
    act_db_query($sql);
  }

  return resp( array('ok' => TRUE) );
}



/**
 * @done
 */
function settings_get( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;
  global $export;
  $ret = array();

  $ret['languages'] = actindo_get_languages();

  $settings = $export->sSettings();
//var_dump($settings);

  $ret['manufacturers'] = array();
  $res = act_db_query( "SELECT `id`, `name` FROM `s_articles_supplier`" );
  while( $row = act_db_fetch_assoc($res) )
  {
    $ret['manufacturers'][] = array(
      'manufacturers_id' => (int)$row['id'],
      'manufacturers_name' => $row['name']
    );
  }
  act_db_free( $res );

  $ret['customers_status'] = array();
  $res = act_db_query( "SELECT * FROM `s_core_customergroups`" );
  while( $_status = act_db_fetch_assoc($res) )
  {
    $_key = $_status['id'];
    $ret['customers_status'][(int)$_key] = array(
      'customers_status_id' => (int)$_key,
      'customers_status_min_order' => (float)$_status['minimumorder'],
      'customers_status_discount' => (float)$_status['discount'],
      'customers_status_show_price_tax' => (int)$_status['tax'],
      'customers_status_name' => array(),
    );
    foreach( array_keys($ret['languages']) as $_langid )
      $ret['customers_status'][(int)$_key]['customers_status_name'][(int)$_langid] = $_status['groupkey'].' - '.$_status['description'];
  }
  act_db_free( $res );


  $ret['vpe'] = array();
  foreach( $settings['units'] as $_key => $_vpe )
  {
    foreach( array_keys($ret['languages']) as $_langid )
    {
      if( $_key == 0 )
        continue;
      $ret['vpe'][(int)$_key][(int)$_langid] = array(
        'products_vpe' => (int)$_key,
        'vpe_name' => $_vpe['unit'].' - '.$_vpe['description']
      );
    }
  }



  $ret['shipping'] = array();
  for( $i=0; $i<=31; $i++ )
  {
    $ret['shipping'][] = array( 'id'=>$i+1, 'text' => sprintf("%d Tage", $i) );
  }



//var_dump($settings['order_states']);
  $ret['orders_status'] = array();
  foreach( $settings['order_states'] as $_key => $_name )
  {
    foreach( array_keys($ret['languages']) as $_langid )
      $ret['orders_status'][$_key][$_langid] = $_name;
  }


  // xsell is static here...
  $ret['xsell_groups'] = array(
    1 => array( 'products_xsell_grp_name_id' => 1, 'xsell_sort_order'=>0, 'groupname' => array() ),
    2 => array( 'products_xsell_grp_name_id' => 2, 'xsell_sort_order'=>1, 'groupname' => array() ),
  );
  foreach( $ret['xsell_groups'] as $_grpid => $_grp )
  {
    foreach( array_keys($ret['languages']) as $_langid )
      $ret['xsell_groups'][$_grpid]['groupname'][(int)$_langid] = $_grpid == 1 ? 'Zubehör-Artikel' : 'Ähnliche Artikel';
  }

  $res = actindo_get_fields( TRUE, TRUE );
  $ret['artikel_properties'] = $res['fields'];
  $ret['artikel_property_sets'] = $res['field_sets'];

  $ret['installed_payment_modules'] = array();
  $res = act_db_query( "SELECT id, name, description, active FROM `s_core_paymentmeans` ORDER BY `name`" );
  while( $row = act_db_fetch_assoc($res) )
  {
    $ret['installed_payment_modules'][$row['name']] = array(
      'id' => (int)$row['id'],
      'code' => $row['name'],
      'active' => (int)$row['active'],
      'name' => $row['description']
    );
  }
  act_db_free( $res );

  $ret['installed_shipping_modules'] = array();
  $res = act_db_query( "SELECT id, name, description, active FROM `s_shippingcosts_dispatch` ORDER BY `name`" );
  while( $row = act_db_fetch_assoc($res) )
  {
    $ret['installed_shipping_modules'][$row['name']] = array(
      'id' => (int)$row['id'],
      'code' => $row['name'],
      'active' => (int)$row['active'],
      'name' => $row['description']
    );
  }
  act_db_free( $res );

  return resp( array( 'ok' => TRUE, 'settings' => $ret /*, 'shopware_settings'=>$settings*/ ) );
}


/**
 * @done
 */
function product_count( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  $count = call_user_func_array( 'export_products_count', $params );
  if( !is_array($count) )
    return xmlrpc_error( EINVAL );
  if( !count($count) )
    return xmlrpc_error( ENOENT );

  return resp( array('ok'=>TRUE, 'count'=>$count) );
}


/**
 * @done
 */
function product_get( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  if( !$params[3] )
    $prod = call_user_func_array( 'export_products', $params );
  else
    $prod = call_user_func_array( 'export_products_list', $params );
  if( !$prod['ok'] )
    return xmlrpc_error( $prod['errno'], $prod['error'] );

  return resp( $prod );
}


/**
 * @done
 */
function product_create_update( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  $res = call_user_func_array( 'import_product', $params );
  $res['ok'] = $res['ok'] ? 1 : 0;

  return resp( $res );
}


/**
 * @done
 */
function product_update_stock( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;
  global $import;

  ob_start();

  $res = call_user_func_array( 'import_product_stock', $params );
  if( !$res['ok'] )
    return xmlrpc_error( $res['errno'], $res['error'] );

  return resp( $res );
}


/**
 * @done
 */
function product_delete( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;
  global $import;

  ob_start();

  $para = array("ordernumber"=>$params[0]);
  $articleID = $import->sGetArticleID( $para );
  if( !((int)$articleID) )
    return xmlrpc_error( ENOENT, 'Unbekannter Artikel' );

  $res = $import->sDeleteArticle( $para );
  if( !$res )
    return xmlrpc_error( EIO, 'Fehler beim löschen des Artikels' );

  return resp( array('ok'=>TRUE) );
}


/**
 * @done
 */
function orders_count( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  return resp( call_user_func_array('export_orders_count', $params) );
}


/**
 * @done
 */
function orders_list( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  return resp( call_user_func_array('export_orders_list', $params) );
}


/**
 * @done
 */
function orders_list_positions( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  return resp( call_user_func_array('export_orders_positions', $params) );
}


/**
 * Holger, Smarty Modifier werden benötigt damit die E-Mail korrekt aufbereitet wird.
 * Shopware nutzt in den Bestell-Status-E-Mails die proprietären Tags "fill" und "padding"
 * Mit diesen Funktionen werden sie umgesetzt.
 */
function smarty_modifier_fill ($str, $width=10, $break="...", $fill=" ")
{
	if(!is_scalar($break))
		$break = "...";
	if(empty($fill)||!is_scalar($fill))
		$fill = " ";
	if(empty($width)||!is_numeric($width))
		$width = 10;
	else 
		$width = (int)$width;
	if(!is_scalar($str))
		return str_repeat($fill,$width);
	if(strlen($str)>$width)
		$str = substr($str,0,$width-strlen($break)).$break;
	if($width>strlen($str))
		return $str.str_repeat($fill,$width-strlen($str));
	else 
		return $str;
}

function smarty_modifier_padding ($str, $width=10, $break="...", $fill=" ")
{
	if(!is_scalar($break))
		$break = "...";
	if(empty($fill)||!is_scalar($fill))
		$fill = " ";
	if(empty($width)||!is_numeric($width))
		$width = 10;
	else 
		$width = (int)$width;
	if(!is_scalar($str))
		return str_repeat($fill,$width);
	if(strlen($str)>$width)
		$str = substr($str,0,$width-strlen($break)).$break;
	if($width>strlen($str))
		return str_repeat($fill,$width-strlen($str)).$str;
	else 
		return $str;
}

/**
 * @done
 */
function orders_set_status( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  global $export;

  $orderID = $params[0];
  $status = $params[1];
  $comment = $params[2];
  $send_customer = $params[3];

  $res = $export->sUpdateOrderStatus(array(
    "orderID"=>$orderID,
    "status"=>$status,
    "comment"=>$comment
  ));

  if( !$res )
    return resp( array('ok'=>FALSE, 'errno'=>EIO, 'error'=>'Fehler beim Speichern des Bestellstatus') );

  if( $send_customer )
  {
	// Holger, von wegen wir können keine E-Mails versenden ;)
	// Achtung, die api.php muß geändert werden, damit das Shopware() Objekt zur Verfügung steht
	
	$mailname = "sORDERSTATEMAIL".$status;
	$template = clone Shopware()->Config()->Templates[$mailname];
	
	$templateEngine = Shopware()->Template();
	$templateEngine->register_modifier("fill", "smarty_modifier_fill");
	$templateEngine->register_modifier("padding", "smarty_modifier_padding");
	$templateData = $templateEngine->createData();
	$templateData->assign('sConfig', Shopware()->Config());
	
	$sOrder = $export->sGetOrders(array("orderID"=>$orderID));
	$sOrder = current($sOrder);
	$sOrderDetails = $export->sOrderDetails(array("orderID"=>$orderID));
	$sOrderDetails = array_values($sOrderDetails);
	$sUser = current($export->sOrderCustomers(array("orderID"=>$orderID)) );
	
	$templateData->assign("sOrder", $sOrder);
	$templateData->assign("sOrderDetails", $sOrderDetails);
	$templateData->assign("sUser", $sUser);
	
	$mail_vars = array(
		"content" => utf8_encode($templateEngine->fetch('string:'.$template->content, $templateData)), 
		"subject" => utf8_encode(trim($templateEngine->fetch('string:'.$template->subject, $templateData))),
		"email" => utf8_encode(trim($sUser['email'])),
		"frommail" => utf8_encode(trim($templateEngine->fetch('string:'.$template->frommail, $templateData))),
		"fromname" => utf8_encode(trim($templateEngine->fetch('string:'.$template->fromname, $templateData)))
	);
	
	if(!empty($mail_vars['content']))
	{
		$mail = clone Shopware()->Mail();
		$mail->IsHTML(0);
		$mail->From     = utf8_decode($mail_vars['frommail']);
		$mail->FromName = utf8_decode($mail_vars['frommail']);
		$mail->Subject  = utf8_decode($mail_vars['subject']);
		$mail->Body     = utf8_decode($mail_vars['content']);
		
		$mail->ClearAddresses();
		$mail->AddAddress($mail_vars['email'], '');
		
		if (!$mail->Send()){
			return resp( array('ok'=>FALSE, 'errno'=>EIO, 'error'=>'Fehler beim senden der Status-Mail') );
		}
	}
  }

  return resp( array('ok'=>TRUE) );
}


/**
 * @done
 */
function orders_set_trackingcode( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  global $export;

  $res = $export->sSetTrackingID( array("orderID"=>(int)$params[0]), $params[1] );
  if( !$res )
    return resp( array('ok'=>FALSE, 'errno'=>EIO) );
  return resp( array('ok'=>TRUE) );
}


/**
 * @done
 */
function customer_set_deb_kred_id( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  global $export;
  $res = $export->sSetCustomernumber( $params[0], $params[1] );

  if( !$res )
    return xmlrpc_error( EIO );
  return resp( array('ok'=>TRUE) );
}


/**
 * @done
 */
function customers_count($params)
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  return resp( call_user_func_array('export_customers_count', $params) );
}


/**
 * @done
 */
function customers_list( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  return resp( call_user_func_array('export_customers_list', $params) );
}


function actindo_set_token( $params )
{
  return xmlrpc_error( ENOSYS, 'Not implemented' );
}


/**
 * @done
 */
function actindo_get_time( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  $arr = array(
    'time_server' => date( 'Y-m-d H:i:s' ),
    'gmtime_server' => gmdate( 'Y-m-d H:i:s' ),
    'time_database' => date( 'Y-m-d H:i:s' ),
    'gmtime_database' => gmdate( 'Y-m-d H:i:s' ),
  );

  if( !empty($arr['gmtime_database']) )
  {
    $diff = strtotime( $arr['time_database'] ) - strtotime( $arr['gmtime_database'] );
  }
  else
  {
    $diff = strtotime( $arr['time_server'] ) - strtotime( $arr['gmtime_server'] );
  }
  $arr['diff_seconds'] = $diff;
  $diff_neg = $diff < 0;
  $diff = abs( $diff );
  $arr['diff'] = ($diff_neg ? '-':'').sprintf( "%02d:%02d:%02d", floor($diff / 3600), floor( ($diff % 3600) / 60 ), $diff % 60 );

  return resp( $arr );
}


/**
 * @done
 */
function shop_get_connector_version( &$arr, $params )
{
  global $export;

  $revision = '$Revision: 386-HR-fix5 $';
  $arr = array(
    'revision' => $revision,
    'protocol_version' => '2.'.substr( $revision, 11, -2 ),
    'shop_type' => act_get_shop_type( ),
    'shop_version' => shopware_get_version( ),
    'capabilities' => act_shop_get_capabilities(),
  );
}


/**
 * @done
 */
function act_shop_get_capabilities()
{
  return array(
    'artikel_vpe' => 1,                 // Verpackungseinheiten
    'artikel_shippingtime' => 0,        // Produkt Lieferzeit als fest definierte werte
    'artikel_shippingtime_days' => 1,   // Produkt Lieferzeit als int für n Tage
    'artikel_properties' => 1,
    'artikel_property_sets' => is_shopware3() ? 1 : 0,
    'artikel_contents' => 1,
    'artikel_attributsartikel' => 1,    // Attributs-Kombinationen werden tatsächlich eigene Artikel
    'wg_sync' => 1,
    'artikel_list_filters' => 1,
    'multi_livelager' => 1,
  );
}

function actindo_get_cryptmode( )
{
  $str = "cryptmode=MD5Shopware";
  return $str;
}


?>