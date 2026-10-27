=== Cartlum – Cart Recovery, Fake Order Protection & Automation for WooCommerce ===
Contributors: swadhinai
Tags: abandoned cart, fake order, cash on delivery, order sms, automation
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.35.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Win back abandoned carts by SMS, call and WhatsApp, stop fake orders before they ship, and automate order SMS. Phone-first, Bangla included.

== Description ==

Cartlum is for WooCommerce stores that sell cash on delivery, where customers check out with a phone number and rarely read email. It does three jobs in one plugin:

= 1. Cart recovery =

* **Captures the cart the moment a phone number is typed** at checkout — before "Place order" — on the classic checkout, the block checkout, and CartFlows.
* **Save-cart popup** (optional): asks shoppers with something in the cart for their number when they are about to leave or right after "Add to cart", so carts are saved even before checkout.
* **One-tap cart link** in every message: refills the cart, applies the coupon and opens checkout.
* **SMS** through eSMS, BulkSMSBD, SSL Wireless, Alpha SMS or MiMSMS (Bangladesh), Twilio, or any HTTP SMS API — no code needed.
* **Phone call list** for your team: call with one tap, log how it went.
* **WhatsApp**: opens a chat with the customer's number and a ready-written message.
* **Designed reminder emails**: your logo, the cart's products with photos, and a "Complete your order" button in your colour — or your WooCommerce email style.
* **One-time coupons** per cart, created only when you offer one.
* **Honest numbers**: an order only counts as *recovered* if you contacted the customer first. Customers who came back on their own are shown separately.
* **Most abandoned products** report, and **CSV export** of your carts.
* **Reports**: where each period's orders ended up (delivered, with the courier, not sent yet, cancelled before shipping, returned or refused), delivery success, fake orders stopped, confirmations, and why orders did not arrive.

= 2. Fake customer protection =

* **Orders to confirm**: every new order in one list, oldest first, with the customer's history and one tap to call or WhatsApp. Record the answer in one more tap: confirmed, no answer, call back, cancel with a reason, or fake (and block the number). Confirming never changes the order status, so courier plugins and order SMS work as before.
* **Fake-order protection** (optional): block numbers, IP addresses (and ranges) and devices, repeat-order and daily limits, bot trap, an SMS code for risky orders, and each number's order history for staff.
* **Phone-first checkout** (optional): mobile number first, then name, address and district. Accepts numbers typed in Bangla digits, checks Bangladeshi mobile numbers, and keeps WooCommerce's payments, delivery charges and coupons.
* **Log in with phone number** (optional): customers sign in to My Account with an SMS code and see every order placed with their number.

= 3. Easy automation =

* **Automatic reminder**: one SMS or email after a cart is abandoned, with quiet hours, a daily SMS limit and a test mode that sends to your own number first.
* **Order SMS and tracking** (optional): texts at each step of the order, and a progress bar with the courier's status in My Account.
* **Review requests by SMS** (optional): some days after an order or after delivery, the customer gets a private link to rate each product with stars and a few words, no login. Answers become WooCommerce product reviews marked "verified owner", held for your approval if you like. Or put {review_link} in your "delivered" SMS instead of sending one more message.
* **Webhooks** to Zapier, Make, Pabbly or your own system: cart saved, abandoned and recovered, signed and retried.

= Also included =

