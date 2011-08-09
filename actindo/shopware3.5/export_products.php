<?php

/**
 * export products
 *
 * actindo Faktura/WWS connector
 *
 * @package actindo
 * @author Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 384 $
 * @copyright Copyright (c) 2007-2008, Patrick Prasse (Schneebeerenweg 26, D-85551 Kirchheim, GERMANY, pprasse@actindo.de)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author  Holger Ronecker
 * @link    http://artdevil.de/ShopConnector ShopConnector Seite aus ArtDevil.de
 * @copyright Copyright (c) 2011, Holger Ronecker, devil@artdevil.de
 */
function export_products_count($categories_id=0, $products_model='')
{
	global $export;

	$categories = array();

	if($categories_id>0) {
		$q = "pc.`categoryparentID`={$categories_id}";
	} elseif(!empty($products_model)) {
		$q = "m.`products_model`='".esc($products_model)."'";
	}
	else
		$q = '1';

	// want only EXACTLY this category, not parents, therefore categoryparentID
	$sql = "SELECT ".($categories_id<0 ? 'pc.`categoryparentID` AS cid,' : '\''.(int)$categories_id.'\' AS cid')."COUNT(DISTINCT pc.`articleID`) AS cnt FROM `s_articles` AS m, `s_articles_categories` AS pc, `s_categories` AS c WHERE ".
			"(c.`id`=pc.`categoryparentID` OR (pc.`categoryparentID`=0 AND c.id IS NULL)) AND m.`id`=pc.`articleID` AND {$q}".($categories_id<0 ? ' GROUP BY pc.`categoryparentID`' : '');
	$cnt = act_get_array($sql, false, $force_array = true);
	foreach ($cnt as $_cnt)
		$categories[(int)$_cnt['cid']] = (int)$_cnt['cnt'];

	$sql = "SELECT COUNT(DISTINCT s_articles.id) AS count FROM s_articles, s_articles_details WHERE s_articles_details.articleID=s_articles.id";
	$cnt = act_get_array($sql, false, $force_array = true);
	$categories[-1] = (int)$cnt[0]['count'];

	return $categories;
}

