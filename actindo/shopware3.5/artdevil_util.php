<?php

/**
 * various utilities for ShopConnectorNG
 *
 * @author  Holger Ronecker
 * @link    http://artdevil.de/ShopConnector ShopConnector Seite auf ArtDevil.de
 * @copyright Copyright (c) 2012, Holger Ronecker, devil@artdevil.de
 */


/**
 * Artikelnummern �berpr�fen
 *
 * actindo l�dt bisher willk�rlich alle Artikelnummern aus dem eigenen System hoch. Shopware hat allerdings einen
 * anderen Wertebereich f�r Artikelnummern (ordernumber), was zu Defekten in Shopware f�hrt (zum Beispiel das / Zeichen).
 */
function devil_connectorNG_artnr_isValid($ordernumber, &$matches)
{
	// to disable the check uncomment the following line
	// return 1;

	return !preg_match('/[^A-Za-z0-9_\.\-]/', $ordernumber, $matches);
}

?>
