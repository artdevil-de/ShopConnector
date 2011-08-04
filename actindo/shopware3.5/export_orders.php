<?php

/**
 * export orders
 *
 * actindo Faktura/WWS connector
 *
 * @package actindo
 * @author  Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 384 $
 * @copyright Copyright (c) 2007, Patrick Prasse (Schneebeerenweg 26, D-85551 Kirchheim, GERMANY, pprasse@actindo.de)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

function export_orders_count( )
{
  global $export;
  $res = $export->sGetLastOrderID();

  $counts = array( "count"=>(int)$res["count"], "max_order_id"=>(int)$res["lastID"] );
  return array( 'ok'=>TRUE, 'counts' => $counts );
}



function export_orders_list( $filters=array(), $from=0, $count=0x7FFFFFFF )
{
  global $export;

  isset($filters['start']) or $filters['start'] = (int)$from;
  isset($filters['limit']) or $filters['limit'] = (int)$count;
  !empty($filters['sortColName']) or $filters['sortColName'] = 'order_id';
  !empty($filters['sortOrder']) or $filters['sortOrder'] = 'DESC';

  $salutation_map = actindo_get_salutation_map( );
  $paymentmeans = actindo_get_paymentmeans( );

  $mapping = array(
    'order_id' => array('o', 'id'),
    'external_order_id' => array('o', 'ordernumber'),
    'deb_kred_id' => array('b', 'customernumber'),
    '_customers_id' => array('o', 'userID'),
    'orders_status' => array('o', 'status'),
  );
  $qry = create_query_from_filter( $filters, $mapping );
  if( $qry === FALSE )
    return array( 'ok'=>false, 'errno'=>EINVAL, 'error'=>'Error in filter definition' );

/* Holger
Dann lösen wir mal wieder für actindo deren Bugs ... 
Diese Woche "not enough memory" beim Bestellungsimport erstellen ...

Solange die vom Kunden in actindo ausgewählten Filterstatus bei der Anfrage nicht mit übergeben werden, 
bleibt nicht viel anderes übrig als hardcodiert ein paar weitere Bestellung Status auszuschließen.
- status 2 => "Komplett abgeschlossen"
- status 7 => "Komplett ausgeliefert"
- status 4 => "Storniert / Abgelehnt"

Für eine richtige Lösung sollte immer noch der Wert aus "Faktuara > Einstellungen > Webshop > Shop > Bestllimport" 
in dieser Anfrage an den Shop übermittelt werden. 
Angebot, wenn Sie Ihre API entsprechend erweitern, programmiere ich den Teil für den Shop.
*/

if (isset($filters['filter'][0])) {
  //alle
  $hr_extend_sql = '';
} else {
  // nur 50
  $hr_extend_sql = ' AND o.status NOT IN (2,4,7)';
}

// original Abfrage
//  $orders1 = $export->sGetOrders(
//    array( 'where' => $qry['q_search'].' AND o.status>=0', 'limit' => $qry['limit'], 'order'=>$qry['order']/*'`orderID` DESC'*/ )
//  );

// und hier meine

  $orders1 = $export->sGetOrders(
    array( 'where' => $qry['q_search'].' AND o.status>=0'.$hr_extend_sql, 'limit' => $qry['limit'], 'order'=>$qry['order']/*'`orderID` DESC'*/ )
  );

