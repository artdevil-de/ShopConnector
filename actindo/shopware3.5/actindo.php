<?php

error_reporting(E_ALL);
/**
 * xmlrpc server
 *
 * actindo Faktura/WWS connector
 *
 * @package actindo
 * @author  Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 442 $
 * @copyright Copyright (c) 2007, Patrick Prasse (Schneebeerenweg 26, D-85551 Kirchheim, GERMANY, pprasse@actindo.de)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author  Holger Ronecker
 * @link    http://artdevil.de/ShopConnector ShopConnector Seite auf ArtDevil.de
 * @copyright Copyright (c) 2011, Holger Ronecker, devil@artdevil.de
 */
define( 'ACTINDO_SHOPCONN_REVISION', '$Revision: 442-devil0.4 $' );
define( 'ACTINDO_PROTOCOL_REVISION', '2.'.substr( ACTINDO_SHOPCONN_REVISION, 11, -2 ) );

if(ACTINDO_CONNECTOR_TYPE=='shopware3.5'||ACTINDO_CONNECTOR_TYPE=='shopware3.04'||ACTINDO_CONNECTOR_TYPE=='shopware3.03')	 // do not use is_shopware3 here...
	require_once('api.php');
else
	require_once('../api.php');


define( 'SHOPWARE_BASEPATH', $swd=realpath(dirname($_SERVER['SCRIPT_FILENAME']).'/../../../../') );
define( 'ACTINDO_SHOP_BASEDIR', $swd );

$api = new sAPI();
$mapping = & $api->convert->mapping;
$import = & $api->import->shopware;
$export = & $api->export->shopware;

$sprache = 2;

require_once( ACTINDO_CONNECTOR_SHOP_DIR.'util.php' );

require_once( 'export_products.php' );
require_once( 'export_orders.php' );
require_once( 'export_customers.php' );
require_once( 'import_products.php' );
require_once( 'artdevil_util.php' );

/**
 * ShopConnectorNG by artdevil.de
 * - no changes necessary, already efficient use of Shopware API
 */
function categories_get($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	global $export;
	$mask = array(
		"childs" => "children",
		"id" => "categories_id",
		"parent" => "parent_id",
		"description" => "categories_name"
	);
	$cat = $export->sCategoryTree($mask);

	$cats = array();
	foreach (array_keys(actindo_get_languages()) as $_langid)
		$cats[$_langid] = $cat;

	return resp(array('ok' => TRUE, 'categories' => $cats));
}

/**
 * ShopConnectorNG by artdevil.de
 * - rewrote code to use Shopware API
 * - solved the "can not move category" problem, categories can now be moved from within actindo
 * - introduced a workaround for a bug in actindos category move signaling
 *
 * @TODO actindo 2.442 introduced moving. analyse it
 */
