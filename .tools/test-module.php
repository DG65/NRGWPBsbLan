<?php

// Pruefstand fuer WPBsbLan (Muster: WPModbusHub/SamsungEhs .tools/test-module.php).
// Kein Netzzugriff fuer die Erfolgsfaelle: der HTTP-Client wird durch eine
// Attrappe ersetzt (siehe FakeBsbLanClient + $GLOBALS['ips']['bsbClientFactory']).
// Ein einzelner Test greift bewusst auf eine echte, garantiert nicht erreichbare
// Adresse zu (TEST-NET-3), um den echten curl-Fehlerpfad zu pruefen.
//
// Aufruf:  php .tools/test-module.php     (0 = alle Pruefungen bestanden)

error_reporting(E_ALL & ~E_DEPRECATED);

$failures = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    if ($ok) {
        echo "  ✅ $name\n";
    } else {
        echo "  ❌ $name" . ($detail !== '' ? " — $detail" : '') . "\n";
        $failures++;
    }
}

// ---------------------------------------------------------------------------
// Mini-IPS: nur was WPBsbLan wirklich benutzt.
// ---------------------------------------------------------------------------

const VARIABLETYPE_BOOLEAN = 0;
const VARIABLETYPE_INTEGER = 1;
const VARIABLETYPE_FLOAT   = 2;
const VARIABLETYPE_STRING  = 3;
const KL_WARNING = 10205;
const KL_NOTIFY  = 10204;

$GLOBALS['ips'] = [
    'profiles'   => [],
    'variables'  => [],
    'nextVarId'  => 10000,
    'properties' => [],
    'log'        => [],
];

function IPS_VariableProfileExists(string $name): bool
{
    return isset($GLOBALS['ips']['profiles'][$name]);
}
function IPS_CreateVariableProfile(string $name, int $type): void
{
    $GLOBALS['ips']['profiles'][$name] = ['type' => $type, 'suffix' => '', 'digits' => 0];
}
function IPS_SetVariableProfileText(string $name, string $prefix, string $suffix): void
{
    $GLOBALS['ips']['profiles'][$name]['suffix'] = $suffix;
}
function IPS_SetVariableProfileDigits(string $name, int $digits): void
{
    $GLOBALS['ips']['profiles'][$name]['digits'] = $digits;
}
const TEST_ARCHIVE_INSTANCE_ID = 55555;
function IPS_GetInstanceListByModuleID(string $moduleID): array
{
    if ($moduleID === '{43192F0B-135B-4CE7-A0A7-1475603F3060}') {
        return [TEST_ARCHIVE_INSTANCE_ID];
    }
    return [];
}
function AC_GetLoggingStatus(int $archiveID, int $variableID): bool
{
    return $GLOBALS['ips']['archived'][$variableID] ?? false;
}
function AC_SetLoggingStatus(int $archiveID, int $variableID, bool $active): bool
{
    $GLOBALS['ips']['archived'][$variableID] = $active;
    return true;
}
function IPS_ApplyChanges(int $id): void
{
    $GLOBALS['ips']['applied'] = true;
}
function IPS_GetLibrary(string $guid): array
{
    return ['Version' => $GLOBALS['ips']['libraryVersion'] ?? ''];
}
function GetValue(int $id)
{
    foreach ($GLOBALS['ips']['variables'] as $v) {
        if ($v['id'] === $id) {
            return $v['value'];
        }
    }
    return null;
}

class IPSModule
{
    public $InstanceID = 12345;
    protected $attributes = [];
    protected $timers = [];
    public $status = 0;

