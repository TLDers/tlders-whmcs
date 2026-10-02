# TLDers Price Monitor for WHMCS

This addon compares your domain prices with the cheapest registrar on the market for every
TLD you sell. Market data comes from [TLDers](https://www.tlders.com) (145+ registrars,
refreshed daily).

- Your register and renew price next to the market's cheapest, with the registrar's name
- Flags TLDs priced more than X% above market, and TLDs where you're below the cheapest registrar
- Converts USD prices using your WHMCS currency rates (INR, EUR, GBP …)

## Install

1. Download [tlders-whmcs-1.0.1.zip](https://github.com/TLDers/tlders-whmcs/releases/download/v1.0.1/tlders-whmcs-1.0.1.zip)
   and upload `modules/addons/tlders/` into your WHMCS root, so you get
   `<whmcs>/modules/addons/tlders/tlders.php`.
2. **Setup → Addon Modules → TLDers Price Monitor → Activate**.
3. **Configure**: paste your TLDers API key (from [tlders.com/developers](https://www.tlders.com/developers))
   and give your admin role access.
4. Open **Addons → TLDers Price Monitor**.

Requires WHMCS 8+, PHP 7.4+ and cURL.

## Packaging for the WHMCS Marketplace

This folder is published as https://github.com/TLDers/tlders-whmcs, with the zip attached to each release.

```sh
cd whmcs-module && zip -r tlders-whmcs-1.0.1.zip modules README.md
```

## Changelog

### 1.0.1
- Market prices are cached for 24 hours instead of 6. TLDers updates prices once a day, so
  nothing is lost, and a free API key (100 requests a month) now lasts the whole month.

### 1.0.0
- First release.