function category_action($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	global $import,$export;
	$default_lang = default_lang();

	list( $point, $id, $pid, $aid, $data ) = $params;

	if($point=='add') {
		$category = array(
			'id' => $id,
			'description' => $data['description'][$default_lang]['name'],
			'parent' => $pid,
			'metakeywords' => $data['description'][$default_lang]['name'],
			'active' => 1
		);
		$res = $import->sCategory($category);
		if(!$res)
			return xmlrpc_error(EIO, 'Fehler beim Anlegen der Kategorie');

	} elseif($point=='delete') {
		$category = array();
		$category[] = intval($id);
		
		$delcats = $export->sCategories('',intval($id));
		if (is_array($delcats)) {
			while (list($key, $val) = each($delcats)) {
				$category[] = intval($key);
			}
		}

		$res = $import->sDeleteCategories($category);
		if(!$res)
			return xmlrpc_error(EIO, 'Fehler beim Löschen der Kategorie');

	} elseif($point=='textchange') {
		$category = array(
			'id' => $id,
			'description' => $data['description'][$default_lang]['name'],
			'metakeywords' => $data['description'][$default_lang]['name']
		);
		$res = $import->sCategory($category);
		if(!$res)
			return xmlrpc_error(EIO, 'Fehler beim Umbenennen der Kategorie');

	} elseif($point=='above'||$point=='below'||$point=='append') {
		$sql = "
			SELECT parent
			FROM s_categories
			WHERE id = {$id}
		";
		$oldparent = $import->sDB->GetOne($sql);

			$category = array(
				'id' => $id,
				'parent' => $pid,
			);
			$res = $import->sCategory($category);
			if(!$res)
				return xmlrpc_error(EIO, 'Fehler beim Umbenennen der Kategorie');

		$sql = "
			SELECT id,position
			FROM s_categories
			WHERE parent = {$pid}
			ORDER BY position ASC
		";
		$positions = $import->sDB->GetAssoc($sql);

		/**
		 * actindo bug. Wenn man eine Kategorie in eine andere Mutterkategorie verschiebt und man die 
		 * Kategorie dabei an Position 2 stellt, schickt actindo ein "above" obwohl ein "below" korrekt wäre.
		 * Das bekomme ich den Actindos nie erklärt, zumindest werden sie es nicht freiwillig annehmen, daher
		 * hier ein Workaround der den Bug behebt
		 */
		if($point=='above' && $aid <> 0 && $positions[$aid] == 1)
			$point = 'below';
	
		unset($newpositions);
		if ($point=='below') {
			foreach ($positions as $cat1 => $pos1)
			{
				if($cat1 == $id) {
				} elseif($cat1 == $aid) {
					$newpositions[] = $aid;
					$newpositions[] = $id;
				} else {
					$newpositions[] = $cat1;
				}
			}
		} elseif($point=='above') {
			if($aid == 0)
				$newpositions[] = $id;
			foreach ($positions as $cat1 => $pos1)
			{
				if($cat1 == $id) {
				} elseif($cat1 == $aid) {
					$newpositions[] = $id;
					$newpositions[] = $aid;
				} else {
					$newpositions[] = $cat1;
				}
			}
		} elseif($point=='append') {
			foreach ($positions as $cat1 => $pos1)
			{
				if($cat1 == $id) {
				} else {
					$newpositions[] = $cat1;
				}
			}
			$newpositions[] = $id;
		}

		foreach ($newpositions as $pos2 => $cat2)
		{
			$category = array(
				'id' => $cat2,
				'parent' => $pid,
				'position' => intval($pos2 + 1)
			);
			$res = $import->sCategory($category);
			if(!$res)
				return xmlrpc_error(EIO, 'Fehler beim Verschieben der Kategorie');
		}

		// in eine andere Mutterkategorie verschoben, daher alte Kategorie auch neu sortieren
		if ($pid <> $oldparent) {
			$sql = "
				SELECT id,position
				FROM s_categories
				WHERE parent = {$oldparent}
				ORDER BY position ASC
			";
			$positions = $import->sDB->GetAssoc($sql);

			unset($newpositions);
			foreach ($positions as $cat1 => $pos1)
			{
				$newpositions[] = $cat1;
			}

			foreach ($newpositions as $pos2 => $cat2)
			{
				$category = array(
					'id' => $cat2,
					'parent' => $oldparent,
					'position' => intval($pos2 + 1)
				);
				$res = $import->sCategory($category);
				if(!$res)
					return xmlrpc_error(EIO, 'Fehler beim Verschieben der Kategorie');
			}
		}

	}

	return resp(array('ok' => TRUE, 'id' => $res));
}

