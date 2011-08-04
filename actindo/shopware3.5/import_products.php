<?php

/**
 * import products
 *
 * actindo Faktura/WWS connector
 *
 * @package actindo
 * @author Patrick Prasse <prasse@actindo.de>
 * @version $Revision: 386 $
 * @copyright Copyright (c) 2008, Patrick Prasse (Schneebeerenweg 26, D-85551 Kirchheim, GERMANY, haimerl@actindo.de)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */
function import_product($product)
{
	global $api, $mapping, $import, $sprache;
	$pgruppe_translation = get_preisgruppe_translation();   // $pgruppe_translation is now an array like array( 'EK'=>2, 'H'=>3, etc)
	$default_language_id = default_lang();
	$default_language_code = get_language_code_by_id($default_language_id);
	$languages = actindo_get_languages();
	$customergroups = get_customergroups();

	$i18n = array();	// ['langcode']['objecttype']['objectkey']

	$failed = 0;
	$success = 0;

	if(!is_array($product)||!count($product)) {
		return array('ok' => FALSE, 'errno' => EINVAL);
	}


	// check primary category
	$sql = "SELECT `id` FROM `s_categories` WHERE `id`=".(int)$product['swg'];
	if(is_object($result = act_db_query($sql))) {
		if(act_db_num_rows($result)<=0)
			return array('ok' => FALSE, 'errno' => ENOENT, 'error' => 'Kategorie nicht vorhanden');
	}
	else {
		return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Kategorie nicht vorhanden');
	}


	$data = array();

	// Holger, Überbleibsel aus Programmier-Tests? Der Befehl macht hier keinen Sinn
	//$data['pricegroupID'] = 1;
	$data['pricegroupID'] = isset($product['pricegroupID']) ? $product['pricegroupID'] : '0';
	// Holger, Die Shopware API reagiert auf gesetzte Filter mit setzen des Feldes "PriceGroupActive", was nicht wünscheswert ist
	// Vermutlich ist es ein Bug in der Shopware-API, Shopware überprüft es mit Ticket ID #11749 vom 12.05.2011
	// - Mit aktualisierter Import API vom 27.07.2011 ist der Fehler von Shopware behoben (version 3.5.4.1)
	$data['pricegroupActive'] = isset($product['pricegroupActive']) ? $product['pricegroupActive'] : '0';

	// art_nr, art_name, products_id, , products_status, created, last_modified already here.
	$data['ordernumber'] = $product['art_nr'];
	$data['name'] = stripslashes($product['art_name']);
	$data['stockmin'] = $product['l_minbestand'];
	$data['instock'] = $product['l_bestand'];
	$data['supplierID'] = $product['shop']['art']['manufacturers_id'];
	$data['laststock'] = $product['shop']['art']['abverkauf'];
	$data['crossbundlelook'] = $product['shop']['art']['bundle'];
	$data['weight'] = $product['shop']['art']['products_weight'];
	$data['active'] = $product['shop']['art']['products_status'];
	// Holger, irgendwo auf dem Weg aus actindo nach Shopware wird das Datum noch umformatiert. Das Datenbankfeld ist vom Type 
	// "date" und das erwartet das Datum in der Formatierung "YYYY-MM-DD" und in dem Format wird es auch von actindo angeliefert.
	if(!empty($product['shop']['art']['products_date_available'])) {
		list($year, $month, $day) = split('[/.-]', substr($product['shop']['art']['products_date_available'], 0, 10));
		$data['releasedate'] = $year."-".$day."-".$month;
	}
	$data['shippingtime'] = max(0, $product['shop']['art']['shipping_status']-1);   // no map, just ID's


	if($product['shop']['art']['products_vpe_status']>0) {
		$data['unitID'] = $product['shop']['art']['products_vpe'];
		$data['purchaseunit'] = $product['shop']['art']['products_vpe_value'];
		$data['referenceunit'] = isset($product['shop']['art']['products_vpe_referenzeinheit']) ? $product['shop']['art']['products_vpe_referenzeinheit'] : $product['shop']['art']['products_vpe_value'];
		$data['purchasesteps'] = isset($product['shop']['art']['products_vpe_staffelung']) ? $product['shop']['art']['products_vpe_staffelung'] : $product['shop']['art']['products_vpe_value'];
		// TODO: also minpurchase ?
		// Holger, Antwort: Ja
		// Erklärung: Nehmen wir einen Artikel den wir in 6er Schritten verkaufen wollen, z.B. ein 6-Pack Bier. Wenn wir "minpurchase" nicht setzen bietet
		// Shopware folgende Staffelugn an: 1,7,13,19,... Wenn wir "minpurchase" = "purchasesteps" setzen, dann erhalten wir das gewünschte Ergebnis: 6,12,18,24,...
		$data['minpurchase'] = isset($product['shop']['art']['products_vpe_staffelung']) ? $product['shop']['art']['products_vpe_staffelung'] : $product['shop']['art']['products_vpe_value'];

		// Holger, Purchase Unit Artikel erhalten hiermit die Einheit im Bestellmengen-Dropdown
		// to-do: translation fehlt noch
		$sql = "SELECT `unit`,`description` FROM `s_core_units` WHERE `id` = ".(int)$data['unitID'];
		if(is_object($result = act_db_query($sql))) {
			$row = $result->FetchRow();
			if((float)$data['purchaseunit']==1)
				$data['packunit'] = $row['description'];
			else
				$data['packunit'] = 'x '.(float)$data['purchaseunit'].' '.$row['description'];
		}
	}
	else {
		$data['unitID'] = $data['purchaseunit'] = $data['referenceunit'] = $data['purchasesteps'] = $data['minpurchase'] = 0;
		$data['packunit'] = '';
	}

	if(isset($product['shop']['art']['products_digital']))
		$data['esd'] = $product['shop']['art']['products_digital'];
	if(isset($product['shop']['art']['pseudosales']))
		$data['pseudosales'] = $product['shop']['art']['pseudosales'];
	if(isset($product['shop']['art']['shipping_free']))
		$data['shippingfree'] = $product['shop']['art']['shipping_free'];
	if(isset($product['shop']['art']['suppliernumber']))
		$data['suppliernumber'] = $product['shop']['art']['suppliernumber'];
	if(isset($product['shop']['art']['topseller']))
		$data['topseller'] = $product['shop']['art']['topseller'];
	if(isset($product['shop']['art']['products_sort']))
		$data['position'] = $product['shop']['art']['products_sort'];
	if(isset($product['shop']['art']['filtergroup_id']))
		$data['filtergroupID'] = $product['shop']['art']['filtergroup_id'];

	// Holger, Shopware-Artikel-Feld "eMail-Benachrichtigung, wenn nicht auf Lager:". 
	// Datenbank Tabelle "s_articles", Feld "notification", Typ "int(1) unsinged" 
	// Bitte binden Sie es wie "abverkauf" oder "bundle" als "Ja/Nein" Select-Box ein.
	// Als Standard Vorbelegung bitte "Ja" auswählen. Die Shopware-API unterstützt die Variable.
	// Vielen Dank
	if(empty($product['shop']['art']['notification']))
		$product['shop']['art']['notification'] = 1;  // solange actindo es nicht unterstützt, immer aktivieren
	$data['notification'] = $product['shop']['art']['notification'];


	// check manufacturer
	$sql = "SELECT `id` FROM `s_articles_supplier` WHERE `id`=".(int)$data['supplierID'];
	if(is_object($result = act_db_query($sql))) {
		if(act_db_num_rows($result)<=0)
			return array('ok' => FALSE, 'errno' => ENOENT, 'error' => 'Hersteller nicht vorhanden');
	}
	else {
		return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Hersteller nicht vorhanden');
	}


	// descriptions, names in all languages
	_do_import_descriptions($product, $data, $languages, $i18n, FALSE);


	$data['categories'] = array($product['swg']);
	if(is_array($product["shop"]["all_categories"])&&count($product["shop"]["all_categories"])) {
		foreach ($product["shop"]["all_categories"] as $_cat) {
			$data['categories'][] = $_cat;
		}
	}


	// taxes (Lieferschwellen)
	if(isset($product['taxes_advanced'])) {
		// Lieferschwellen is not yet supported by shopware
	}
	if(isset($product['leist_art'])&&$product['leist_art']>0) {   // for Lieferschwellen
		$data['taxID'] = $product['leist_art'];
	} else {
		switch ($product['mwst_stkey']) {
			case 3:
				$data['taxID'] = 1;
				break;
			case 2:
				$data['taxID'] = 4;
				break;
			case 0:
			case 1:
			case 11:
				$data['taxID'] = 0;
				break;

			default:
				return array('ok' => FALSE, 'errno' => EUNKNOWN, 'error' => 'Im Shop nicht verfügbarer Steuersatz.');
		}
	}



	$res = $import->sArticle($data, array('update' => true, 'ignore_configurator' => true));
	if(!is_array($res)) {
		return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim anlegen / updaten des Artikels');
	}
	$data['articledetailsID'] = (int)$res['articledetailsID'];
	$data['articleID'] = (int)$res['articleID'];

	$res = $import->sArticleCategories(array('articleID' => $data['articleID'], 'articledetailsID' => $data['articledetailsID']), $data['categories']);


	// base price, taxes +
	// price brackets
	$res = _do_import_preisgruppen($data, $product, $pgruppe_translation, $customergroups);
	if(!$res['ok']) {
		return $res;
	}

	// other categories already done in $import->sArticle
	// images
	$res = _do_import_images($data['articleID'], $product, $default_language_code);
	if(!$res['ok']) {
		return $res;
	}


	// attributes [HERE: Varianten!]
	$res = _do_import_attributes($data, $product, $pgruppe_translation, $default_language_id, $languages, $i18n, $default_language_code);
	if(!$res['ok']) {
		return $res;
	}


	// cross-selling
	$res = _do_import_xselling($data, $product);
	if(!$res['ok']) {
		return $res;
	}


	// content
	$res = _do_import_content($data, $product, $languages, $default_language_code, $i18n);
	if(!$res['ok']) {
		return $res;
	}


	// Attributes [Zusatzfelder!]
	$res = _do_import_properties($data, $product, $languages, $default_language_code, $i18n);
	if(!$res['ok']) {
		return $res;
	}

	// Zugriffsrechte, Artikel werden nicht allen Kunden angezeigt - Special Thanks to HR
	if(ACTINDO_CONNECTOR_TYPE=='shopware3.5') {
		$res = _do_import_permissions($data, $product, $pgruppe_translation);
		if(!$res['ok']) {
			return $res;
		}
	}


	// descriptions, names in all languages in s_core_translations
	_do_import_descriptions($product, $data, $languages, $i18n, TRUE);


	// ['langcode']['objecttype']['objectkey']
	$res = TRUE;
	foreach ($i18n as $langcode => $tmp1) {
		foreach ($tmp1 as $objecttype => $tmp2) {
			foreach ($tmp2 as $objectkey => $arr) {
				$data = serialize($arr);
				$sql = "DELETE FROM `s_core_translations` WHERE `objecttype`=".act_quote($objecttype)." AND `objectkey`=".act_quote($objectkey)." AND `objectlanguage`=".act_quote($langcode);
				$res &= act_db_query($sql);
				$sql = "INSERT INTO `s_core_translations` SET
          `objecttype`=".act_quote($objecttype).",
          `objectkey`=".act_quote($objectkey).",
          `objectlanguage`=".act_quote($langcode).",
          `objectdata`=".act_quote($data);
				$res &= act_db_query($sql);
			}
		}
	}
	if(!$res)
		return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim einfügen der Internationalisierung');


	$success++;
	return array('ok' => TRUE, 'success' => $success, 'warning' => $warning);
}