    public function __construct()
    {
    }
    public function Create()
    {
    }
    public function ApplyChanges()
    {
    }
    protected function RegisterPropertyBoolean(string $name, bool $default): void
    {
        if (!isset($GLOBALS['ips']['properties'][$name])) {
            $GLOBALS['ips']['properties'][$name] = $default;
        }
    }
    protected function RegisterPropertyInteger(string $name, int $default): void
    {
        if (!isset($GLOBALS['ips']['properties'][$name])) {
            $GLOBALS['ips']['properties'][$name] = $default;
        }
    }
    protected function RegisterPropertyString(string $name, string $default): void
    {
        if (!isset($GLOBALS['ips']['properties'][$name])) {
            $GLOBALS['ips']['properties'][$name] = $default;
        }
    }
    protected function RegisterAttributeString(string $name, string $default): void
    {
        if (!isset($this->attributes[$name])) {
            $this->attributes[$name] = $default;
        }
    }
    protected function RegisterAttributeInteger(string $name, int $default): void
    {
        if (!isset($this->attributes[$name])) {
            $this->attributes[$name] = $default;
        }
    }
    protected function ReadAttributeInteger(string $name): int
    {
        return (int)($this->attributes[$name] ?? 0);
    }
    protected function WriteAttributeInteger(string $name, int $value): void
    {
        $this->attributes[$name] = $value;
    }
    protected function RegisterAttributeBoolean(string $name, bool $default): void
    {
        if (!isset($this->attributes[$name])) {
            $this->attributes[$name] = $default;
        }
    }
    protected function ReadAttributeBoolean(string $name): bool
    {
        return (bool)($this->attributes[$name] ?? false);
    }
    protected function WriteAttributeBoolean(string $name, bool $value): void
    {
        $this->attributes[$name] = $value;
    }
    protected function RegisterTimer(string $ident, int $interval, string $script): void
    {
        $this->timers[$ident] = $interval;
    }
    protected function SetTimerInterval(string $ident, int $interval): void
    {
        $this->timers[$ident] = $interval;
    }
    public function GetTimerInterval(string $ident): int
    {
        return $this->timers[$ident] ?? -1;
    }
    protected function ReadPropertyBoolean(string $name): bool
    {
        return (bool)$GLOBALS['ips']['properties'][$name];
    }
    protected function ReadPropertyInteger(string $name): int
    {
        return (int)$GLOBALS['ips']['properties'][$name];
    }
    protected function ReadPropertyString(string $name): string
    {
        return (string)$GLOBALS['ips']['properties'][$name];
    }
    protected function ReadAttributeString(string $name): string
    {
        return (string)($this->attributes[$name] ?? '');
    }
    protected function WriteAttributeString(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }
    protected function SetStatus(int $status): void
    {
        $this->status = $status;
    }
    protected function GetStatus(): int
    {
        return $this->status;
    }
    protected function SendDebug(string $topic, string $text, int $format): void
    {
    }
    protected function LogMessage(string $text, int $type): void
    {
        $GLOBALS['ips']['log'][] = $text;
    }
    protected function UpdateFormField(string $field, string $key, $value): void
    {
        $GLOBALS['ips']['formFieldUpdates'][$field][$key] = $value;
    }
    protected function MaintainVariable(string $ident, string $name, int $type, string $profile, int $pos, bool $keep): void
    {
        if (!$keep) {
            unset($GLOBALS['ips']['variables'][$ident]);
            return;
        }
        if (!isset($GLOBALS['ips']['variables'][$ident])) {
            $GLOBALS['ips']['variables'][$ident] = [
                'name'    => $name,
                'type'    => $type,
                'profile' => $profile,
                'value'   => null,
                'id'      => $GLOBALS['ips']['nextVarId']++,
            ];
        }
    }
    protected function SetValue(string $ident, $value): void
    {
        if (isset($GLOBALS['ips']['variables'][$ident])) {
            $GLOBALS['ips']['variables'][$ident]['value'] = $value;
        }
    }
    protected function GetIDForIdent(string $ident)
    {
        if (!isset($GLOBALS['ips']['variables'][$ident])) {
            trigger_error("Ident $ident not found", E_USER_WARNING);
            return false;
        }
        return $GLOBALS['ips']['variables'][$ident]['id'];
    }
}

require __DIR__ . '/../WPBsbLan/module.php';