/* actindo 2.442

  else if( $point == 'above' || $point == 'below' || $point == 'append' )
  {
    if(version_compare('3.5.5', shopware_get_version(), '>')) {
        return xmlrpc_error(ENOSYS, 'Verschieben von Kategorien wird erst ab Shopware 3.5.5 unterstützt');
    }

    /*
     * $id = category id to move
     * $pid = parent category of $pid
     * $aid = reference category (to move above/below / append)
     */
    
    $oldParents = aGetCategoryParents($id);

    $category = act_get_row(sprintf('SELECT `id`, `parent`, `position` FROM `s_categories` WHERE `id` = %d', $id));
    if(!is_array($category)) {
        return xmlrpc_error(ENOSYS, 'Konnte zu verschiebende Warengruppe nicht finden');
    }

    $reference = act_get_row(sprintf('SELECT `id`, `parent`, `position` FROM `s_categories` WHERE `id` = %d', $aid));
    if(!is_array($reference) && $point != 'above') {
        return xmlrpc_error(ENOSYS, 'Konnte Ziel-Warengruppe nicht finden');
    }

    if($point == 'above' && $aid == 0) {
        // increment position for ref and all following cats with the same parent
        act_db_query(sprintf('UPDATE `s_categories` SET `position` = `position` + 1 WHERE `parent` = %d', $pid));
        // move category over
        act_db_query(sprintf('UPDATE `s_categories` SET `parent` = %d, `position` = %d WHERE `id` = %d', $pid, 1, $category['id']));
    }
    elseif($point == 'below' || $point == 'append' || ($point == 'above' && $aid > 0)) {
        // increment position for all rows followed by reference
        act_db_query(sprintf('UPDATE `s_categories` SET `position` = `position` + 1 WHERE `parent` = %d AND `position` > %d', $reference['parent'], $reference['position']));
        // move category over
        act_db_query(sprintf('UPDATE `s_categories` SET `parent` = %d, `position` = %d WHERE `id` = %d', $reference['parent'], $reference['position'] + 1, $category['id']));
    }
    // update position value for old category brothers
    act_db_query(sprintf('UPDATE `s_categories` SET `position` = `position` - 1 WHERE `parent` = %d AND `position` > %d', $category['parent'], $category['position']));

    $newParents = aGetCategoryParents($id);
    
    // update s_articles_categories table with new tree
    $remove = array_diff($oldParents, $newParents);
    $add    = array_diff($newParents, $oldParents);
    foreach($add AS $catID) {
        // add entries for new categories in the tree
        $parent = (int) array_shift(aGetCategoryParents($catID, 1));
        if(empty($parent)) continue;
        $sql = sprintf(
                   'INSERT IGNORE INTO `s_articles_categories` (`articleID`, `categoryID`, `categoryparentID`)
                    (
                        SELECT `articleID`, %d, %d
                        FROM `s_articles_categories`
                        WHERE `categoryID` = %d
                    )', $catID, $parent, $id);
        act_db_query($sql);
    }
    if(!empty($remove)) {
        // remove entries for categories which are no longer parents
        $sql = sprintf('
            DELETE `cat`.*
            FROM `s_articles_categories` AS `cat`
            INNER JOIN `s_articles_categories` AS `ref` ON `ref`.`categoryID` = %d AND `ref`.`articleID` = `cat`.`articleID`
            WHERE `cat`.`categoryID` IN (%s)', $id, implode(',', $remove));
        act_db_query($sql);
    }

    return resp(array('ok' => true));
  }

*/


/**
 * @done
 */
function settings_get($params)
{
	if(!parse_args($params, $ret))
		return $ret;
	global $export;
	$ret = array();

	$ret['languages'] = actindo_get_languages();

	$settings = $export->sSettings();
//var_dump($settings);

	$ret['manufacturers'] = array();
	$res = act_db_query("SELECT `id`, `name` FROM `s_articles_supplier`");
	while ($row = act_db_fetch_assoc($res)) {
		$ret['manufacturers'][] = array(
			'manufacturers_id' => (int)$row['id'],
			'manufacturers_name' => $row['name']
		);
	}
	act_db_free($res);

	$ret['customers_status'] = array();
	$res = act_db_query("SELECT * FROM `s_core_customergroups`");
	while ($_status = act_db_fetch_assoc($res)) {
		$_key = $_status['id'];
		$ret['customers_status'][(int)$_key] = array(
			'customers_status_id' => (int)$_key,
			'customers_status_min_order' => (float)$_status['minimumorder'],
			'customers_status_discount' => (float)$_status['discount'],
			'customers_status_show_price_tax' => (int)$_status['tax'],
			'customers_status_name' => array(),
		);
		foreach (array_keys($ret['languages']) as $_langid)
			$ret['customers_status'][(int)$_key]['customers_status_name'][(int)$_langid] = $_status['groupkey'].' - '.$_status['description'];
	}
	act_db_free($res);


	$ret['vpe'] = array();
	foreach ($settings['units'] as $_key => $_vpe) {
		foreach (array_keys($ret['languages']) as $_langid) {
			if($_key==0)
				continue;
			$ret['vpe'][(int)$_key][(int)$_langid] = array(
				'products_vpe' => (int)$_key,
				'vpe_name' => $_vpe['unit'].' - '.$_vpe['description']
			);
		}
	}



	$ret['shipping'] = array();
	for ($i = 0; $i<=31; $i++) {
		$ret['shipping'][] = array('id' => $i+1, 'text' => sprintf("%d Tage", $i));
	}



//var_dump($settings['order_states']);
	$ret['orders_status'] = array();
	foreach ($settings['order_states'] as $_key => $_name) {
		foreach (array_keys($ret['languages']) as $_langid)
			$ret['orders_status'][$_key][$_langid] = $_name;
	}


	// xsell is static here...
	$ret['xsell_groups'] = array(
		1 => array('products_xsell_grp_name_id' => 1, 'xsell_sort_order' => 0, 'groupname' => array()),
		2 => array('products_xsell_grp_name_id' => 2, 'xsell_sort_order' => 1, 'groupname' => array()),
	);
	foreach ($ret['xsell_groups'] as $_grpid => $_grp) {
		foreach (array_keys($ret['languages']) as $_langid)
			$ret['xsell_groups'][$_grpid]['groupname'][(int)$_langid] = $_grpid==1 ? 'Zubehör-Artikel' : 'Ähnliche Artikel';
	}

	$res = actindo_get_fields(TRUE, TRUE);
	$ret['artikel_properties'] = $res['fields'];
	$ret['artikel_property_sets'] = $res['field_sets'];

	$ret['installed_payment_modules'] = array();
	$res = act_db_query("SELECT id, name, description, active FROM `s_core_paymentmeans` ORDER BY `name`");
	while ($row = act_db_fetch_assoc($res)) {
		$ret['installed_payment_modules'][$row['name']] = array(
			'id' => (int)$row['id'],
			'code' => $row['name'],
			'active' => (int)$row['active'],
			'name' => $row['description']
		);
	}
	act_db_free($res);

	$ret['installed_shipping_modules'] = array();
	$res = act_db_query("SELECT id, name, description, active FROM `s_shippingcosts_dispatch` ORDER BY `name`");
	while ($row = act_db_fetch_assoc($res)) {
		$ret['installed_shipping_modules'][$row['name']] = array(
			'id' => (int)$row['id'],
			'code' => $row['name'],
			'active' => (int)$row['active'],
			'name' => $row['description']
		);
	}
	act_db_free($res);

	$ret['multistores'] = actindo_get_multistores();

	return resp(array('ok' => TRUE, 'settings' => $ret /* , 'shopware_settings'=>$settings */));
}

/**
 * @done
 */
function product_count($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	ob_start();
	$count = call_user_func_array('export_products_count', $params);
	if(!is_array($count))
		return xmlrpc_error(EINVAL);
	if(!count($count))
		return xmlrpc_error(ENOENT);

	return resp(array('ok' => TRUE, 'count' => $count));
}

/**
 * @done
 */
function product_get($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	ob_start();
	if(!$params[3])
		$prod = call_user_func_array('export_products', $params);
	else
		$prod = call_user_func_array('export_products_list', $params);
	if(!$prod['ok'])
		return xmlrpc_error($prod['errno'], $prod['error']);

	return resp($prod);
}

/**
 * @done
 */
function product_create_update($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	ob_start();
	$res = call_user_func_array('import_product', $params);
	$res['ok'] = $res['ok'] ? 1 : 0;

	return resp($res);
}

/**
 * @done
 */
function product_update_stock($params)
{
	if(!parse_args($params, $ret))
		return $ret;
	global $import;

	ob_start();

	$res = call_user_func_array('import_product_stock', $params);
	if(!$res['ok'])
		return xmlrpc_error($res['errno'], $res['error']);

	return resp($res);
}

/**
 * @done
 */
function product_delete($params)
{
	if(!parse_args($params, $ret))
		return $ret;
	global $import;

	ob_start();

	$para = array("ordernumber" => $params[0]);
	$articleID = $import->sGetArticleID($para);
	if(!((int)$articleID))
		return xmlrpc_error(ENOENT, 'Unbekannter Artikel');

	$res = $import->sDeleteArticle($para);
	if(!$res)
		return xmlrpc_error(EIO, 'Fehler beim löschen des Artikels');

	return resp(array('ok' => TRUE));
}

/**
 * @done
 */
function orders_count($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	ob_start();
	return resp(call_user_func_array('export_orders_count', $params));
}

/**
 * @done
 */
function orders_list($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	ob_start();
	return resp(call_user_func_array('export_orders_list', $params));
}

/**
 * @done
 */
function orders_list_positions($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	ob_start();
	return resp(call_user_func_array('export_orders_positions', $params));
}

/**
 * Holger, Smarty Modifier werden benötigt damit die E-Mail korrekt aufbereitet wird.
 * Shopware nutzt in den Bestell-Status-E-Mails die proprietären Tags "fill" und "padding"
 * Mit diesen Funktionen werden sie umgesetzt.
 */
function smarty_modifier_fill($str, $width=10, $break="...", $fill=" ")
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
		return str_repeat($fill, $width);
	if(strlen($str)>$width)
		$str = substr($str, 0, $width-strlen($break)).$break;
	if($width>strlen($str))
		return $str.str_repeat($fill, $width-strlen($str));
	else
		return $str;
}

