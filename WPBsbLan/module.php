<?php

require_once __DIR__ . '/../libs/WPBSBL_Text.php';
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
    // Punkt 1): Schluessel = Version ohne Beta-/Build-Suffix, Wert = Zeilen. Die Zeilen
    // sind englische Quelltexte, die deutsche Fassung steht in locale.json.
    const NEWS_VERSIONS = [
        '0.1.0' => [
            'First version: read heating systems/heat pumps via a local BSB-LAN adapter -- outdoor, flow and return temperature, hot water actual/target and operating mode, all parameter numbers freely adjustable.',
        ],
        '0.1.1' => [
            '"Operating mode" is now called "Operating mode heating circuit 1" -- multi-circuit systems have their own, independent parameters for each heating/cooling circuit, which do not change automatically along.',
        ],
        '0.2.0' => [
            'New field "Operating mode cooling circuit 1" -- its own, independent parameter for cooling mode, in addition to "Operating mode heating circuit 1".',
        ],
        '0.2.1' => [
            'Logging in the Symcon archive is no longer switched on automatically. In the "General" section there is the button "Enable logging for the measured values", so you enable it deliberately (Symcon review). Logging that is already enabled stays untouched.',
        ],
        '0.3.0' => [
            'The module is now fully bilingual (German/English): form, status messages, notes and the names of new variables follow the Symcon language.',
        ],
    ];
    private const LIBRARY_GUID = '{61F8D0DA-6E1F-497F-9A1A-AC797E298A35}';

    // Anzeigenamen der Felder (englische Quelltexte, uebersetzt beim Anzeigen), in
    // Anlegereihenfolge -- genutzt von maintainDeviceVariables() und der Statuszeile.
    // Die Idents bleiben deutsch: sie sind der Vertrag mit WPHub/EMS/Dashboard.
    private const FIELD_CAPTIONS = [
        'Aussentemperatur'    => 'Outdoor temperature',
        'Vorlauftemperatur'   => 'Flow temperature',
        'Ruecklauftemperatur' => 'Return temperature',
        'Warmwasser'          => 'Hot water',
        'WarmwasserSoll'      => 'Hot water setpoint',
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
        'ParamBetriebsartKuehl1'   => 901,
    ];

    // Betriebsart-Felder: BSB-LAN/Siemens fuehrt je Heiz-/Kuehlkreis EIGENE,
    // unabhaengige Betriebsart-Parameter (Forum-Post #13/#15, 23.09.2026 --
    // Lütfüs Anlage hat Heizkreis 1 (700) im Einsatz, Heizkreis 2 (1200) und
    // Kühlkreis 2 (1201) vorhanden aber ungenutzt, dafuer nutzt er Kühlkreis 1
    // (901) gelegentlich). Property => [Ident, Caption] -- jeweils zwei
    // Variablen (Ident = Code/INTEGER, Ident+'Text' = Klartext aus BSB-LANs
    // eigenem 'desc'-Feld/STRING, keine lokale Enum-Tabelle noetig).
    private const MODE_FIELDS = [
        'ParamBetriebsart'       => ['ident' => 'Betriebsart',        'caption' => 'Operating mode heating circuit 1', 'codeCaption' => 'Operating mode heating circuit 1 (code)'],
        'ParamBetriebsartKuehl1' => ['ident' => 'BetriebsartKuehl1',  'caption' => 'Operating mode cooling circuit 1', 'codeCaption' => 'Operating mode cooling circuit 1 (code)'],
    ];

    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID);
        // Statuszeile, Formular und Variablennamen uebersetzen ueber WPBSBL_Text (englische Quelltexte, Deutsch in
        // locale.json); jeder Text fragt Symcon hoechstens einmal je Prozess (SUITE.md Stolperstein 24).
        WPBSBL_Text::setzen(WPBSBL_Text::mitMerkliste(function (string $text): string {
            return $this->Translate($text);
        }));
    }

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
        // Formulartexte der form.json sind englische Quelltexte; die dynamisch gebauten Teile unten
        // uebersetzen sich selbst (nicht doppelt uebersetzen).
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        $libraryInfo = @json_decode((string)@file_get_contents(__DIR__ . '/../library.json'), true);
        $libraryVersion = (is_array($libraryInfo) && isset($libraryInfo['version'])) ? (string)$libraryInfo['version'] : '?';
        $this->updateFormElement($form['elements'], 'VersionInfo', [
            'caption' => WPBSBL_Text::t('ℹ️ WPBsbLan version %s -- local connection of heating systems/heat pumps via a BSB-LAN adapter.', $libraryVersion),
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
                $items[] = ['type' => 'Label', 'caption' => WPBSBL_Text::t('Version %s:', $ver)];
            }
            foreach ($lines as $line) {
                $items[] = ['type' => 'Label', 'caption' => '• ' . WPBSBL_Text::t($line)];
            }
        }
        $items[] = ['type' => 'Button', 'caption' => WPBSBL_Text::t('Understood – do not show again'), 'onClick' => 'WPBSBL_AckNews($id);'];
        $latest = array_key_last($pending);
        return ['type' => 'ExpansionPanel', 'name' => 'NewsPanel', 'caption' => WPBSBL_Text::t('🆕 New up to version %s', $latest), 'expanded' => true, 'items' => $items];
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
            'caption'  => WPBSBL_Text::t('👋  What is this module for?'),
            'items'    => [
                ['type' => 'Label', 'caption' => WPBSBL_Text::t('WPBsbLan reads a heating system or heat pump via a local BSB-LAN adapter -- without internet, without a manufacturer account. BSB-LAN is a widespread open-source hardware/firmware that translates the Siemens heating bus (BSB/LPB/PPS) of many manufacturers (Atlantic, Brötje, Elco, Fujitsu Waterstage and others with Siemens RVS/LMU controllers) into a local HTTP/JSON interface.')],
                ['type' => 'Label', 'caption' => WPBSBL_Text::t('Unlike the other heat pump modules of the family, the parameter numbers for the individual values are NOT uniform -- every BSB-LAN installation can have its own numbers. So you enter them yourself below; the values confirmed on a real Fujitsu Waterstage (Siemens RVS21.831/127) are pre-filled as a starting point.')],
                ['type' => 'Button', 'caption' => WPBSBL_Text::t('Understood – do not show again'), 'onClick' => 'WPBSBL_AckPurposeIntro($id);'],
            ],
        ];
    }

    public function AckPurposeIntro(): void
    {
        $this->WriteAttributeBoolean('PurposeIntroGone', true);
        $this->UpdateFormField('PurposeIntroPanel', 'visible', false);
    }

    // Seit 27.10.2026 ist main der Release-Branch (beta ist identisch, ems-integration abgeschafft).
    private const LICENSE_URL = 'https://github.com/DG65/NRGWPBsbLan/blob/main/LICENSE';
    private const PAYPAL_URL = 'https://paypal.me/DietmarGureth';

    private function LicenseHint(): array
    {
        return [
            'type'     => 'ExpansionPanel',
            'expanded' => false,
            'caption'  => WPBSBL_Text::t('🧡  About this module'),
            'items'    => [
                ['type' => 'Label', 'caption' => WPBSBL_Text::t('Born out of genuine enthusiasm for my own installation — and a few evenings of typing. Still: hobby software or not, this is intellectual property and real work went into it.')],
                ['type' => 'Label', 'caption' => WPBSBL_Text::t('License: PolyForm Noncommercial 1.0.0 — free for private and non-commercial use; commercial use needs a separate license from the rights holder.')],
                ['type' => 'Button', 'caption' => WPBSBL_Text::t('View license text'), 'onClick' => "echo '" . self::LICENSE_URL . "';", 'link' => true],
                ['type' => 'Label', 'caption' => WPBSBL_Text::t('Commercial use or questions about the license? Just get in touch: dietmar@gureth.eu')],
                ['type' => 'Label', 'caption' => WPBSBL_Text::t('Do you like the module and still want to leave something? I would be happy about a small donation — completely voluntary, nothing owed in return.')],
                ['type' => 'Button', 'caption' => WPBSBL_Text::t('☕  Donate via PayPal'), 'onClick' => "echo '" . self::PAYPAL_URL . "';", 'link' => true],
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

    /** Konfigurierte Betriebsart-Felder (Ident => Parameternummer), analog configuredFields(). */
    private function configuredModes(): array
    {
        $out = [];
        foreach (self::MODE_FIELDS as $property => $def) {
            $paramId = $this->ReadPropertyInteger($property);
            if ($paramId > 0) {
                $out[$def['ident']] = $paramId;
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
        $modes  = $this->configuredModes();
        $allParams = array_merge(array_values($fields), array_values($modes));

        $client = $this->bsbLanClient();
        $raw = ($allParams !== []) ? $client->queryParameters($allParams) : [];
        $this->recordCycle($fields, $modes, $raw);

        $reachable = ($raw !== null) && (count($allParams) === 0 || count($raw) > 0);
        $values = [];
        $modeValues = [];
        if ($raw !== null) {
            foreach ($fields as $ident => $paramId) {
                if (isset($raw[$paramId])) {
                    $values[$ident] = $raw[$paramId]['value'];
                }
            }
            foreach ($modes as $ident => $paramId) {
                if (isset($raw[$paramId])) {
                    $modeValues[$ident] = $raw[$paramId];
                }
            }
        }

        $this->maintainDeviceVariables($values, $modeValues, $reachable);
        if ($reachable) {
            $this->WriteAttributeInteger('LastSeenAt', time());
        }

        $this->SetStatus($reachable ? 102 : 201);
        if (!$reachable) {
            $this->LogMessage(WPBSBL_Text::t('BSB-LAN adapter not reachable (%s:%d): %s', $host, $this->ReadPropertyInteger('Port'), $client->lastError), KL_WARNING);
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

    private function maintainDeviceVariables(array $values, array $modeValues, bool $reachable): void
    {
        $pos = 0;
        $this->MaintainVariable('Erreichbar', WPBSBL_Text::t('Reachable'), VARIABLETYPE_BOOLEAN, '~Alert.Reversed', $pos++, true);
        $this->SetValue('Erreichbar', $reachable);

        foreach (self::FIELD_CAPTIONS as $ident => $caption) {
            if (!isset(self::FIELD_PROPERTIES[$ident]) || $this->ReadPropertyInteger(self::FIELD_PROPERTIES[$ident]) <= 0) {
                continue;
            }
            $this->MaintainVariable($ident, WPBSBL_Text::t($caption), VARIABLETYPE_FLOAT, 'NRG.Celsius', $pos++, true);
            if (array_key_exists($ident, $values)) {
                $this->SetValue($ident, (float)$values[$ident]);
            }
        }

        foreach (self::MODE_FIELDS as $property => $def) {
            if ($this->ReadPropertyInteger($property) <= 0) {
                continue;
            }
            $ident = $def['ident'];
            $this->MaintainVariable($ident, WPBSBL_Text::t($def['codeCaption']), VARIABLETYPE_INTEGER, '', $pos++, true);
            $this->MaintainVariable($ident . 'Text', WPBSBL_Text::t($def['caption']), VARIABLETYPE_STRING, '', $pos++, true);
            if (isset($modeValues[$ident])) {
                $mode = $modeValues[$ident];
                $this->SetValue($ident, (int)$mode['value']);
                // BSB-LAN liefert den Klartext im 'desc'-Feld direkt mit (z. B.
                // "Schutzbetrieb") -- keine eigene Enum-Tabelle noetig, anders
                // als bei den Modbus-Herstellern.
                $this->SetValue($ident . 'Text', $mode['desc'] !== '' ? $mode['desc'] : (string)(int)$mode['value']);
            }
        }
    }

    /**
     * Schaltet das Logging im Symcon-Archiv fuer die Messwerte (Fliesskomma-
     * Variablen) dieser Instanz ein -- AUSSCHLIESSLICH auf Klick im Formular.
     * Symcon-Review 30.10.2026: ein Modul darf Variablen nicht von selbst
     * archivieren, der Nutzer muss das bewusst einschalten. Schaltet nie etwas
     * aus, was schon laeuft.
     */
    public function EnableArchiving(): string
    {
        try {
            $archiveIDs = @IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
            if (!is_array($archiveIDs) || count($archiveIDs) === 0) {
                return WPBSBL_Text::t('❌ No archive instance found -- logging cannot be enabled.');
            }
            $archiveID = (int)$archiveIDs[0];
            $new = 0;
            $already = 0;
            foreach (IPS_GetChildrenIDs($this->InstanceID) as $id) {
                if (IPS_GetObject($id)['ObjectType'] !== OBJECTTYPE_VARIABLE || IPS_GetVariable($id)['VariableType'] !== VARIABLETYPE_FLOAT) {
                    continue;
                }
                if (AC_GetLoggingStatus($archiveID, $id)) {
                    $already++;
                    continue;
                }
                AC_SetLoggingStatus($archiveID, $id, true);
                $new++;
            }
            if ($new > 0) {
                IPS_ApplyChanges($archiveID);
            }
            if ($new === 0 && $already === 0) {
                return WPBSBL_Text::t('ℹ️ No measured values yet -- wait for a poll first, then click again.');
            }
            return $already > 0
                ? WPBSBL_Text::t('✅ Logging enabled for %d measured value(s) (%d were already enabled).', $new, $already)
                : WPBSBL_Text::t('✅ Logging enabled for %d measured value(s).', $new);
        } catch (\Throwable $e) {
            return WPBSBL_Text::t('❌ Logging could not be enabled: %s', $e->getMessage());
        }
    }

    /**
     * Merkt sich, wann der letzte Lesezyklus lief und welche konfigurierten
     * Felder dabei NICHT gelesen wurden -- Grundlage der Statuszeile.
     */
    private function recordCycle(array $fields, array $modes, ?array $raw): void
    {
        $this->WriteAttributeInteger('LastCycleAt', time());
        $missing = [];
        foreach (array_merge($fields, $modes) as $ident => $paramId) {
            if ($raw === null || !isset($raw[$paramId])) {
                $missing[] = $ident;
            }
        }
        $this->WriteAttributeString('LastMissing', implode(',', $missing));
    }

    private function ageText(int $timestamp): string
    {
        $sec = max(0, time() - $timestamp);
        if ($sec < 120) {
            return WPBSBL_Text::t('%d s ago', $sec);
        }
        if ($sec < 7200) {
            return WPBSBL_Text::t('%d min ago', intdiv($sec, 60));
        }
        if ($sec < 172800) {
            return WPBSBL_Text::t('%d h ago', intdiv($sec, 3600));
        }
        return WPBSBL_Text::t('%d days ago', intdiv($sec, 86400));
    }

    /** Anzeigename zu einem Ident -- Temperaturfeld oder Betriebsart-Feld. */
    private function captionOf(string $ident): string
    {
        if (isset(self::FIELD_CAPTIONS[$ident])) {
            return WPBSBL_Text::t(self::FIELD_CAPTIONS[$ident]);
        }
        foreach (self::MODE_FIELDS as $def) {
            if ($def['ident'] === $ident) {
                return WPBSBL_Text::t($def['caption']);
            }
        }
        return $ident;
    }

    /**
     * Zuletzt uebernommene Werte als Text, z. B. "Außentemperatur 11,9 °C, ...".
     * Vor der ersten Antwort des Adapters gibt es keine Werte: die Variablen stehen dann auf 0, das waere eine
     * falsche Angabe ("0,0 °C"). $skipMissing laesst Felder weg, die im letzten Zyklus nicht gelesen wurden
     * (ihr alter Wert gehoert nicht in "gelesen vor ...").
     */
    private function lastValuesText(bool $skipMissing = false): string
    {
        if ($this->ReadAttributeInteger('LastSeenAt') === 0) {
            return '';
        }
        $missing = $skipMissing ? array_filter(explode(',', $this->ReadAttributeString('LastMissing'))) : [];
        $parts = [];
        foreach (self::FIELD_CAPTIONS as $ident => $caption) {
            if (!isset(self::FIELD_PROPERTIES[$ident]) || $this->ReadPropertyInteger(self::FIELD_PROPERTIES[$ident]) <= 0 || in_array($ident, $missing, true)) {
                continue;
            }
            $id = $this->contractFieldID($ident);
            if ($id === 0) {
                continue;
            }
            $parts[] = WPBSBL_Text::t($caption) . ' ' . WPBSBL_Text::zahl((float)GetValue($id)) . ' °C';
        }
        foreach (self::MODE_FIELDS as $property => $def) {
            if ($this->ReadPropertyInteger($property) <= 0 || in_array($def['ident'], $missing, true)) {
                continue;
            }
            $id = $this->contractFieldID($def['ident'] . 'Text');
            if ($id === 0) {
                continue;
            }
            $text = (string)GetValue($id);
            if ($text !== '') {
                $parts[] = WPBSBL_Text::t($def['caption']) . ' ' . $text;
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
                return [WPBSBL_Text::t('ℹ️ Not set up yet: no IP address of the BSB-LAN adapter entered. Then switch on "WPBsbLan active" and apply.'), -1];
            }
            return [WPBSBL_Text::t('ℹ️ Switched off -- switch on "WPBsbLan active" and apply, then the BSB-LAN adapter is read.'), -1];
        }
        if (!$hasHost) {
            return [WPBSBL_Text::t('⛔ Required entry missing: the IP address of the BSB-LAN adapter.'), 0xFF0000];
        }

        $fields = $this->configuredFields();
        $modes = $this->configuredModes();
        if (count($fields) === 0 && count($modes) === 0) {
            return [WPBSBL_Text::t('⛔ No field configured: enter at least one parameter number, otherwise there is nothing to read.'), 0xFF0000];
        }

        $lastCycle = $this->ReadAttributeInteger('LastCycleAt');
        if ($lastCycle === 0) {
            return [WPBSBL_Text::t('ℹ️ No read cycle has run yet -- the first follows within %d s after applying.', $interval), -1];
        }

        $reachableID = $this->contractFieldID('Erreichbar');
        $reachable = ($reachableID !== 0) && (bool)GetValue($reachableID);
        $lastSeen = $this->ReadAttributeInteger('LastSeenAt');
        if (!$reachable) {
            $values = $this->lastValuesText(false);
            $text = WPBSBL_Text::t('⚠️ BSB-LAN adapter does not answer (last answer: %s). Check IP address, port and, if set, the password.', $lastSeen > 0 ? $this->ageText($lastSeen) : WPBSBL_Text::t('never'));
            if ($values !== '') {
                $text .= ' ' . WPBSBL_Text::t('Last known values: %s.', $values);
            }
            return [$text, -1];
        }
        $values = $this->lastValuesText(true);
        if (time() - $lastCycle > 3 * $interval + 10) {
            return [WPBSBL_Text::t('⚠️ The last update was %s, one is expected every %d s -- check the timer and the instance status. Last values: %s.', $this->ageText($lastCycle), $interval, $values), -1];
        }
        $missingIdents = array_filter(explode(',', $this->ReadAttributeString('LastMissing')));
        if (count($missingIdents) > 0) {
            $names = [];
            foreach ($missingIdents as $ident) {
                $names[] = $this->captionOf($ident);
            }
            $count = count($fields) + count($modes);
            return [WPBSBL_Text::t('⚠️ Adapter answers, but %d of %d fields were not read (%s) -- check the parameter number(s). Read %s: %s.', count($names), $count, implode(', ', $names), $this->ageText($lastCycle), $values), -1];
        }
        return [WPBSBL_Text::t('✅ BSB-LAN adapter answers, read %s: %s.', $this->ageText($lastCycle), $values), -1];
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
            'Caption'              => WPBSBL_Text::t('Heat pump (BSB-LAN)'),
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