function __do_export_products($categories_id=0, $products_model='', $products_id=0, $lang='', $just_list=TRUE, $from=0, $count=0x7FFFFFFF, $filters=array())
{
	global $export;

	$categories_id = (int)$categories_id;
	$products = array();

	if(!$lang)
		$lang = default_lang();

	if($categories_id) {
		// want only EXACTLY this category, not parents
		$q = "pc.`categoryparentID`={$categories_id}";
	} else if(!empty($products_model)) {
		$q = "d.`ordernumber`='".esc($products_model)."'";
	} else if($products_id) {
		$q = "a.`id`=".(int)$products_id;
	}
	else
		$q = '1';


	isset($filters['start'])or$filters['start'] = (int)$from;
	isset($filters['limit'])or$filters['limit'] = (int)$count;
	$mapping = array(
		'products_id' => array('a', 'id'),
		'art_nr' => array('d', 'ordernumber'),
		'created' => array('a', 'datum'),
		'last_modified' => array('a', 'changetime'),
		'products_status' => array('a', 'active', 'boolean'),
		'art_name' => array('a', 'name'),
		'categories_id' => array('pc', 'categoryparentID')
	);
	$qry = create_query_from_filter($filters, $mapping);
	if(!is_array($qry))
		return array('ok' => false, 'errno' => EINVAL, 'error' => 'Error in filter definition');


	$exported = array();
	$sql = "SELECT
      a.id as `products_id`,
      d.ordernumber AS `art_nr`,
      0.0 AS grundpreis,
      a.datum as `products_date_added`,
      a.changetime as `products_last_modified`,
      d.active AS `products_status`,
      a.name AS `art_name`,
  ";

	if(!$just_list) {
		$sql .= "d.id as `articledetailsID`,
      d.instock AS products_quantity,
      a.shippingtime AS shipping_status,
      d.weight AS products_weight,
      a.supplierID AS manufacturers_id,
      a.releasedate AS products_date_available,
      a.description AS products_short_description,
      a.description_long AS products_description,
      a.keywords AS products_keywords,
      a.unitID AS products_vpe,
      IF(a.unitID=0,0,1) AS products_vpe_status,
      a.purchaseunit AS products_vpe_value,
      a.referenceunit AS products_vpe_referenzeinheit,
      a.purchasesteps AS products_vpe_staffelung,
      d.weight AS weight,
      IF(a.unitID=0,'Stück',cu.unit) AS einheit,
    ";
// Holger, auf Artikelbasisdaten Reiter Felder Artikeleinheit: & Artikelgewicht: setzen

		if(is_shopware3()) {
			$sql.= "d.esd AS products_digital,
        a.pseudosales AS pseudosales,
        a.shippingfree AS shipping_free,
        d.suppliernumber AS suppliernumber,
        a.topseller AS topseller,
        d.position AS products_sort,
        a.filtergroupID AS filtergroup_id,
        a.laststock AS abverkauf,
        a.crossbundlelook AS bundle,
        a.notification AS notification,
      ";
// Holger, das "Bitte eMail-Benachrichtigung einbinden" Feld soll natürlich auch exportiert werden
		}
	}
	$sql .= "
      pc.categoryparentID AS categories_id
    FROM
      (s_articles a,
      s_articles_details d)
    LEFT JOIN s_articles_categories AS pc ON (pc.articleID=a.id)
    LEFT JOIN s_core_units AS cu ON (cu.id=a.unitID)
    WHERE
      d.ordernumber NOT LIKE 'BLOG%' AND
      d.articleID = a.id AND d.kind=1 AND ({$q}) AND ({$qry['q_search']}) GROUP BY a.`id` ORDER BY {$qry['order']} LIMIT {$qry['limit']}";

	$result = act_db_query($sql);
	if(!$result)
		return array('ok' => false, 'errno' => EIO, 'error' => 'Error in search query');

	while ($prod = act_db_fetch_assoc($result)) {
		if(!$categories_id&&isset($exported[(int)$prod['products_id']]))   // already exported, skip
			continue;

		$exported[(int)$prod['products_id']] = 1;

		$prod['products_id'] = (int)$prod['products_id'];
		$prod['grundpreis'] = (float)$prod['grundpreis'];
		$prod['categories_id'] = (int)$prod['categories_id'];
		$prod['products_status'] = (int)$prod['products_status'];
		$prod['created'] = datetime_to_timestamp($prod['products_date_added']);
		$prod['last_modified'] = datetime_to_timestamp($prod['products_last_modified']);
		if($prod['last_modified']<=0)
			$prod['last_modified'] = $prod['created'];
		unset($prod['products_date_added'], $prod['products_last_modified']);

		$products[] = $prod;
	}
	act_db_free($result);

	return array('ok' => TRUE, 'products' => $products);
}

function export_products_list($categories_id=0, $products_model='', $lang='', $just_list=TRUE, $from=0, $count=0x7FFFFFFF, $filters=array())
{
	return __do_export_products($categories_id, $products_model, /* $products_id= */ 0, $lang, /* $just_list= */ TRUE, $from, $count, $filters);
}