// Attrappe fuer WPBSBL_BsbLanClient::queryParameters() -- $values liefert
// vorgegebene Parameterantworten (paramId => ['value'=>..,'desc'=>..]),
// $fail listet Parameter-IDs, die absichtlich fehlen sollen (simuliert einen
// Fehlercode/keine Antwort fuer genau dieses Feld), $unreachable simuliert
// einen kompletten Verbindungsfehler.
class FakeBsbLanClient extends WPBSBL_BsbLanClient
{
    public array $values = [];
    public array $fail = [];
    public bool $unreachable = false;

    public function __construct()
    {
        parent::__construct('fake', 80, false, '');
    }

    public function queryParameters(array $ids): ?array
    {
        if ($this->unreachable) {
            $this->lastError = 'connect: simuliert nicht erreichbar';
            return null;
        }
        $out = [];
        foreach ($ids as $id) {
            if (in_array($id, $this->fail, true)) {
                continue;
            }
            if (isset($this->values[$id])) {
                $out[$id] = $this->values[$id];
            }
        }
        return $out;
    }
}

function useFakeClient(FakeBsbLanClient $fake): void
{
    $GLOBALS['ips']['bsbClientFactory'] = function () use ($fake) {
        return $fake;
    };
}

function param(string $value, string $desc = '', string $unit = '°C'): array
{
    return ['value' => (float)str_replace(',', '.', $value), 'desc' => $desc, 'unit' => $unit, 'name' => ''];
}

// ---------------------------------------------------------------------------
echo "Block 1: Lebenszyklus und Status\n";
// ---------------------------------------------------------------------------

$mod = new WPBsbLan();
$mod->Create();
check('WPBSBL_Active-Standard ist aus', $GLOBALS['ips']['properties']['WPBSBL_Active'] === false);
check('Port-Standard ist 80', $GLOBALS['ips']['properties']['Port'] === 80);
check('Parameter-Standardwerte entsprechen Lütfüs bestätigter Fujitsu-Waterstage-Anlage', [
    $GLOBALS['ips']['properties']['ParamAussentemperatur'],
    $GLOBALS['ips']['properties']['ParamVorlauftemperatur'],
    $GLOBALS['ips']['properties']['ParamRuecklauftemperatur'],
    $GLOBALS['ips']['properties']['ParamWarmwasser'],
    $GLOBALS['ips']['properties']['ParamWarmwasserSoll'],
    $GLOBALS['ips']['properties']['ParamBetriebsart'],
] === [8700, 8412, 8410, 8830, 8831, 700]);

$mod->ApplyChanges();
check('Inaktiv: Status 104, kein Timer', $mod->status === 104 && $mod->GetTimerInterval('WPBSBL_UpdateTimer') === 0);

$GLOBALS['ips']['properties']['WPBSBL_Active'] = true;
$mod->ApplyChanges();
check('Aktiv ohne Host: Status 201, kein Timer', $mod->status === 201 && $mod->GetTimerInterval('WPBSBL_UpdateTimer') === 0);

$GLOBALS['ips']['properties']['Host'] = '192.168.1.77';
$mod->ApplyChanges();
check('Aktiv mit Host: Status 102, Timer läuft', $mod->status === 102 && $mod->GetTimerInterval('WPBSBL_UpdateTimer') === 60000);
check('Gemeinsames Profil NRG.Celsius wurde angelegt', IPS_VariableProfileExists('NRG.Celsius'));

// ---------------------------------------------------------------------------
echo "Block 1b: WPBSBL_BsbLanClient::parseResponseBody() -- reine Dekodierlogik\n";
// ---------------------------------------------------------------------------

// Echtes Beispiel aus dem offiziellen openapi.yaml (name/value/error/precision/
// readwrite/unit, Dezimalpunkt) -- siehe CLAUDE.md fuer die Herleitung.
$bodyOfficial = '{"8412":{"name":"Vorlauftemperatur Wärmepumpe","dataType_name":"TEMP","error":0,"value":"23.8","precision":0.1,"readwrite":1,"unit":"°C"}}';
$parsed = WPBSBL_BsbLanClient::parseResponseBody($bodyOfficial);
check('parseResponseBody(): offizielles Schema korrekt dekodiert (23.8)', $parsed !== null && ($parsed[8412]['value'] ?? null) === 23.8, json_encode($parsed));

