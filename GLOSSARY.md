# Conversion attribution

Credits completed orders to the traffic sources their visitors came from.

## Language

**Attribution**:
What a completed order is credited to, recorded shortly after checkout: the newest non-direct page view tracked for the order's or its customer's client ids in the lookback window before checkout or, if there is none, the newest direct one. If no page view was tracked in the window, the order is credited to nothing.
_Avoid_: Credit, last non-direct click, conversion

**Client id**:
The id of a browser, kept in its `setono_client_id` cookie, which every page view tracked from that browser carries. An order gets the client id of the browser it was checked out from, and its customer collects the client ids of all the browsers they checked out from, so a returning customer's page views on another of those browsers are credited too.
_Avoid_: Visitor id, device id, session

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