function _do_import_images($art_id, &$product, $default_language_code, $is_child_article=FALSE, $relations="")
{
	global $import;

	if($is_child_article&&((ACTINDO_CONNECTOR_TYPE!='shopware3.5'&&ACTINDO_CONNECTOR_TYPE!='shopware3.04')||version_compare(shopware_get_version(), '3.0.4', 'lt')))	// support for child article images from shopware 3.0.4
		return array('ok' => TRUE, 'not_supported' => TRUE);

	if(!$is_child_article)
		$import->sDeleteArticleImages(array('articleID' => $art_id));

	if(is_array($product['shop']['images'])&&count($product['shop']['images'])) {
		if(ACTINDO_CONNECTOR_TYPE=='shopware3.5'||ACTINDO_CONNECTOR_TYPE=='shopware3.04') {
			if($is_child_article) {
				$sql1 = "SELECT MAX(`position`) AS p FROM `s_articles_img` WHERE articleID=".$art_id;
				$result = act_db_query($sql1);
				$row = $result->FetchRow();
				$max_img_nr = (int)$row['p']+1;
			}

			$primary_image = 0;
			foreach ($product['shop']['images'] as $img) {
				$res = actindo_create_temporary_file($img['image']);
				if(!$res['ok'])
					return $res;

				$image = array(
					'articleID' => $art_id,
					'position' => (int)(!$is_child_article ? $img['image_nr'] : $max_img_nr++),
					'image' => $res['file'],
					'description' => $img['image_title'][$default_language_code],
				);
				if(!$is_child_article)
					$image['main'] = $img['image_nr']==1 ? 1 : 2;
				else {
					$image['main'] = 2;
					$image['relations'] = $relations;
				}

//        echo "\n\nIMAGE: ".var_dump_string($image['position']).'='.var_dump_string($image)."\n";

				$res = $import->sArticleImage($image);
				if($image['main']==1)
					$primary_image = $res;
			}
			if($primary_image>0) {
				$sql1 = "UPDATE `s_articles_img` SET `main`=1 WHERE `articleID`=".(int)$art_id." AND `id`=".(int)$primary_image;
				$res = act_db_query($sql1);
			}
		} else {	// shopware 2
			$i = 0;
			foreach ($product['shop']['images'] as $img) {
				$res = actindo_create_temporary_file($img['image']);
				if(!$res['ok'])
					return $res;

				$image = array(
					'articleID' => $art_id,
					'main' => $i==0,
					'position' => $i+1,
					'image' => $res['file'],
				);

				$res = $import->sArticleImage($image);
				$i++;
			}
		}
	}

	return array('ok' => TRUE);
}