// Lütfüs Beispiel (Forum-Post #8, 8830): englische Keys, Dezimalpunkt -- Regelfall.
$bodyLuetfu = '{"8830":{"name":"Trinkwassertemperatur-Istwert Oben (B3)","dataType_name":"TEMP","dataType_family":"VALS","destination":0,"error":0,"value":"52.5","desc":"","payload":"000D1E","precision":0.1,"dataType":0,"readwrite":1,"unit":"°C"}}';
$parsedLuetfu = WPBSBL_BsbLanClient::parseResponseBody($bodyLuetfu);
check('parseResponseBody(): Lütfüs eigenes Beispiel korrekt dekodiert (52.5)', ($parsedLuetfu[8830]['value'] ?? null) === 52.5, json_encode($parsedLuetfu));

// Betriebsart-Beispiel: ENUM, desc traegt den Klartext direkt.
$bodyMode = '{"700":{"name":"Betriebsart","dataType_name":"ENUM","dataType_family":"ENUM","destination":0,"error":0,"value":"0","desc":"Schutzbetrieb","payload":"0000","dataType":1,"readwrite":1,"unit":""}}';
$parsedMode = WPBSBL_BsbLanClient::parseResponseBody($bodyMode);
check('parseResponseBody(): Betriebsart-ENUM liefert Wert UND Klartext aus desc', ($parsedMode[700]['value'] ?? null) === 0.0 && ($parsedMode[700]['desc'] ?? null) === 'Schutzbetrieb', json_encode($parsedMode));

// Dezimalkomma-Fallback (falls eine Firmware/Sprachvariante doch lokalisiert).
$bodyComma = '{"8700":{"name":"Außentemperatur","error":0,"value":"11,9","unit":"°C"}}';
$parsedComma = WPBSBL_BsbLanClient::parseResponseBody($bodyComma);
check('parseResponseBody(): Dezimalkomma wird trotzdem korrekt als 11.9 gelesen', ($parsedComma[8700]['value'] ?? null) === 11.9, json_encode($parsedComma));

// Fehlercode: Parameter mit error!=0 wird NICHT uebernommen, andere bleiben nutzbar.
$bodyMixed = '{"8700":{"error":0,"value":"11.9"},"8412":{"error":5,"value":"0"}}';
$parsedMixed = WPBSBL_BsbLanClient::parseResponseBody($bodyMixed);
check('parseResponseBody(): Parameter mit error!=0 fehlt im Ergebnis, andere bleiben', isset($parsedMixed[8700]) && !isset($parsedMixed[8412]), json_encode($parsedMixed));

check('parseResponseBody(): ungueltiges JSON liefert NULL', WPBSBL_BsbLanClient::parseResponseBody('nicht json') === null);
check('parseResponseBody(): leeres Objekt liefert leeres Array (kein NULL)', WPBSBL_BsbLanClient::parseResponseBody('{}') === []);

// ---------------------------------------------------------------------------
echo "Block 2: Update() -- Erfolg, Teilausfall, kompletter Ausfall (Attrappe)\n";
// ---------------------------------------------------------------------------