function export_products($categories_id=0, $products_id=0, $lang='', $just_list=FALSE, $from=0, $count=0x7FFFFFFF, $filters=array())
{
	global $export;
	$pgruppe_translation = array_flip(get_preisgruppe_translation());   // $pgruppe_translation is now an array like array( 'EK'=>2, 'H'=>3, etc)
	$attribute_translation = actindo_attribute_translation_table();
	$res = actindo_get_fields(TRUE, FALSE);
	$property_field_ids = array_keys($res['fields']);

	$res = __do_export_products($categories_id, /* $products_model= */ '', $products_id, $lang, /* $just_list= */ FALSE, $from, $count, $filters);
	if(!$res['ok'])
		return $res;

	$products = $res['products'];
	$articleIDs = array();
	$articledetailsIDs = array();
	foreach ($res['products'] as $_p) {
		$articleIDs[$_p['products_id']] = (int)$_p['products_id'];
		$articledetailsIDs[$_p['products_id']] = (int)$_p['articledetailsID'];
	}
	unset($res);

	$articledetails = $export->sArticlesDetails(array('articleIDs' => $articleIDs));
	$articleprices = $export->sArticlePrices(compact("articledetailsIDs"));
	$articlecategories = a_sArticleCategories(array("articleIDs" => $articleIDs));	  // !! actindo
	$articleimages = $export->sArticleImages(array("articleIDs" => $articleIDs));


	foreach ($products as $_idx => $p) {
		$id = $p['products_id'];

		// art_nr, art_name, products_id, , products_status, created, last_modified already here.

		$p["weight_unit"] = "kg";
		$p["l_bestand"] = (float)$p["products_quantity"];
		$p['shipping_status'] += 1;	 // this is important, AS id=1 means _0_ days, id=2 means 1 day, etc.

		foreach ($attribute_translation as $_shopware => $_actindo)
			$p[$_actindo] = $attributes[$id][$_shopware];


		// primary category
		$p['categories_id'] = (int)$p['categories_id'];

		// other categories
		$p['all_categories'] = $articlecategories[$id];

		// base price, taxes +
		// price brackets
		_do_export_preisgruppen($p, $articleprices[(int)$p['articledetailsID']], $pgruppe_translation);

		// descriptions, names in all languages
		_do_export_descriptions($p);

		// cross-selling
		_do_export_xselling($p, $articledetails[$id]);


		// images
		$p['images'] = array();
		$i = 0;
		foreach ($articleimages[$id] as $_img) {
			if(!empty($_img['relations']))	// attributs-artikel-bilder
				continue;

			$imgpath = SHOPWARE_BASEPATH.$export->sSystem->sCONFIG['sARTICLEIMAGES'].'/'.$_img['img'].'.jpg';
			$p['images'][] = array(
				'image' => file_get_contents($imgpath),
				'image_size' => filesize($imgpath),
				'image_type' => 'image/jpeg',
				'image_name' => $_img['img'].'.jpg',
				'image_nr' => $i++,
			);
		}

		// attributes [HERE: Konfigurator!]
		_do_export_attributes($p, $articleprices[(int)$p['articledetailsID']], $pgruppe_translation, $articleimages[$id]);

		// content
		_do_export_content($p);

		// Attributes [Zusatzfelder!]
		_do_export_properties($p, $property_field_ids);

		$products[$_idx] = $p;
	}

	return array('ok' => TRUE, 'products' => $products);
}

function _do_export_properties(&$p, $property_field_ids)
{
	global $export;

	$id = $p['products_id'];
	$detailsid = (int)$p['articledetailsID'];
	$default_language_id = default_lang();
	$languages = actindo_get_languages();
	$p['properties'] = array();

	$sql = "SELECT * FROM `s_articles_attributes` WHERE articleID=".(int)$id." AND articledetailsID=".(int)$detailsid;
	$result = act_db_query($sql);
	$row = act_db_fetch_assoc($result);
	if(is_array($row)&&count($row)) {
		$xl = actindo_get_translation('article', $id);
		unset($row['articleID'], $row['articledetailsID'], $row['id']);

		foreach ($row as $_key => $_val) {
			if(!in_array($_key, $property_field_ids))
				continue;

			$r = array(
				'field_id' => $_key,
				'language_code' => null,
				'field_value' => $_val,
			);
			if(!count($xl))
				$p['properties'][] = $r;
			else {
				$no_translations = true;
				foreach ($xl as $_langcode => $tmp) {
					if(isset($tmp[$_key])) {
						$r['language_code'] = $_langcode;
						$r['field_value'] = $tmp[$_key];
						$p['properties'][] = $r;
						$no_translations = false;
					}
				}

				if($no_translations)
					$p['properties'][] = $r;
				else {
					$r['language_code'] = get_language_code_by_id($default_language_id);
					$p['properties'][] = $r;
				}
			}
		}
	}

	if(is_shopware3()) {
		$sql = "SELECT * FROM `s_filter_values` WHERE articleID=".(int)$id;
		$result = act_db_query($sql);
		if(is_object($result)) {
			$xl = actindo_get_translation('properties', $id);
			while ($row = act_db_fetch_assoc($result)) {
				$_key = (int)$row['optionID'];
				$r = array(
					'field_id' => 'filter'.(int)$row['optionID'],
					'language_code' => null,
					'field_value' => $row['value'],
				);
				if(!count($xl)) {
					$p['properties'][] = $r;
				} else {
					$r['language_code'] = get_language_code_by_id($default_language_id);
					$p['properties'][] = $r;

					$no_translations = true;
					foreach ($xl as $_langcode => $tmp) {
						if(isset($tmp[$_key])) {
							$r['language_code'] = $_langcode;
							$r['field_value'] = $tmp[$_key];
							$p['properties'][] = $r;
							$no_translations = false;
						}
					}
				}
			}
			act_db_free($result);
		}
	}
}