function _do_import_properties($data, $product, $languages, $default_language_code, &$i18n)
{
	global $import;
	$res = actindo_get_fields(TRUE, FALSE);
	$attr_fields = $res['fields'];

	$art_id = (int)$data['articleID'];
	$sql = array();
	$done_attrs = array();
	$i18n1 = array();
	$res = TRUE;

	if(is_array($product['shop']['properties'])) {
		$filters = array();
		foreach ($product['shop']['properties'] as $prop) {
			if(!empty($prop['field_id'])&&isset($attr_fields[$prop['field_id']])) {
				if(empty($prop['language_code'])||!strcasecmp($prop['language_code'], $default_language_code)) {
					if(!in_array($prop['field_id'], $done_attrs)) {
						$done_attrs[] = $prop['field_id'];
						$sql[] = "`{$prop['field_id']}`=".act_quote($prop['field_value']);
					}
				} else {
					$i18n1[$prop['language_code']] = $prop;
				}
			}

			if(!empty($prop['field_id'])&&strpos($prop['field_id'], 'filter')===0) {
				$filters[] = $prop;
			}
		}

		$sql1 = "DELETE FROM `s_articles_attributes` WHERE `articleID`=".$art_id." AND `articledetailsID`=".(int)$data['articledetailsID'];
		if(act_db_query($sql1)===false) {
			return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim löschen der alten Attribute (Zusatzfelder)');
		}


		foreach ($languages as $lang) {
			if($lang['is_default']||!isset($i18n1[$lang['language_code']]))
				continue;
			$i18n[$lang['language_code']]['article'][$art_id][$prop['field_id']] = $i18n1[$lang['language_code']]['field_value'];
		}

		if(is_shopware3()) {
			$sql1 = "DELETE FROM `s_filter_values` WHERE `articleID`=".$art_id;
			if(act_db_query($sql1)===false) {
				return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim löschen der alten Filter-Werte (Zusatzfelder)');
			}

			$sql1 = "SELECT rel.`optionID`, a.`filtergroupID` FROM `s_articles` AS a, `s_filter_relations` AS rel WHERE rel.`groupID`=a.`filtergroupID` AND a.`id`=".$art_id;
			$result = act_db_query($sql1);
			$valid_filter_ids = array();
			$filter_group_id = 0;
			while ($row = $result->FetchRow()) {
				$valid_filter_ids[] = (int)$row['optionID'];
				$filter_group_id = (int)$row['filtergroupID'];
			}

			if($filter_group_id) {
				foreach ($filters as $prop) {
					$id = (int)substr($prop['field_id'], 6);
					if(!in_array($id, $valid_filter_ids))
						continue;
					$sql1 = "INSERT INTO `s_filter_values` (`groupID`, `optionID`, `articleID`, `value`) VALUES( ".(int)$filter_group_id.", ".(int)$id.", ".(int)$art_id.", ".act_quote($prop['field_value'])." )";
					if($result = act_db_query($sql1)===false) {
						return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim speichern der neuen Filter-Werte (Zusatzfelder)');
					}
				}
			}
		}
	}

// Holger, Sonderbehandlung für die Attributsfelder "EAN" und "FSK18". In actindo gibt es eigene Felder dafür
// und wir wollen das sie die generischen Attribute (actindo Vokabel "Zusatzfelder") immer überschreiben.
// Daher wurde der Code aus function import_product() gelöscht und hierher umgezogen.
// Erklärung: In Zeile 385 wird der Befehlt "DELETE `s_articles_attributes`" ausgeführt und alle vorher gesetzen Attribute werden damit gelöscht 
	$attribute_translation = actindo_attribute_translation_table();
	foreach ($attribute_translation as $shopware_name => $actindo_name) {
		if(isset($product['shop']['art'][$actindo_name]))
			$sql[] = "`{$shopware_name}`=".act_quote($product['shop']['art'][$actindo_name]);
	}

	if(count($sql)) {
		$sql = "INSERT INTO `s_articles_attributes` SET `articleID`=".$art_id.", `articledetailsID`=".(int)$data['articledetailsID'].", ".
				join(', ', $sql)." ON DUPLICATE KEY UPDATE ".join(', ', $sql);
		if(act_db_query($sql)===false)
			return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim anlegen der Attribute (Zusatzfelder)');
	}
	else {	// MUST have s_articles_attributes row even if we have no attributes!
		$sql = "INSERT INTO `s_articles_attributes` SET `articleID`=".$art_id.", `articledetailsID`=".(int)$data['articledetailsID'].
				" ON DUPLICATE KEY UPDATE `articleID`=".$art_id.", `articledetailsID`=".(int)$data['articledetailsID'];
		if(act_db_query($sql)===false)
			return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim anlegen des Dummy-Eintrags für die Attribute (Zusatzfelder)');
	}

	return array('ok' => TRUE);
}