function smarty_modifier_padding($str, $width=10, $break="...", $fill=" ")
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
		return str_repeat($fill, $width);
	if(strlen($str)>$width)
		$str = substr($str, 0, $width-strlen($break)).$break;
	if($width>strlen($str))
		return str_repeat($fill, $width-strlen($str)).$str;
	else
		return $str;
}

/**
 * @done
 */
function orders_set_status($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	global $export;

	$orderID = $params[0];
	$status = $params[1];
	$comment = $params[2];
	$send_customer = $params[3];

	$res = $export->sUpdateOrderStatus(array(
		"orderID" => $orderID,
		"status" => $status,
		"comment" => $comment
			));

	if(!$res)
		return resp(array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim Speichern des Bestellstatus'));

	if($send_customer) {
		// Holger, von wegen wir können keine E-Mails versenden ;)

		$mailname = "sORDERSTATEMAIL".$status;
		$template = clone Shopware()->Config()->Templates[$mailname];

		$templateEngine = Shopware()->Template();
		$templateEngine->register_modifier("fill", "smarty_modifier_fill");
		$templateEngine->register_modifier("padding", "smarty_modifier_padding");
		$templateData = $templateEngine->createData();
		$templateData->assign('sConfig', Shopware()->Config());

		$sOrder = $export->sGetOrders(array("orderID" => $orderID));
		$sOrder = current($sOrder);
		$sOrderDetails = $export->sOrderDetails(array("orderID" => $orderID));
		$sOrderDetails = array_values($sOrderDetails);
		$sUser = current($export->sOrderCustomers(array("orderID" => $orderID)));

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

		if(!empty($mail_vars['content'])) {
			$mail = clone Shopware()->Mail();
			$mail->IsHTML(0);
			$mail->From = utf8_decode($mail_vars['frommail']);
			$mail->FromName = utf8_decode($mail_vars['frommail']);
			$mail->Subject = utf8_decode($mail_vars['subject']);
			$mail->Body = utf8_decode($mail_vars['content']);

			$mail->ClearAddresses();
			$mail->AddAddress($mail_vars['email'], '');

			if(!$mail->Send()) {
				return resp(array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim senden der Status-Mail'));
			}
		}
	}

	return resp(array('ok' => TRUE));
}

/**
 * @done
 */
function orders_set_trackingcode($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	global $export;

	$res = $export->sSetTrackingID(array("orderID" => (int)$params[0]), $params[1]);
	if(!$res)
		return resp(array('ok' => FALSE, 'errno' => EIO));
	return resp(array('ok' => TRUE));
}

/**
 * @done
 */
function customer_set_deb_kred_id($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	global $export;
	$res = $export->sSetCustomernumber($params[0], $params[1]);

	if(!$res)
		return xmlrpc_error(EIO);
	return resp(array('ok' => TRUE));
}

/**
 * @done
 */
function customers_count($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	ob_start();
	return resp(call_user_func_array('export_customers_count', $params));
}

/**
 * @done
 */
function customers_list($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	ob_start();
	return resp(call_user_func_array('export_customers_list', $params));
}

function actindo_set_token($params)
{
	return xmlrpc_error(ENOSYS, 'Not implemented');
}

/**
 * @done
 */
function actindo_get_time($params)
{
	if(!parse_args($params, $ret))
		return $ret;

	$arr = array(
		'time_server' => date('Y-m-d H:i:s'),
		'gmtime_server' => gmdate('Y-m-d H:i:s'),
		'time_database' => date('Y-m-d H:i:s'),
		'gmtime_database' => gmdate('Y-m-d H:i:s'),
	);

	if(!empty($arr['gmtime_database'])) {
		$diff = strtotime($arr['time_database'])-strtotime($arr['gmtime_database']);
	} else {
		$diff = strtotime($arr['time_server'])-strtotime($arr['gmtime_server']);
	}
	$arr['diff_seconds'] = $diff;
	$diff_neg = $diff<0;
	$diff = abs($diff);
	$arr['diff'] = ($diff_neg ? '-' : '').sprintf("%02d:%02d:%02d", floor($diff/3600), floor(($diff%3600)/60), $diff%60);

	return resp($arr);
}

/**
 * @done
 */
function shop_get_connector_version(&$arr, $params)
{
	global $export;

	$revision = ACTINDO_SHOPCONN_REVISION;
	$arr = array(
		'revision' => $revision,
		'protocol_version' => '2.'.substr($revision, 11, -2),
		'shop_type' => act_get_shop_type(),
		'shop_version' => shopware_get_version(),
		'capabilities' => act_shop_get_capabilities(),
	);
}

/**
 * @done
 */
function act_shop_get_capabilities()
{
	return array(
		'artikel_vpe' => 1, // Verpackungseinheiten
		'artikel_shippingtime' => 0, // Produkt Lieferzeit als fest definierte werte
		'artikel_shippingtime_days' => 1, // Produkt Lieferzeit als int für n Tage
		'artikel_properties' => 1,
		'artikel_property_sets' => is_shopware3() ? 1 : 0,
		'artikel_contents' => 1,
		'artikel_attributsartikel' => 1, // Attributs-Kombinationen werden tatsächlich eigene Artikel
		'wg_sync' => 1,
		'artikel_list_filters' => 1,
		'multi_livelager' => 1,
	);
}

function actindo_get_cryptmode()
{
	$str = "cryptmode=MD5Shopware";
	return $str;
}

?>