function _do_export_content(&$p)
{
	global $export;
	$id = $p['products_id'];
	$default_language_id = default_lang();
	$languages = actindo_get_languages();
	$p['content'] = array();

	$result = act_db_query("SELECT * FROM `s_articles_information` WHERE articleID=".(int)$id);
	if(is_object($result)) {
		while ($info = act_db_fetch_assoc($result)) {   // information
			$c = array(
				'language_code' => get_language_code_by_id($default_language_id),
				'type' => 'link',
				'content' => $info['link'],
				'content_target' => $info['target'],
				'content_name' => $info['description'],
			);
			$p['content'][] = $c;
			$xl = actindo_get_translation('link', $info['id']);
			foreach ($xl as $_langcode => $tmp) {
				if(isset($tmp['linkname'])) {
					$c['language_code'] = $_langcode;
					$c['content_name'] = $tmp['linkname'];
					$p['content'][] = $c;
				}
			}
		}
		act_db_free($result);
	}

	$result = act_db_query("SELECT * FROM `s_articles_downloads` WHERE articleID=".(int)$id);
	if(is_object($result)) {
		while ($dnld = act_db_fetch_assoc($result)) {   // information
			$dnldpath = SHOPWARE_BASEPATH.$export->sSystem->sCONFIG['sARTICLEFILES'].'/'.$dnld['filename'];
			$content = file_get_contents($dnldpath);

			$c = array(
				'language_code' => get_language_code_by_id($default_language_id),
				'type' => 'file',
				'content' => $content,
				'content_file_name' => $dnld['filename'],
				'content_file_size' => $dnld['size'],
				'content_file_md5' => md5($content),
				'content_name' => $dnld['description'],
			);
			$p['content'][] = $c;
			$xl = actindo_get_translation('download', $dnld['id']);
			foreach ($xl as $_langcode => $tmp) {
				if(isset($tmp['downloadname'])) {
					$c['language_code'] = $_langcode;
					$c['content_name'] = $tmp['downloadname'];
					$p['content'][] = $c;
				}
			}
		}
		act_db_free($result);
	}

	return array('ok' => TRUE);
}

function _do_export_descriptions(&$p)
{
	global $export;
	$id = $p['products_id'];
	$default_language_id = default_lang();
	$languages = actindo_get_languages();

	$p['description'] = array();
	$rows = array();
	$sql = "SELECT `objectdata`, `objectlanguage` FROM `s_core_translations` WHERE `objecttype`='article' AND `objectkey`=".(int)$id;
	if(is_object($result = act_db_query($sql))) {
		while ($row = act_db_fetch_assoc($result))
			$rows[$row["objectlanguage"]] = unserialize($row["objectdata"]);
		act_db_free($result);
	}

	foreach ($languages AS $lang) {
		$langid = (int)$lang['language_id'];

		if($lang['is_default']) {
			$p['description'][$langid] = array(
				'language_id' => $langid,
				'products_name' => $p['art_name'],
				'products_description' => $p['products_description'],
				'products_short_description' => $p['products_short_description'],
				'products_keywords' => $p['products_keywords'],
					/*
					  'products_meta_title' =>
					  'products_meta_description' =>
					  'products_meta_keywords' =>
					  'products_url' =>
					  'products_viewed' =>
					 */
			);
		} else {
			$r = $rows[$lang['_shopware_code']];
			if(is_array($r)) {
				$p['description'][$langid] = array(
					'language_id' => $langid,
					'products_name' => $r['txtArtikel'],
					'products_description' => $r['txtlangbeschreibung'],
					'products_short_description' => $r['txtshortdescription'],
					'products_keywords' => $r['txtkeywords'],
						/*
						  'products_meta_title' =>
						  'products_meta_description' =>
						  'products_meta_keywords' =>
						  'products_url' =>
						  'products_viewed' =>
						 */
				);
			}
		}
	}

	return array('ok' => TRUE);
}