function _do_import_content($data, $product, $languages, $default_language_code, &$i18n)
{
	global $import;
	$id = $data['articleID'];

	if(is_array($product['shop']['content'])) {
		// delete files also?
		// we leave 'em as backup
		$sql = "DELETE FROM `s_articles_downloads` WHERE `articleID`=".(int)$id;
		$res = act_db_query($sql);

		$sql = "DELETE FROM `s_articles_information` WHERE `articleID`=".(int)$id;
		$res &= act_db_query($sql);

		foreach ($product['shop']['content'] as $content) {
			if($content['type']=='link') {
				$sql = "INSERT INTO `s_articles_information` SET `articleID`=".(int)$id.",
          `description`=".act_quote($content['content_name']).",
          `link`=".act_quote($content['content']).",
          `target`=".act_quote(isset($content['content_link_target']) ? $content['content_link_target'] : '_blank');
				$res &= act_db_query($sql);
			} else if($content['type']=='file') {
				$file = actindo_create_temporary_file($content['content']);
				if(!$file['ok'])
					return $file;
				$bn = basename($file['file']);
				$uploadpath = realpath($import->sPath.$import->sSystem->sCONFIG['sARTICLEFILES'])."/".$bn.'-'.$content['content_file_name'];
				$result = rename($file['file'], $uploadpath);
				if(!$result) {
					return array('ok' => FALSE, 'errno' => EIO, 'error' => "Downloads: Konnte Datei '{$file['file']}' nicht in '{$uploadpath}' umbenennen.");
				}

				$sql = "INSERT INTO `s_articles_downloads` SET `articleID`=".(int)$id.",
          `description`=".act_quote($content['content_name']).",
          `filename`=".act_quote(basename($uploadpath)).",
          `size`=".(int)filesize($uploadpath);
				$res &= act_db_query($sql);
			}
		}

		if(!$res)
			return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim einfügen der Links / Downloads');
	}

	return array('ok' => TRUE);
}