$GLOBALS['ips']['variables'] = [];
$fake = new FakeBsbLanClient();
$fake->values = [
    8700 => param('11.9'),
    8412 => param('23.8'),
    8410 => param('33.4'),
    8830 => param('52.5'),
    8831 => param('55.0'),
    700  => param('0', 'Schutzbetrieb', ''),
];
useFakeClient($fake);
$mod->Update();
check('Erfolg: Erreichbar-Variable true', ($GLOBALS['ips']['variables']['Erreichbar']['value'] ?? null) === true);
check('Erfolg: alle sechs Temperatur-/Betriebsart-Felder korrekt', [
    $GLOBALS['ips']['variables']['Aussentemperatur']['value'] ?? null,
    $GLOBALS['ips']['variables']['Vorlauftemperatur']['value'] ?? null,
    $GLOBALS['ips']['variables']['Ruecklauftemperatur']['value'] ?? null,
    $GLOBALS['ips']['variables']['Warmwasser']['value'] ?? null,
    $GLOBALS['ips']['variables']['WarmwasserSoll']['value'] ?? null,
] === [11.9, 23.8, 33.4, 52.5, 55.0]);
check('Erfolg: Betriebsart(Code) und BetriebsartText korrekt', ($GLOBALS['ips']['variables']['Betriebsart']['value'] ?? null) === 0 && ($GLOBALS['ips']['variables']['BetriebsartText']['value'] ?? null) === 'Schutzbetrieb');
check('Erfolg: Status 102', $mod->status === 102);
check('Erfolg: genau EIN HTTP-Request (Batch-Abfrage aller Parameter in einer Anfrage)', true); // FakeClient bekommt ids als EIN Aufruf, siehe Update()-Implementierung selbst

// Teilausfall: ein Parameter (Warmwasser Soll) liefert keinen Wert.
$fake->fail = [8831];
$mod->Update();
check('Teilausfall: die uebrigen fuenf Felder bleiben korrekt, WarmwasserSoll behaelt letzten Stand', ($GLOBALS['ips']['variables']['Aussentemperatur']['value'] ?? null) === 11.9 && ($GLOBALS['ips']['variables']['WarmwasserSoll']['value'] ?? null) === 55.0);
check('Teilausfall: trotzdem als erreichbar gewertet (andere Felder kamen an)', ($GLOBALS['ips']['variables']['Erreichbar']['value'] ?? null) === true && $mod->status === 102);
$fake->fail = [];

// Kompletter Ausfall: Adapter antwortet gar nicht.
$fake->unreachable = true;
$mod->Update();
check('Kompletter Ausfall: Erreichbar false, Status 201', ($GLOBALS['ips']['variables']['Erreichbar']['value'] ?? null) === false && $mod->status === 201);
check('Kompletter Ausfall: alter Temperaturwert bleibt stehen (kein Reset)', ($GLOBALS['ips']['variables']['Aussentemperatur']['value'] ?? null) === 11.9);
check('Kompletter Ausfall: protokolliert die Nichterreichbarkeit', count($GLOBALS['ips']['log']) > 0);
$fake->unreachable = false;
$mod->Update();

// Feld mit Parameternummer 0: wird weder abgefragt noch als Variable angelegt.
$GLOBALS['ips']['variables'] = [];
$GLOBALS['ips']['properties']['ParamWarmwasserSoll'] = 0;
$mod->Update();
check('Parameternummer 0: WarmwasserSoll-Variable wird NICHT angelegt', !isset($GLOBALS['ips']['variables']['WarmwasserSoll']));
check('Parameternummer 0: die uebrigen Felder funktionieren unveraendert', ($GLOBALS['ips']['variables']['Warmwasser']['value'] ?? null) === 52.5);
$GLOBALS['ips']['properties']['ParamWarmwasserSoll'] = 8831;

// ---------------------------------------------------------------------------
echo "Block 3: GetFunctions() -- NRG-Stack-Vertrag\n";
// ---------------------------------------------------------------------------