function _do_export_preisgruppen(&$p, $articleprices, $pgruppe_translation)
{
	// ACHTUNG: $price['net'] > 0 ist BRUTTO!!!!!
	// Holger, Fehler: Die Variable heißt $price['netto'] !!! nicht ['net']
	// Dadurch wurde auch in der Funktion export_price() fehlerhaft gearbeitet. Dort wurde der Fehler ebenfalls behoben
	// Die ganzen (float)s bei der Übergabe an die Funktion export_price() wurden entfernt, sie haben die Kommastelle abgeschnitten

	$p['preisgruppen'] = $p['products_pseudoprices'] = array();

	$prices_by_group = array();
	// for now we always use EK as primary pricegroup
	foreach ($articleprices as $_price) {
		if($_price['pricegroup']=='EK'&&$_price['from']==1) {
			$p['is_brutto'] = ((int)$_price['netto']) ? 0 : 1;
			$p['mwst'] = (float)$_price['tax'];
			$p['grundpreis'] = export_price($_price['price'], $_price['netto'], $_price['tax']);
		}

		$pg_id = $pgruppe_translation[$_price['pricegroup']];

		isset($prices_by_group[$pg_id])or$prices_by_group[$pg_id] = array();
		$prices_by_group[$pg_id][] = $_price;
	}

	/*
	  // Holger, aus irgend einem Grund funktioniert die Schleife nicht, wenn es mehr als einen Preis pro Preigruppe gibt
	  // Konkret, die  if( $price['from'] == 1 )  Abfrage ist fehlerhaft.
	  // Nachfolgend meine Version die funktioniert
	  foreach( $prices_by_group as $pg_id => $prices )
	  {
	  usort( $prices, '_prices_sorter' );

	  $i = 0;
	  foreach( $prices as $price )
	  {
	  // Der Befehl gehört hierhin um das Brutto/Netto der Preisgruppe abgereifen zu können und nicht
	  // wie vorher immer den Wert der Grundpreis Gruppe zu übergeben.
	  $p['preisgruppen'][$pg_id] = array(
	  'is_brutto' => ((int)$price['netto']) ? 0 : 1,
	  );

	  if( $price['from'] == 1 )
	  {
	  $p['preisgruppen'][$pg_id]['grundpreis'] = export_price( $price['price'], $price['netto'], $price['tax'] );
	  $p['products_pseudoprices'][$pg_id] = export_price( $price['pseudoprice'], $price['netto'], $price['tax'] );
	  }
	  else
	  {
	  $i++;
	  $p['preisgruppen'][$pg_id]['preis_gruppe'.$i] = export_price( $price['price'], $price['netto'], $price['tax'] );
	  $p['preisgruppen'][$pg_id]['preis_range'.$i] = $price['from'];
	  }
	  }
	  }
	 */

	foreach ($prices_by_group as $pg_id => $prices) {
		usort($prices, '_prices_sorter');

		$i = 0;
		foreach ($prices as $price) {
			// Der Befehl gehört hierhin um das Brutto/Netto der Preisgruppe abgereifen zu können und nicht 
			// wie vorher immer den Wert der Grundpreis Gruppe zu übergeben.
			$p['preisgruppen'][$pg_id] = array(
				'is_brutto' => ((int)$price['netto']) ? 0 : 1,
			);

			if($price['from']==1) {
				$p1[$pg_id]['grundpreis'] = export_price($price['price'], $price['netto'], $price['tax']);
				$p['products_pseudoprices'][$pg_id] = export_price($price['pseudoprice'], $price['netto'], $price['tax']);
			} else {
				$i++;
				$p2[$pg_id]['preis_gruppe'.$i] = export_price($price['price'], $price['netto'], $price['tax']);
				$p2[$pg_id]['preis_range'.$i] = $price['from'];
			}
		}
	}

	foreach ($p1 as $key => $value) {
		foreach ($value as $datakey => $datavalue) {
			$p['preisgruppen'][$key][$datakey] = $datavalue;
		}
		foreach ($p2[$key] as $datakey => $datavalue) {
			$p['preisgruppen'][$key][$datakey] = $datavalue;
		}
	}

// Holger, Ende

	return array('ok' => TRUE);
}

