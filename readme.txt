=== Cartlum – Abandoned Cart Recovery for WooCommerce ===
Contributors: swadhinai
Tags: abandoned cart, cart recovery, sms, whatsapp, woocommerce
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.19.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover abandoned WooCommerce carts by SMS, phone call, WhatsApp and email — and see exactly how much revenue each one brings back.

== Description ==

Most abandoned-cart plugins send an email. In many markets customers check out with a phone number and never read email, so the cart is lost. Cartlum is built phone-first.

* **Captures the cart the moment a phone number is typed** at checkout — before "Place order" — on the classic checkout, the block checkout, and CartFlows.
* **Save-cart popup** (optional): asks shoppers with something in the cart for their number when they are about to leave or right after "Add to cart", so carts are saved even before checkout.
* **Phone-first checkout** (optional): mobile number first, then name, address and district. Accepts numbers typed in Bangla digits, checks Bangladeshi mobile numbers, and keeps WooCommerce's payments, delivery charges and coupons.
* **Buy now button** (optional): straight from the product page to checkout, with the chosen colour or size.
* **Order on WhatsApp and Call to order buttons** (optional) on product pages.
* **Fake-order protection** (optional): block numbers, IP addresses (and ranges) and devices, repeat-order and daily limits, bot trap, and each number's order history for staff.
* **Order SMS and tracking** (optional): texts at each step of the order, and a progress bar with the courier's status in My Account.
* **Log in with phone number** (optional): customers sign in to My Account with an SMS code and see every order placed with their number.
* **One-tap cart link** in every message: refills the cart, applies the coupon and opens checkout.
* **SMS** through eSMS (Bangladesh), Twilio, or any HTTP SMS API — no code needed.
* **Phone call list** for your team: call with one tap, log how it went.
* **WhatsApp**: opens a chat with the customer's number and a ready-written message.
* **Designed reminder emails**: your logo, the cart's products with photos, and a "Complete your order" button in your colour — or your WooCommerce email style.
* **One-time coupons** per cart, created only when you offer one.
* **Automatic reminder**: one SMS or email after a cart is abandoned, with quiet hours, a daily SMS limit and a test mode that sends to your own number first.
* **Honest numbers**: an order only counts as *recovered* if you contacted the customer first. Customers who came back on their own are shown separately.
* **Most abandoned products** report, and **CSV export** of your carts.
* **Webhooks** to Zapier, Make, Pabbly or your own system: cart saved, abandoned and recovered, signed and retried.
* **Bangla included**, and a "Language for customers" setting to show shoppers Bangla while your admin stays in English.
* Unsubscribe link in every message, automatic data deletion, and WordPress privacy export/erase support.

== External services ==

Cartlum sends messages only through services you choose and configure. Nothing is sent until you set one up, and nothing is sent anywhere else.

* **eSMS (Bangladesh)** — when you choose it as your SMS gateway, the customer's phone number and the message text are sent to https://login.esms.com.bd/ each time an SMS is sent. Terms: https://esms.com.bd/terms-and-conditions/ · Privacy: https://esms.com.bd/privacy-policy/
* **Twilio** — when you choose it as your SMS gateway, the customer's phone number and the message text are sent to https://api.twilio.com/ each time an SMS is sent. Terms: https://www.twilio.com/legal/tos · Privacy: https://www.twilio.com/legal/privacy
* **Custom HTTP API** — when you configure it, the phone number and message are sent to the address you enter, under that provider's terms.
* **WhatsApp** — WhatsApp buttons open https://wa.me/ in the person's own browser or app: for staff, with the customer's number and a message filled in; for shoppers (the optional product page button), with the store's number and the product they are looking at. The site itself sends nothing to WhatsApp. Terms: https://www.whatsapp.com/legal/terms-of-service · Privacy: https://www.whatsapp.com/legal/privacy-policy

* **Webhooks** — when you enter a webhook URL, the cart's details (customer name, phone, email, products, total and cart link) are sent to that address for the events you tick, under the terms of the service that address belongs to.

Cart details are stored in your own WordPress database and are deleted automatically after the period you set (90 days by default).

== Installation ==

1. Install and activate WooCommerce.
2. Install Cartlum from Plugins → Add New, or upload the zip, and activate it.
3. Go to Cartlum → Settings. Set your country calling code, choose an SMS gateway and send yourself a test SMS.
4. Optional: turn on the automatic reminder under Settings → Automation. Set a test phone number first, so you receive the reminders yourself before customers do.

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

eSMS (Bangladesh) and Twilio are built in. Any other provider with an HTTP API can be added under Settings → SMS → Custom, without code.

= Will it slow down or block my checkout? =

No. Saving the cart happens in the background while the customer types, and every optional feature (popup, phone-first checkout, protection) can be turned off. Fake-order protection only asks risky orders for an SMS code.

= Does the phone-first checkout replace CartFlows? =
It can, for stores that only used CartFlows for a shorter checkout. CartFlows' global checkout is set aside for visitors who see Cartlum's checkout; CartFlows funnel pages keep their own checkout.

== Screenshots ==

1. Dashboard: money recovered, carts to follow up, what works by channel and the most abandoned products.
2. A cart: the customer, what they left, one-tap call, WhatsApp, SMS and email, and the full history.
3. Phone-first checkout: mobile number first, then name, address and district.
4. The designed reminder email, with product photos and a button in your colour.
5. The save-cart popup on a phone.
6. Settings: SMS gateways with a test button.

== Changelog ==

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