function _do_import_attributes($data, $product, $pgruppe_translation, $default_language_id, $languages, &$i18n, $default_language_code)
{
	global $import;
	$id = $data['articleID'];

	if(is_array($product['shop']['attributes'])) {
		$res = _do_import_attributes_options($product['shop']['attributes']['names'], $product['shop']['attributes']['values'], $id, $default_language_id, FALSE, $languages, $i18n);
		if(!$res)
			return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim anlegen der Konfigurator-Gruppen und -Optionen');

		$res = _do_set_article_attributes($product['shop']['attributes']['combination_advanced'], $product['shop']['attributes']['names'], $product['shop']['attributes']['values'], $id, $product, $pgruppe_translation, $data, $default_language_id, $default_language_code);
		if(!$res['ok'])
			return $res;
	}
	else if(isset($product['shop']['attributes'])) {
		$sql = "DELETE FROM `s_articles_groups_value` WHERE `articleID`=".(int)$id;
		$res = act_db_query($sql);
		$sql = "DELETE FROM `s_articles_groups_prices` WHERE `articleID`=".(int)$id;
		$res &= act_db_query($sql);
		$sql = "DELETE FROM `s_articles_groups` WHERE `articleID`=".(int)$id;
		$res &= act_db_query($sql);
		$sql = "DELETE FROM `s_articles_groups_option` WHERE `articleID`=".(int)$id;
		$res &= act_db_query($sql);
		if(!$res)
			return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim löschen der Konfigurator-Artikel');
	}

	return array('ok' => TRUE);
}

function _do_set_article_attributes($combination_advanced, &$options, &$values, $art_id, $product, $pgruppe_translation, $data, $default_language_id, $default_language_code)
{
	global $import;
	$res = $pres = TRUE;
	$default_language_code = get_language_code_by_id($default_language_id);

	$sql = "DELETE FROM `s_articles_groups_value` WHERE `articleID`=".(int)$art_id;
	$result0 = act_db_query($sql);
	$sql = "DELETE FROM `s_articles_groups_prices` WHERE `articleID`=".(int)$art_id;
	$result0 = act_db_query($sql);

	$have_attribute_images = 0;
	foreach ($combination_advanced as $want_art_nr => $comb) {
		$have_attribute_images += is_array($comb['shop']['images']) ? count($comb['shop']['images']) : 0;
	}

	$errors = array();

	// Holger, war 1 ist falsch. Mit 1 entstehen Konfigurator Artikel die 2x standard haben ... 
	// es kann aber nur einen standard für einen Artikel geben, daher auf 0 geändert.
	$default_standard = 0;
	foreach ($combination_advanced as $want_art_nr => $comb) {
		$vals = array();
		$relations = array();
		foreach ($comb['attribute_value_id'] as $_idx => $_value_id) {
			$_name_id = $comb['attribute_name_id'][$_idx];
			$shop_options_id = (int)$options[$_name_id]['_shop_id'];
			$shop_values_id = (int)$values[$_name_id][$_value_id]['_shop_id'];
			$vals[] = "`attr".$shop_options_id."`=".(int)$shop_values_id;

			$relations[] = array($options[$_name_id][$default_language_code], $values[$_name_id][$_value_id][$default_language_code]);  // for images
		}


		$active = isset($comb['data']['products_status']) ? (int)$comb['data']['products_status'] : 1;
		$standard = 0;
		if($active) {
			if(isset($comb['data']['products_is_standard']))
				$standard = (int)$comb['data']['products_is_standard'];
			else {
				// if products_is_standard is not set, we set it for
				$standard = $default_standard;
			}
		}

		// Holger, EAN als "Freitext 1" im Konfigurator
		if(isset($comb['shop']['art']['products_ean'])) {
			$vals[] = "`gv_attr1`=".act_quote($comb['shop']['art']['products_ean']);
		}

		$sql = "REPLACE INTO `s_articles_groups_value` SET `articleID`=".(int)$art_id.", `standard`=".(int)$standard.", `active`=".(int)$active.", `ordernumber`=".act_quote($want_art_nr).", `instock`=".(float)$comb['l_bestand'].(count($vals)>0 ? ", ".join(', ', $vals) : "" );
		$res2 = act_db_query($sql);
		$res &= $res2;
		if(!$res2) {
			$errors[] = act_db_error();
			continue;
		} else {
			$comb['valueID'] = (int)act_insert_id();
			if($standard)
				$default_standard = 0;
		}


		is_array($comb['preisgruppen'])or$comb['preisgruppen'] = array();
		foreach ($product['preisgruppen'] as $pg_id => $pgruppe) {   // $product['preisgruppen'] is right here.
			$prices1 = array();
			$price_ranges = array();
			$pricegroup = $pgruppe_translation[$pg_id];

			if(is_null($pricegroup))
				continue;

			if(isset($comb['preisgruppen'][$pg_id])&&$comb['preisgruppen'][$pg_id]['grundpreis']>0)
				$pgruppe = $comb['preisgruppen'][$pg_id];

			// no mengenstaffel support in Konfigurator
			$sql = "REPLACE INTO `s_articles_groups_prices` SET `articleID`=".(int)$art_id.",
        `valueID`=".(int)$comb['valueID'].",
        `groupkey`=".act_quote($pricegroup).",
        `price`=".(float)import_price($pgruppe['grundpreis'], $pgruppe['is_brutto'], get_tax_rate($data['taxID']));
			$pres &= act_db_query($sql);
			if(!$pres) {
				$errors[] = act_db_error();
			}
		}

		if($have_attribute_images) {
			// images
			$relation = _do_construct_attribute_image_relation($relations);
			if(!is_array($comb['shop']['images'])||!count($comb['shop']['images'])) {
				$res = _do_import_images($art_id, $product, $default_language_code, TRUE, $relation);
			} else {
				$res = _do_import_images($art_id, $comb, $default_language_code, TRUE, $relation);
			}
			if(!$res['ok']) {
				return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim anlegen der Bilder des Konfigurator-Artikels: '.$res['error']);
			}
		}
	}

	if(!$res)
		return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim anlegen der Konfigurator-Gruppen und Options-Zuweisung: '.join("\n", $errors));
	if(!$pres)
		return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim anlegen der Konfigurator-Artikel Preise: '.join("\n", $errors));

	return array('ok' => TRUE);
}

