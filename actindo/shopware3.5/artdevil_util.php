<?php

/**
 * various utilities for ShopConnectorNG
 *
 * @author  Holger Ronecker
 * @link    http://artdevil.de/ShopConnector ShopConnector Seite auf ArtDevil.de
 * @copyright Copyright (c) 2012, Holger Ronecker, devil@artdevil.de
 */


/**
 * Artikelnummern überprüfen
 *
 * actindo lädt bisher willkürlich alle Artikelnummern aus dem eigenen System hoch. Shopware hat allerdings einen
 * anderen Wertebereich für Artikelnummern (ordernumber), was zu Defekten in Shopware führt (zum Beispiel das / Zeichen).
 */
function devil_connectorNG_artnr_isValid($ordernumber, &$matches)
{
	// to disable the check uncomment the following line
	// return 1;

	return !preg_match('/[^A-Za-z0-9_\.\-]/', $ordernumber, $matches);
}


/**
 * Bildern sinnvolle Namen geben
 *
 * Im Standard läd actindo die Artikelbilder einfach hoch und läßt Shopware willkürliche Namen zuweisen. Das ist natürlich 
 * überhaupt nicht SEO tauglich, weswegen diese Funktion den Bildern sinnvolle Namen verpasst, auf das Google sich darüber freut.
 *
 * Da jeder Shopbetreiber eigene Wünsche hat wie die Bilder denn nun heisen sollen, ist die nachfolgenden Funktion flexibel aufgebaut.
 * Sie können über verändern der Variable $bilder_namen die Benennungsart bestimmen. Nachfolgend eine Erklärung der Optionen:
 *
 *  1: [original Dateiname]-[Sortierierung].jpg
 *  2: [Artikel Name (ohne Leer und Sonderzeichen)]-[Sortierierung].jpg
 *  3: [Bild Beschreibung, wenn nicht vorhanden Artikelname]-[Sortierierung].jpg
 *  4: [Artikel Name (ohne Leer und Sonderzeichen)]-[original Dateiname].jpg
 *  
 *  Solltet Ihr noch andere Wünsche haben, lasst es mich über www.artdevil.de wissen, ich erweiter diese Funktion gerne für Euch.
 */
function devil_connectorNG_image_seo_name(&$image,$img,$language)
{


	$bilder_namen = 4;


	$filename = $img['image_name'];
	$filename = explode(".", $filename);
	$filenameext = $filename[count($filename)-1];
	$filename = $filename[0];
	$filename = str_replace(" ","",$filename);

	switch ($bilder_namen) {
		case 4:
			$row0 = act_get_row("SELECT name FROM `s_articles` WHERE `id` = ". $image['articleID']);
			if(is_array($row0)&&isset($row0['name']))
				$return = friendly_url( $row0['name']."-".$filename );
			break;

		case 3:
			if($img['image_title'][$language] <> "") {
				$return = friendly_url( $img['image_title'][$language]."-".$image['position'] );
			} else {
				$row0 = act_get_row("SELECT name FROM `s_articles` WHERE `id` = ". $image['articleID']);
				if(is_array($row0)&&isset($row0['name']))
					$return = friendly_url( $row0['name']."-".$image['position'] );
			}
			break;

		case 2:
			$row0 = act_get_row("SELECT name FROM `s_articles` WHERE `id` = ". $image['articleID']);
			if(is_array($row0)&&isset($row0['name']))
				$return = friendly_url( $row0['name']."-".$image['position'] );
			break;
	}
	
	if (empty($return))
		$return = friendly_url( $filename."-".$image['position'] );

	$image['name'] = $return;
}


/**
 *  Thanks to José María, http://neo22s.com/
 */
function friendly_url($url) {
	// everything to lower and no spaces begin or end
	$url = strtolower(trim($url));

	//replace accent characters, depends your language is needed
	$url=replace_accents($url);

	// decode html maybe needed if there's html I normally don't use this
	$url = html_entity_decode($url,ENT_QUOTES,'UTF8');

	// adding - for spaces and union characters
	$find = array(' ', '&', '\r\n', '\n', '+',',','_');
	$url = str_replace ($find, '-', $url);

	//delete and replace rest of special chars
	$find = array('/[^a-z0-9\-<>]/', '/[\-]+/', '/<[^>]*>/');
	$repl = array('', '-', '');
	$url = preg_replace ($find, $repl, $url);

	//return the friendly url
	return $url; 
}
function replace_accents($var){ 
	$a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'Ð', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', '?', '?', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', '?', '?', 'L', 'l', 'N', 'n', 'N', 'n', 'N', 'n', '?', 'O', 'o', 'O', 'o', 'O', 'o', 'Œ', 'œ', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'Š', 'š', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Ÿ', 'Z', 'z', 'Z', 'z', 'Ž', 'ž', '?', 'ƒ', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', '?', '?', '?', '?', '?', '?'); 
	$b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o'); 
	$var= str_replace($a, $b,$var);
	return $var; 
}

?>
