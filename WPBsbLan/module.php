<?php

require_once __DIR__ . '/../libs/WPBSBL_BsbLanClient.php';

// NRG-Stack WPBsbLan -- lokale Anbindung von Heizungen/Waermepumpen ueber
// einen BSB-LAN-Adapter (github.com/fredlcore/bsb_lan, ESP32-Hardware/
// -Firmware). BSB-LAN uebersetzt den Siemens-Boiler-System-Bus (BSB/LPB/PPS)
// verschiedenster Heizungshersteller (Atlantic, Broetje, Elco, Fujitsu
// Waterstage u.v.a. mit Siemens-RVS-/LMU-Reglern) in eine lokale HTTP/JSON-
// Schnittstelle -- kein Modbus, kein Herstellerkonto, kein Cloud-Umweg.
//
// Vierter Baustein der Waermepumpen-Vertikale im Verbund, neben:
//   WPHub         -- Herstellercloud (Panasonic, Vaillant)
//   HeishaMon     -- lokal per MQTT, nur Panasonic mit HeishaMon-Platine
//   WPModbusHub   -- lokal per Modbus TCP, mehrere Hersteller
//   WPBsbLan      -- lokal per BSB-LAN-Adapter, mehrere Hersteller ueber
//                     Siemens-RVS-/LMU-Regler
//
// Entstanden aus einer Forumsanfrage (ArMu/Lütfü, WPHub-Thread, 23.09.2026):
// Fujitsu Waterstage WSYK160DG9 mit Siemens-Regler RVS21.831/127, bereits
// per BSB-LAN lokal angebunden. Anders als bei den Modbus-Herstellern
// (WPMBHUB_Drivers, statische Registerkarte je Hersteller) sind BSB-LAN-
// Parameternummern nicht firmenweit einheitlich -- sie werden von einer
// individuellen, geraetespezifischen Parameterdefinition erzeugt (bei
// Lütfü von einem BSB-LAN-Maintainer aus den Rohdaten SEINER Anlage
// generiert, ~6000 Parameter). Deshalb bewusst KEINE statische Registerkarte
// wie bei WPModbusHub, sondern sechs frei editierbare Parameternummern-Felder
// im Formular, mit Lütfüs an seiner Anlage live bestaetigten Werten als
// Startwert (siehe WPBsbLan-CLAUDE.md) -- ob dieselben Nummern auf andere
// RVS21-Anlagen (andere Konfiguration/Firmware) uebertragbar sind, ist NICHT
// gesagt, nur fuer Lütfüs eigene Anlage bestaetigt.
//
// JSON-Schema (name/value/error/precision/readwrite/unit, Dezimalpunkt) direkt
// aus dem offiziellen openapi.yaml des BSB-LAN-Projekts uebernommen, NICHT aus
// Lütfüs erstem, abweichendem Forenbeispiel (siehe WPBsbLan-CLAUDE.md fuer die
// Herleitung) -- Stand heute stabil und versioniert (`/JV`), unabhaengig vom
// angeschlossenen Heizungsmodell.
//
// Vertrag WPBSBL_GetFunctions()-kompatibel: Type=>'heatpump', contractVersion
// 1.15, dieselben Feldnamen wie WPHub/WPModbusHub/SamsungEhs.
//
// Bewusst NUR lesend (keine Steuerbefehle) -- obwohl BSB-LAN offiziell auch
// Schreiben (/JS) unterstuetzt, analog zur v1-Linie aller anderen WP-Module.
// Bewusst KEINE Leistungs-/Energiezaehler -- PowerID/EnergyID bleiben 0.

class WPBsbLan extends IPSModule
{
    // Verbund-Konvention "NEWS_VERSIONS" (SUITE.md "Einheitliche Formular-Optik"
    // Punkt 1): Schluessel = Version ohne Beta-/Build-Suffix, Wert = Zeilen.
    const NEWS_VERSIONS = [
        '0.1.0' => [
            'Erste Version: Heizungen/Wärmepumpen über einen lokalen BSB-LAN-Adapter auslesen -- Außen-, Vorlauf-, Rücklauftemperatur, Warmwasser Ist/Soll und Betriebsart, alle Parameternummern frei einstellbar.',
        ],
        '0.1.1' => [
            '„Betriebsart“ heißt jetzt „Betriebsart Heizkreis 1“ -- an mehrkreisigen Anlagen gibt es eigene, unabhängige Parameter je Heiz-/Kühlkreis, die sich nicht automatisch mitändern.',
        ],
    ];
    private const LIBRARY_GUID = '{61F8D0DA-6E1F-497F-9A1A-AC797E298A35}';

