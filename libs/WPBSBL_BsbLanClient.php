<?php

// WPBSBL_BsbLanClient -- schlanker HTTP-Client fuer die JSON-API eines lokalen
// BSB-LAN-Adapters (github.com/fredlcore/bsb_lan). BSB-LAN uebersetzt den
// Siemens-Boiler-System-Bus (BSB/LPB/PPS) verschiedenster Heizungshersteller
// (Atlantic, Broetje, Elco, Fujitsu Waterstage u.v.a. mit Siemens-RVS-/LMU-
// Reglern) in eine lokale HTTP/JSON-Schnittstelle -- kein Modbus, kein NASA,
// eigenes, vom Projekt offiziell dokumentiertes und versioniertes JSON-Schema.
//
// Endpunkt: GET /JQ=<id1>,<id2>,... liefert ein JSON-Objekt mit einer
// Parameter-ID je Schluessel (Schema siehe openapi.yaml im BSB-LAN-Repo,
// Typ "Parameter": name/dataType_family/dataType_name/destination/error/
// value/desc/payload/precision/dataType/readwrite/unit -- englische,
// kleingeschriebene Feldnamen, Dezimalpunkt. Live an docs.bsb-lan.de/
// openapi.yaml gegengeprueft, 23.09.2026, siehe WPBsbLan-CLAUDE.md).
// Optionales Passwort wird laut BSB-LAN-Doku als Pfadsegment VOR dem
// URL-Befehl eingefuegt (http://host/<passwort>/JQ=...), nicht als Query-
// Parameter oder Header.

class WPBSBL_BsbLanClient
{
    private string $host;
    private int $port;
    private bool $https;
    private string $password;
    public string $lastError = '';
    public string $lastUrl = '';

    public function __construct(string $host, int $port, bool $https = false, string $password = '')
    {
        $this->host     = $host;
        $this->port     = $port;
        $this->https    = $https;
        $this->password = $password;
    }

    /**
     * Fragt eine Menge von Parameter-IDs in EINER Anfrage ab (BSB-LAN erlaubt
     * kommagetrennte Listen, siehe /JQ=<x>,<y>,<z> in der offiziellen Doku) --
     * ein Lesezyklus, ein HTTP-Request, analog zum "eine Verbindung je
     * Zyklus"-Muster der Modbus-Module. Rueckgabe: [ParameterID(int) =>
     * ['value'=>float, 'desc'=>string, 'unit'=>string, 'name'=>string]],
     * NULL nur bei komplettem Verbindungsfehler. Ein einzelner Parameter mit
     * error!=0 (laut Schema: Fehlercode des Bus-Telegramms) wird als
     * fehlgeschlagen NICHT in die Rueckgabe aufgenommen, die uebrigen bleiben
     * nutzbar (kein Alles-oder-nichts, gleiches Muster wie readRegisters()
     * in WPModbusHub).
     */
    public function queryParameters(array $ids): ?array
    {
        $this->lastError = '';
        if (count($ids) === 0) {
            return [];
        }
        $scheme = $this->https ? 'https' : 'http';
        $path   = ($this->password !== '') ? '/' . rawurlencode($this->password) : '';
        $url    = $scheme . '://' . $this->host . ':' . $this->port . $path . '/JQ=' . implode(',', array_map('intval', $ids));
        $this->lastUrl = $url;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $this->lastError = 'HTTP-Fehler: ' . curl_error($ch);
            curl_close($ch);
            return null;
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($status !== 200) {
            $this->lastError = 'HTTP-Status ' . $status;
            return null;
        }

        $parsed = self::parseResponseBody((string)$body);
        if ($parsed === null) {
            $this->lastError = 'Antwort ist kein gueltiges JSON (' . substr((string)$body, 0, 120) . ')';
            return null;
        }
        return $parsed;
    }

    /**
     * Reine Dekodierfunktion, ausgelagert damit der Pruefstand sie OHNE echtes
     * HTTP/curl direkt mit einer vorgefertigten JSON-Antwort testen kann --
     * dieselbe Funktion, die auch queryParameters() im Live-Betrieb nutzt
     * (kein separater, nur-fuer-Tests-gebauter Pfad). NULL nur bei ungueltigem
     * JSON, ein leeres Array ist ein gueltiges (wenn auch leeres) Ergebnis.
     */
    public static function parseResponseBody(string $body): ?array
    {
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return null;
        }
        $out = [];
        foreach ($json as $id => $param) {
            if (!is_array($param) || !array_key_exists('value', $param)) {
                continue;
            }
            if ((int)($param['error'] ?? 0) !== 0) {
                continue;
            }
            $out[(int)$id] = [
                // BSB-LAN liefert Werte als String, Dezimalpunkt (openapi.yaml-
                // Beispiele) -- str_replace faengt trotzdem ein Dezimalkomma ab,
                // falls eine aeltere/andere Firmware-Version doch lokalisiert.
                'value' => (float)str_replace(',', '.', (string)$param['value']),
                'desc'  => (string)($param['desc'] ?? ''),
                'unit'  => (string)($param['unit'] ?? ''),
                'name'  => (string)($param['name'] ?? ''),
            ];
        }
        return $out;
    }
}