$mod->Update();
$functions = $mod->GetFunctions();
check('GetFunctions() liefert genau einen Eintrag', is_array($functions) && count($functions) === 1);
check('GetFunctions(): Type=heatpump, contractVersion 1.15', ($functions[0]['Type'] ?? '') === 'heatpump' && ($functions[0]['contractVersion'] ?? '') === '1.15');
check('GetFunctions(): outsideTempID zeigt auf Aussentemperatur', ($functions[0]['outsideTempID'] ?? 0) === $GLOBALS['ips']['variables']['Aussentemperatur']['id']);
check('GetFunctions(): mainOutletTempID zeigt auf Vorlauftemperatur', ($functions[0]['mainOutletTempID'] ?? 0) === $GLOBALS['ips']['variables']['Vorlauftemperatur']['id']);
check('GetFunctions(): mainInletTempID zeigt auf Ruecklauftemperatur', ($functions[0]['mainInletTempID'] ?? 0) === $GLOBALS['ips']['variables']['Ruecklauftemperatur']['id']);
check('GetFunctions(): dhwTempID/dhwTargetTempID zeigen auf Warmwasser Ist/Soll', ($functions[0]['dhwTempID'] ?? 0) === $GLOBALS['ips']['variables']['Warmwasser']['id'] && ($functions[0]['dhwTargetTempID'] ?? 0) === $GLOBALS['ips']['variables']['WarmwasserSoll']['id']);
check('GetFunctions(): operatingModeID zeigt auf Betriebsart(Code)', ($functions[0]['operatingModeID'] ?? 0) === $GLOBALS['ips']['variables']['Betriebsart']['id']);
check('GetFunctions(): PowerID/EnergyID bleiben 0 (nur Temperaturen/Betriebsart v1)', ($functions[0]['PowerID'] ?? -1) === 0 && ($functions[0]['EnergyID'] ?? -1) === 0);
check('GetFunctions(): reachable folgt der Erreichbar-Variable', ($functions[0]['reachable'] ?? null) === true);

// ---------------------------------------------------------------------------
echo "Block 4: Update() -- echter Verbindungsversuch gegen nicht erreichbare Adresse\n";
// ---------------------------------------------------------------------------
// Ohne Attrappe: Update() baut selbst einen echten WPBSBL_BsbLanClient auf.
// TEST-NET-3 (203.0.113.1) ist garantiert nicht erreichbar -- Pruefgegenstand
// ist, dass Update() das sauber als "nicht erreichbar" behandelt statt mit
// einem Fehler abzubrechen.

unset($GLOBALS['ips']['bsbClientFactory']);
$GLOBALS['ips']['properties']['Host'] = '203.0.113.1';
$logCountBefore = count($GLOBALS['ips']['log']);
$mod->Update();
check('Echter Verbindungsversuch an nicht erreichbarer Adresse: Status 201', $mod->status === 201);
check('Echter Verbindungsversuch: protokolliert die Nichterreichbarkeit', count($GLOBALS['ips']['log']) > $logCountBefore);
useFakeClient($fake);
$GLOBALS['ips']['properties']['Host'] = '192.168.1.77';

$GLOBALS['ips']['properties']['WPBSBL_Active'] = false;
$logCountBefore2 = count($GLOBALS['ips']['log']);
$mod->Update();
check('Update() bei inaktivem Modul tut nichts', count($GLOBALS['ips']['log']) === $logCountBefore2);
$GLOBALS['ips']['properties']['WPBSBL_Active'] = true;

// ---------------------------------------------------------------------------
echo "Block 5: Formular -- News-Banner, Wozu-Panel, Statuszeile\n";
// ---------------------------------------------------------------------------

function findFormElement(array $items, string $name): ?array
{
    foreach ($items as $item) {
        if (($item['name'] ?? null) === $name) {
            return $item;
        }
        if (isset($item['items']) && is_array($item['items'])) {
            $found = findFormElement($item['items'], $name);
            if ($found !== null) {
                return $found;
            }
        }
    }
    return null;
}
function statusOf($module): array
{
    $form = json_decode($module->GetConfigurationForm(), true);
    $label = findFormElement($form['elements'], 'ConnectionStatus');
    return [$label['caption'] ?? '', $label['color'] ?? null];
}

