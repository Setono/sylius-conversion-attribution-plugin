# API checkouts and headless shops

The plugin attributes orders completed through the shop's checkout only. Orders completed through Sylius' API, e.g. from a headless frontend, get no client id and aren't attributed.

## Why this is out of scope

Both halves of the plugin are built around the pages Sylius renders itself:

- **Tracking:** the tracking script is injected into the HTML responses Sylius renders, and posts to `/track` on the same site, which has no CORS. A headless frontend on its own origin has neither, so no page views are tracked for its visitors.
- **Checkout:** the client id is stamped on the order on `sylius.order.pre_complete`, which only the shop's checkout route dispatches. The API's `CompleteOrderHandler` applies the `sylius_order_checkout` `complete` transition directly and dispatches an `OrderCompleted` message instead.

Stamping a client id on API orders alone would only help an API checkout running on the same site as Sylius-rendered pages, which is a narrow case. Doing it properly means hooking the checkout state machine for both adapters Sylius 1.x supports (winzou, the default, and Symfony Workflow from 1.13). It also means tracking page views from a foreign frontend (shipping the script to it, CORS on `/track`, and the client id cookie crossing origins), and that frontend's cookie and consent setup is outside the plugin's control.

## Prior requests

- #23: "Orders completed through Sylius' API never get a client id"
