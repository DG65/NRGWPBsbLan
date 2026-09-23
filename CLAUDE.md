# WPBsbLan — Übergabe-Kontext für die neue Sitzung

Angelegt am 23.09.2026, vierter Baustein der Wärmepumpen-Vertikale (neben WPHub,
HeishaMon, WPModbusHub/WPModbusHubGateway, SamsungEhs). Primärquelle für alle
Verbund-Konventionen ist die lokale SUITE.md (`/Users/dietmar/Nextcloud/Claude/SUITE.md`)
— bei Zweifeln dort zuerst grep'en.

## Entstehung (WPHub-Forumsthread, 23.09.2026)

Forumsnutzer "ArMu"/Lütfü hat im WPHub-Thread (Beitrag #4) nach einer Fujitsu Waterstage
WSYK160DG9 mit Siemens-Regler RVS21.831/127 gefragt, bereits lokal über einen BSB-LAN-
Adapter (ESP32) in Symcon eingebunden. **Einordnung, bevor irgendetwas gebaut wurde:**
WPHub ist eine reine Cloud-Anbindung (Panasonic/Vaillant, Auth0-Token) -- BSB-LAN ist
lokal, ohne Cloud, architektonisch naeher an WPModbusHub/SamsungEhs. Forumsantwort hat
das offen erklaert und gezielt nach Details gefragt (Muster Ghostraider/Christian: erst
Daten, dann Registerkarte), nicht direkt gebaut.

**Rechercheweg (Dietmars Nachfrage "Hast Du den Link auch angeguckt?", 23.09.2026):**
Erst NACH einer eigenen Websuche in der offiziellen BSB-LAN-Doku (docs.bsb-lan.de) und
dem Projekt-eigenen `openapi.yaml` (github.com/fredlcore/bsb_lan, API-Version 2.4)
wurde klar: Lütfüs erstes Forenbeispiel (deutsche, grossgeschriebene Feldnamen,
Dezimalkomma) war KEINE echte API-Antwort, sondern vermutlich aus der Weboberflaeche
abgetippt -- seine beiden anderen Beispiele (englische, kleingeschriebene Feldnamen,
Dezimalpunkt) entsprechen exakt dem offiziellen, versionierten Schema. Dietmars Hinweis
"Lütfü ist vermutlich kein Programmierer" fuehrte dazu, ihn NICHT nach dem Schema-
Unterschied zu fragen (selbst geklaert), sondern nur noch nach simplen, ohne
Technikwissen beantwortbaren Dingen (Parameternummern im Webinterface ablesen bzw.
vorgefertigte URLs aufrufen und das Ergebnis zurueckmelden).

**Zweite Recherche (Dietmars Hinweis "gibt es ein Forum fuer Waermepumpen und
Heizungen"):** Websuche fand einen Blog eines ANDEREN Fujitsu-Waterstage/RVS21-
Besitzers (mattstech.info/posts/fujitsu-bsblan-monitoring/) mit einer vollstaendigen
Parameterliste fuer genau diese Baureihe. Starke Gegenprobe: seine Vorlauf-/Ruecklauf-
Parameter (8412/8410) stimmten EXAKT mit Lütfüs eigenen, bereits bestaetigten Werten
ueberein. Daraus abgeleitete Kandidaten fuer Warmwasser Ist/Soll (8830/8831) und
Betriebsart (700) wurden NICHT ungeprueft uebernommen, sondern Lütfü zur einfachen
Bestaetigung vorgelegt (drei fertige URLs zum Anklicken).

**Bestaetigung (Forum-Post #8, 23.09.2026):** "Die Werte sind identisch mit den Werten,
die die Anlage auf dem Display anzeigt." Alle sechs Felder damit an echter Hardware
verifiziert -- Tabelle siehe unten.

## Dietmars Bau-Entscheidung

Dietmar hat explizit "Ja, bauen" gewaehlt (AskUserQuestion, 23.09.2026), NACHDEM alle
sechs Werte bestaetigt waren -- analog zum Proxon-Muster (WPModbusHub-CLAUDE.md): erst
Daten, dann Dietmars Freigabe, dann Bau. Kein Vorgriff auf eine Entscheidung, die nur er
treffen kann.

## Architektur -- bewusst ANDERS als WPModbusHub

**Keine statische Registerkarte je Hersteller** (anders als `WPMBHUB_Drivers`). BSB-LAN-
Parameternummern sind NICHT firmenweit einheitlich -- sie werden je Anlage individuell
erzeugt (bei Lütfü von einem BSB-LAN-Maintainer aus den Rohdaten SEINER Anlage generiert,
~6000 Parameter fuer seinen Regler). Deshalb: sechs frei editierbare NumberSpinner-Felder
im Formular (`ParamAussentemperatur` usw.), vorbelegt mit Lütfüs bestaetigten Werten als
Startpunkt, 0 = Feld nicht vorhanden/nicht genutzt (`configuredFields()` filtert das raus,
weder Abfrage noch Variable). Ob dieselben Nummern auf ANDERE RVS21-Anlagen (andere
Konfiguration/Firmware) uebertragbar sind, ist NICHT gesagt -- nur fuer Lütfüs eigene
Anlage bestaetigt.

**JSON-Schema direkt aus der offiziellen `openapi.yaml`** (nicht aus einer Forenzusammen-
fassung): `Parameter`-Typ mit `name`/`dataType_family`/`dataType_name`/`destination`/
`error`/`value`/`desc`/`payload`/`precision`/`dataType`/`readwrite`/`unit`, englische
kleingeschriebene Feldnamen, Dezimalpunkt -- stabil und versioniert (`/JV`), unabhaengig
vom angeschlossenen Heizungsmodell. `WPBSBL_BsbLanClient::parseResponseBody()` ist die
reine, ausgelagerte Dekodierfunktion (Pruefstand testet sie direkt mit vorgefertigten
JSON-Strings, ohne curl/Netz).

**Ein HTTP-Request je Lesezyklus, alle konfigurierten Parameter gebuendelt**
(`GET /JQ=<id1>,<id2>,...`, von BSB-LAN offiziell fuer genau diesen Zweck vorgesehen) --
analog zum "eine Verbindung je Zyklus"-Muster der Modbus-Module, nur mit HTTP statt TCP-
Rohsocket.

**Betriebsart braucht KEINE lokale Enum-Tabelle:** BSB-LAN liefert den Klartext direkt im
`desc`-Feld mit (z. B. "Schutzbetrieb" bei Code 0) -- anders als bei den Modbus-
Herstellern, wo Enum-Bedeutungen aus der Herstellerdoku uebernommen werden muessten.
Zwei Variablen: `Betriebsart` (INTEGER, roher Code) + `BetriebsartText` (STRING, `desc`).
`operatingModeNormID` (verbundweit normalisierter Zustand: standby/heating/cooling/dhw)
ist NOCH NICHT befuellt -- dafuer muesste jeder moegliche Betriebsart-Code auf diese
Kategorien abgebildet werden, bislang ist nur EIN Wert ("Schutzbetrieb") live gesehen.

**Testseam ohne Subclass-Notwendigkeit:** `bsbLanClient()` prueft zuerst
`$GLOBALS['ips']['bsbClientFactory']` (Callable) und liefert dann eine vom Pruefstand
gesetzte Attrappe statt eines echten curl-Clients -- anders als bei WPModbusHubGateway
(Closure durch den Konstruktor), weil dieser Client keinen Modul-Kontext braucht.

## Bestaetigte Werte (Fujitsu Waterstage WSYK160DG9, Siemens RVS21.831F/127)

| Ident | Parameter | Bestaetigter Wert (23.09.2026) |
|---|---|---|
| Aussentemperatur | 8700 | 11,9 °C |
| Vorlauftemperatur | 8412 | 23,8 °C |
| Ruecklauftemperatur | 8410 | 33,4 °C |
| Warmwasser | 8830 ("Trinkwassertemperatur-Istwert Oben (B3)") | 52,5 °C |
| WarmwasserSoll | 8831 ("Trinkwassertemperatur-Sollwert aktuell") | 55,0 °C |
| Betriebsart | 700 (ENUM) | 0 = "Schutzbetrieb" |

## Schreibtest durch Lütfü (Forum-Post #9, 23.09.2026, unaufgefordert)

Lütfü hat selbst einen Schreibtest ueber BSB-LAN gemacht: Parameter **1200
("Betriebsartumschaltung")** liess sich erfolgreich von "Reduziert" auf "Komfort"
aendern und danach korrekt zuruecklesen. **Anderer Parameter als 700** (die
Statuszeile "Betriebsart", nur lesend) -- BSB-LAN/Siemens trennen hier offenbar
Auswahl-Parameter (schreibbar) von Status-Parameter (nur lesend), Muster aus der
openapi.yaml (`/JS`, `/JB`) bestaetigt sich damit erstmals an echter Hardware.
**Bewusst NICHT in v1 aufgenommen** -- Schreiben waere eine groessere
Architekturentscheidung (Steuerhoheit, Sicherheitsimplikationen bei einer Heizung),
die wie der Modulbau selbst erst Dietmars ausdrueckliche Freigabe braucht, nicht aus
einem einzelnen Testerkommentar abgeleitet wird. Dokumentiert fuer eine spaetere
Erweiterung, falls gewuenscht.

## Was bewusst NICHT Teil von v1 ist

- **Keine Steuerbefehle** (nur lesend) -- obwohl BSB-LAN offiziell auch `/JS` (Schreiben)
  und `/JB` (Liste schreibbarer Parameter) unterstuetzt UND Lütfü das an seiner eigenen
  Anlage bereits erfolgreich getestet hat (siehe Abschnitt oben). Analog zur v1-Linie
  aller anderen WP-Module.
- **Keine Leistungs-/Energiezaehler** -- `PowerID`/`EnergyID` bleiben 0.
- **Kein `operatingModeNormID`** (siehe oben) -- braucht mehr live gesehene Betriebsart-
  Codes, bevor eine Abbildung auf standby/heating/cooling/dhw vertretbar ist.
- **Kein MQTT-Transport**, obwohl BSB-LAN das offiziell empfiehlt (Topic-Schema
  `<Topic>/<Geraete-ID>/<Kategorie>/<Parameter>`) -- HTTP/JSON-Polling passt besser zum
  Zyklus-Poll-Muster der anderen WP-Module und braucht keinen zusaetzlichen MQTT-Broker
  als Voraussetzung. Moeglicher spaeterer Ausbau, kein v1-Blocker.
- **Kein Ausnutzen der selbstbeschreibenden Metadaten** (`/JK`/`/JC` liefern Name/Einheit/
  moegliche Werte direkt vom Geraet) -- koennte die Formular-Pflege perspektivisch
  vereinfachen (Namen/Einheiten vom Geraet statt hart im Code), noch nicht gebaut.
- **Noch kein Forumsthread** -- `ForumHint()` liefert bewusst `null`, bis Dietmar das
  Modul angekuendigt hat (Muster: alle anderen WP-Module hatten das Panel auch erst nach
  dem Store-Announcement).

## Branch-Modell

`ems-integration` ist der aktive Entwicklungsbranch. **Verbundweite Konvention (seit
18.09.2026, Dietmars Entscheidung): beide Branches laufen automatisch gleich** -- jeder
Push nach `ems-integration` geht im selben Zug auch nach `beta`. `main` existiert fuer
dieses Repo noch nicht.

## Verbund-Manifest SUITE.md

Lokal unter `/Users/dietmar/Nextcloud/Claude/SUITE.md`, kein GitHub-Remote (Dietmars
Entscheidung 31.08.2026, siehe andere Modul-CLAUDE.md-Dateien fuer die volle Begruendung).