// Holger Ende  

  if( !is_array($orders1) )
    return array( 'ok' => FALSE );

  $orderIDs = array_keys($orders1);
  $customers = $export->sOrderCustomers(array("orderIDs"=> $orderIDs));

  $orders = array();
  foreach( $orders1 as $order )
  {
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
        /*'deb_kred_id' => $customer['customernumber'] > 0 ? $customer['customernumber'] : 0,*/ /* MB: bug #5933 */
        'anrede' => $salutation_map[$customer['billing_salutation']],
        'kurzname' => !empty($customer['billing_company']) ? $customer['billing_company'] : sprintf( "%s, %s", $customer['billing_lastname'], $customer['billing_firstname'] ),
        'firma' => $customer['billing_company'],
        'name' => $customer['billing_lastname'],
        'vorname' => $customer['billing_firstname'],
        'adresse' => $customer['billing_street'].' '.strtr($customer['billing_streetnumber'], array(' '=>'')),
        'plz' => $customer['billing_zipcode'],
        'ort' => $customer['billing_city'],
        'land' => $customer['billing_countryiso'],
        'tel' => $customer['billing_phone'],
        'fax' => $customer['billing_fax'],
        'ustid' => $customer['ustid'],
        'email' => $customer['email'],
        'preisgruppe' => $customer['preisgruppe'],
        'gebdat' => $customer['birthday'],
      ),
      'delivery' => array(
        'anrede' => $salutation_map[$customer['shipping_salutation']],
        'firma' => $customer['shipping_company'],
        'name' => $customer['shipping_lastname'],
        'vorname' => $customer['shipping_firstname'],
        'adresse' => $customer['shipping_street'].' '.strtr($customer['shipping_streetnumber'], array(' '=>'')),
        'plz' => $customer['shipping_zipcode'],
        'ort' => $customer['shipping_city'],
        'land' => $customer['shipping_countryiso'],
        'tel' => $customer['billing_phone'],    // no shipping_ equivalent
        'fax' => $customer['billing_fax'],
        'ustid' => $customer['ustid'],
      ),

      /*'deb_kred_id' => $customer['customernumber'] > 0 ? $customer['customernumber'] : 0,*/
      '_customers_id' => (int)$order['userID'],

      // 'payment_method' needs special mapping

      'beleg_status_text' => trim( str_replace( "'", "", $order['customercomment'] ) ),
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

    preg_match( '/^(\d{4}-\d{2}-\d{2})(\s+(\d+:\d+:\d+))?$/', $order['ordertime'], $matches );
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
    );
    $actindoorder['customer']['verf'] = $verfmap[$actindoorder['_payment_method']];
    if( is_null($actindoorder['customer']['verf']) )
      $actindoorder['customer']['verf'] = 'VK';         // generic prepaid

    if( $actindoorder['customer']['verf'] == 'L' )
    {
      $res1 = $export->sDB->Execute( "SELECT * FROM `s_user_debit` WHERE `userID`=".(int)$order['userID'] );
      $row = $res1->FetchRow();
      $res1->Close();
      $actindoorder['customer']['blz'] = str_replace( " ", "", str_replace( "-", "", str_replace( "/", "", $row['bankcode'] ) ) );
      $actindoorder['customer']['kto'] = $row['account'];
    }
    else if( $actindoorder['customer']['verf'] == 'PP' )
    {
      if( act_have_table('paypal_orders') )
      {
        $actindoorder['payment']['pp'] = array();
        $res1 = $export->sDB->Execute( "SELECT * FROM `paypal_orders` WHERE `stransId`='".esc($order['transactionID'])."'" );
        $row = $res1->FetchRow();
        $res1->Close();
        $actindoorder['payment']['pp']['trx_no'] = $row['transactionId'];
        $actindoorder['payment']['pp']['type'] = $row['authorization'] ? 'authorization' : 'payment';
        $actindoorder['payment']['pp']['payer_id'] = $row['payerId'];
      }
    }

//    $actindoorder['customer']['langcode'] = strtolower( $order['langcode'] );
//    $actindoorder['delivery']['langcode'] = strtolower( $order['langcode'] );

    $actindoorder['val_date'] = $actindoorder['bill_date'];

// Holger
// Zahlendreher in der nachfolgenden Abfrage, "? 0 : 1;" ist korrekt
// Ansonsten ist in der Bestellung das Feld "USt-Ausweis auf Beleg" falsch
//    $actindoorder['customer']['print_brutto'] = $order['net'] ? 1 : 0;
    $actindoorder['customer']['print_brutto'] = $order['net'] ? 0 : 1;


    $actindoorder['saldo'] = $order['invoice_amount'];
    $actindoorder['netto2'] = $order['invoice_amount_net'];
    $actindoorder['rabatt_type'] = 'betrag';
    $actindoorder['rabatt_betrag'] = 0.00;
    $actindoorder['netto'] = $actindoorder['netto2'] - $actindoorder['rabatt_betrag'];

    $actindoorder['_shoporder'] = $order;

    $positions1 = $export->sOrderDetails( array("orderID"=> $order['orderID']) );
    if( is_array($positions1) )
    {
      foreach( $positions1 as $pos )
      {
        if( $pos['modus'] == 3 )    // DISCOUNT
          $actindoorder['rabatt_betrag'] -= (float)$pos['price'];   // rabatt is positive, price is negative
      }
    }

    $orders[] = $actindoorder;
  }


  return array( 'ok'=>TRUE, 'orders'=>$orders );
}



