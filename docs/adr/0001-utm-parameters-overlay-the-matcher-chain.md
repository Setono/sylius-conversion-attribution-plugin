# UTM parameters overlay the matcher chain

The source matchers (click ids, the referrer and any a host app tags) stay a first-match-wins chain, but the UTM parameters aren't one of them: they're applied on top of whatever the chain matched, so a UTM parameter that is set always wins over the same field from a click id or the referrer, and a `utm_source` naming a different source drops the chain's match entirely. UTM parameters are the one input the merchant sets on purpose, while a click id or referrer is inferred (Google Ads adds `gclid` to every ad click, Facebook adds `fbclid` to links in general), and auto-tagged links often carry only some UTM parameters (e.g. just `utm_campaign`), so they need the click id or referrer to fill in the rest. When the UTM parameters give a medium or campaign but nothing names a source, the source is `(not set)`.

## Considered options

- **Swap the priorities** (UTM above click ids, first match still wins whole): simplest, but `?gclid=…&utm_campaign=…` still loses the campaign or the medium, and UTM parameters without `utm_source` stay ignored.
- **Let every matcher return partial results and merge them field by field in priority order**: more general, but it changes the contract of every matcher, including host apps' own, and mixes fields from unrelated sources (e.g. a `utm_source=newsletter` link shared on Facebook would get Facebook's medium).

## Consequences

- No tag priority can put a custom matcher above or below the UTM parameters.
- A `(not set)` placeholder is safe only because the chain supplies a source first. As a matcher in the chain, it would have beaten a known referrer.
