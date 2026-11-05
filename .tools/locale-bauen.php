<?php
/**
 * Kopiert libs/locale.json (Quelle der deutschen Fassung) in den Modulordner, denn Symcon liest
 * locale.json nur im Ordner des Moduls (WPBsbLan/).
 * Aufruf: php .tools/locale-bauen.php
 */
declare(strict_types=1);
$quelle = (string)file_get_contents(__DIR__ . '/../libs/locale.json');
$tabelle = json_decode($quelle, true, 512, JSON_THROW_ON_ERROR);
file_put_contents(__DIR__ . '/../WPBsbLan/locale.json', $quelle);
echo 'locale.json fuer WPBsbLan geschrieben (' . count($tabelle['translations']['de']) . " Texte)\n";
