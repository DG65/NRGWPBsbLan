# WPBsbLan — lokale Anbindung von Heizungen/Wärmepumpen über BSB-LAN (IP-Symcon)

![Symcon](https://img.shields.io/badge/Symcon-PHPModul-blue)
![Modul Version](https://img.shields.io/badge/Modul_Version-0.1.0-blue)
![Symcon Version](https://img.shields.io/badge/Symcon_Version-9.0%2B-blue)
![License](https://img.shields.io/badge/License-PolyForm_Noncommercial_1.0.0-lightgrey)
[![PayPal](https://img.shields.io/badge/PayPal-Me-blue?logo=paypal)](https://paypal.me/DietmarGureth)

## Übersicht

WPBsbLan liest eine Heizung oder Wärmepumpe über einen lokalen **BSB-LAN-Adapter** ([github.com/fredlcore/bsb_lan](https://github.com/fredlcore/bsb_lan)) aus — ganz ohne Internet und ohne Herstellerkonto. BSB-LAN ist eine verbreitete Open-Source-Hardware/-Firmware (ESP32), die den Siemens-Heizungsbus (BSB/LPB/PPS) vieler Hersteller (Atlantic, Brötje, Elco, Fujitsu Waterstage u. a. mit Siemens-RVS-/LMU-Reglern) in eine lokale HTTP/JSON-Schnittstelle übersetzt. Vierter Baustein der Wärmepumpen-Vertikale im NRG-Stack, neben [WPHub](https://github.com/DG65/NRGWPHub) (Herstellerclouds), [HeishaMon](https://github.com/DG65/NRGHeishaMon) (lokal, nur Panasonic) und [WPModbusHub](https://github.com/DG65/NRGWPModbusHub) (lokal, Modbus TCP).

Eine Instanz = eine Heizung/Wärmepumpe. Eine Statuszeile im Formular zeigt live, ob der Adapter antwortet und welche Werte gerade ankommen.

## Besonderheit gegenüber den anderen Wärmepumpen-Modulen

Anders als bei WPModbusHub gibt es hier **keine feste Registerkarte je Hersteller** — BSB-LAN-Parameternummern werden je Anlage individuell erzeugt (bei der ersten bestätigten Anlage von einem BSB-LAN-Maintainer aus den Rohdaten der konkreten Anlage generiert). Die sechs benötigten Parameternummern (Außentemperatur, Vorlauf, Rücklauf, Warmwasser Ist/Soll, Betriebsart) sind deshalb **frei im Formular editierbar** — vorbelegt mit den an einer echten Fujitsu Waterstage (Siemens RVS21.831/127) bestätigten Werten als Startpunkt, nicht als Garantie für jede Anlage.

## Status

Stand 0.1.0 (23.09.2026) — **bewusst nur lesend** (keine Steuerbefehle, obwohl BSB-LAN offiziell auch Schreiben unterstützt). Alle sechs Felder sind an einer echten Fujitsu-Waterstage-Anlage (Siemens RVS21.831/127) live bestätigt (Forum-Threads WPHub, Beiträge #6/#8, 23.09.2026) — bei anderen BSB-LAN-Anlagen können abweichende Parameternummern nötig sein.

Das JSON-Schema (Feldnamen, Datenformat) stammt direkt aus der offiziellen `openapi.yaml`-Spezifikation des BSB-LAN-Projekts, nicht aus einer Forenzusammenfassung.

**Ausgelesen werden:** Außentemperatur, Vorlauf-/Rücklauftemperatur, Warmwasser Ist/Soll und Betriebsart (Klartext liefert BSB-LAN direkt mit) — noch keine Leistungs- oder Energiezähler.

## Verbund

Teil des **NRG-Stack** — dem Energie-Modulverbund von DG65.

## Lizenz

PolyForm Noncommercial 1.0.0 — siehe [LICENSE](LICENSE). Privat frei, gewerblich lizenzpflichtig.
