# Changelog — NRG-Stack WPBsbLan

## 0.1.0 (Build 1) — 23.09.2026

- **Erste Version.** Liest eine Heizung/Wärmepumpe über einen lokalen BSB-LAN-Adapter (github.com/fredlcore/bsb_lan) per HTTP/JSON aus: Außentemperatur, Vorlauf-/Rücklauftemperatur, Warmwasser Ist/Soll und Betriebsart. Alle sechs Parameternummern sind frei im Formular einstellbar (0 = Feld nicht vorhanden), vorbelegt mit den an einer echten Fujitsu Waterstage (Siemens RVS21.831/127) bestätigten Werten. Entstanden aus einer Forumsanfrage (ArMu/Lütfü, WPHub-Thread, 23.09.2026), alle sechs Werte an seiner Anlage live bestätigt.
- Statuszeile im Formular von Anfang an dabei (SUITE.md "Verbund-Verbindungen im Formular sichtbar machen"), "Was ist Neu"-Panel nach der neuen `NEWS_VERSIONS`-Konvention (SUITE.md, 23.09.2026).
- Bewusst nur lesend (keine Steuerbefehle), keine Leistungs-/Energiezähler.
- Prüfstand: `php .tools/test-module.php`, 58 Prüfungen, neun Mutationen der Zielstellen geprüft.