* **Buy now button** (optional): straight from the product page to checkout, with the chosen colour or size.
* **Order on WhatsApp and Call to order buttons** (optional) on product pages.
* **Sell through AI assistants** (optional): /llms.txt, a product feed with prices and order links (also in OpenAI's product feed format), delivery and return times in each product's data, order links that open your checkout, and orders from ChatGPT, Gemini, Claude, Grok, Perplexity or Kimi counted in Reports.
* **Order again** (optional): one tap puts an earlier order back in the cart and opens a filled-in checkout, from My Account or from {reorder_link} in an order SMS; returning guests find the checkout filled in from their last order.
* **Option buttons** (optional): colours and sizes as buttons instead of drop-down lists, with unavailable choices crossed out.
* **Bangla included**, and a "Language for customers" setting to show shoppers Bangla while your admin stays in English.
* Unsubscribe link in every message, automatic data deletion, and WordPress privacy export/erase support.

== External services ==

Cartlum sends messages only through services you choose and configure. Nothing is sent until you set one up, and nothing is sent anywhere else.

* **cartlum.com (usage sharing, only if you agree)** — Cartlum asks once, on its Today screen. Only if you press "Yes, share" (or tick Settings → Privacy → Share usage with Cartlum), once a week your site address, store name, admin email, your WordPress, WooCommerce, PHP and Cartlum versions, theme, language, SMS gateway name and which Cartlum settings are switched on are sent to https://cartlum.com/. Never customers, orders, carts, messages or keys. When you deactivate Cartlum, an optional box asks why; only if you press "Send and deactivate" are your answer and versions sent (with your site address, name and email only if you agreed to share). Untick the setting to stop. Privacy: https://cartlum.com/privacy-policy/

* **eSMS (Bangladesh)** — when you choose it as your SMS gateway, the customer's phone number and the message text are sent to https://login.esms.com.bd/ each time an SMS is sent. Terms and privacy: https://esms.com.bd/tos/
* **BulkSMSBD (Bangladesh)** — when you choose it as your SMS gateway, the customer's phone number and the message text are sent to https://bulksmsbd.net/ each time an SMS is sent. BulkSMSBD publishes no terms or privacy pages; your account with them sets the terms: https://bulksmsbd.net/
* **SSL Wireless (Bangladesh)** — when you choose it as your SMS gateway, the customer's phone number and the message text are sent to https://smsplus.sslwireless.com/ each time an SMS is sent. Terms and privacy: as agreed in your SSL Wireless contract, https://www.sslwireless.com/
* **Alpha SMS (Bangladesh)** — when you choose it as your SMS gateway, the customer's phone number and the message text are sent to https://api.sms.net.bd/ each time an SMS is sent. Terms: https://sms.bd/Terms_Condition/
* **MiMSMS (Bangladesh)** — when you choose it as your SMS gateway, the customer's phone number and the message text are sent to https://api.mimsms.com/ each time an SMS is sent. Terms: https://www.mimsms.com/terms-of-service · Privacy: https://www.mimsms.com/privacy-policy
* **Twilio** — when you choose it as your SMS gateway, the customer's phone number and the message text are sent to https://api.twilio.com/ each time an SMS is sent. Terms: https://www.twilio.com/legal/tos · Privacy: https://www.twilio.com/legal/privacy
* **Custom HTTP API** — when you configure it, the phone number and message are sent to the address you enter, under that provider's terms.
* **WhatsApp** — WhatsApp buttons open https://wa.me/ in the person's own browser or app: for staff, with the customer's number and a message filled in; for shoppers (the optional product page button), with the store's number and the product they are looking at. The site itself sends nothing to WhatsApp. Terms: https://www.whatsapp.com/legal/terms-of-service · Privacy: https://www.whatsapp.com/legal/privacy-policy

* **Webhooks** — when you enter a webhook URL, the cart's details (customer name, phone, email, products, total and cart link) are sent to that address for the events you tick, under the terms of the service that address belongs to.

Cart details are stored in your own WordPress database and are deleted automatically after the period you set (90 days by default).

== Installation ==

1. Install and activate WooCommerce.
2. Install Cartlum from Plugins → Add New, or upload the zip, and activate it.
3. Open Cartlum → Today and follow "Set up Cartlum in 5 minutes": choose your SMS provider, send yourself a test SMS, then the checkout, order SMS and buy buttons.
4. Optional: turn on the automatic reminder under Abandoned Carts → Reminders. Set a test phone number first, so you receive the reminders yourself before customers do.

== Frequently Asked Questions ==

= When is a cart saved? =
As soon as the customer types a valid phone number or email on the checkout page. Nothing is saved for shoppers who never reach checkout.

= What counts as "recovered"? =
An order placed after you sent an SMS, WhatsApp message or email, had a call with the customer, or after they opened the cart link from one of your messages. Orders placed without any contact are counted as "Ordered", not recovered.

= How safe is phone login? =
A code is 6 digits, works once, expires after 10 minutes and is thrown away after 5 wrong tries. One number can get a code once a minute and 5 times an hour, one IP address 10 times an hour, and you set a daily maximum. Accounts that can edit the site (administrators, shop managers, editors) can never log in with a code. Guest orders are linked to an account only after its owner has proved the number with a code.

= Does it work with the block checkout? =
Yes. With the phone-first checkout on, a block checkout page shows Cartlum's checkout instead.

= Which SMS providers work? =

eSMS (Bangladesh) and Twilio are built in. Any other provider with an HTTP API can be added under Integrations → SMS → Custom HTTP API, without code.

= Will it slow down or block my checkout? =

No. Saving the cart happens in the background while the customer types, and every optional feature (popup, phone-first checkout, protection) can be turned off. Fake-order protection only asks risky orders for an SMS code.

= Does the phone-first checkout replace CartFlows? =
It can, for stores that only used CartFlows for a shorter checkout. CartFlows' global checkout is set aside for visitors who see Cartlum's checkout; CartFlows funnel pages keep their own checkout.

== Screenshots ==

1. Today: what needs you now (each job with its button), results with trends, money recovered per day, who to contact and what works.
2. A cart: the customer, what they left, one-tap call, WhatsApp, SMS and email, and the full history.
3. Phone-first checkout: mobile number first, then name, address and district.
4. The designed reminder email, with product photos and a button in your colour.
5. The save-cart popup on a phone, right after "Add to cart".
6. Integrations: every connected service with its status.

== Changelog ==

= 0.35.0 =
* Orders → To confirm, faster and cleaner: a line with orders new today, waiting (and their value) and confirmed today; filters for New, No answer, Call back and Risky orders; each order as a card with product photos, the customer's history and earlier tries.
* Answers are saved without reloading the page: the card slides away and the next is ready (the buttons still work without JavaScript).
* Select several orders (or all) and confirm them at once. For developers: the cartlum_confirm_bulk_actions and cartlum_confirm_bulk_run filters.

= 0.34.2 =
* Readme: eSMS's terms and privacy link corrected.

= 0.34.1 =
* Ready for Cartlum Pro 0.18.3: styles for the new Bundles and Upsells editors.

= 0.34.0 =
* Ready for Cartlum Pro 0.18.2: Offers opens on an Overview, one card per kind of offer (order bump, upsells, quantity offers, bundles, recommendations, loyalty points, gift cards) with whether it is on, what is live and what it added to orders, and a button to set it up. For developers: the cartlum_offers_overview filter.

= 0.33.0 =
* Fix: order links (?cartlum_order=ID) now work for AI agents and other tools that keep no cookies. They landed on an empty cart page; the checkout now puts the product in the cart itself. Browsers are not affected, a refresh does not add the product twice, and a quantity changed at checkout is kept.
* New: the product feed in OpenAI's format at ?cartlum_feed=openai (JSON Lines with OpenAI's field names: item_id, price as "950.00 BDT", availability, group_id and variant_dict for colours, images, returns, reviews), linked from llms.txt. Products without a photo are left out, as OpenAI requires one. OpenAI decides which countries it accepts.
* Product data now includes the brand (WooCommerce brand, or the store's name) and SKU, and also on stores where Rank Math writes the product data: delivery time, return window, payment and brand are added to Rank Math's product too.

= 0.32.0 =
* New: Share usage with Cartlum, only if you agree (asked once on Today; Settings → Privacy). Once a week: your site address, store name, admin email, versions and which Cartlum settings are on — never customers, orders, carts, messages or keys. Nothing is sent before you answer, or if you say no.
* When you deactivate Cartlum, an optional box asks why. Skip sends nothing.
* For developers: the cartlum_cancel_reason_set action, when a cancellation reason is saved on an order.
* Reports counts orders confirmed by an AI call on their own line, and an order confirmed that way is noted as "confirmed by the AI call".
* Ready for Cartlum Pro 0.17: Offers gets an Order bump tab, and the AI page gets Shopping chat and Photo search tabs.
* Bangla for every new text.

= 0.31.0 =
* New: Sell through AI assistants (Checkout & Buttons → AI assistants, off by default). For people who ask ChatGPT, Gemini, Claude, Grok, Perplexity or Kimi what to buy: /llms.txt describes the shop in plain words (what it sells, how to order, payment, delivery times, returns, phone) with every product's price and order link; a JSON product feed (?cartlum_feed=json) lists every product and colour with price, stock and order link; each product page's data gains delivery time, return window and the payment methods; order links (?cartlum_order=ID) open the checkout with that product in the cart. Orders whose visit came from an AI assistant get a note and are counted in Reports → Orders from AI assistants. Checkout stays the store's own, with cash on delivery.
* Redesigned Today: one "Needs you now" list, each job with its button (Call, Confirm, Send, Check, Tag) and anything switched on that still needs setting up; "Updated · Refresh" sits in its header. No more coloured side stripes. "Too early to judge" is said once under What works.
* Today's results as cards and a chart: Recovered, Recovery rate, Still waiting and Carts saved, each with its change against the period before and a trend line; "Recovered per day" (per week for 90 days) as columns with the value on hover or keyboard focus and a "Show as table" view; icons for each job in Needs you now and each channel in What works.
* Cartlum's menu is grouped into Daily work, Sell more and Setup, and shows how many carts to call, orders to confirm and risky orders wait. On Cartlum's screens WordPress's copy of the menu stays folded.
* Today's feature board lists Option buttons, Order again and Sell through AI assistants.
* Ready for Cartlum Pro 0.16: Offers gets a Recommendations tab; the offer after the order shows right under "Your order is confirmed" on Cartlum's thank-you page; Landing pages sits after Offers in the Cartlum menu, and Offers has its own icon.
* For developers: the cartlum_thankyou_after_confirmation action and the cartlum_rail_items filter (menu items may link outside Cartlum's pages).

= 0.30.0 =
* New: Option buttons (Checkout & Buttons → Buy now & buttons, off by default). A product's colours, sizes and other options as buttons instead of drop-down lists; choices with no variation or out of stock are crossed out, and a second tap clears a choice. Price, stock, picture, Add to cart and Buy now work as with the drop-down.
* New: Order again (Checkout & Buttons → Customer login, off by default). "Order again" next to each order in My Account puts that order back in the cart, with its colours and sizes, and opens the checkout already filled in. Customers who are not logged in find the checkout filled in from their last order on the same phone or computer (180 days); nothing they type is overwritten.
* New order SMS placeholder {reorder_link}: a private link that does the same from a message. It works with the setting off, link previews in WhatsApp or Messenger do not touch the cart, and sold-out products are left out with a note.
* Ready for Cartlum Pro 0.15: a new Offers page (Bundles, Quantity offers, Upsells, Loyalty points, Gift cards), shown only with Pro. Old links to Bundles on Checkout & Buttons land on the new page, and any settings tab opened on the wrong page goes to its own.
* For developers: the cartlum_settings_after_form action, for screens with forms of their own under a settings tab.

= 0.29.0 =
* Ready for Cartlum Pro 0.14: Orders gets a Phone app tab and Fake Orders a Fraud network tab, and calls logged on a cart work from outside wp-admin too.
* For developers: Cartlum_Contact::log_call() and Cartlum_Contact::call_outcomes().

= 0.28.0 =
* New: Orders → To confirm. Every new Processing and On hold order in one list, oldest first, with the customer's history, call and WhatsApp buttons, and one-tap answers: Confirmed, No answer (counted), Call back, Cancel with a reason, Fake order (with "Also block this number"). Confirming keeps the order status as it is and adds a note; WooCommerce → Orders gets a Confirmed column. Today's "Orders to confirm" card counts this list.
* New: Reports (Cartlum → Reports). For the last 7, 30 or 90 days: delivery success (delivered out of delivered + returned), money delivered, fake orders stopped, where every order went, confirmations on the phone and by the customer's link, and why orders did not arrive.
* New SMS gateways for Bangladesh: BulkSMSBD, SSL Wireless, Alpha SMS and MiMSMS (Integrations → SMS).
* A failed SMS now shows the provider's own reason ("Insufficient balance", "Invalid UserName & Password") instead of its raw answer.
* New cancellation reasons: "Cancelled by the customer" and "Fake order or wrong number".
* For developers: the cartlum_order_confirmed and cartlum_reports_after actions, and the cartlum_order_sms_placeholders filter to add order SMS placeholders.

= 0.27.1 =
* New name: "Cartlum – Cart Recovery, Fake Order Protection & Automation for WooCommerce", with the description and features grouped under those three jobs.
* Calmer Today screen: "Your features" moved to the bottom and starts closed with an On / Off / Needs setup count; "All caught up" is one line; customers not yet contacted are no longer shown in bold orange; empty sections are plain text.

= 0.27.0 =
* New: review requests (Orders → Review requests). One SMS, a set number of days after the order or after delivery, with a private link to a short review form: stars and an optional comment for each product, no login, works at 360px and in block themes. Answers are saved as WooCommerce product reviews marked "verified owner" (held for approval by default) and noted on the order. Respects quiet hours, skips cancelled orders, never asks twice. Off by default.
* New placeholder {review_link} for order SMS texts, e.g. to add the review link to the "delivered" SMS instead of sending a separate one.

= 0.26.0 =
* Ready for Cartlum Pro 0.12's couriers: Pathao, RedX and Paperfly get their own Integrations cards and tabs, and Orders gets a Parcels tab.
* The "delivered" SMS and the customer's order tracking now work with any courier, not only Steadfast. Tracking shows "picked up", "on the way", "out for delivery" and "returned" too.
* Today's "Parcels to send" counts orders not sent to any courier.

= 0.25.3 =
* The plugin's website link now points to the Cartlum page on GitHub.

= 0.25.2 =
* Buy now in product grids now looks like the Add to cart beside it (same size, letters and shape from your theme); only its colour is your product button colour.

= 0.25.1 =
* Buy now in product grids uses your product button colour (or the checkout colour), also on the home page and other pages with product grids, instead of the theme's default button colour.

= 0.25.0 =
* Coupons: new type **Free delivery**. The reminder's one-time code makes the delivery charge 0 at checkout, with any delivery methods (no special free-shipping setup). Opening the reminder link applies it.
* Restoring a saved cart no longer warns when an item has no variation details.

= 0.24.4 =
* A cart is marked abandoned, and its reminders started, exactly once even if the background job happens to run twice at the same moment; a doubled schedule of that job is cleaned up by itself.

= 0.24.3 =
* Product buttons: the quantity box keeps its own width instead of stretching across the row, and its number stays visible when a theme makes the box narrow.

= 0.24.2 =
* Product button colour now also colours Add to cart and Buy now on stores where WooCommerce's own button style (purple) used to win.

= 0.24.1 =
* Buy now on shop pages sits right next to each product's Add to cart, and skips cards that a theme or plugin gives its own buttons (for example with a colour picker), so no card shows two Buy now buttons.
* "Order on WhatsApp" also names a colour chosen with a shop's own colour swatches.
* Setup: the Buy buttons step warns that a theme's own buttons and Cartlum's would show twice.

= 0.24.0 =
* New admin, organised by a shop's daily work: Today · Abandoned Carts · Orders · Fake Orders · Checkout & Buttons · AI · Integrations · Settings, with the menu always on the left (a strip on phones).
* Today: what needs you now (carts to call, orders to confirm, parcels to send, risky orders, cancellations without a reason, anything half set up) and "Your features" — every feature with On / Off / Needs setup.
* Integrations: every outside service (SMS, Steadfast, Facebook, FraudBD, WhatsApp, ElevenLabs, AI, webhooks) in one place, each with its status, keys and test button. Feature tabs say which service they use and whether it is connected.
* Find a setting (Ctrl K / ⌘ K) on every screen jumps straight to any setting.
* Settings screens: switches, plain labels, technical options under "More options", a Save bar that saves without reloading, and a warning before leaving with unsaved changes.
* "Set up Cartlum in 5 minutes": a step-by-step first setup.
* বাংলা button: Cartlum's screens in Bangla for you only.
* Old links and bookmarks keep working; no setting changes.

= 0.23.1 =
* SMS: when the SMS provider receives a message but is too slow to answer (seen with eSMS), the SMS now counts as sent instead of failed. Phone-login codes stay valid (before, the customer got the code but it was refused), the test says "sent, but slow to confirm", and order notes mention it. A provider that cannot be reached is still an error.

= 0.23.0 =
* Cancellation reasons: "Earlier reason field" shows reasons that a snippet or plugin saved before Cartlum (same reason codes).
* Speed (off by default): smaller product-grid photos on phones, with lazy loading; and an option to skip Elementor's icon font on sites that don't use Elementor's icons.

= 0.22.1 =
* A mistyped number (for example 12 digits for Bangladesh) now gets a clear message — "015702938681 is not a valid mobile number…" — instead of the SMS provider's error, and is never sent to the provider. Saving "Your numbers" or the test number points out a wrong one.

= 0.22.0 =
* Checkout: a note above the order button, for example your delivery charge and refund policy, with links. Works on the phone-first checkout and WooCommerce’s classic checkout.
* My Account: optional "Cart" link in the menu.

= 0.21.0 =
* Order SMS to the store: several numbers (comma-separated), {items_detail} (one line per item with SKU or name, quantity and colour), {customer}, {address}, {amount}, and an SMS to the store when an order changes to statuses you choose (for example Cancelled).
* Cancellation reasons: pick why an order was cancelled on the order screen (7 reasons), see it in the orders list, and on Fake Orders see the last 30 days' reasons plus the cancelled orders still missing one, taggable in two clicks. Reasons saved by an earlier tool can be read through a filter.
* Phone-first checkout: a Place order bar at the bottom of the screen on phones while the real button is out of view.

= 0.20.2 =
* Log in with phone number: customers whose account was made by another phone-login system (user name = their phone number) now get that same account, with all its orders, the first time they log in with a Cartlum code, instead of a new empty one. Staff accounts are never matched.

= 0.20.1 =
* New message placeholders: {amount} (the total as a plain number, so "Tk {amount}" keeps an SMS free of Bangla characters and costs about a third as much) and {items_short} (product names cut to 60 characters, quantities written with a plain "x").

= 0.20.0 =
* New Cartlum → Pro page (only while Cartlum Pro is not installed) listing what the paid add-on adds, and one short line on the Automation, Protection and WhatsApp tabs. No notices elsewhere, and no locked features in this plugin.

= 0.19.0 =
* New menu, one page per job: Dashboard, Abandoned Carts, Fake Orders, Messages, Checkout and Settings (plus AI Agent and Facebook with Cartlum Pro). Each page has its own settings as tabs. Old links to Settings tabs open the right new page.
* New Fake Orders page: recent orders from risky or blocked numbers that are still to ship, with each number's history and a call button, plus a count of blocked numbers, IP addresses and devices.

= 0.18.0 =
* Redesigned dashboard. Shows whether automatic reminders and SMS are set up, compares recovered money with the period before, and shows where abandoned carts went (recovered, still waiting, lost).
* "Waiting for you" now says what has already been tried with each person (SMS sent, called, not contacted yet) and when the next automatic reminder goes, so nobody is contacted twice by mistake.
* "What works" shows each channel's recovery rate as a bar and marks results from too few carts. Most abandoned products show product photos.
* Works one-handed on a phone: nothing scrolls sideways at 360px.

= 0.17.0 =
* Fake-order protection: block a device (a browser that keeps ordering under different numbers) and IP address ranges such as 103.4.5.0/24. The order screen shows the order's IP address and device with Block / Unblock buttons.

= 0.16.2 =
* Fix: on WooCommerce 8.x with a block theme, the Buy now button showed as a second "Add to cart".
* Fix: no PHP 8.4 deprecation notice when exporting carts to CSV.
* Tested from PHP 7.4 + WordPress 6.2 + WooCommerce 8.0 up to PHP 8.4 + WordPress 7.1 + WooCommerce 11.2.

= 0.16.1 =
* Cart history and the dashboard's "What works" show AI calls made by Cartlum Pro.

= 0.16.0 =
* Dashboard: **Most abandoned products** — which products are left behind most, how many of those carts came back, and the value left.
* Carts: **Export CSV** of the list (keeps the status filter and search). Names typed by shoppers are made safe for Excel and Google Sheets.
* Webhooks (Settings → Webhooks): send cart saved, abandoned and recovered events as JSON to Zapier, Make, Pabbly or your own system. Signed with a secret, sent in the background, retried twice on failure.

= 0.15.0 =
* Save-cart popup (Settings → Popup, off by default): asks shoppers who have something in the cart for their mobile number when they are about to leave, right after "Add to cart", or both. The cart is saved even if they never reach the checkout, and their number is filled in at checkout. Never shown on the checkout, account or thank-you pages, after Buy now, or to shoppers whose number you already have. Works with classic and block themes.

= 0.14.0 =
* Designed reminder emails: your logo, the cart's products with photos, prices and total, and a "Complete your order" button in your checkout colour. Looks right on phones and in Gmail and Outlook. Choose **Plain** under Email to keep your WooCommerce email style.
* Settings → Email → **Preview the email** shows the reminder with products from your store, without sending anything.

= 0.13.0 =
* SMS code for risky orders (optional, under Protection): cash-on-delivery customers confirm their number with a code before ordering when the number often cancels, a new number orders a large amount, or the connection already ordered twice today. Everyone else orders as before. The code is only sent to the number of the checkout that asked for it.
* Login and checkout codes share one code system with the same limits.

= 0.12.1 =
* Security: phone login now matches accounts only by numbers proven with an SMS code. Before, someone could put another person's number in their own account and receive that person's orders when they logged in.
* Security: the custom SMS gateway refuses local and private network addresses (including cloud metadata addresses).
* Security: the customer's name in reminders and order SMS is reduced to a short plain first name, so the store's messages cannot be used to send someone else's text or links.
* Security: at most one automatic reminder per phone number (or email) per 24 hours across carts, and a limit on new carts per IP address, against message flooding.
* Login codes are also limited to 10 per number per day.
* Link previews (WhatsApp, Facebook, Telegram …) no longer count as a click on the cart link, and no longer unsubscribe the customer.
* Admin notices only show text Cartlum signed.

= 0.12.0 =
* Bangla translation of everything in Cartlum (checkout, thank-you page, login, buttons, tracking, messages and admin screens).
* "Language for customers" setting: shoppers see Cartlum (and get default SMS texts) in Bangla while wp-admin keeps the site's language.
* The phone-first checkout labels email, district, order note, "optional" and the order button itself, so they follow Cartlum's language.

= 0.11.0 =
* Fake-order protection (optional): blocklist of numbers and IP addresses, no repeat orders minutes apart, daily order limits per number and per IP address, and a hidden bot trap. Works on classic, block, CartFlows and Cartlum checkouts; shop managers are never stopped.
* Staff see each number's history on the Orders list and order screen (completed, cancelled, courier success rate from Steadfast) with a Block / Unblock button; new orders from numbers that often cancel get a note to call first.

= 0.10.0 =
* Order SMS (optional): order received, handed to the courier (Steadfast), delivered and cancelled messages to the customer, and a new-order message to the store. Each is sent once, in the background, and noted on the order.
* Order tracking in My Account (optional): placed → processing → with the courier → delivered, with the courier's consignment number, status and an optional "Track parcel" link.

= 0.9.0 =
* Thank-you page in the phone-first checkout's style: confirmation, order details, "pay on delivery" amount, order summary, and a WhatsApp button.
* Log in with phone number (optional): My Account login by mobile number and a 6-digit SMS code. The first login creates the account; orders placed with that number are linked to it. Codes are rate-limited, single use and expire in 10 minutes; staff accounts must use their password.
* Addresses no longer show the district twice when it is also the city.

= 0.8.0 =
* "Order on WhatsApp" and "Call to order" buttons on product pages. The WhatsApp message names the chosen colour/size, quantity and price; both also show on out-of-stock products. Shortcode [cartlum_contact_buttons] for page builders.
* Add to cart, Buy now, WhatsApp and Call sit together as a 2 x 2 group, optionally all in one product button colour.
* Buy now settings moved to a new Product page tab.

= 0.7.0 =
* Buy now button: next to "Add to cart", it adds the product with the chosen options and quantity and goes straight to checkout. Optional on shop pages, custom text, and a choice between keeping the cart or checking out only this product.

= 0.6.0 =
* Checkout preview: while it is on, only shop managers see the new checkout, so it can be tried on the live store before customers see it. On by default.
* Works next to CartFlows: visitors who get Cartlum's checkout see it on the store's checkout page instead of CartFlows' global checkout; CartFlows funnel pages are left alone.

= 0.5.0 =
* Phone-first checkout redesigned: a focused page with only your logo, the form beside the order summary (a folding summary bar on phones), product photos, a discount code box, delivery and payment as outlined choices, and labels inside the fields.
* New checkout settings: button colour (text colour picked automatically for contrast) and logo.
* Clearer checkout errors, e.g. "Please enter your full name."

= 0.4.0 =
* Phone-first checkout: a short WooCommerce checkout (mobile number, name, address, district) with delivery charges by district. Works with classic and block checkout pages.
* Phone numbers typed in Bangla, Arabic-Indic or Devanagari digits are understood everywhere.

= 0.3.0 =
* Hooks for add-ons: settings tabs and fields, automation steps, message placeholders, cart screen panels.
* Passes the WordPress.org Plugin Check with no errors or warnings.
* Translation template (languages/cartlum.pot).

= 0.2.0 =
* Automatic reminder with quiet hours, daily SMS limit and test mode.
* Redesigned admin: phone-first carts list, sticky call bar, insert chips, honest status.
* Database queries use identifier placeholders.

= 0.1.0 =
* First development version.