function _prices_sorter($a, $b)
{
	if((float)$a['from']<(float)$b['from'])
		return -1;
	else if((float)$a['from']>(float)$b['from'])
		return 1;
	return 0;
}

function _do_export_attributes(&$p, $articleprices, $pgruppe_translation, &$images)
{
	global $export;
	$id = $p['products_id'];
	$default_language_id = default_lang();
	$default_language_code = get_language_code_by_id($default_language_id);

	$sql = "SELECT `groupID`, `groupname` FROM `s_articles_groups` WHERE `articleID`=".(int)$id." ORDER BY `groupID`";
	if(is_object($result0 = act_db_query($sql))&&act_db_num_rows($result0)) {
		$p['attributes'] = array();
		while ($row = act_db_fetch_assoc($result0)) {
			$row['groupID'] = (int)$row['groupID'];

			$p['attributes']['names'][$row['groupID']] = array(get_language_code_by_id($default_language_id) => $row['groupname']);
			$xl = actindo_get_translation('configuratorgroup', $id);
			foreach ($xl as $_langcode => $tmp) {
				if(isset($tmp[$row['groupID']])&&isset($tmp[$row['groupID']]['gruppenName']))
					$p['attributes']['names'][$row['groupID']][$_langcode] = $tmp[$row['groupID']]['gruppenName'];
			}

			$p['attributes']['values'][$row['groupID']] = array();

			$sql = "SELECT `optionID`, `optionname` FROM `s_articles_groups_option` WHERE `articleID`=".(int)$id." AND `groupID`=".(int)$row['groupID']." ORDER BY `optionID`";
			if(is_object($result1 = act_db_query($sql))&&act_db_num_rows($result1)) {
				while ($oid = act_db_fetch_assoc($result1)) {
					$oid['optionID'] = (int)$oid['optionID'];
					$p['attributes']['values'][$row['groupID']][$oid['optionID']][$default_language_code] = $oid['optionname'];
					$xl = actindo_get_translation('configuratoroption', $id);
					foreach ($xl as $_langcode => $tmp) {
						if(isset($tmp[$oid['optionID']])&&isset($tmp[$oid['optionID']]['optionName']))
							$p['attributes']['values'][$row['groupID']][$oid['optionID']][$_langcode] = $tmp[$oid['optionID']]['optionName'];
					}
				}
			}
		}
		act_db_free($result0);

		$p['attributes']['combination_advanced'] = array();
		$sql = "SELECT * FROM `s_articles_groups_value` WHERE `articleID`=".(int)$id." ORDER BY `valueID`";
		if(is_object($result = act_db_query($sql))&&act_db_num_rows($result)) {
			while ($row = act_db_fetch_assoc($result)) {
				$image_combis = array();

				$p['attributes']['combination_advanced'][$row['ordernumber']] = array(
					'attribute_name_id' => array(),
					'attribute_value_id' => array(),
					'l_bestand' => (float)$row['instock'],
					'preisgruppen' => array(),
					'data' => array(
						'products_status' => (int)$row['active'],
						'products_is_standard' => (int)$row['standard'],
					),
				);
				foreach (array_keys($p['attributes']['names']) as $attr_name_id) {
					$image_combis[] = strtolower($p['attributes']['names'][$attr_name_id][$default_language_code]).':'.strtolower($p['attributes']['values'][$attr_name_id][(int)$row['attr'.$attr_name_id]][$default_language_code]);
					$p['attributes']['combination_advanced'][$row['ordernumber']]['attribute_name_id'][] = $attr_name_id;
					$p['attributes']['combination_advanced'][$row['ordernumber']]['attribute_value_id'][] = (int)$row['attr'.$attr_name_id];
				}

				$sql = "SELECT `price`, `groupkey` FROM `s_articles_groups_prices` WHERE `articleID`=".(int)$id." AND `valueID`=".(int)$row['valueID']." ORDER BY `groupkey`";
				if(is_object($result1 = act_db_query($sql))&&act_db_num_rows($result1)) {
					while ($_price = act_db_fetch_assoc($result1)) {
						$found_pg = null;
						foreach ($articleprices as $_pg) {
							if($_pg['pricegroup']==$_price['groupkey']) {
								$found_pg = $_pg;
								break;
							}
						}
						if(is_null($found_pg))
							continue;

						$pg_id = $pgruppe_translation[$_price['groupkey']];
						$pg = array(
							'is_brutto' => $p['preisgruppen'][$pg_id]['is_brutto'],
							'grundpreis' => export_price((float)$_price['price'], $found_pg['net'], $found_pg['tax']),
						);
						$p['attributes']['combination_advanced'][$row['ordernumber']]['preisgruppen'][$pg_id] = $pg;
						if($_price['groupkey']=='EK') {
							// this is grundpreis
							$p['attributes']['combination_advanced'][$row['ordernumber']] =
									array_merge($p['attributes']['combination_advanced'][$row['ordernumber']], $pg);
						}
					}
					act_db_free($result1);
				}

				foreach (array_keys($p['attributes']['names']) as $attr_name_id) {
					$p['attributes']['combination_simple'][$attr_name_id][(int)$row['attr'.$attr_name_id]] = array(
						'options_values_price' => 0,
						'attributes_model' => '',
						'options_values_weight' => 0,
						'sortorder' => 0
					);
				}

				// images
				$p['attributes']['combination_advanced'][$row['ordernumber']]['shop']['images'] = array();
				$p_images = & $p['attributes']['combination_advanced'][$row['ordernumber']]['shop']['images'];
				$i = 0;
				foreach ($images as $_img) {
					if(empty($_img['relations']))	// haupt-artikel-bilder
						continue;

					// oh hell, this will never be fast
					$conditions = array();
					preg_match('/^([&|])\{([^\}]+)\}$/', $_img['relations'], $matches);
					foreach (split('/', $matches[2]) as $_cond)
						$conditions[] = sprintf("in_array('%s', \$image_combis)", addslashes($_cond));
					$conditions = join($matches[1]=='&' ? ' && ' : ' || ', $conditions);
					$image_relevant = false;
					eval("\$image_relevant = {$conditions};");

					if(!$image_relevant)
						continue;

					$imgpath = SHOPWARE_BASEPATH.$export->sSystem->sCONFIG['sARTICLEIMAGES'].'/'.$_img['img'].'.jpg';
					$p_images[] = array(
						'image' => file_get_contents($imgpath),
						'image_size' => filesize($imgpath),
						'image_type' => 'image/jpeg',
						'image_name' => $_img['img'].'.jpg',
						'image_nr' => $i++,
					);
				}
				unset($p_images);
			}
			act_db_free($result);
		}
	}

	return array('ok' => TRUE);
}

