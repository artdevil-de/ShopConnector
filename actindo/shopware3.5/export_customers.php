<?php

/**
 * export customers
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
function export_customers_count()
{
	global $export;

	$sql = "SELECT MAX(`id`) AS lastID FROM `s_user`";
	if(($row = $export->sDB->GetRow($sql))===false)
		return false;
	$lastID = $row['lastID'];

	$sql = "SELECT MAX(`customernumber`) AS deb_kred_id FROM `s_user_billingaddress`";
	if(($row = $export->sDB->GetRow($sql))===false)
		return false;
	$deb_kred_id = $row['deb_kred_id'];

	$sql = "SELECT COUNT(`id`) AS count FROM `s_user`";
	if(($row = $export->sDB->GetRow($sql))===false)
		return false;
	$count = $row['count'];

	$counts = array("count" => (int)$count, "max_customers_id" => (int)$lastID, 'max_deb_kred_id' => (int)$deb_kred_id);
	return array('ok' => TRUE, 'counts' => $counts);
}

function export_customers_list($just_list=TRUE, $filters=array())
{
	global $export;

	$salutation_map = actindo_get_salutation_map();
	$paymentmeans = actindo_get_paymentmeans();

	$mapping = array(
		'_customers_id' => array('u', 'id'),
		'deb_kred_id' => array('b', 'customernumber'),
		'vorname' => array('b', 'firstname'),
		'name' => array('b', 'lastname'),
		'firma' => array('b', 'company'),
		'land' => array('bc', 'countryiso'),
		'email' => array('u', 'email'),
	);
	$qry = create_query_from_filter($filters, $mapping);
	if($qry===FALSE)
		return array('ok' => false, 'errno' => EINVAL, 'error' => 'Error in filter definition');

	if($just_list) {
		$sql = "SELECT SQL_CALC_FOUND_ROWS u.id AS customers_id, u.email, u.language, b.customernumber, b.salutation, b.company, b.firstname, b.lastname, b.street, b.streetnumber, b.zipcode, b.city, `bc`.`countryiso` FROM (`s_user` AS u, `s_user_billingaddress` AS b) LEFT JOIN `s_core_countries` AS `bc` ON(bc.id=b.countryID) WHERE b.userID=u.id AND {$qry['q_search']} GROUP BY b.userID ORDER BY {$qry['order']}, b.userID DESC LIMIT {$qry['limit']}";
	} else {
// Kundengruppe
//    $sql = "SELECT SQL_CALC_FOUND_ROWS u.id AS customers_id, u.email, u.language, b.*, `bc`.`countryiso` FROM (`s_user` AS u, `s_user_billingaddress` AS b) LEFT JOIN `s_core_countries` AS `bc` ON(bc.id=b.countryID) WHERE b.userID=u.id AND {$qry['q_search']} GROUP BY b.userID ORDER BY {$qry['order']}, b.userID DESC LIMIT {$qry['limit']}";
		$sql = "SELECT SQL_CALC_FOUND_ROWS u.id AS customers_id, u.email, u.language, b.*, `bc`.`countryiso`, g.id AS preisgruppe, g.tax AS net FROM (`s_user` AS u, `s_user_billingaddress` AS b) LEFT JOIN `s_core_countries` AS `bc` ON(bc.id=b.countryID) LEFT JOIN `s_core_customergroups` AS `g` ON ( u.customergroup=g.groupkey) WHERE b.userID=u.id AND {$qry['q_search']} GROUP BY b.userID ORDER BY {$qry['order']}, b.userID DESC LIMIT {$qry['limit']}";
	}

	$rows = $export->sDB->GetAll($sql);
	if(!is_array($rows))
		return array('ok' => FALSE, 'errno' => EIO);

	$count = $export->sDB->GetAll("SELECT FOUND_ROWS() AS cnt");

	foreach ($rows as $customer) {
		if($just_list) {
			$actindocustomer = array(
				'deb_kred_id' => (int)($customer['customernumber']>0 ? $customer['customernumber'] : 0),
				'anrede' => $salutation_map[$customer['salutation']],
				'kurzname' => !empty($customer['company']) ? $customer['company'] : sprintf("%s, %s", $customer['lastname'], $customer['firstname']),
				'firma' => $customer['company'],
				'name' => $customer['lastname'],
				'vorname' => $customer['firstname'],
				'adresse' => $customer['street'].' '.strtr($customer['streetnumber'], array(' ' => '')),
				'plz' => $customer['zipcode'],
				'ort' => $customer['city'],
				'land' => $customer['countryiso'],
				'email' => $customer['email'],
				'_customers_id' => (int)$customer['customers_id'],
			);
		} else {
			$actindocustomer = array(
				'deb_kred_id' => (int)($customer['customernumber']>0 ? $customer['customernumber'] : 0),
				'anrede' => $salutation_map[$customer['salutation']],
				'kurzname' => !empty($customer['company']) ? trim($customer['company']) : sprintf("%s, %s", trim($customer['lastname']), trim($customer['firstname'])),
				'firma' => trim($customer['company']),
				'name' => trim($customer['lastname']),
				'vorname' => trim($customer['firstname']),
				'adresse' => trim($customer['street']).' '.trim( strtr($customer['streetnumber'], array(' ' => '')) ),
				'adresse2' => trim($customer['department']),
				'plz' => trim($customer['zipcode']),
				'ort' => trim($customer['city']),
				'land' => $customer['countryiso'],
				'tel' => $customer['phone'],
				'fax' => $customer['fax'],
				'ustid' => $customer['ustid'],
				'email' => $customer['email'],
				'print_brutto' => $customer['net'] ? 1 : 0,
				'_customers_id' => (int)$customer['customers_id'],
				'currency' => 'EUR',
				'preisgruppe' => $customer['preisgruppe'],
				'gebdat' => $customer['birthday'],
				'verf' => 'VK',
				'zahl_bed' => '1',
				'delivery_addresses' => array(),
			);

			$sql = "SELECT s.*, `bc`.`countryiso` FROM `s_user_shippingaddress` AS s LEFT JOIN `s_core_countries` AS `bc` ON(bc.id=s.countryID) WHERE s.userID=".(int)$customer['customers_id']." ORDER BY s.id DESC";
			$delivery_rows = $export->sDB->GetAll($sql);
			$actindodelivery = null;
			foreach ($delivery_rows as $delivery) {
				$actindodelivery = array(
					'delivery_id' => (int)$delivery['id'],
					'delivery_anrede' => $salutation_map[$delivery['salutation']],
					'delivery_firma' => trim($delivery['company']),
					'delivery_name' => trim($delivery['lastname']),
					'delivery_vorname' => trim($delivery['firstname']),
					'delivery_adresse' => trim($delivery['street']).' '.trim( strtr($delivery['streetnumber'], array(' ' => '')) ),
					'delivery_adresse2' => trim($delivery['department']),
					'delivery_plz' => trim($delivery['zipcode']),
					'delivery_ort' => trim($delivery['city']),
					'delivery_land' => $delivery['countryiso'],
					'delivery_ustid' => $customer['ustid'],
				);

				// holger, compare if billing address is identical to delivery address
				// if yes, don't pass it on as actindo will use the billing address by default
				$result = array_diff(
					array(
					$actindocustomer['anrede'],
					$actindocustomer['firma'],
					$actindocustomer['name'],
					$actindocustomer['vorname'],
					$actindocustomer['adresse'],
					$actindocustomer['adresse2'],
					$actindocustomer['plz'],
					$actindocustomer['ort'],
					$actindocustomer['land'],
					$actindocustomer['ustid']
					),
					array(
					$actindodelivery['delivery_anrede'],
					$actindodelivery['delivery_firma'],
					$actindodelivery['delivery_name'],
					$actindodelivery['delivery_vorname'],
					$actindodelivery['delivery_adresse'],
					$actindodelivery['delivery_adresse2'],
					$actindodelivery['delivery_plz'],
					$actindodelivery['delivery_ort'],
					$actindodelivery['delivery_land'],
					$actindodelivery['delivery_ustid']
					)
				);
				if( count($result) == 0) { unset($actindodelivery); }

				if(is_array($actindodelivery)) {
					$actindocustomer['delivery_addresses'][] = $actindodelivery;
				}
			}
			if(is_array($actindodelivery)) {
				$actindocustomer = array_merge($actindocustomer, $actindodelivery);   // merge standard delivery address
			} else {
				
			}
		}

		$customers[] = $actindocustomer;
	}

	return array('ok' => TRUE, 'customers' => $customers, 'count' => $count[0]['cnt']);
}

?>