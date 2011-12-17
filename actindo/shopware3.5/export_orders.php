<?php

/**
 * export orders
 *
 * actindo Faktura/WWS connector
 *
 * @package actindo
 * @author  Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 392 $
 * @copyright Copyright (c) 2007, Patrick Prasse (Schneebeerenweg 26, D-85551 Kirchheim, GERMANY, pprasse@actindo.de)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author  Holger Ronecker
 * @link    http://artdevil.de/ShopConnector ShopConnector Seite auf ArtDevil.de
 * @copyright Copyright (c) 2011, Holger Ronecker, devil@artdevil.de
 */
function export_orders_count()
{
	global $export;
	$res = $export->sGetLastOrderID();

	$counts = array("count" => (int)$res["count"], "max_order_id" => (int)$res["lastID"]);
	return array('ok' => TRUE, 'counts' => $counts);
}

function export_orders_list($filters=array(), $from=0, $count=0x7FFFFFFF)
{
	global $export;
	global $connectorlogin;

	isset($filters['start'])or$filters['start'] = (int)$from;
	isset($filters['limit'])or$filters['limit'] = (int)$count;
	!empty($filters['sortColName'])or$filters['sortColName'] = 'order_id';
	!empty($filters['sortOrder'])or$filters['sortOrder'] = 'DESC';

	$salutation_map = actindo_get_salutation_map();
	$paymentmeans = actindo_get_paymentmeans();

	$mapping = array(
		'order_id' => array('o', 'id'),
		'external_order_id' => array('o', 'ordernumber'),
		'deb_kred_id' => array('b', 'customernumber'),
		'_customers_id' => array('o', 'userID'),
		'orders_status' => array('o', 'status'),
	);
	$qry = create_query_from_filter($filters, $mapping);
	if($qry===FALSE)
		return array('ok' => false, 'errno' => EINVAL, 'error' => 'Error in filter definition');

	$orders1 = $export->sGetOrders(
			array('where' => $qry['q_search'].' AND o.status>=0', 'limit' => $qry['limit'], 'order' => $qry['order']/* '`orderID` DESC' */)
	);
	if(!is_array($orders1))
		return array('ok' => FALSE, 'errno' => ENOENT, 'error' => 'Keine Bestellungen gefunden oder Datenbank-Fehler');

	$orderIDs = array_keys($orders1);
	$customers = $export->sOrderCustomers(array("orderIDs" => $orderIDs));

	$orders = array();
	foreach ($orders1 as $order) {
		$customer = $customers[$order['orderID']];

		/* actindo, PP:
		 * deb_kred_id ist hier auskommentiert, da Shopware die Kundennummern bis 3.03b nicht in s_order_billingaddress.customernumber übernommen hat.
		 * Da ab 3.04 die customernumber von s_user_billingaddress in s_order_billingaddress übernommen wird und die Kundennummer von Shopware intern
		 * erzeugt wird, kommt es in actindo zu Konflikten.
		 *
		 * Beispiel:
		 * Shop-Kunde Müller, KdNr Shop 20001 wird in actindo übernommen und dort mit 20001 angelegt.
		 * Vom Händler wird eine telefonische Bestellung für Kunde Maier in actindo aufgenommen, dieser bekommt die KdNr 20002 in actindo.
		 *
		 * Nun ist die nächste Kundennummer in Shopware 20002 und in actindo 20003.
		 * Der Import der nächsten Bestellung eines Neukunden nach actindo hätte einen mismatch der Kundennummern zur folge.
		 *
		 * actindo matcht allerdings natürlich trotzdem richtigerweise die Email-Adresse der Kunden, so dass die richtige Kundennummer gefunden wird.
		 *
		 * @see bug #5933
		 */

		$actindoorder = array(
			'order_id' => $order['orderID'],
			'external_order_id' => $order['order_number'],
			'project_id' => $order['order_number'],
			'customer' => array(
				/* 'deb_kred_id' => (int)($customer['customernumber'] > 0 ? $customer['customernumber'] : 0), */ /* MB: bug #5933 */
				'anrede' => $salutation_map[$customer['billing_salutation']],
				'kurzname' => !empty($customer['billing_company']) ? $customer['billing_company'] : sprintf("%s, %s", $customer['billing_lastname'], $customer['billing_firstname']),
				'firma' => $customer['billing_company'],
				'name' => $customer['billing_lastname'],
				'vorname' => $customer['billing_firstname'],
				'adresse' => trim($customer['billing_street']).' '.trim( strtr($customer['billing_streetnumber'], array(' ' => '')) ),
				'adresse2' => trim($customer['billing_department']),
				'plz' => $customer['billing_zipcode'],
				'ort' => $customer['billing_city'],
				'land' => $customer['billing_countryiso'],
				'tel' => $customer['billing_phone'],
				'fax' => $customer['billing_fax'],
				'ustid' => $customer['ustid'],
				'email' => $customer['email'],
				'_customers_id' => (int)$order['userID'],
				'preisgruppe' => $customer['preisgruppe'],
				'gebdat' => $customer['birthday'],
			),
			'delivery' => array(
				'anrede' => $salutation_map[$customer['shipping_salutation']],
				'firma' => $customer['shipping_company'],
				'name' => $customer['shipping_lastname'],
				'vorname' => $customer['shipping_firstname'],
				'adresse' => trim($customer['shipping_street']).' '.trim( strtr($customer['shipping_streetnumber'], array(' ' => '')) ),
				'adresse2' => trim($customer['shipping_department']),
				'plz' => $customer['shipping_zipcode'],
				'ort' => $customer['shipping_city'],
				'land' => $customer['shipping_countryiso'],
				'tel' => $customer['billing_phone'], // no shipping_ equivalent
				'fax' => $customer['billing_fax'],
				'ustid' => $customer['ustid'],
			),
			/* 'deb_kred_id' => (int)($customer['customernumber'] > 0 ? $customer['customernumber'] : 0), */
			'_customers_id' => (int)$order['userID'],
			// 'payment_method' needs special mapping

			'beleg_status_text' => trim(str_replace("'", "", $order['customercomment'])),
			'subshop_id' => $order['subshopID'],
			'tstamp' => $order['ordertime'],
			'bill_date' => $order['ordertime'],
			'currency' => $order['currency'],
			'currency_value' => $order['currencyFactor'],
			'language' => $customer['language'],
			'langcode' => $customer['language'],
			'_payment_method' => $paymentmeans[$order['paymentID']]['name'],
			'orders_status' => $order['statusID'],
		);

		// Holger, Bearbeiter setzen
		if(isset($connectorlogin)) {
			$actindoorder['signum'] = $connectorlogin;
		}

		preg_match('/^(\d{4}-\d{2}-\d{2})(\s+(\d+:\d+:\d+))?$/', $order['ordertime'], $matches);
		$actindoorder['webshop_order_date'] = $matches[1];
		$actindoorder['webshop_order_time'] = $matches[3];


		$verfmap = array(
			'debit' => 'L',
			'cash' => 'NN',
			'invoice' => 'U',
			'prepayment' => 'VK',
			'debituos' => 'L',
			'credituos' => 'KK',
			'giropayuos' => 'GP',
			'uos_ut' => 'UT',
			'uos_ut_gp' => 'GP',
			'uos_ut_kk' => 'KK',
			'uos_ut_ls' => 'L',
			'uos_ut_vk' => 'VK',
			'prepaiduos' => 'VK',
			'invoiceuos' => 'U',
			'paypal' => 'PP',
			'paypalexpress' => 'PP',
			'ipayment' => 'KK',
			'cashExpress' => 'NN',
			'FinanzkaufBySantander' => 'FZ',
			'sofortueberweisung' => 'SU',
			'billsafe_invoice' => 'BS',
		);
		$actindoorder['customer']['verf'] = $verfmap[$actindoorder['_payment_method']];
		if(is_null($actindoorder['customer']['verf']))
			$actindoorder['customer']['verf'] = 'VK';		 // generic prepaid

		if($actindoorder['customer']['verf']=='L') {
			$res1 = $export->sDB->Execute("SELECT * FROM `s_user_debit` WHERE `userID`=".(int)$order['userID']);
			$row = $res1->FetchRow();
			$res1->Close();
			$actindoorder['customer']['blz'] = str_replace(" ", "", str_replace("-", "", str_replace("/", "", $row['bankcode'])));
			$actindoorder['customer']['kto'] = $row['account'];
		} else if($actindoorder['customer']['verf']=='PP') {
			$actindoorder['payment']['pp'] = array();
			$res1 = $export->sDB->Execute("SELECT * FROM `paypal_orders` WHERE `stransId`='".esc($order['transactionID'])."'");
			$row = $res1->FetchRow();
			$res1->Close();
			$actindoorder['payment']['pp']['trx_no'] = $row['transactionId'];
			$actindoorder['payment']['pp']['type'] = $row['authorization'] ? 'authorization' : 'payment';
			$actindoorder['payment']['pp']['payer_id'] = $row['payerId'];
		}

//    $actindoorder['customer']['langcode'] = strtolower( $order['langcode'] );
//    $actindoorder['delivery']['langcode'] = strtolower( $order['langcode'] );

		$actindoorder['val_date'] = $actindoorder['bill_date'];

		$actindoorder['customer']['print_brutto'] = $order['net'] ? 0 : 1;


		$actindoorder['saldo'] = $order['invoice_amount'];
		$actindoorder['netto2'] = $order['invoice_amount_net'];
		$actindoorder['rabatt_type'] = 'betrag';
		$actindoorder['rabatt_betrag'] = 0.00;
		$actindoorder['netto'] = $actindoorder['netto2']-$actindoorder['rabatt_betrag'];

		$actindoorder['_shoporder'] = $order;

		$positions1 = $export->sOrderDetails(array("orderID" => $order['orderID']));
		if(is_array($positions1)) {
			foreach ($positions1 as $pos) {
				if($pos['modus']==3) {	// DISCOUNT
					$actindoorder['rabatt_betrag'] -= (float)$pos['price'] / round ($actindoorder['saldo'] / $actindoorder['netto'], 2);   // rabatt is positive, price is negative
				}
			}
		}

		$orders[] = $actindoorder;
	}


	return array('ok' => TRUE, 'orders' => $orders);
}