function export_orders_positions( $order_id )
{
  global $export;

  $orders1 = $export->sGetOrders(
    array( 'orderID' => $order_id )
  );
  $orders1 = $orders1[$order_id];
  if( !is_array($orders1) )
    return array( 'ok' => FALSE );

  // HR
  // Anpassung für "Versand" und "Zuschlag für Zahlungsart", den maximalen Steuersatz der Bestellung herausfinden
  $versand_mwst = 0;
  $row0 = act_get_row("SELECT max(`t`.`tax`) as taxmax FROM `s_order_details` as `d` LEFT JOIN `s_core_tax` as `t` ON `t`.`id` = `d`.`taxID` WHERE `d`.`orderID`=".$orders1['orderID']);
  if( is_array($row0) && isset($row0['taxmax']) )
    $versand_mwst = $row0['taxmax'];

  $positions1 = $export->sOrderDetails( array("orderID"=> $order_id) );
  if( !is_array($positions1) )
    return array( 'ok' => FALSE );

  $positions = array();
  foreach( $positions1 as $pos )
  {
    if( $pos['modus'] == 3 )    // DISCOUNT
      continue;

    $attributes = array();
    if( $pos['modus'] == 0 || $pos['modus'] == 1 )    // Artikel
    {
      $sql = "SELECT * FROM `s_articles_groups_value` WHERE `ordernumber`=".$export->sDB->Quote($pos['articleordernumber']);
      if( is_object($result = $export->sDB->Execute($sql)) )
      {
        $row = $result->FetchRow( );
        if( is_array($row) && count($row) )
        {
          $art_id = (int)$row['articleID'];
          for( $i=1; $i<=10; $i++ )
          {
            $valueid = (int)$row['attr'.$i];
            if( !$valueid )
              continue;
            $sql = "SELECT `groupname` FROM `s_articles_groups` WHERE `articleID`=".(int)$art_id." AND `groupID`=".(int)$i;
            if( is_object($res1 = $export->sDB->Execute($sql)) && is_array($row1=$res1->FetchRow()) )
            {
              $sql = "SELECT `optionname` FROM `s_articles_groups_option` WHERE `articleID`=".(int)$art_id." AND `groupID`=".(int)$i." AND `optionID`=".(int)$valueid;
              if( is_object($res2 = $export->sDB->Execute($sql)) && is_array($row2=$res2->FetchRow()) )
              {
                $attributes[] = array( $row1['groupname'], $row2['optionname'] );
              }
            }
          }
        }
      }
      
      // Holger, das muß hier hin verschoben werden, ansonsten heißt auf einmal der Gutschein (modus 4) genau so wie der Artikel mit der identischen ID
      // bug #17213: Attributs-Wert wird bereits in $product['attributes'] an actindo übergeben und sind damit im Artilelnamen obsolet
      $art = act_get_row( "SELECT `name` FROM `s_articles` WHERE `id`=".(int)$pos['articleID'] );
      if( is_array($art) && isset($art['name']) )
      {
        $product['art_name'] = $art['name'];
      }
      // Holger ende
    }   // if( $pos['modus'] == 0 || $pos['modus'] == 1 )

    $product = array(
      'art_nr' => $pos['articleordernumber'],
      'art_nr_base' => $pos['articleordernumber'],
      'art_name' => htmlspecialchars_decode($pos['name']),
      'preis' => (float)$pos['price'],
      'is_brutto' => $orders1['net'] ? 0 : 1,
      'type' => ($pos['modus'] == 0 || $pos['modus'] == 1) ? 'Lief' : 'NLeist',
      'mwst' => (float)$pos['tax'],
      'menge' => (float)$pos['quantity'],
      'attributes' => $attributes,
      'langtext' => '',
    );

    // Special Thanks to HR
    // aus irgend einem Grund wird der Steuersatz bei Prämien nicht übergeben, daher holen wir ihn uns hier über die articleID nochmal
    if ($pos['modus'] == 1) {
      $row6 = act_get_row("SELECT `t`.`tax` FROM `s_articles` as `a` LEFT JOIN `s_core_tax` as `t` ON `t`.`id` = `a`.`taxID` WHERE `a`.`id` = ".$pos['articleID'] );
      if( is_array($row6) && isset($row6['tax']) )
        $product['mwst'] = $row6['tax'];
    }

    // Special Thanks to HR
    // Anpassung für Gutscheine, der Steuersatz muß aus der Tablle s_core_config ausgelesen werden
    if( $pos['modus'] == 2 )     // Gutschein
    {
      // Der Wert wird auch in der Config Variable gespeichert, sprich wir können uns die SQL Abfrage sparen
      // Binford: Mehr Power, hrhr
      $product['mwst'] = (float) $export->sSystem->sCONFIG['sVOUCHERTAX'];
//      $product['is_brutto'] = 1;
    }

    // Special Thanks to HR
    // Anpassung für "Zuschlag für Zahlungsart", der Steuersatz muß noch gesetzt werden
    if( $pos['modus'] == 4 )     // Zuschlag Zahlungsart
    {
      $row = act_get_row("SELECT `pd`.`tax_calculation`, `t`.`tax` FROM `s_premium_dispatch` as `pd` LEFT JOIN `s_core_tax` as `t` ON `t`.`id` = `pd`.`tax_calculation` WHERE `pd`.`id`=".$orders1['dispatchID'] );
      if( is_array($row) && isset($row['tax']) )
        $product['mwst'] = $row['tax'];
      else
        $product['mwst'] = $versand_mwst;
    }

    // Special Thanks to HR
    // Liveshopping markierern
    if ( $pos['modus'] == 0 )
    {
      $rowL = act_get_row("SELECT `a`.`price` as `preis`
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
      if( is_array($rowL) && isset($rowL['preis']) ) 
      {
        $product['langtext'] = '<b>Liveshopping Artikel</b><br><i>Regul&auml;rer Preis: '.number_format($rowL['preis'], 2, ',', '.').
                               ' EUR, Sie sparen '.round( (1 - $pos['price'] / $rowL['preis']) * 100 , 2 ).'%</i>';
      }
    }
 
    $positions[] = $product;
  }

  // Special Thanks to HR
  $versand_nr   = 'VERSAND';
  $versand_name = 'Versandkosten';
  
  $row = act_get_row("SELECT `value` FROM `s_core_config` WHERE `name` LIKE 'sPREMIUMSHIPPIUNG'");
  if( ( is_array($row) && isset($row['value']) ) && $row['value'] == 1 )
  {
    // Premium Versand Modul
    $sql = "SELECT `name` FROM `s_premium_dispatch` WHERE `id`=".$orders1['dispatchID'] ;
    if( is_object($res3 = $export->sDB->Execute($sql)) && is_array($row3=$res3->FetchRow()) )
      $versand_nr = $versand_name = $row3['name'];
    
    $row = act_get_row("SELECT `pd`.`tax_calculation`, `t`.`tax` FROM `s_premium_dispatch` as `pd` LEFT JOIN `s_core_tax` as `t` ON `t`.`id` = `pd`.`tax_calculation` WHERE `pd`.`id`=".$orders1['dispatchID'] );
    if( is_array($row) && isset($row['tax']) )
      $versand_mwst = $row['tax'];
    
  } else {
    // altes Versand Modul
    // Der Wert wird auch in der Config Variable gespeichert, sprich wir können uns die SQL Abfrage sparen
    // Binford: Mehr Power, hrhr
    $versand_mwst = (float) $export->sSystem->sCONFIG['sTAXSHIPPING'];
    $sql = "SELECT `name` FROM `s_shippingcosts_dispatch` WHERE `id`=".$orders1['dispatchID'] ;
    if( is_object($res3 = $export->sDB->Execute($sql)) && is_array($row3=$res3->FetchRow()) )
      $versand_nr = $versand_name = $row3['name'];
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
    'langtext' => '',
  );


  return $positions;
}


?>