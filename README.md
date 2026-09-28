# TLDers Price Monitor for WHMCS

This addon compares your domain prices with the cheapest registrar on the market for every
TLD you sell. Market data comes from [TLDers](https://www.tlders.com) (145+ registrars,
refreshed daily).

- Your register and renew price next to the market's cheapest, with the registrar's name
- Flags TLDs priced more than X% above market, and TLDs where you're below the cheapest registrar
- Converts USD prices using your WHMCS currency rates (INR, EUR, GBP …)

## Install

1. Download [tlders-whmcs-1.0.0.zip](https://github.com/TLDers/tlders-whmcs/releases/download/v1.0.0/tlders-whmcs-1.0.0.zip)
   and upload `modules/addons/tlders/` into your WHMCS root, so you get
   `<whmcs>/modules/addons/tlders/tlders.php`.
2. **Setup → Addon Modules → TLDers Price Monitor → Activate**.
3. **Configure**: paste your TLDers API key (from [tlders.com/developers](https://www.tlders.com/developers))
   and give your admin role access.
4. Open **Addons → TLDers Price Monitor**.

Requires WHMCS 8+, PHP 7.4+ and cURL.

## Packaging for the WHMCS Marketplace

The zip is attached to each [release](https://github.com/TLDers/tlders-whmcs/releases).

```sh
zip -r tlders-whmcs-1.0.0.zip modules README.md
```
