# Conversion attribution

Credits completed orders to the traffic sources their visitors came from.

## Language

**Attribution**:
What a completed order is credited to, recorded shortly after checkout: the newest non-direct page view tracked for the order's or its customer's client ids in the lookback window before checkout or, if there is none, the newest direct one. If no page view was tracked in the window, the order is credited to nothing.
_Avoid_: Credit, last non-direct click, conversion

**Click id**:
A query parameter on a landing page's URL that names the site the visitor came from: an ad platform's click identifier (`gclid`, `fbclid`, `msclkid`, …) or the generic `source` and `ref` parameters. Which parameters count, and the source and medium each one implies, is configurable.
_Avoid_: Tracking parameter, source parameter

**Client id**:
The id of a browser, kept in its `setono_client_id` cookie, which every page view tracked from that browser carries. An order gets the client id of the browser it was checked out from. If the shop links browsers to customers, the customer also collects the client ids of all the browsers they checked out from or logged in to the shop on, so a returning customer's page views on another of those browsers are credited too. Otherwise only the order's client id counts.
_Avoid_: Visitor id, device id, session

**Direct**:
The source of a tracked page view that came with no click id, no UTM parameter and no referrer from another site.
_Avoid_: None, organic

**(not set)**:
The source of a tracked page view whose UTM parameters give a medium or campaign but no source, and that no click id or referrer names a source for either. Unlike direct, it counts as a non-direct page view.
_Avoid_: Unknown, empty source

**Lookback window**:
How long before checkout a tracked page view can still be credited for an order (30 days by default). Tracked page views are kept that long and no longer.
_Avoid_: Attribution window, retention period

**Page view**:
A page of the shop being shown to a visitor, as reported by the tracking script: the page's URL and its referrer. Only the first page view of a session is tracked, and a blank referrer or query parameter counts as missing.
_Avoid_: Request, hit, visit

**Referrer database**:
The list of known sites (search engines, social networks, email providers, …) and the source and medium a referral from each counts as, taken from Snowplow's referer-parser project, with the shop's configurable overrides on top. A site missing from it counts as a referral from its host.
_Avoid_: Referrers cache, referer list

**Source**:
What a tracked page view is credited to: the site, platform or campaign sender the visitor came from (e.g. `google`, `facebook`, `newsletter`), with an optional medium (`cpc`, `social`, `email`, …) and campaign. Sources and mediums are lowercase, so one site has one name however it was recognised; campaigns keep the merchant's spelling.
_Avoid_: Channel, origin, traffic source

**UTM parameters**:
The `utm_source`, `utm_medium` and `utm_campaign` query parameters a merchant puts on their own links. Each one that is set wins over what a click id or the referrer says about the same field. If `utm_source` names a different source, nothing else from the click id or referrer is kept.
_Avoid_: UTM tags, campaign parameters
