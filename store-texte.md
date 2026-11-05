# Store-Texte WPBsbLan 0.3.0 (Entwurf, nicht veröffentlicht)

Kanal: Beta zuerst (Stable nur nach Dietmars Entscheidung; Zweisprachigkeit zählt laut SUITE.md als relevant für alle). Dokumentation: de `https://github.com/DG65/NRGWPBsbLan/blob/main/README.md`, en `.../README.en.md`. Name: „NRG-Stack WPBsbLan“ (beide Sprachen).

## Beschreibung (de)

WPBsbLan liest eine Heizung oder Wärmepumpe über einen lokalen BSB-LAN-Adapter (ESP32, github.com/fredlcore/bsb_lan) aus – ganz ohne Internet und ohne Herstellerkonto. BSB-LAN übersetzt den Siemens-Heizungsbus (BSB/LPB/PPS) vieler Hersteller (Atlantic, Brötje, Elco, Fujitsu Waterstage u. a. mit Siemens-RVS-/LMU-Reglern) in eine lokale HTTP/JSON-Schnittstelle.

Ausgelesen werden Außentemperatur, Vorlauf-/Rücklauftemperatur, Warmwasser Ist/Soll und die Betriebsart von Heizkreis 1 und Kühlkreis 1 (Klartext liefert BSB-LAN direkt mit). Bewusst nur lesend, keine Steuerbefehle.

Anders als bei Modbus-Anbindungen gibt es keine feste Registerliste je Hersteller – BSB-LAN-Parameternummern sind je Anlage individuell. Die benötigten Nummern trägst du selbst im Formular ein, vorbelegt mit an einer echten Fujitsu Waterstage (Siemens RVS21.831/127) bestätigten Werten als Startpunkt.

Das Logging im Archiv schaltet das Modul nie von selbst ein, dafür gibt es einen Knopf im Formular. Die Oberfläche ist zweisprachig (Deutsch/Englisch). Liefert den NRG-Stack-Wärmepumpenvertrag (Type=heatpump) – Verbund-Module wie das Dashboard erkennen die Werte automatisch.

## Description (en)

WPBsbLan reads a heating system or heat pump via a local BSB-LAN adapter (ESP32, github.com/fredlcore/bsb_lan) – no internet and no manufacturer account needed. BSB-LAN translates the Siemens heating bus (BSB/LPB/PPS) of many manufacturers (Atlantic, Brötje, Elco, Fujitsu Waterstage and others with Siemens RVS/LMU controllers) into a local HTTP/JSON interface.

It reads outdoor temperature, flow/return temperature, hot water actual/target and the operating mode of heating circuit 1 and cooling circuit 1 (BSB-LAN delivers the plain-text name itself). Read-only by design, no control commands.

Unlike Modbus integrations there is no fixed register list per manufacturer – BSB-LAN parameter numbers are individual for each installation. You enter the numbers needed yourself in the form, pre-filled with values confirmed on a real Fujitsu Waterstage (Siemens RVS21.831/127) as a starting point.

The module never switches on archive logging by itself, there is a button in the form for that. The interface is bilingual (German/English). It provides the NRG-Stack heat pump contract (Type=heatpump) – family modules such as the Dashboard recognize the values automatically.

## Versionsinformation (de)

- Neu: Das Modul ist jetzt zweisprachig (Deutsch/Englisch). Formular, Statusmeldungen, Hinweise und die Namen neuer Variablen folgen der Symcon-Sprache.
- Neu: Englische Dokumentation (README.en.md).
- Hinweis: Idents, Einstellungen und Funktionen sind unverändert, bestehende Instanzen laufen weiter.

## Release notes (en)

- New: The module is now bilingual (German/English). Form, status messages, notes and the names of new variables follow the Symcon language.
- New: English documentation (README.en.md).
- Note: Idents, settings and functions are unchanged, existing instances keep running.
