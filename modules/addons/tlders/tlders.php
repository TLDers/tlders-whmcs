<?php
/**
 * TLDers Price Monitor — WHMCS addon module.
 *
 * Compares the reseller's own domain prices (Setup → Products → Domain
 * Pricing) with the cheapest registrar price for each TLD from the TLDers
 * API, so they can see where they're overpriced or leaving margin behind.
 *
 * Requires a TLDers API key: https://www.tlders.com/developers
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

const TLDERS_API = 'https://www.tlders.com/api/v1';
const TLDERS_CACHE_TTL = 6 * 3600;

function tlders_config()
{
    return [
        'name' => 'TLDers Price Monitor',
        'description' => 'Compare your domain prices with the cheapest of 145+ registrars, updated daily.',
        'author' => '<a href="https://www.tlders.com" target="_blank">TLDers</a>',
        'language' => 'english',
        'version' => '1.0.0',
        'fields' => [
            'apiKey' => [
                'FriendlyName' => 'TLDers API key',
                'Type' => 'password',
                'Size' => '60',
                'Description' => 'Create one at <a href="https://www.tlders.com/developers" target="_blank">tlders.com/developers</a>',
            ],
            'usdRate' => [
                'FriendlyName' => 'USD → your currency',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '',
                'Description' => 'Leave blank to use the USD rate from Setup → Payments → Currencies.',
            ],
            'threshold' => [
                'FriendlyName' => 'Flag when above market by (%)',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '25',
            ],
        ],
    ];
}

function tlders_activate()
{
    return ['status' => 'success', 'description' => 'Add your TLDers API key under Configure, then open Addons → TLDers Price Monitor.'];
}

function tlders_deactivate()
{
    tlders_clear_cache();
    return ['status' => 'success', 'description' => 'TLDers Price Monitor deactivated.'];
}

function tlders_cache_file()
{
    return sys_get_temp_dir() . '/tlders-prices-' . md5(__DIR__) . '.json';
}

function tlders_clear_cache()
{
    @unlink(tlders_cache_file());
}

/** GET a TLDers endpoint; returns [data, error]. */
function tlders_request($path, $apiKey)
{
    $ch = curl_init(TLDERS_API . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Accept: application/json'],
        CURLOPT_USERAGENT => 'TLDers-WHMCS/1.0',
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return [null, 'Could not reach TLDers: ' . $curlError];
    }
    $json = json_decode($body, true);
    if ($status !== 200) {
        return [null, isset($json['error']) ? $json['error'] : "TLDers returned HTTP $status"];
    }
    return [$json, null];
}

/** tld => ['register' => [price, registrar], 'renew' => [...]] in USD, cached. */
function tlders_market_prices($apiKey, $refresh)
{
    $file = tlders_cache_file();
    if (!$refresh && is_readable($file) && filemtime($file) > time() - TLDERS_CACHE_TTL) {
        $cached = json_decode(file_get_contents($file), true);
        if (is_array($cached)) {
            return [$cached, null];
        }
    }

    $prices = [];
    $offset = 0;
    do {
        list($json, $error) = tlders_request("/tlds?limit=500&offset=$offset", $apiKey);
        if ($error) {
            return [null, $error];
        }
        foreach ($json['data'] as $t) {
            $prices[$t['tld']] = [
                'register' => $t['cheapestRegister'] ? [$t['cheapestRegister']['price'], $t['cheapestRegister']['registrar']['name']] : null,
                'renew' => $t['cheapestRenew'] ? [$t['cheapestRenew']['price'], $t['cheapestRenew']['registrar']['name']] : null,
            ];
        }
        $offset += 500;
    } while ($offset < $json['meta']['total']);

    @file_put_contents($file, json_encode($prices));
    return [$prices, null];
}

/** Multiplier from USD to the WHMCS default currency, or null if unknown. */
function tlders_usd_rate($configured)
{
    if (is_numeric($configured) && $configured > 0) {
        return (float) $configured;
    }
    $default = Capsule::table('tblcurrencies')->where('default', 1)->first();
    if ($default && strtoupper($default->code) === 'USD') {
        return 1.0;
    }
    // WHMCS stores each currency's rate relative to the default currency.
    $usd = Capsule::table('tblcurrencies')->where('code', 'USD')->first();
    return ($usd && $usd->rate > 0) ? 1 / $usd->rate : null;
}