function export_orders_positions($order_id)
{
	global $export;

	$orders1 = $export->sGetOrders(
			array('orderID' => $order_id)
	);
	$orders1 = $orders1[$order_id];
	if(!is_array($orders1))
		return array('ok' => FALSE);

	$positions1 = $export->sOrderDetails(array("orderID" => $order_id));
	if(!is_array($positions1))
		return array('ok' => FALSE);

	// Special Thanks to HR
	$versand_nr = 'VERSAND';
	$versand_name = 'Versandkosten';
	$versand_mwst = 0;
	$versand_langtext = '';

	if($export->sSystem->sCONFIG['sPREMIUMSHIPPIUNG']==1) {
		// Premium Versand Modul
		$versand_nr = $versand_name = $orders1['dispatch_description'];
		$row0 = act_get_row("SELECT `pd`.`tax_calculation`, `t`.`tax` FROM `s_premium_dispatch` as `pd` LEFT JOIN `s_core_tax` as `t` ON `t`.`id` = `pd`.`tax_calculation` WHERE `pd`.`id`=".$orders1['dispatchID']);
		if(is_array($row0)&&isset($row0['tax']))
			$versand_mwst = $row0['tax'];
	} else {
		// altes Versand Modul
		$versand_nr = $versand_name = $orders1['dispatch_description'];
		$versand_mwst = $export->sSystem->sCONFIG['sTAXSHIPPING'];
	}

	// Holger, Kontrolle von unserem Versand Steuersatz.
	// Wenn er vom Höchsten im Warenkorb abweicht ist was falsch gelaufen und wir übernehmen den Höchsten aus dem Warenkorb
	unset($row0);
	$row0 = act_get_row("SELECT max(`t`.`tax`) as taxmax FROM `s_order_details` as `d` LEFT JOIN `s_core_tax` as `t` ON `t`.`id` = `d`.`taxID` WHERE `d`.`orderID`=".$orders1['orderID']);
	if((is_array($row0)&&isset($row0['taxmax']))&&($row0['taxmax']<>$versand_mwst))
		$versand_mwst = $row0['taxmax'];

	// Schleife über die Bestellpositionen
	$positions = array();
	foreach ($positions1 as $pos) {
		if($pos['modus']==3)	// DISCOUNT
			continue;

		$attributes = array();
		if($pos['modus']==0||$pos['modus']==1) {	// Artikel
			$sql = "SELECT * FROM `s_articles_groups_value` WHERE `ordernumber`=".$export->sDB->Quote($pos['articleordernumber']);
			if(is_object($result = $export->sDB->Execute($sql))) {
				$row = $result->FetchRow();
				if(is_array($row)&&count($row)) {
					$art_id = (int)$row['articleID'];
					for ($i = 1; $i<=10; $i++) {
						$valueid = (int)$row['attr'.$i];
						if(!$valueid)
							continue;
						$sql = "SELECT `groupname` FROM `s_articles_groups` WHERE `articleID`=".(int)$art_id." AND `groupID`=".(int)$i;
						if(is_object($res1 = $export->sDB->Execute($sql))&&is_array($row1 = $res1->FetchRow())) {
							$sql = "SELECT `optionname` FROM `s_articles_groups_option` WHERE `articleID`=".(int)$art_id." AND `groupID`=".(int)$i." AND `optionID`=".(int)$valueid;
							if(is_object($res2 = $export->sDB->Execute($sql))&&is_array($row2 = $res2->FetchRow())) {
								$attributes[] = array($row1['groupname'], $row2['optionname']);
							}
						}
					}
				}
			}

			// Holger, das muß hier hin verschoben werden, ansonsten heißt auf einmal der Gutschein (modus 4) genau so wie der Artikel mit der identischen ID
			// bug #17213: Attributs-Wert wird bereits in $product['attributes'] an actindo übergeben und sind damit im Artilelnamen obsolet
			unset($row0);
			$row0 = act_get_row("SELECT `name` FROM `s_articles` WHERE `id`=".(int)$pos['articleID']);
			if(is_array($row0)&&isset($row0['name'])) {
				$pos['name'] = $row0['name'];
			}
		}   // if( $pos['modus'] == 0 || $pos['modus'] == 1 )
		// Holger, dann wollen wir mal in meinem Durcheinander ein wenig aufräumen
		//         und gleichzeitig den Artikel Langtext nutzen ...
		$pos['langtext'] = '';

		// Special Thanks to HR
		if($pos['modus']==0) { // regulärer Artikel
			// Liveshopping Artikel markieren
			unset($row0);
			$row0 = act_get_row("SELECT ( if(`a`.`price` > `a`.`pseudoprice`,`a`.`price`,`a`.`pseudoprice`) * 1.19) as `preis` 
                           FROM `s_order` as `o`
                           INNER JOIN `s_order_details` as `d`
                           ON  (`o`.`id` = `d`.`orderID`)
                           INNER JOIN `s_user` as `u`
                           ON  (`o`.`userID` = `u`.`id`)
                           INNER JOIN `s_articles_prices`as `a`
                           ON  (`d`.`articleID` = `a`.`articleID` AND `u`.`customergroup` = `a`.`pricegroup`)
                           INNER JOIN `s_articles_live` as `l`
                           ON  (`d`.`articleID` = `l`.`articleID`)

                           WHERE `d`.`orderID` = ".$orders1['orderID']."
                           AND `d`.`articleID` = ".$pos['articleID']."
                           AND `d`.`modus` = 0
                           AND `d`.`price` < `a`.`price`
                           AND `l`.`customergroups` IN (`customergroups`)
                           AND `l`.`valid_from` < `o`.`ordertime` < `l`.`valid_to`");
			if(is_array($row0)&&isset($row0['preis'])) {
				$pos['langtext'] .= '<b>Liveshopping Artikel</b><br><i>Regul&auml;rer Preis: '.number_format($row0['preis'], 2, ',', '.').
						' EUR, Sie sparen '.round((1-$pos['price']/$row0['preis'])*100, 2).'%</i>';
			}
		}

		if($pos['modus']==1) { // Prämien Artikel
			$pos['langtext'] .= "<i>".Shopware()->Snippets()->getSnippet()->get('CartItemInfoPremium')."</i>";
		}

		// Special Thanks to HR
		// Anpassung für Gutscheine, in der API wird kein Steuersatz übergeben
		if($pos['modus']==2) {  // Gutschein
			unset($row0);
			$row0 = act_get_row("SELECT taxconfig FROM s_emarketing_vouchers WHERE ordercode=".$pos['articleordernumber']);
			if(is_array($row0)&&isset($row0['taxconfig'])) {
				// 3.5.4 und später, Gutscheine sind einstellbar.
				$resultVoucherTaxMode = $row0['taxconfig'];
				if(empty($resultVoucherTaxMode)||$resultVoucherTaxMode=="default") {
					$pos['tax'] = $export->sSystem->sCONFIG['sVOUCHERTAX'];
				} elseif($resultVoucherTaxMode=="auto") {
					$pos['tax'] = $versand_mwst;
				} elseif($resultVoucherTaxMode=="none") {
					$pos['tax'] = 0;
				} elseif(intval($resultVoucherTaxMode)) {
					// Steuersatz ist im Feld angegeben und muß ausgelesen werden
					$rowT = act_get_row("SELECT tax FROM s_core_tax WHERE id = ".$resultVoucherTaxMode);
					if(is_array($rowT)&&isset($rowT['tax'])) {
						$pos['tax'] = $rowT['tax'];
					} else {
						$pos['tax'] = $versand_mwst;
					}
				}
			} else {
				// 3.5.3 und früher, fixer Satz in der Konfiguration
				$pos['tax'] = $export->sSystem->sCONFIG['sVOUCHERTAX'];
			}

			// Holger, Name ändern. Zwangsweise haben Gutscheine unterschiedliche "Bestellnummern", diese wandert in den Namen des Artikels
			// und die Bestellnummer wird ein generisches "Gutschein" oder was auch immer vom Shopbetreiber im Backend eingetragen wurde.
			$pos['name'] = $pos['articleordernumber'];
			$pos['articleordernumber'] = $export->sSystem->sCONFIG['sVOUCHERNAME'];
		}

		// Special Thanks to HR
		// In der Shopware Logik hat der "Zuschlag für Zahlungsart" keinen Steuersatz, dann setzen wir den mal ...
		if($pos['modus']==4) {  // Zuschlag Zahlungsart
			$pos['tax'] = $versand_mwst;
		}

		// Artikel Bundle Rabatt Position. Das hier ist die "-10 EUR" Position, irgendwo im Warenkorb verstecken sich die Bundle Artikel.
		// Position hübsch formatieren und die Bundle Artikel ausfindig machen, der Langtext folgt im zweiten Schritt.
		if($pos['modus']==10) {  // Bundle Rabatt
			// ID des Bundleartikels auslesen
			unset($row0);
			unset($bundleOrdernumbers);
			$row0 = act_get_row("SELECT id, articleID, name FROM `s_articles_bundles` WHERE `ordernumber` = ".act_quote($pos['articleordernumber']));
			if(is_array($row0)&&isset($row0['id'])) {
				// ordernumber & name des bundle artikels herausbekommen
				$rowB = act_get_row("SELECT `ad`.`ordernumber`, `a`.`name` FROM `s_articles_details` AS `ad`
                             LEFT JOIN `s_articles` AS `a` ON ( `ad`.`articleID` = `a`.`id` )
                             WHERE `ad`.`articleID` =".$row0['articleID']);
				if(is_array($rowB)&&isset($rowB['ordernumber'])) {
					$bundleOrdernumbers[] = $rowB['ordernumber'];
					$bundleArticle['name'] = $rowB['name'];
					$bundleArticle['number'] = $rowB['ordernumber'];
				}

				// zugeordnete Bundleartikel ermitteln
				unset($rowB);
				$rowB = $export->sDB->Execute("SELECT ordernumber FROM `s_articles_bundles_articles` WHERE `bundleID` = ".$row0['id']);
				while ($row = $rowB->FetchRow())
					$bundleOrdernumbers[] = $row['ordernumber'];
			}

			// Position hübsch machen
			$pos['articleordernumber'] = 'bundle';
			$pos['name'] = '"'.$row0['name'].'"';
			$pos['langtext'] .= '<b>'.ucwords(strtolower( Shopware()->Snippets()->getSnippet()->get('CartItemInfoBundle') )).'</b>, Artikel #'.$bundleArticle['number'];
		}

		$product = array(
			'art_nr' => $pos['articleordernumber'],
			'art_nr_base' => $pos['articleordernumber'],
			'art_name' => htmlspecialchars_decode($pos['name']),
			'preis' => (float)$pos['price'],
			'is_brutto' => $orders1['net'] ? 0 : 1,
			'type' => ($pos['modus']==0||$pos['modus']==1) ? 'Lief' : 'NLeist',
			'mwst' => (float)$pos['tax'],
			'menge' => (float)$pos['quantity'],
			'attributes' => $attributes,
			'langtext' => $pos['langtext'],
		);

		$positions[] = $product;
	}

	// Bundle Teil 2, die versteckten Bundle Artikel in der Bestellung ausfindig machen und einen Langtext verpassen.
	if(isset($bundleOrdernumbers)) {
		foreach ($positions as $key => $val) {
			if(in_array($val['art_nr'], $bundleOrdernumbers)) {
				if ($positions[$key]['langtext'] <> "") $positions[$key]['langtext'] .= '<br>';
				$positions[$key]['langtext'] .= '<b>Bundle Artikel</b>';
			}
		}
	}

	$positions[] = array(
		'art_nr' => $versand_nr,
		'art_nr_base' => $versand_nr,
		'art_name' => $versand_name,
		'preis' => (float)($orders1['net'] ? $orders1['invoice_shipping_net'] : $orders1['invoice_shipping']),
		'is_brutto' => $orders1['net'] ? 0 : 1,
		'type' => 'NLeist',
		'mwst' => $versand_mwst,
		'menge' => 1,
		'langtext' => $versand_langtext
	);

	return $positions;
}

?>