function _do_construct_attribute_image_relation($relations)
{
//  - Special Thanks to HR
	foreach ($relations as &$_rel)
		$_rel = join(':', array_map('strtolower', str_replace("/", "", str_replace(" ", "", $_rel))));
	unset($_rel);

	return "&{".join('/', $relations)."}";
}

/**
 * insert (/ move) products options / values
 *
 * Here we create (or insert and move) products options groups and values.
 * You are not expected to understand this.
 *
 * @return bool TRUE success, FALSE error
 */
function _do_import_attributes_options(&$options, &$values, $art_id, $default_language_id, $just_get=FALSE, $languages, &$i18n)
{
	global $import;
	$default_language_code = get_language_code_by_id($default_language_id);

	$sql = "DELETE FROM `s_articles_groups` WHERE `articleID`=".(int)$art_id;
	act_db_query($sql);

	$res = TRUE;
	// LOCK here.
	foreach ($options as $id => $_arr) {
		if(!$just_get) {
			// 1.3 create necessary groups
			if(!$_arr['_shop_id']) {	  // always the case at this time...
				$next_id = _get_next_groups_id($art_id);
				$sql = "INSERT INTO `s_articles_groups` SET `groupID`=".(int)$next_id.", `articleID`=".(int)$art_id.", `groupname`=".act_quote($_arr[$default_language_code]);
				act_db_query($sql);
				$options[$id]['_shop_id'] = $_arr['_shop_id'] = $next_id;
			}
			foreach ($languages as $lang) {
				if($lang['is_default']||!is_string($_arr[$lang['language_code']]))
					continue;
				$i18n[$lang['_shopware_code']]['configuratorgroup'][$art_id][$_arr['_shop_id']] = array('gruppenName' => $_arr[$lang['language_code']]);
			}
		}


		// 2nd: find products_values
		$values_arr = array();
		$sql = "SELECT `optionID`, `optionname` FROM `s_articles_groups_option` WHERE `articleID`=".(int)$art_id." AND `groupID`=".(int)$_arr['_shop_id']." ORDER BY `optionID`";
		if(is_object($result0 = act_db_query($sql))&&$result0->RecordCount()) {
			while ($row = $result0->FetchRow()) {
				$values_arr[(int)$row['optionID']][$default_language_code] = $row['optionname'];
			}
		}

		// 2.1 search for values
		foreach ($values[$id] as $_id => $_arr1) {
			foreach ($values_arr as $_i => $_oarr) {
				if(_attr_opts_cmp($_arr1, $_oarr)) {
					$values[$id][$_id]['_shop_id'] = $_i;
					unset($values_arr[$_i]);
				}
			}
		}

		if(!$just_get) {
			// 2.2 delete unneeded options
			foreach ($values_arr as $_id => $a) {
				$sql = "DELETE FROM `s_articles_groups_option` WHERE `groupID`=".(int)$_arr['_shop_id']." AND `articleID`=".(int)$art_id." AND `optionID`=".(int)$_id;
				act_db_query($sql);
			}
			unset($values_arr);

			// 2.2 create necessary groups
			foreach ($values[$id] as $_id => $_arr1) {
				if(!$_arr1['_shop_id']&&!$just_get) {
					$sql = "INSERT INTO `s_articles_groups_option` SET `groupID`=".(int)$_arr['_shop_id'].", `articleID`=".(int)$art_id.", `optionname`=".act_quote($_arr1[$default_language_code]);
					act_db_query($sql);
					$values[$id][$_id]['_shop_id'] = $_arr1['_shop_id'] = act_insert_id();
				}
				foreach ($languages as $lang) {
					if($lang['is_default']||!is_string($_arr1[$lang['language_code']]))
						continue;
					$i18n[$lang['_shopware_code']]['configuratoroption'][$art_id][$_arr1['_shop_id']] = array('optionName' => $_arr1[$lang['language_code']]);
				}
			}
		}
	}
	// UNLOCK here.


	return $res;
}