    // Anzeigenamen der sechs Felder, in Anlegereihenfolge -- genutzt von
    // maintainDeviceVariables() und der Statuszeile.
    private const FIELD_CAPTIONS = [
        'Aussentemperatur'    => 'Außentemperatur',
        'Vorlauftemperatur'   => 'Vorlauftemperatur',
        'Ruecklauftemperatur' => 'Rücklauftemperatur',
        'Warmwasser'          => 'Warmwasser',
        'WarmwasserSoll'      => 'Warmwasser Sollwert',
    ];
    // Property-Name je Ident -- Nutzer traegt hier seine eigene BSB-LAN-
    // Parameternummer ein, 0 = Feld nicht vorhanden/nicht genutzt.
    private const FIELD_PROPERTIES = [
        'Aussentemperatur'    => 'ParamAussentemperatur',
        'Vorlauftemperatur'   => 'ParamVorlauftemperatur',
        'Ruecklauftemperatur' => 'ParamRuecklauftemperatur',
        'Warmwasser'          => 'ParamWarmwasser',
        'WarmwasserSoll'      => 'ParamWarmwasserSoll',
    ];
    // Lütfüs an seiner Fujitsu-Waterstage/RVS21.831F/127 live bestaetigte
    // Werte (Forum-Post #6/#8, 23.09.2026) -- Startwert, kein Anspruch auf
    // Allgemeingueltigkeit fuer jede BSB-LAN-Anlage.
    private const FIELD_DEFAULTS = [
        'ParamAussentemperatur'    => 8700,
        'ParamVorlauftemperatur'   => 8412,
        'ParamRuecklauftemperatur' => 8410,
        'ParamWarmwasser'          => 8830,
        'ParamWarmwasserSoll'      => 8831,
        'ParamBetriebsart'         => 700,
    ];

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Host', '');
        $this->RegisterPropertyInteger('Port', 80);
        $this->RegisterPropertyBoolean('UseHttps', false);
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyBoolean('WPBSBL_Active', false);
        $this->RegisterPropertyInteger('WPBSBL_Interval', 60);

        foreach (self::FIELD_DEFAULTS as $property => $default) {
            $this->RegisterPropertyInteger($property, $default);
        }

        $this->RegisterAttributeBoolean('PurposeIntroGone', false);
        $this->RegisterAttributeString('SeenNews', '');
        $this->RegisterAttributeInteger('LastSeenAt', 0);
        $this->RegisterAttributeInteger('LastCycleAt', 0);
        $this->RegisterAttributeString('LastMissing', '');
        $this->RegisterAttributeBoolean('ForumHintGone', false);

        $this->RegisterTimer('WPBSBL_UpdateTimer', 0, 'WPBSBL_Update($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->ensureSharedProfiles();

        $active   = $this->ReadPropertyBoolean('WPBSBL_Active');
        $interval = max(30, $this->ReadPropertyInteger('WPBSBL_Interval'));
        $hasHost  = trim($this->ReadPropertyString('Host')) !== '';

        if (!$active) {
            $this->SetTimerInterval('WPBSBL_UpdateTimer', 0);
            $this->SetStatus(104);
        } elseif (!$hasHost) {
            $this->SetTimerInterval('WPBSBL_UpdateTimer', 0);
            $this->SetStatus(201);
        } else {
            $this->SetTimerInterval('WPBSBL_UpdateTimer', $interval * 1000);
            $this->SetStatus(102);
        }
    }

