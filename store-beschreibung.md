WPBsbLan liest eine Heizung oder Wärmepumpe über einen lokalen BSB-LAN-Adapter (ESP32, github.com/fredlcore/bsb_lan) aus – ganz ohne Internet und ohne Herstellerkonto. BSB-LAN übersetzt den Siemens-Heizungsbus (BSB/LPB/PPS) vieler Hersteller (Atlantic, Brötje, Elco, Fujitsu Waterstage u. a. mit Siemens-RVS-/LMU-Reglern) in eine lokale HTTP/JSON-Schnittstelle.

Ausgelesen werden Außentemperatur, Vorlauf-/Rücklauftemperatur, Warmwasser Ist/Soll und die aktuelle Betriebsart (Klartext liefert BSB-LAN direkt mit). Bewusst nur lesend, keine Steuerbefehle.

Anders als bei Modbus-Anbindungen gibt es keine feste Registerliste je Hersteller – BSB-LAN-Parameternummern sind je Anlage individuell. Die sechs benötigten Nummern trägst du selbst im Formular ein, vorbelegt mit an einer echten Fujitsu Waterstage (Siemens RVS21.831/127) bestätigten Werten als Startpunkt.

Liefert den NRG-Stack-Wärmepumpenvertrag (Type=heatpump) – Verbund-Module wie das Dashboard erkennen die Werte automatisch.