function _get_next_groups_id($id)
{
	global $import;

	$sql = "SELECT MAX(groupID) AS maxid FROM `s_articles_groups` WHERE `articleID`=$id";
	$result = act_db_query($sql);
	if(is_object($result0 = act_db_query($sql))&&$result0->RecordCount()) {
		$row = $result0->FetchRow();
		$row['maxid'] += 1;
		return (int)$row['maxid'];
	}
	else
		return null;
}

function _attr_opts_cmp($a, $b)
{
	$keys = array_intersect(array_keys($a), array_keys($b));
	$same = TRUE;
	foreach ($keys as $k)
		$same &=!strcasecmp($a[$k], $b[$k]);
	return $same;
}

/**
 * Import Descriptions
 * Import descriptions in base language (de) first, then in other languages via $i18n using $export_i18n=TRUE
 */
function _do_import_descriptions($product, &$data, $languages, &$i18n, $export_i18n=FALSE)
{
	$art_id = (int)$data['articleID'];

	if(is_array($product['shop']['desc'])&&count($product['shop']['desc'])) {
		foreach ($product['shop']['desc'] as $num => $description) {
			$lang_id = (int)$description['language_id'];
			$lang = $languages[$lang_id];

			if(!strlen($description['products_name'])&&strlen($product['art_name']))
				$description['products_name'] = $product['art_name'];

			if($lang['is_default']) {
				if($export_i18n)
					continue;
				$data['name'] = $description['products_name'];
				$data['description_long'] = $description['products_description'];
				$data['description'] = $description['products_short_description'];
				$data['keywords'] = $description['products_keywords'];
			}
			else {
				if(!$export_i18n)
					continue;
				$arr = array(
					'txtArtikel' => $description['products_name'],
					'txtlangbeschreibung' => $description['products_description'],
					'txtshortdescription' => $description['products_short_description'],
					'txtkeywords' => $description['products_keywords'],
				);
				is_array($i18n[$lang['_shopware_code']]['article'][$art_id])or$i18n[$lang['_shopware_code']]['article'][$art_id] = array();
				$i18n[$lang['_shopware_code']]['article'][$art_id] = array_merge($i18n[$lang['_shopware_code']]['article'][$art_id], $arr);
			}
		}
	}
	else {
		return array('ok' => FALSE, 'errno' => EUNKNOWN, 'error' => 'Keine Shoptexte hinterlegt');
	}
}

/**
 * Import Preisgruppen
 */
function _do_import_preisgruppen($data, $product, $pgruppe_translation, $customergroups)
{
	global $import;

	$prices = array();
	foreach ($product['preisgruppen'] as $pg_id => $pgruppe) {
		$prices1 = array();
		$price_ranges = array();
		$pricegroup = $pgruppe_translation[$pg_id];

		foreach (array_keys($pgruppe) as $grp) {
			if(strpos($grp, 'preis_gruppe')!==0)
				continue;
			$i = (int)substr($grp, 12);
			if($pgruppe['preis_range'.$i]>0&&$pgruppe['preis_gruppe'.$i]!=0) {
				$price_ranges[$i] = array('from' => $pgruppe['preis_range'.$i], 'price' => (float)import_price($pgruppe['preis_gruppe'.$i], $pgruppe['is_brutto'], get_tax_rate($data['taxID'])));
			}
		}
		krsort($price_ranges, SORT_NUMERIC);

		$to = 'beliebig';
		foreach ($price_ranges as $pr) {
			$price = array('pricegroup' => $pricegroup, 'from' => $pr['from'], 'to' => $to, 'price' => $pr['price']);
			if(is_array($product['shop']['products_pseudoprices'])&&isset($product['shop']['products_pseudoprices'][$pg_id]))
				$price['pseudoprice'] = (float)import_price($product['shop']['products_pseudoprices'][$pg_id], $pgruppe['is_brutto'], get_tax_rate($data['taxID']));
			if($price['price']>=$price['pseudoprice'])
				unset($price['pseudoprice']);
			$prices1[] = $price;
			$to = $pr['from']-1;
		}

		// grundpreis
		$price = array('pricegroup' => $pricegroup, 'from' => 1, 'to' => $to, 'price' => (float)import_price($pgruppe['grundpreis'], $pgruppe['is_brutto'], get_tax_rate($data['taxID'])));
		if(is_array($product['shop']['products_pseudoprices'])&&isset($product['shop']['products_pseudoprices'][$pg_id]))
			$price['pseudoprice'] = (float)import_price($product['shop']['products_pseudoprices'][$pg_id], $pgruppe['is_brutto'], get_tax_rate($data['taxID']));
		if($price['price']>=$price['pseudoprice'])
			unset($price['pseudoprice']);
		$prices1[] = $price;

		$prices1 = array_reverse($prices1);

		// renumber array
		foreach ($prices1 as $p)
			$prices[] = $p;
	}


	$res = true;
	foreach ($prices as $price) {
		$price['articleID'] = $data['articleID'];
		$price['articledetailsID'] = $data['articledetailsID'];
		$res &= $import->sArticlePrice($price);
	}

//  if( !$res )
//    return array( 'ok'=>FALSE, 'errno'=>EIO, 'error'=>'Fehler beim speichern der Preise' );

	return array('ok' => TRUE);
}