    private function updateFormElement(array &$items, string $name, array $patch): bool
    {
        foreach ($items as &$item) {
            if (($item['name'] ?? null) === $name) {
                $item = array_merge($item, $patch);
                return true;
            }
            if (isset($item['items']) && is_array($item['items'])) {
                if ($this->updateFormElement($item['items'], $name, $patch)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        $libraryInfo = @json_decode((string)@file_get_contents(__DIR__ . '/../library.json'), true);
        $libraryVersion = (is_array($libraryInfo) && isset($libraryInfo['version'])) ? (string)$libraryInfo['version'] : '?';
        $this->updateFormElement($form['elements'], 'VersionInfo', [
            'caption' => 'ℹ️ WPBsbLan Version ' . $libraryVersion . ' -- lokale Anbindung von Heizungen/Wärmepumpen über einen BSB-LAN-Adapter.',
        ]);

        [$statusText, $statusColor] = $this->statusLine();
        $this->updateFormElement($form['elements'], 'ConnectionStatus', ['caption' => $statusText, 'color' => $statusColor]);

        $newsBanner = $this->newsBanner();
        if ($newsBanner !== null) {
            array_unshift($form['elements'], $newsBanner);
        }

        $purposeIntro = $this->PurposeIntro();
        if ($purposeIntro !== null) {
            array_unshift($form['elements'], $purposeIntro);
        }

        $forumHint = $this->ForumHint();
        if ($forumHint !== null) {
            $form['elements'][] = $forumHint;
        }

        $form['elements'][] = $this->LicenseHint();

        return json_encode($form);
    }

    /** Siehe WPModbusHub/module.php::BaseVersion() -- identisches Muster. */
    private function BaseVersion(string $v): string
    {
        return preg_replace('/-.*$/', '', $v) ?? $v;
    }

    /** Siehe WPModbusHub/module.php::newsBanner() -- identisches Muster. */
    private function newsBanner(): ?array
    {
        $seen = (string) $this->ReadAttributeString('SeenNews');
        $pending = [];
        foreach (self::NEWS_VERSIONS as $ver => $lines) {
            if ($seen === '' || version_compare($ver, $seen, '>')) {
                $pending[$ver] = $lines;
            }
        }
        if (count($pending) === 0) {
            return null;
        }
        uksort($pending, 'version_compare');
        $items = [];
        $multi = count($pending) > 1;
        foreach ($pending as $ver => $lines) {
            if ($multi) {
                $items[] = ['type' => 'Label', 'caption' => 'Version ' . $ver . ':'];
            }
            foreach ($lines as $line) {
                $items[] = ['type' => 'Label', 'caption' => '• ' . $line];
            }
        }
        $items[] = ['type' => 'Button', 'caption' => 'Verstanden – nicht mehr anzeigen', 'onClick' => 'WPBSBL_AckNews($id);'];
        $latest = array_key_last($pending);
        return ['type' => 'ExpansionPanel', 'name' => 'NewsPanel', 'caption' => '🆕 Neu bis Version ' . $latest, 'expanded' => true, 'items' => $items];
    }

    public function AckNews(): void
    {
        $lib = @IPS_GetLibrary(self::LIBRARY_GUID);
        $ver = is_array($lib) ? $this->BaseVersion((string) ($lib['Version'] ?? '')) : '';
        if ($ver === '') {
            $ver = (string) array_key_last(self::NEWS_VERSIONS);
        }
        $this->WriteAttributeString('SeenNews', $ver);
        $this->UpdateFormField('NewsPanel', 'visible', false);
    }

    private function PurposeIntro(): ?array
    {
        if ($this->ReadAttributeBoolean('PurposeIntroGone')) {
            return null;
        }
        return [
            'type'     => 'ExpansionPanel',
            'name'     => 'PurposeIntroPanel',
            'expanded' => true,
            'caption'  => '👋  Wozu dieses Modul?',
            'items'    => [
                ['type' => 'Label', 'caption' => 'WPBsbLan liest eine Heizung oder Wärmepumpe über einen lokalen BSB-LAN-Adapter aus -- ohne Internet, ohne Herstellerkonto. BSB-LAN ist eine verbreitete Open-Source-Hardware/-Firmware, die den Siemens-Heizungsbus (BSB/LPB/PPS) vieler Hersteller (Atlantic, Brötje, Elco, Fujitsu Waterstage u. a. mit Siemens-RVS-/LMU-Reglern) in eine lokale HTTP/JSON-Schnittstelle übersetzt.'],
                ['type' => 'Label', 'caption' => 'Anders als bei den übrigen Wärmepumpen-Modulen des Verbunds sind die Parameternummern für die einzelnen Werte NICHT einheitlich -- jede BSB-LAN-Anlage kann eigene Nummern haben. Deshalb trägst du sie unten selbst ein; vorbelegt sind die an einer echten Fujitsu Waterstage (Siemens RVS21.831/127) bestätigten Werte als Startpunkt.'],
                ['type' => 'Button', 'caption' => 'Verstanden – nicht mehr anzeigen', 'onClick' => 'WPBSBL_AckPurposeIntro($id);'],
            ],
        ];
    }

    public function AckPurposeIntro(): void
    {
        $this->WriteAttributeBoolean('PurposeIntroGone', true);
        $this->UpdateFormField('PurposeIntroPanel', 'visible', false);
    }

    private const LICENSE_URL = 'https://github.com/DG65/NRGWPBsbLan/blob/beta/LICENSE';
    private const PAYPAL_URL = 'https://paypal.me/DietmarGureth';

    private function LicenseHint(): array
    {
        return [
            'type'     => 'ExpansionPanel',
            'expanded' => false,
            'caption'  => '🧡  Über dieses Modul',
            'items'    => [
                ['type' => 'Label', 'caption' => 'Entstanden aus echter Begeisterung für die eigene Anlage — und ein paar durchgetippten Abenden. Trotzdem: Software-Hobby hin oder her, das hier ist geistiges Eigentum und echte Arbeit steckt drin.'],
                ['type' => 'Label', 'caption' => 'Lizenz: PolyForm Noncommercial 1.0.0 — privat und nicht-kommerziell frei nutzbar, für den gewerblichen Einsatz braucht es eine gesonderte Lizenz vom Rechteinhaber.'],
                ['type' => 'Button', 'caption' => 'Lizenztext ansehen', 'onClick' => "echo '" . self::LICENSE_URL . "';", 'link' => true],
                ['type' => 'Label', 'caption' => 'Gewerbliche Nutzung oder Fragen zur Lizenz? Einfach melden: dietmar@gureth.eu'],
                ['type' => 'Label', 'caption' => 'Gefällt dir das Modul und du möchtest trotzdem etwas dalassen? Über eine kleine Spende freue ich mich — völlig freiwillig, keine Gegenleistung nötig.'],
                ['type' => 'Button', 'caption' => '☕  Spenden via PayPal', 'onClick' => "echo '" . self::PAYPAL_URL . "';", 'link' => true],
            ],
        ];
    }

    /**
     * Noch KEIN eigener Forumsthread (Modul frisch, noch nicht angekuendigt) --
     * ForumHint() liefert bewusst NULL, bis Dietmar einen Thread eroeffnet hat
     * (Muster: WPModbusHub/SamsungEhs hatten das Panel auch erst nach dem
     * Store-Announcement). AckForumHint()/ForumHintGone-Attribut sind schon
     * vorbereitet, damit spaeter nur der Text+Link ergaenzt werden muss.
     */
    private function ForumHint(): ?array
    {
        return null;
    }

    public function AckForumHint(): void
    {
        $this->WriteAttributeBoolean('ForumHintGone', true);
        $this->UpdateFormField('ForumHintPanel', 'visible', false);
    }

    /**
     * Konfigurierte Felder (Ident => Parameternummer) -- Felder mit
     * Parameternummer 0 gelten als "nicht vorhanden" und werden weder
     * abgefragt noch als Variable angelegt.
     */
    private function configuredFields(): array
    {
        $out = [];
        foreach (self::FIELD_PROPERTIES as $ident => $property) {
            $paramId = $this->ReadPropertyInteger($property);
            if ($paramId > 0) {
                $out[$ident] = $paramId;
            }
        }
        return $out;
    }

    public function Update(): void
    {
        if (!$this->ReadPropertyBoolean('WPBSBL_Active')) {
            return;
        }
        $host = trim($this->ReadPropertyString('Host'));
        if ($host === '') {
            $this->SetStatus(201);
            return;
        }
        $fields = $this->configuredFields();
        $betriebsartParam = $this->ReadPropertyInteger('ParamBetriebsart');
        $allParams = array_values($fields);
        if ($betriebsartParam > 0) {
            $allParams[] = $betriebsartParam;
        }

        $client = $this->bsbLanClient();
        $raw = ($allParams !== []) ? $client->queryParameters($allParams) : [];
        $this->recordCycle($fields, $betriebsartParam, $raw);

        $reachable = ($raw !== null) && (count($allParams) === 0 || count($raw) > 0);
        $values = [];
        if ($raw !== null) {
            foreach ($fields as $ident => $paramId) {
                if (isset($raw[$paramId])) {
                    $values[$ident] = $raw[$paramId]['value'];
                }
            }
        }
        $betriebsart = ($raw !== null && $betriebsartParam > 0 && isset($raw[$betriebsartParam])) ? $raw[$betriebsartParam] : null;

        $this->maintainDeviceVariables($values, $betriebsart, $reachable);
        if ($reachable) {
            $this->WriteAttributeInteger('LastSeenAt', time());
        }

        $this->SetStatus($reachable ? 102 : 201);
        if (!$reachable) {
            $this->LogMessage('BSB-LAN-Adapter nicht erreichbar (' . $host . ':' . $this->ReadPropertyInteger('Port') . '): ' . $client->lastError, KL_WARNING);
        }
    }

    /**
     * Baut den HTTP-Client. Testseam: der Pruefstand kann eine Fabrikfunktion
     * unter $GLOBALS['ips']['bsbClientFactory'] hinterlegen (liefert dann eine
     * Attrappe statt eines echten curl-Clients) -- Muster analog zur
     * SendDataToParent-Closure bei WPModbusHubGateway, hier ueber $GLOBALS
     * statt Konstruktor-Closure, weil dieser Client keinen Modul-Kontext
     * braucht.
     */
    private function bsbLanClient(): WPBSBL_BsbLanClient
    {
        if (isset($GLOBALS['ips']['bsbClientFactory']) && is_callable($GLOBALS['ips']['bsbClientFactory'])) {
            return ($GLOBALS['ips']['bsbClientFactory'])();
        }
        return new WPBSBL_BsbLanClient(
            trim($this->ReadPropertyString('Host')),
            $this->ReadPropertyInteger('Port'),
            $this->ReadPropertyBoolean('UseHttps'),
            $this->ReadPropertyString('Password')
        );
    }

    private function maintainDeviceVariables(array $values, ?array $betriebsart, bool $reachable): void
    {
        $pos = 0;
        $this->MaintainVariable('Erreichbar', 'Erreichbar', VARIABLETYPE_BOOLEAN, '~Alert.Reversed', $pos++, true);
        $this->SetValue('Erreichbar', $reachable);

        foreach (self::FIELD_CAPTIONS as $ident => $caption) {
            if (!isset(self::FIELD_PROPERTIES[$ident]) || $this->ReadPropertyInteger(self::FIELD_PROPERTIES[$ident]) <= 0) {
                continue;
            }
            $this->MaintainVariable($ident, $caption, VARIABLETYPE_FLOAT, 'NRG.Celsius', $pos++, true);
            $this->ensureArchived($ident);
            if (array_key_exists($ident, $values)) {
                $this->SetValue($ident, (float)$values[$ident]);
            }
        }

        if ($this->ReadPropertyInteger('ParamBetriebsart') > 0) {
            $this->MaintainVariable('Betriebsart', 'Betriebsart Heizkreis 1 (Code)', VARIABLETYPE_INTEGER, '', $pos++, true);
            $this->MaintainVariable('BetriebsartText', 'Betriebsart Heizkreis 1', VARIABLETYPE_STRING, '', $pos++, true);
            if ($betriebsart !== null) {
                $this->SetValue('Betriebsart', (int)$betriebsart['value']);
                // BSB-LAN liefert den Klartext im 'desc'-Feld direkt mit (z. B.
                // "Schutzbetrieb") -- keine eigene Enum-Tabelle noetig, anders
                // als bei den Modbus-Herstellern.
                $this->SetValue('BetriebsartText', $betriebsart['desc'] !== '' ? $betriebsart['desc'] : (string)(int)$betriebsart['value']);
            }
        }
    }

    private function ensureArchived(string $ident): void
    {
        $id = @$this->GetIDForIdent($ident);
        if ($id === false) {
            return;
        }
        $archiveIDs = @IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        if (!is_array($archiveIDs) || count($archiveIDs) === 0) {
            return;
        }
        try {
            if (!AC_GetLoggingStatus($archiveIDs[0], $id)) {
                AC_SetLoggingStatus($archiveIDs[0], $id, true);
                IPS_ApplyChanges($archiveIDs[0]);
            }
        } catch (\Throwable $e) {
            // Archivierung ist ein Komfortfeature, kein Zyklus-Abbruch wert.
        }
    }

    /**
     * Merkt sich, wann der letzte Lesezyklus lief und welche konfigurierten
     * Felder dabei NICHT gelesen wurden -- Grundlage der Statuszeile.
     */
    private function recordCycle(array $fields, int $betriebsartParam, ?array $raw): void
    {
        $this->WriteAttributeInteger('LastCycleAt', time());
        $missing = [];
        foreach ($fields as $ident => $paramId) {
            if ($raw === null || !isset($raw[$paramId])) {
                $missing[] = $ident;
            }
        }
        if ($betriebsartParam > 0 && ($raw === null || !isset($raw[$betriebsartParam]))) {
            $missing[] = 'Betriebsart';
        }
        $this->WriteAttributeString('LastMissing', implode(',', $missing));
    }

    private function ageText(int $timestamp): string
    {
        $sec = max(0, time() - $timestamp);
        if ($sec < 120) {
            return 'vor ' . $sec . ' s';
        }
        if ($sec < 7200) {
            return 'vor ' . intdiv($sec, 60) . ' min';
        }
        if ($sec < 172800) {
            return 'vor ' . intdiv($sec, 3600) . ' h';
        }
        return 'vor ' . intdiv($sec, 86400) . ' Tagen';
    }

    /** Zuletzt uebernommene Werte als Text, z. B. "Außentemperatur 11,9 °C, ...". */
    private function lastValuesText(): string
    {
        $parts = [];
        foreach (self::FIELD_CAPTIONS as $ident => $caption) {
            if (!isset(self::FIELD_PROPERTIES[$ident]) || $this->ReadPropertyInteger(self::FIELD_PROPERTIES[$ident]) <= 0) {
                continue;
            }
            $id = $this->contractFieldID($ident);
            if ($id === 0) {
                continue;
            }
            $parts[] = $caption . ' ' . number_format((float)GetValue($id), 1, ',', '') . ' °C';
        }
        if ($this->ReadPropertyInteger('ParamBetriebsart') > 0) {
            $id = $this->contractFieldID('BetriebsartText');
            if ($id !== 0) {
                $text = (string)GetValue($id);
                if ($text !== '') {
                    $parts[] = 'Betriebsart Heizkreis 1 ' . $text;
                }
            }
        }
        return implode(', ', $parts);
    }

    /**
     * Statuszeile fuer das Formular (SUITE.md "Verbund-Verbindungen im
     * Formular sichtbar machen", 21.09.2026): live berechnet, sagt was
     * tatsaechlich ankommt. [Text, Farbe], Farbe -1 = Standard, 0xFF0000 = rot.
     */
    private function statusLine(): array
    {
        $active   = $this->ReadPropertyBoolean('WPBSBL_Active');
        $hasHost  = trim($this->ReadPropertyString('Host')) !== '';
        $interval = max(30, $this->ReadPropertyInteger('WPBSBL_Interval'));

        if (!$active) {
            if (!$hasHost) {
                return ['ℹ️ Noch nicht eingerichtet: keine IP-Adresse des BSB-LAN-Adapters eingetragen. Danach „WPBsbLan aktiv“ einschalten und übernehmen.', -1];
            }
            return ['ℹ️ Ausgeschaltet -- „WPBsbLan aktiv“ einschalten und übernehmen, dann wird der BSB-LAN-Adapter gelesen.', -1];
        }
        if (!$hasHost) {
            return ['⛔ Pflichtangabe fehlt: die IP-Adresse des BSB-LAN-Adapters.', 0xFF0000];
        }

        $fields = $this->configuredFields();
        $hasBetriebsart = $this->ReadPropertyInteger('ParamBetriebsart') > 0;
        if (count($fields) === 0 && !$hasBetriebsart) {
            return ['⛔ Kein Feld konfiguriert: mindestens eine Parameternummer eintragen, sonst gibt es nichts zu lesen.', 0xFF0000];
        }

        $lastCycle = $this->ReadAttributeInteger('LastCycleAt');
        if ($lastCycle === 0) {
            return ['ℹ️ Noch kein Lesezyklus gelaufen -- der erste folgt innerhalb von ' . $interval . ' s nach dem Übernehmen.', -1];
        }

        $reachableID = $this->contractFieldID('Erreichbar');
        $reachable = ($reachableID !== 0) && (bool)GetValue($reachableID);
        $lastSeen = $this->ReadAttributeInteger('LastSeenAt');
        $values = $this->lastValuesText();

        if (!$reachable) {
            $text = '⚠️ BSB-LAN-Adapter antwortet nicht (letzte Antwort: ' . ($lastSeen > 0 ? $this->ageText($lastSeen) : 'noch nie') . '). IP-Adresse, Port und ggf. Passwort prüfen.';
            if ($values !== '') {
                $text .= ' Letzte bekannte Werte: ' . $values . '.';
            }
            return [$text, -1];
        }
        if (time() - $lastCycle > 3 * $interval + 10) {
            return ['⚠️ Die letzte Aktualisierung liegt ' . str_replace('vor ', '', $this->ageText($lastCycle)) . ' zurück, erwartet wären ' . $interval . ' s -- Timer und Instanzstatus prüfen. Letzte Werte: ' . $values . '.', -1];
        }
        $missingIdents = array_filter(explode(',', $this->ReadAttributeString('LastMissing')));
        if (count($missingIdents) > 0) {
            $names = [];
            foreach ($missingIdents as $ident) {
                $names[] = self::FIELD_CAPTIONS[$ident] ?? $ident;
            }
            $count = count($fields) + ($hasBetriebsart ? 1 : 0);
            return ['⚠️ Adapter antwortet, aber ' . count($names) . ' von ' . $count . ' Feldern wurden nicht gelesen (' . implode(', ', $names) . ') -- Parameternummer(n) prüfen. Gelesen ' . $this->ageText($lastCycle) . ': ' . $values . '.', -1];
        }
        return ['✅ BSB-LAN-Adapter antwortet, gelesen ' . $this->ageText($lastCycle) . ': ' . $values . '.', -1];
    }

    /**
     * NRG-Stack-Vertrag fuer Waermepumpen, konsistent zu WPHub/WPModbusHub/
     * SamsungEhs (Type=>'heatpump', contractVersion 1.15, dieselben
     * Feldnamen). PowerID/EnergyID bleiben 0 -- BSB-LAN liefert bei uns
     * bislang nur Temperaturen/Betriebsart, keine Leistungs-/Energiezaehler.
     */
    public function GetFunctions()
    {
        $reachableID = @$this->GetIDForIdent('Erreichbar');
        return [[
            'contractVersion'      => '1.15',
            'Type'                 => 'heatpump',
            'Caption'              => 'Wärmepumpe (BSB-LAN)',
            'PowerID'              => 0,
            'EnergyID'             => 0,
            'Measured'             => false,
            'unit'                 => 'W',
            'reachable'            => ($reachableID === false) ? false : (bool)GetValue($reachableID),
            'outsideTempID'        => $this->contractFieldID('Aussentemperatur'),
            'outdoorTemperatureID' => $this->contractFieldID('Aussentemperatur'),
            'z1WaterTempID'        => 0,
            'z1WaterTargetTempID'  => 0,
            'z2WaterTempID'        => 0,
            'z2WaterTargetTempID'  => 0,
            'dhwTempID'            => $this->contractFieldID('Warmwasser'),
            'dhwTargetTempID'      => $this->contractFieldID('WarmwasserSoll'),
            'mainInletTempID'      => $this->contractFieldID('Ruecklauftemperatur'),
            'mainOutletTempID'     => $this->contractFieldID('Vorlauftemperatur'),
            'bufferTempID'         => 0,
            // Rohes Diagnosefeld (BSB-LAN-ENUM-Code); operatingModeNormID (der
            // verbundweit normalisierte Zustand) noch NICHT befuellt -- dafuer
            // muesste jeder moegliche Betriebsart-Code auf standby/heating/
            // cooling/dhw abgebildet werden, das ist geraetespezifisch und
            // noch nicht verifiziert (nur EIN Wert, "Schutzbetrieb", bisher
            // live gesehen). Siehe WPBsbLan-CLAUDE.md.
            'operatingModeNormID'  => 0,
            'operatingModeID'      => $this->contractFieldID('Betriebsart'),
            'quietModeID'          => 0,
            'ecoComfortModeID'     => 0,
            'holidayTimerID'       => 0,
            'managedBy'            => 'wpbsbl',
            'lastSeenAt'           => $this->ReadAttributeInteger('LastSeenAt'),
        ]];
    }

    private function contractFieldID(string $ident): int
    {
        $id = @$this->GetIDForIdent($ident);
        return ($id === false) ? 0 : (int)$id;
    }

    private function ensureSharedProfiles(): void
    {
        if (!IPS_VariableProfileExists('NRG.Celsius')) {
            IPS_CreateVariableProfile('NRG.Celsius', VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileText('NRG.Celsius', '', ' °C');
            IPS_SetVariableProfileDigits('NRG.Celsius', 1);
        }
    }
}
