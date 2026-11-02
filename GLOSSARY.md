# Conversion attribution

Credits completed orders to the traffic sources their visitors came from.

## Language

**Page view**:
A page of the shop being shown to a visitor, as reported by the tracking script: the page's URL and its referrer. Only the first page view of a session is tracked, and a blank referrer or query parameter counts as missing.
_Avoid_: Request, hit, visit

**Referrer database**:
The list of known sites (search engines, social networks, email providers, …) and the source and medium a referral from each counts as, taken from Snowplow's referer-parser project. A site missing from it counts as a referral from its host.
_Avoid_: Referrers cache, referer list
