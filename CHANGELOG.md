# Changelog — NRG-Stack WPBsbLan

## 0.1.1 (Build 2) — 23.09.2026

- **Erste Live-Bestätigung: Modul installiert und an einer echten Anlage geprüft.** Lütfü (Fujitsu Waterstage WSYK160DG9) hat WPBsbLan installiert: „Die Werte werden alle korrekt angezeigt und sind identisch mit den Werten auf dem Display meiner Anlage.“
- **„Betriebsart“ heißt jetzt „Betriebsart Heizkreis 1“.** Lütfüs weitere Tests zeigten: Parameter 700 ist speziell der Heizkreis-1-Status, es gibt daneben eigene Parameter für Heizkreis 2 (1200) und Kühlkreis 2 (1201), die sich unabhängig voneinander schalten lassen. Nur die Beschriftung wurde präzisiert, keine Verhaltensänderung.

## 0.1.0 (Build 1) — 23.09.2026

- **Erste Version.** Liest eine Heizung/Wärmepumpe über einen lokalen BSB-LAN-Adapter (github.com/fredlcore/bsb_lan) per HTTP/JSON aus: Außentemperatur, Vorlauf-/Rücklauftemperatur, Warmwasser Ist/Soll und Betriebsart. Alle sechs Parameternummern sind frei im Formular einstellbar (0 = Feld nicht vorhanden), vorbelegt mit den an einer echten Fujitsu Waterstage (Siemens RVS21.831/127) bestätigten Werten. Entstanden aus einer Forumsanfrage (ArMu/Lütfü, WPHub-Thread, 23.09.2026), alle sechs Werte an seiner Anlage live bestätigt.
- Statuszeile im Formular von Anfang an dabei (SUITE.md "Verbund-Verbindungen im Formular sichtbar machen"), "Was ist Neu"-Panel nach der neuen `NEWS_VERSIONS`-Konvention (SUITE.md, 23.09.2026).
- Bewusst nur lesend (keine Steuerbefehle), keine Leistungs-/Energiezähler.
- Prüfstand: `php .tools/test-module.php`, 58 Prüfungen, neun Mutationen der Zielstellen geprüft.