/**
 * Import Cross-Selling (Zubehör-Artikel, Ähnliche Artikel)
 */
function _do_import_xselling($data, $product)
{
	global $import;

	$res = $import->sDeleteArticleCrossSelling(array('articleID' => $data["articleID"]));
	$res = $import->sDeleteArticleSimilar(array('articleID' => $data["articleID"]));
	$res = true;
	if(is_array($product['shop']['xselling'])&&count($product['shop']['xselling'])) {
		$xs1 = $xs2 = array();
		foreach ($product['shop']['xselling'] as $xselling) {
//      $art = $import->sGetArticleID( array('ordernumber'=>$xselling['art_nr']) );

			if($xselling['group']==1)
				$xs1[] = $xselling['art_nr'];
			elseif($xselling['group']==2)
				$xs2[] = $xselling['art_nr'];
		}

		$res = $import->sArticleCrossSelling(array('articleID' => $data["articleID"]), $xs1);
		$res &= $import->sArticleSimilar(array('articleID' => $data["articleID"]), $xs2);
	}

//  if( !$res )
//    return array( 'ok'=>FALSE, 'errno'=>EIO, 'error'=>'Fehler beim speichern des Cross-Selling' );

	return array('ok' => TRUE);
}

// Special Thanks to HR
/**
 * Import Artikel Zugriffsrechte
 */
function _do_import_permissions($data, $product, $pgruppe_translation)
{
	global $import;

	$res = $import->sDeleteArticlePermissions(array('articleID' => $data["articleID"]));
	$res = true;
	if(is_array($product['shop']['group_permission'])) {
		$sql = array();
		foreach (array_keys($pgruppe_translation) as $groupID) {
			if(!(in_array($groupID, $product['shop']['group_permission']))) {
				$sql[] = $groupID;
			}
		}

		$res = $import->sArticlePermissions(array('articleID' => $data["articleID"]), $sql);
	}

//  if( !$res )
//    return array( 'ok'=>FALSE, 'errno'=>EIO, 'error'=>'Fehler beim speichern des Zugriffsbeschränkungen' );

	return array('ok' => TRUE);
}

function import_product_stock($art)
{
	if(is_array($art)) {
		if(!isset($art['art_nr'])&&count($art)) {
			$res = array('ok' => TRUE, 'success' => array(), 'failed' => array());
			foreach ($art as $_i => $_a) {
				$res1 = _import_product_stock($_a);
				$res['success'][$_i] = $res1['ok'];
				if(!$res1['ok'])
					$res['failed'][$_i] = $res1;
			}
		}
		else {
			$res = _import_product_stock($art);
		}
	}
	else
		$res = array('ok' => 0, 'errno' => EINVAL);

	return $res;
}

function _import_product_stock($product)
{
	global $api, $import;

	$articleid = $import->sGetArticleID(array("ordernumber" => $product["art_nr"]));
	if($articleid===FALSE)
		return array('ok' => FALSE, 'errno' => ENOENT);

	$res = $import->sArticleStock(array(
		"articleID" => $articleid,
		"instock" => (int)$product["l_bestand"],
		"active" => (int)$product["products_status"],
		"shippingtime" => max(0, (int)$product["shipping_status"]-1)
			));
	if(!$res)
		return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler in sArticleStock');

	if(is_array($product['attributes'])&&is_array($product['attributes']['combination_advanced'])) {
		foreach ($product['attributes']['combination_advanced'] as $_art_nr => $_val) {
			$sql = array('`instock`='.(int)$_val["l_bestand"]);
			if(isset($_val['data']["products_status"]))
				$sql[] = '`active`='.(int)$_val['data']["products_status"];
			/* TODO: NOT YET SUPPORTED BY Shopware
			  if( isset($_val['data']["shipping_status"]) )
			  $sql[] = '`shippingtime`='.(int)$_val['data']["shipping_status"] - 1;
			 */
			$sql = "UPDATE `s_articles_groups_value` SET ".join(', ', $sql)." WHERE `ordernumber`=".act_quote($_art_nr)." AND `articleID`=".(int)$articleid;
			$res &= act_db_query($sql);
		}
	}

	if(!$res)
		return array('ok' => FALSE, 'errno' => EIO, 'error' => 'Fehler beim Update der Konfiguratorartikel');

	return array('ok' => TRUE);
}

?>