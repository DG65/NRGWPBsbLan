🇩🇪 [Deutsche Fassung](README.md)

# WPBsbLan — local connection of heating systems/heat pumps via BSB-LAN (Symcon)

![Symcon](https://img.shields.io/badge/Symcon-PHPModul-blue)
![Module Version](https://img.shields.io/badge/Module_Version-0.3.0-blue)
![Symcon Version](https://img.shields.io/badge/Symcon_Version-9.0%2B-blue)
![License](https://img.shields.io/badge/License-PolyForm_Noncommercial_1.0.0-lightgrey)
[![PayPal](https://img.shields.io/badge/PayPal-Me-blue?logo=paypal)](https://paypal.me/DietmarGureth)

## Overview

WPBsbLan reads a heating system or heat pump via a local **BSB-LAN adapter** ([github.com/fredlcore/bsb_lan](https://github.com/fredlcore/bsb_lan)) — no internet and no manufacturer account needed. BSB-LAN is a widespread open-source hardware/firmware (ESP32) that translates the Siemens heating bus (BSB/LPB/PPS) of many manufacturers (Atlantic, Brötje, Elco, Fujitsu Waterstage and others with Siemens RVS/LMU controllers) into a local HTTP/JSON interface. Fourth building block of the heat pump line in the NRG-Stack, next to [WPHub](https://github.com/DG65/NRGWPHub) (manufacturer clouds), [HeishaMon](https://github.com/DG65/NRGHeishaMon) (local, Panasonic only) and [WPModbusHub](https://github.com/DG65/NRGWPModbusHub) (local, Modbus TCP).

One instance = one heating system/heat pump. A status line in the form shows live whether the adapter answers and which values are arriving.

## What is different from the other heat pump modules

Unlike WPModbusHub there is **no fixed register map per manufacturer** here — BSB-LAN parameter numbers are generated individually for each installation (for the first confirmed system, a BSB-LAN maintainer generated them from the raw data of that very system). The parameter numbers needed (outdoor temperature, flow, return, hot water actual/target, operating mode) are therefore **freely editable in the form** — pre-filled with the values confirmed on a real Fujitsu Waterstage (Siemens RVS21.831/127) as a starting point, not as a guarantee for every system.

## Status

As of 0.3.0 (5 Nov 2026) — **read-only by design** (no control commands, although BSB-LAN officially also supports writing). All fields are confirmed live on a real Fujitsu Waterstage system (Siemens RVS21.831/127) — other BSB-LAN installations may need different parameter numbers.

The JSON schema (field names, data format) comes directly from the official `openapi.yaml` specification of the BSB-LAN project, not from a forum summary.

**Read:** outdoor temperature, flow/return temperature, hot water actual/target, operating mode of heating circuit 1 and cooling circuit 1 (BSB-LAN delivers the plain-text name itself) — no power or energy meters yet.

## Languages

The module is bilingual (German/English): form, status messages, notes and the names of new variables follow the Symcon language. The source texts are English, the German version lives in `libs/locale.json` (`php .tools/locale-bauen.php` copies it into the module folder).

## Part of the NRG-Stack

Part of the **NRG-Stack** — DG65's energy module family.

## License

PolyForm Noncommercial 1.0.0 — see [LICENSE](LICENSE). Free for private use, commercial use requires a license.
