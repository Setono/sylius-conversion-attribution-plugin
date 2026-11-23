# Client ids are validated by client-bundle, not by this plugin

At checkout and at shop logins, the plugin writes the browser's client id, unchecked, to the order and the customer, whose columns hold 255 characters. It can do that because setono/client-bundle only hands out client ids that fit: a cookie whose client id is empty, longer than 255 bytes or not valid UTF-8 counts as no cookie, so the browser gets a new client id and a new cookie. The plugin requires a client-bundle release that guarantees this. Its `/track` and shop login functional tests send a hand-edited cookie, because attribution must never make a checkout fail.

## Considered options

- **A module in this plugin that tracking, the checkout and logins get the client id from, refusing what the columns can't hold** (proposed by the 2026-11-16 architecture review). With the rule in client-bundle, that module would only forward the client context. Worse, a browser with such a cookie would keep it and go untracked, instead of getting a new client id.
- **Rejecting such cookies in setono/client's `Cookie::fromString()`**: client-bundle already owns the length rule, for its own metadata column, so the UTF-8 rule sits next to it.
