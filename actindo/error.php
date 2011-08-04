<?php

/**
 * error constants
 *
 * actindo Faktura/WWS connector
 *
 * @package actindo
 * @author  Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 114 $
 * @copyright Copyright (c) 2007, Patrick Prasse (Schneebeerenweg 26, D-85551 Kirchheim, GERMANY, pprasse@actindo.de)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */
/** Operation not permitted */
define('EPERM', 1);
/** No such file or directory */
define('ENOENT', 2);
/** I/O error */
define('EIO', 5);
/** Argument list too long */
define('E2BIG', 7);
/** Try again */
define('EAGAIN', 11);
/** Out of memory */
define('ENOMEM', 12);
/** Permission denied */
define('EACCESS', 13);
/** Cross device link */
define('EXDEV', 18);
/** Invalid argument */
define('EINVAL', 22);
/** No space left on device */
define('ENOSPC', 28);
/** Function not implemented */
define('ENOSYS', 38);
/** No data available */
define('ENODATA', 61);
/** Timer expired */
define('ETIME', 62);
/** Package not installed */
define('ENOPKG', 65);
/** Value too large for defined data type */
define('EOVERFLOW', 75);
/** Too many users */
define('EUSERS', 87);
/** Operation already in progress */
define('EALREADY', 114);
/** Operation now in progress */
define('ENOWINPROGRESS', 115);
/** Unknown error, see error message */
define('EUNKNOWN', 0x99);  /* 153 */

/** XMLRPC: Login failed */
define('ELOGINFAILED', 0x100);  /* 153 */
/** XMLRPC: Have no permission to access this customer */
define('ELOGINFAILED', 0x101);  /* 153 */
/** XMLRPC: Not logged in */
define('ENOTLOGGEDIN', 0x110);  /* 153 */
/** XMLRPC: Already logged in */
define('ELOGGEDIN', 0x111);  /* 153 */


$GLOBALS['errors_de'] = array(
	1 => 'Operation nicht erlaubt',
	2 => 'Keine solche Datei oder Verzeichnis',
	5 => 'IO / Datenbank Fehler',
	7 => 'Argumenten-Liste zu lang',
	11 => 'Nochmal versuchen',
	12 => 'Ungenügender Speicher',
	13 => 'Keine Berechtigung',
	22 => 'Fehlerhaftes Argument',
	28 => 'Kein Speicher mehr auf dem Geraet',
	38 => 'Funktion nicht implementiert',
	61 => 'Keine Daten vorhanden',
	62 => 'Timer expired',
	65 => 'Packet nicht installiert',
	75 => 'Wert zu gross fuer Datentyp',
	87 => 'Zu viele Benutzer',
	114 => 'Operation wird schon bearbeitet',
	115 => 'Operation wird jetzt bearbeitet',
	0x100 => 'Falscher Login oder falsches Passwort',
	0x101 => 'Keine Berechtigung für den Mandanten',
	0x110 => 'Nicht eingeloggt',
	0x111 => 'Bereits eingeloggt',
);

/**
 * Return string representation of error.
 *
 * @param int Error number
 * @returns string String representation of error, NULL if unknown error
 */
function strerror($errno)
{
	return $GLOBALS['errors_de'][(int)$errno];
}
?>