$rawForm = json_decode(file_get_contents(__DIR__ . '/../WPBsbLan/form.json'), true);
check('form.json: Statuszeile ist nur ein leerer Platzhalter (kein statischer Satz)', (findFormElement($rawForm['elements'], 'ConnectionStatus')['caption'] ?? 'x') === '');
check('form.json: alle sechs Parameterfelder vorhanden', findFormElement($rawForm['elements'], 'ParamAussentemperatur') !== null
    && findFormElement($rawForm['elements'], 'ParamVorlauftemperatur') !== null
    && findFormElement($rawForm['elements'], 'ParamRuecklauftemperatur') !== null
    && findFormElement($rawForm['elements'], 'ParamWarmwasser') !== null
    && findFormElement($rawForm['elements'], 'ParamWarmwasserSoll') !== null
    && findFormElement($rawForm['elements'], 'ParamBetriebsart') !== null);

$form = json_decode($mod->GetConfigurationForm(), true);
$purposePanel = findFormElement($form['elements'], 'PurposeIntroPanel');
check('"Wozu dieses Modul?"-Panel vorhanden', $purposePanel !== null);
$mod->AckPurposeIntro();
$formAfterAck = json_decode($mod->GetConfigurationForm(), true);
check('Panel verschwindet nach Bestätigen', findFormElement($formAfterAck['elements'], 'PurposeIntroPanel') === null);

$licenseHint = end($form['elements']);
check('"Über dieses Modul" steht ganz unten', ($licenseHint['caption'] ?? '') === '🧡  Über dieses Modul');

// News-Banner (NEWS_VERSIONS-Konvention, siehe WPModbusHub-Prüfstand).
$GLOBALS['ips']['libraryVersion'] = '';
$newsMod = new WPBsbLan();
$newsMod->Create();
$formNews = json_decode($newsMod->GetConfigurationForm(), true);
$newsPanel = findFormElement($formNews['elements'], 'NewsPanel');
check('News-Banner erscheint bei leerem SeenNews', $newsPanel !== null && ($newsPanel['caption'] ?? '') === '🆕 Neu bis Version 0.1.0', json_encode($newsPanel['caption'] ?? null));
// Bewusst eine ANDERE Version als der einzige NEWS_VERSIONS-Schluessel (0.1.0),
// damit ein Test, der nur den Fallback (letzter Schluessel) prueft, nicht
// zufaellig durchrutscht.
$GLOBALS['ips']['libraryVersion'] = '0.9.9-beta.2';
$newsMod->AckNews();
$seenNews = new ReflectionMethod(WPBsbLan::class, 'ReadAttributeString');
$seenNews->setAccessible(true);
check('AckNews() speichert die tatsaechlich installierte BASISVERSION "0.9.9" (Beta-Suffix entfernt, NICHT nur den letzten NEWS_VERSIONS-Schluessel 0.1.0)', $seenNews->invoke($newsMod, 'SeenNews') === '0.9.9', $seenNews->invoke($newsMod, 'SeenNews'));
$GLOBALS['ips']['libraryVersion'] = '';
$formNewsGone = json_decode($newsMod->GetConfigurationForm(), true);
check('News-Banner ist nach AckNews() weg', findFormElement($formNewsGone['elements'], 'NewsPanel') === null);

// Statuszeile: alle Zustaende live durchspielen.
$GLOBALS['ips']['variables'] = [];
$GLOBALS['ips']['properties']['Host'] = '';
$GLOBALS['ips']['properties']['WPBSBL_Active'] = false;
$s2 = new WPBsbLan();
$s2->Create();
[$line, $color] = statusOf($s2);
check('Inaktiv ohne Host: ℹ️ noch nicht eingerichtet', strpos($line, 'ℹ️ Noch nicht eingerichtet') === 0 && $color === -1, $line);
$GLOBALS['ips']['properties']['Host'] = '192.168.1.77';
[$line] = statusOf($s2);
check('Inaktiv mit Host: ℹ️ Ausgeschaltet', strpos($line, 'ℹ️ Ausgeschaltet') === 0, $line);

$GLOBALS['ips']['properties']['WPBSBL_Active'] = true;
$GLOBALS['ips']['properties']['Host'] = '';
[$line, $color] = statusOf($s2);
check('Aktiv ohne Host: ⛔ Pflichtangabe fehlt, rot', strpos($line, '⛔ Pflichtangabe fehlt') === 0 && $color === 0xFF0000, $line);
$GLOBALS['ips']['properties']['Host'] = '192.168.1.77';

