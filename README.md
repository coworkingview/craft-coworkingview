# CoworkingView for Craft CMS

Flexible office and coworking prices by city, from the [CoworkingView Price Index](https://coworkingview.com/en/tools/price-index), inside Craft CMS.

`craft.coworkingview.rate('berlin')` returns the median flex desk, dedicated desk, private office, day pass and meeting room price for a city. `craft.coworkingview.cities()` lists every city the index covers. The figures come from the same public API as CoworkingView’s [open data clients](https://github.com/coworkingview/flex-office-data). There is no API key. Responses are stored in Craft’s cache.

Maintained by [CoworkingView](https://coworkingview.com).

## Features

- Twig variable for one city (`rate`) and for the whole index (`cities`).
- Median, 25th and 75th percentile, sample size and a low-confidence flag when fewer than 10 prices sit behind a figure.
- Settings page: cache duration, and the currency prices are displayed in (local, EUR, GBP, USD, CHF or AED).
- Dashboard widget and a Utilities page to look up a city without writing a template.
- Every result carries the Price Index attribution. The index is [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).

Figures are medians of prices operators publish. They are not a quote and not a live booking price.

## Requirements

- Craft CMS 4.5.0 or later, or Craft CMS 5
- PHP 8.0.2 or later (PHP 8.2 or later for Craft 5)
- Outbound HTTPS to `api.coworkingview.com`

## Installation

### Plugin Store

In the control panel, open **Settings → Plugin Store**, search for **CoworkingView**, and install it.

The package on the [Craft Plugin Store](https://plugins.craftcms.com) is `coworkingview/craft-coworkingview`.

### Composer

```bash
composer require coworkingview/craft-coworkingview
php craft plugin/install coworkingview
```

Then open **Settings → Plugins → CoworkingView** and set the cache duration. One hour is a sound default. The public API allows 120 requests a minute per IP and already marks responses as cacheable for several minutes.

## Usage

```twig
{% set berlin = craft.coworkingview.rate('berlin') %}

{% if berlin %}
    <h2>{{ berlin.cityName }}</h2>
    <ul>
        {% for row in berlin.rows %}
            <li>
                {{ row.product }}:
                {{ craft.coworkingview.format(row.median, berlin.currency) }}
                per {{ row.unit }}
                (n={{ row.sample_size }}{% if row.low_confidence %}, low confidence{% endif %})
            </li>
        {% endfor %}
    </ul>
    <p>{{ berlin.attribution }}</p>
    <p><a href="https://coworkingview.com/en/coworking/{{ berlin.citySlug }}">Offices in {{ berlin.cityName }}</a></p>
{% endif %}
```

The same template is in [`examples/prices.twig`](examples/prices.twig).

`rate()` accepts a slug (`abu-dhabi`) or a name with spaces (`Abu Dhabi`). The optional second argument overrides the display currency for that call:

```twig
{% set madrid = craft.coworkingview.rate('madrid', 'USD') %}
{{ madrid.products.flex_desk.median }}
```

`products` is keyed by product slug. Monthly products are `flex_desk` (hot desk), `fix_desk` (dedicated desk) and `private_office`. `day_pass` is per day. `meeting_room` is per hour. A private office median is per desk per month.

Each row has `product`, `unit`, `median`, `p25`, `p75`, `sample_size`, `property_count`, `operator_count`, `low_confidence` and `as_of`. `p25` and `p75` are empty when the index does not publish them.

### Every city

```twig
{% set cities = craft.coworkingview.cities() %}

<ul>
    {% for city in cities %}
        {% if city.published %}
            <li>
                {{ city.city_name }} —
                from {{ craft.coworkingview.format(city.entry_median, city.currency) }}
                ({{ city.property_count }} spaces)
            </li>
        {% endif %}
    {% endfor %}
</ul>
<p>{{ cities.attribution }}</p>
```

`entry_median` is the median of the cheapest monthly option at each space. Cities below the publication threshold are in the list with `published: false` and no prices. Limit the list with a country code: `craft.coworkingview.cities('DE')`.

`rate()` is `null` when the city has no published index or the API cannot be reached. `cities()` is then an empty list. `craft.coworkingview.rate` does not throw into the template.

Print `attribution` (and link `license`) wherever a figure is shown. Hiding it breaks the CC BY 4.0 terms of the Price Index.

## Settings

**Settings → Plugins → CoworkingView.**

| Setting | What it does |
| --- | --- |
| Cache duration | Seconds a response stays in Craft’s data cache. `0` turns caching off. Default `3600`. |
| Currency display | `Local currency`, or convert to EUR, GBP, USD, CHF or AED. |

Settings are stored in Project Config.

## Control panel

- **Dashboard → New widget → CoworkingView prices.** Set the city slug. The widget lists each product’s median, the interquartile range, and the sample size.
- **Utilities → CoworkingView prices.** Look up any city the same way.

Both screens link through to the city on [coworkingview.com](https://coworkingview.com).

## Data

The plugin calls `GET https://api.coworkingview.com/v1/market/rates`, the endpoint documented in [flex-office-data](https://github.com/coworkingview/flex-office-data). No token is sent. Set `COWORKINGVIEW_API_URL` only if you need to point the plugin at another host.

City names follow the current site language when it is English, German or Spanish, and English otherwise.

## Support

Questions and bugs: [info@coworkingview.com](mailto:info@coworkingview.com) or the [issue tracker](https://github.com/coworkingview/craft-coworkingview/issues).

A wrong price should be reported on that space’s page at [coworkingview.com](https://coworkingview.com) so it is corrected at the source.