function _do_export_xselling(&$p, $articledetails)
{
	global $export;

	$p['xselling'] = array();
	if(is_array($articledetails['relationships'])) {
		foreach ($articledetails['relationships'] as $art_nr)
			$p['xselling'][] = array('art_nr' => $art_nr, 'group' => 1, 'sort_order' => 0);
	}

	if(is_array($articledetails['similar'])) {
		foreach ($articledetails['similar'] as $art_nr)
			$p['xselling'][] = array('art_nr' => $art_nr, 'group' => 2, 'sort_order' => 0);
	}

	return array('ok' => TRUE);
}

function a_sArticleCategories($article_categories)
{
	global $export;

	if(!empty($article_categories['articleID']))
		$article_categories['articleIDs'][] = $article_categories['articleID'];
	if(!empty($article_categories['articleIDs'])&&is_array($article_categories['articleIDs'])) {
		$article_categories['articleIDs'] = array_map("intval", $article_categories['articleIDs']);
		$article_categories['where'] = "(`articleID`=".implode(" OR `articleID`=", $article_categories['articleIDs']).")";
	}
	$sql = "SELECT DISTINCT `articleID`, `categoryparentID` FROM `s_articles_categories` WHERE {$article_categories['where']}";
	if(!$result = act_db_query($sql))
		return false;
	while ($row = act_db_fetch_assoc($result)) {
		$rows[$row["articleID"]][] = $row["categoryparentID"];
	}
	act_db_free($result);
	return $rows;
}

?>