function tlders_output($vars)
{
    $apiKey = trim($vars['apiKey']);
    $threshold = is_numeric($vars['threshold']) ? (float) $vars['threshold'] : 25;
    $link = $vars['modulelink'];

    if ($apiKey === '') {
        echo '<div class="alert alert-warning">Add your TLDers API key under Setup → Addon Modules → TLDers Price Monitor → Configure. '
            . 'Get one at <a href="https://www.tlders.com/developers" target="_blank">tlders.com/developers</a>.</div>';
        return;
    }

    $rate = tlders_usd_rate($vars['usdRate']);
    $default = Capsule::table('tblcurrencies')->where('default', 1)->first();
    if ($rate === null) {
        echo '<div class="alert alert-warning">Your default currency isn\'t USD and no USD currency is set up. '
            . 'Enter the "USD → your currency" rate in the module settings.</div>';
        return;
    }

    list($market, $error) = tlders_market_prices($apiKey, isset($_GET['refresh']));
    if ($error) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
        return;
    }

    // Your 1-year prices in the default currency (msetupfee = 1 year).
    $rows = Capsule::table('tbldomainpricing as d')
        ->leftJoin('tblpricing as reg', function ($j) use ($default) {
            $j->on('reg.relid', '=', 'd.id')->where('reg.type', 'domainregister')->where('reg.currency', $default->id);
        })
        ->leftJoin('tblpricing as ren', function ($j) use ($default) {
            $j->on('ren.relid', '=', 'd.id')->where('ren.type', 'domainrenew')->where('ren.currency', $default->id);
        })
        ->orderBy('d.order')
        ->get(['d.extension', 'reg.msetupfee as register', 'ren.msetupfee as renew']);

    $fmt = function ($v) use ($default) {
        return $default->prefix . number_format($v, 2) . $default->suffix;
    };
    $cell = function ($yours, $marketEntry) use ($rate, $threshold, $fmt) {
        if (!$marketEntry || $yours === null || $yours <= 0) {
            return '<td>' . ($yours > 0 ? $fmt($yours) : '—') . '</td><td>—</td><td></td>';
        }
        $marketPrice = $marketEntry[0] * $rate;
        $diff = ($yours - $marketPrice) / $marketPrice * 100;
        $class = $diff > $threshold ? 'text-danger' : ($diff < 0 ? 'text-success' : '');
        return '<td>' . $fmt($yours) . '</td>'
            . '<td>' . $fmt($marketPrice) . ' <small class="text-muted">' . htmlspecialchars($marketEntry[1]) . '</small></td>'
            . '<td class="' . $class . '"><strong>' . ($diff >= 0 ? '+' : '') . number_format($diff, 0) . '%</strong></td>';
    };

    echo '<p>Your 1-year prices vs the cheapest registrar on the market, in ' . htmlspecialchars($default->code)
        . '. <span class="text-danger">Red</span> = more than ' . $threshold . '% above market; '
        . '<span class="text-success">green</span> = below the cheapest registrar. '
        . '<a href="' . $link . '&refresh=1" class="btn btn-default btn-sm">Refresh prices</a></p>';
    echo '<table class="table table-striped table-condensed"><thead><tr>'
        . '<th>TLD</th><th>Your register</th><th>Market register</th><th>Diff</th>'
        . '<th>Your renew</th><th>Market renew</th><th>Diff</th><th></th></tr></thead><tbody>';

    foreach ($rows as $row) {
        $tld = ltrim(strtolower($row->extension), '.');
        $entry = isset($market[$tld]) ? $market[$tld] : null;
        echo '<tr><td><strong>.' . htmlspecialchars($tld) . '</strong></td>'
            . $cell($row->register !== null ? (float) $row->register : null, $entry ? $entry['register'] : null)
            . $cell($row->renew !== null ? (float) $row->renew : null, $entry ? $entry['renew'] : null)
            . '<td><a href="https://www.tlders.com/' . rawurlencode($tld) . '" target="_blank">All prices ↗</a></td></tr>';
    }

    echo '</tbody></table><p class="text-muted small">Market data from '
        . '<a href="https://www.tlders.com" target="_blank">TLDers</a>, refreshed daily. Cached for 6 hours.</p>';
}
