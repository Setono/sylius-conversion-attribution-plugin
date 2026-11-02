# Conversion attribution

Credits completed orders to the traffic sources their visitors came from.

## Language

**Attribution**:
The tracked page view an order is credited to, recorded when the order is completed: the newest non-direct page view in the lookback window before checkout or, if there is none, the newest direct one. An order without a tracked page view in the window has no attribution.
_Avoid_: Credit, last non-direct click, conversion

**Direct**:
The source of a tracked page view that came with no click id, no `utm_source` and no referrer from another site.
_Avoid_: None, organic

**Lookback window**:
How long before checkout a tracked page view can still be credited for an order (30 days by default). Tracked page views are kept that long and no longer.
_Avoid_: Attribution window, retention period

**Page view**:
A page of the shop being shown to a visitor, as reported by the tracking script: the page's URL and its referrer. Only the first page view of a session is tracked, and a blank referrer or query parameter counts as missing.
_Avoid_: Request, hit, visit

**Referrer database**:
The list of known sites (search engines, social networks, email providers, …) and the source and medium a referral from each counts as, taken from Snowplow's referer-parser project. A site missing from it counts as a referral from its host.
_Avoid_: Referrers cache, referer list