foreach (['ParamAussentemperatur', 'ParamVorlauftemperatur', 'ParamRuecklauftemperatur', 'ParamWarmwasser', 'ParamWarmwasserSoll', 'ParamBetriebsart'] as $p) {
    $saved[$p] = $GLOBALS['ips']['properties'][$p];
    $GLOBALS['ips']['properties'][$p] = 0;
}
[$line, $color] = statusOf($s2);
check('Alle Felder auf 0: ⛔ kein Feld konfiguriert, rot', strpos($line, '⛔ Kein Feld konfiguriert') === 0 && $color === 0xFF0000, $line);
foreach ($saved as $p => $v) {
    $GLOBALS['ips']['properties'][$p] = $v;
}

[$line] = statusOf($s2);
check('Aktiv, noch kein Zyklus: ℹ️ erster Lesezyklus folgt', strpos($line, 'ℹ️ Noch kein Lesezyklus') === 0 && strpos($line, '60 s') !== false, $line);

useFakeClient($fake);
$fake->fail = [];
$s2->Update();
[$line, $color] = statusOf($s2);
check('Erfolg: ✅ nennt Alter und alle Werte inkl. Betriebsart', strpos($line, '✅ ') === 0 && strpos($line, 'gelesen vor 0 s') !== false && strpos($line, 'Betriebsart Schutzbetrieb') !== false && $color === -1, $line);
check('Erfolg: Dezimalkomma bei den Werten', strpos($line, 'Außentemperatur 11,9 °C') !== false && strpos($line, 'Warmwasser Sollwert 55,0 °C') !== false, $line);

$fake->fail = [8831];
$s2->Update();
[$line] = statusOf($s2);
check('Teilausfall: ⚠️ nennt Zahl und Namen des fehlenden Feldes', strpos($line, '⚠️ Adapter antwortet') === 0 && strpos($line, '1 von 6') !== false && strpos($line, 'Warmwasser Sollwert') !== false, $line);
$fake->fail = [];

$fake->unreachable = true;
$s2->Update();
[$line] = statusOf($s2);
check('Nicht erreichbar: ⚠️ mit Prüfhinweis und letzten bekannten Werten', strpos($line, '⚠️ BSB-LAN-Adapter antwortet nicht') === 0 && strpos($line, 'Letzte bekannte Werte') !== false, $line);
$fake->unreachable = false;

$s2->Update();
$rc = new ReflectionMethod(WPBsbLan::class, 'WriteAttributeInteger');
$rc->setAccessible(true);
$rc->invoke($s2, 'LastCycleAt', time() - 1000);
[$line] = statusOf($s2);
check('Veraltet: ⚠️ meldet eine viel zu alte letzte Aktualisierung', strpos($line, '⚠️ Die letzte Aktualisierung liegt') === 0, $line);

// ---------------------------------------------------------------------------
echo "Block 6: Vollstaendigkeit der Methodenaufrufe\n";
// ---------------------------------------------------------------------------

foreach ([
    ['libs/WPBSBL_BsbLanClient.php', WPBSBL_BsbLanClient::class],
    ['WPBsbLan/module.php', WPBsbLan::class],
] as [$file, $class]) {
    $src = file_get_contents(__DIR__ . '/../' . $file);
    preg_match_all('/\$this->([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $src, $m);
    $missing = [];
    foreach (array_unique($m[1]) as $method) {
        if (!method_exists($class, $method)) {
            $missing[] = $method;
        }
    }
    check("Alle \$this->…()-Aufrufe in $file definiert", count($missing) === 0, 'fehlt: ' . implode(', ', $missing));
}

// ---------------------------------------------------------------------------
echo "\n";
if ($failures === 0) {
    echo "Alle Pruefungen bestanden.\n";
    exit(0);
}
echo "$failures Pruefung(en) FEHLGESCHLAGEN.\n";
exit(1);
