# Cartlum – Cart Recovery, Fake Order Protection & Automation for WooCommerce

![Cartlum: cart recovery, fake order protection and automation for WooCommerce](.wordpress-org/banner-1544x500.png)

For WooCommerce stores where customers check out with a **phone number** and pay **cash on delivery**. Most of these customers never read email, many carts are left half-way, and some orders are fake. Cartlum does three jobs:

1. **Cart recovery**: saves the cart the moment a phone number is typed, then helps you win it back by SMS, call, WhatsApp and email.
2. **Fake order protection**: confirm every new order in a tap, block numbers that keep cancelling, ask risky buyers for an SMS code.
3. **Easy automation**: order SMS, tracking, review requests and reminders that run by themselves, and a store ChatGPT and Gemini can sell from.

Free and open source. Bangla included. A paid **Pro** add-on adds couriers, offers, landing pages and AI ([see below](#cartlum-pro)).

## Download

**[⬇ Download the latest version](https://github.com/Claudefarid/cartlum-free/releases/latest)**: on the release page, download the file named `cartlum-x.y.z.zip`. Don't use "Source code".

## Install

1. In WordPress, go to **Plugins → Add New → Upload Plugin**.
2. Choose the `cartlum-x.y.z.zip` file you downloaded and click **Install Now**, then **Activate**.
3. Open **Cartlum** in the admin menu and follow **Set up Cartlum** (5 minutes). Under **Integrations → SMS**, choose your SMS gateway (eSMS, BulkSMSBD, SSL Wireless, Alpha SMS, MiMSMS, Twilio or any HTTP SMS API) and send yourself a test SMS.

To update, upload the new zip the same way and choose **Replace current with uploaded**. Your settings and carts are kept.

Requires WordPress 6.2+, WooCommerce 8.0+ and PHP 7.4+.

## Today: what needs you, on one screen

![Today: Needs you now, results with trends, money recovered per day, who to contact](docs/screens/01-today.png)

- **Needs you now**: carts to call, orders to confirm, parcels to send, risky orders and cancellations without a reason, each with the button that starts the job.
- **Results** for 7, 30 or 90 days: money recovered, recovery rate, money still waiting and carts saved, each with its change and trend; money recovered per day as a chart (hover a day, or show it as a table); where every abandoned cart went.
- **Waiting for you**: the people to contact now, with call and WhatsApp one tap away. **What works**: which channel brings carts back.

## 1. Cart recovery

| Every saved cart | One cart: contact and history |
|---|---|
| ![Abandoned carts](docs/screens/02-abandoned-carts.png) | ![A cart](docs/screens/03-cart.png) |

| Save-cart popup | Reminder email | Phone-first checkout |
|---|---|---|
| ![Save your cart?](docs/screens/43-save-cart-popup.png) | ![Reminder email](docs/screens/35-reminder-email.png) | ![Phone-first checkout](docs/screens/44-phone-first-checkout.png) |

- **Saves the cart as the phone number is typed**, on the classic checkout, the block checkout and CartFlows, before "Place order".
- **Save-cart popup**: asks for the number when a shopper is about to leave, or right after "Add to cart".
- **SMS through eSMS, BulkSMSBD, SSL Wireless, Alpha SMS, MiMSMS, Twilio** or any HTTP SMS API; **WhatsApp** with the message written; **designed reminder emails**; a **call list** for staff.
- **One-tap cart link** that refills the cart and opens checkout; **one-time coupons** only when you offer one.
- **An automatic reminder** with quiet hours, a daily SMS limit and a test mode.
- **Honest numbers**: an order counts as recovered only if you contacted the customer first.

## 2. Fake order protection

| Orders to confirm | Risky orders |
|---|---|
| ![Orders to confirm](docs/screens/08-orders-to-confirm.png) | ![Fake orders](docs/screens/14-fake-orders.png) |

- **Orders to confirm**: every new order in one list, oldest first, with the customer's history. Call or WhatsApp, then one tap: Confirmed, No answer, Call back, Cancel with a reason, or Fake (and block the number).
- **Protection**: block numbers, IP addresses (and ranges) and devices; repeat-order and daily limits; a bot trap; an **SMS code for risky orders**.
- **Phone-first checkout**: mobile number first, then name, address and district. Bangla digits are accepted and Bangladeshi numbers checked.
- **Cancellation reasons** and a **Reports** page that shows delivery success, returns and fake orders stopped.

## 3. Easy automation

| Order tracking | Review request | Log in with a phone number |
|---|---|---|
| ![Order tracking](docs/screens/54-order-tracking.png) | ![Rate your order](docs/screens/49-review-request-page.png) | ![Phone login](docs/screens/52-phone-login.png) |

- **Order SMS** at each step of the order, and a **tracking** bar in My Account with the courier's status.
- **Review requests by SMS**: a private link to rate each product, no login; answers become "verified owner" reviews.
- **Log in with a phone number** and an SMS code; every order placed with that number appears in My Account.
- **Order again**: one tap puts an earlier order back in the cart with the checkout filled in, from My Account or from `{reorder_link}` in an SMS.
- **Sell through AI assistants**: ChatGPT, Gemini, Claude, Grok, Perplexity and Kimi can read your shop (`/llms.txt`, a product feed, delivery and return times in each product's data) and send buyers to your checkout with an order link. Orders from AI assistants are counted in Reports.
- **Webhooks** for Zapier, Make and Pabbly, and **CSV export**.

## On the product page

| Buy now, WhatsApp and Call to order | Colours and sizes as buttons | Order again |
|---|---|---|
| ![Product buttons](docs/screens/40-product-buttons-bundle.png) | ![Option buttons](docs/screens/42-product-option-buttons.png) | ![Order again](docs/screens/53-my-account-order-again.png) |

## Built around your day

Cartlum's admin is organised by a shop's daily work: **Daily work** (Today, Abandoned Carts, Orders, Fake Orders, Reports), **Sell more** (Offers and Landing pages, with Pro) and **Setup** (Checkout & Buttons, AI, Integrations, Settings). The menu shows how many carts to call, orders to confirm and risky orders are waiting.

- **Integrations** keeps every outside service in one place, each with its status, keys and a test button.
- **Find a setting** (Ctrl K / ⌘ K) jumps straight to any option. Switches and plain words, with technical options under **More options**.
- **Set up Cartlum in 5 minutes**, and a **বাংলা** button for Cartlum's screens in Bangla.
- **Works on a phone**: every screen at 360px, with the menu as a strip at the top.

| Integrations | Reports |
|---|---|
| ![Integrations](docs/screens/32-integrations.png) | ![Reports](docs/screens/18-reports.png) |
| **Find a setting** | **Today on a phone** |
| ![Find a setting](docs/admin-search.png) | <img src="docs/screens/01-today-phone.png" alt="Today on a phone" width="300"> |

## Cartlum Pro

Cartlum Pro is a paid add-on. It installs next to the free plugin and adds:

| | Free | Pro |
|---|:---:|:---:|
| Save carts at checkout and with the popup | ✅ | ✅ |
| SMS, WhatsApp, email and call from each cart | ✅ | ✅ |
| One automatic reminder | ✅ | ✅ |
| **Multi-step reminders**: as many steps as you like, each with its own time, channel and message | | ✅ |
| **A/B tests**: two versions of a message; the winner is shown | | ✅ |
| **Smart coupons**: a discount only when it helps (not for loyal or risky customers) | | ✅ |
| **Automatic WhatsApp** through Meta's WhatsApp Cloud API | | ✅ |
| **AI call**: an AI voice agent calls the customer in Bangla over your own phone line and reports back | | ✅ |
| **AI message writer**: drafts SMS in English, Bangla or Banglish | | ✅ |
| **Win-back SMS** to past buyers who haven't ordered for a while | | ✅ |
| Fake-order protection: blocklist, limits, SMS code for risky orders | ✅ | ✅ |
| **Courier check**: each customer's delivered and refused parcels at Steadfast, Pathao, RedX and Paperfly | | ✅ |
| **Advance payment**: bKash / Nagad Send Money with Transaction ID before cash-on-delivery orders | | ✅ |
| **Couriers: Steadfast, Pathao, RedX, Paperfly**: send parcels from WooCommerce (one, bulk or automatic); delivery status updates by itself | | ✅ |
| **Facebook Pixel and Conversions API**: no double counting, customer details hashed; for cash on delivery, a purchase counts only if the order isn't cancelled | | ✅ |
| **Bundle offers** on product pages, and a **customer score** on every cart | | ✅ |
| **Quantity offers**: "Buy 2, save ৳100" and "Buy 1, get 1 free", applied in the cart by themselves | | ✅ |
| **Recommendations**: "Customers also bought" under each product, learned from your own orders; the upsell can pick its product from them | | ✅ |
| **Upsell after the order**: one tap adds a product to the same cash-on-delivery order and parcel; a second offer if they say no | | ✅ |
| **Loyalty points**: earned when an order is delivered, spent at checkout after phone login | | ✅ |
| **Gift cards**: sold as a product or given by hand, code by SMS, balance kept on the card | | ✅ |
| **Product landing pages**: a one-product page for your ads with the checkout on the same page | | ✅ |
| **Customers confirm their own order** from a link in the "order received" SMS | | ✅ |
| **Staff phone app**: orders to confirm and carts to call, with new-order notifications | | ✅ |
| **Cartlum fraud network**: how a number did at other shops (hashed, opt-in) | | ✅ |
| **Reports by district and by product** | | ✅ |
| Updates and support with a licence key | | ✅ |

Each Pro feature connects with the store's own accounts (SMS gateway, ElevenLabs, FraudBD, Meta). You pay those services directly; Cartlum adds nothing on top.

### Sell more on every order

| Landing page for your ads | Its order form | Offer after the order |
|---|---|---|
| ![Landing page](docs/screens/45-landing-page.png) | ![Order form with colour and quantity](docs/screens/46-landing-order-form.png) | ![One-tap upsell on the thank-you page](docs/screens/47-upsell-after-order.png) |

- **Landing pages** (`/lp/<name>/`): headline, photos, video, benefits, reviews and questions, then your real checkout on the same page. Cart capture and fake-order protection work there too. Colour and quantity are buttons, and quantity offers show in the total.
- **Upsell after the order**: "Wait! Add this to your order?" One tap adds it to the same cash-on-delivery order, so nothing is charged twice and it ships in the same parcel.

| Customers also bought | Loyalty points at checkout | Staff phone app |
|---|---|---|
| ![Recommendations](docs/screens/41-product-recommendations.png) | ![Loyalty points](docs/screens/55-loyalty-points-checkout.png) | ![Orders to confirm on a phone](docs/screens/50-staff-app-to-confirm.png) |

**Cartlum Pro is coming soon.** To be told when it launches, click **Watch → Custom → Releases** at the top of this page.

## Every screen

Every screen, by area. Features marked *Pro* need Cartlum Pro.

<details open>
<summary><b>Abandoned carts</b></summary>

| | |
|---|---|
| **Every saved cart** | **One cart: call, WhatsApp, SMS, history** |
| ![Every saved cart](docs/screens/02-abandoned-carts.png) | ![One cart: call, WhatsApp, SMS, history](docs/screens/03-cart.png) |
| **Automatic reminders (multi-step: Pro)** | **Message texts** |
| ![Automatic reminders (multi-step: Pro)](docs/screens/04-reminders.png) | ![Message texts](docs/screens/05-message-texts.png) |
| **Save-cart popup** | **Win-back SMS (Pro)** |
| ![Save-cart popup](docs/screens/06-save-cart-popup-settings.png) | ![Win-back SMS (Pro)](docs/screens/07-win-back.png) |

</details>
<details>
<summary><b>Orders</b></summary>

| | |
|---|---|
| **Orders to confirm** | **Order SMS** |
| ![Orders to confirm](docs/screens/08-orders-to-confirm.png) | ![Order SMS](docs/screens/09-order-sms.png) |
| **Review requests** | **Parcels to couriers (Pro)** |
| ![Review requests](docs/screens/10-review-requests.png) | ![Parcels to couriers (Pro)](docs/screens/11-parcels.png) |
| **bKash / Nagad advance (Pro)** | **Staff phone app (Pro)** |
| ![bKash / Nagad advance (Pro)](docs/screens/12-advance-payment.png) | ![Staff phone app (Pro)](docs/screens/13-phone-app-settings.png) |
| **Customer confirms their order (Pro)** | **Carts to call in the phone app (Pro)** |
| ![Customer confirms their order (Pro)](docs/screens/48-customer-confirms-order.png) | ![Carts to call in the phone app (Pro)](docs/screens/51-staff-app-carts.png) |

</details>
<details>
<summary><b>Fake orders</b></summary>

| | |
|---|---|
| **Risky orders and cancel reasons** | **Protection: blocklist, limits, SMS code** |
| ![Risky orders and cancel reasons](docs/screens/14-fake-orders.png) | ![Protection: blocklist, limits, SMS code](docs/screens/15-protection.png) |
| **Courier check (Pro)** | **Cartlum fraud network (Pro)** |
| ![Courier check (Pro)](docs/screens/16-courier-check.png) | ![Cartlum fraud network (Pro)](docs/screens/17-fraud-network.png) |

</details>
<details>
<summary><b>Reports</b></summary>

| | |
|---|---|
| **Delivery success, money, where orders went (district and product: Pro)** | |
| ![Delivery success, money, where orders went (district and product: Pro)](docs/screens/18-reports.png) | |

</details>
<details>
<summary><b>Checkout & buttons</b></summary>

| | |
|---|---|
| **Phone-first checkout** | **Buy now, WhatsApp, Call, option buttons** |
| ![Phone-first checkout](docs/screens/19-easy-checkout.png) | ![Buy now, WhatsApp, Call, option buttons](docs/screens/20-buttons.png) |
| **Phone login and Order again** | **Sell through AI assistants** |
| ![Phone login and Order again](docs/screens/21-customer-login.png) | ![Sell through AI assistants](docs/screens/22-ai-assistants.png) |

</details>
<details>
<summary><b>Offers (Pro)</b></summary>

| | |
|---|---|
| **Bundles** | **Quantity offers** |
| ![Bundles](docs/screens/23-bundles.png) | ![Quantity offers](docs/screens/24-quantity-offers.png) |
| **Upsells after the order** | **Recommendations** |
| ![Upsells after the order](docs/screens/25-upsells.png) | ![Recommendations](docs/screens/26-recommendations.png) |
| **Loyalty points** | **Gift cards** |
| ![Loyalty points](docs/screens/27-loyalty-points.png) | ![Gift cards](docs/screens/28-gift-cards.png) |

</details>
<details>
<summary><b>Landing pages, AI and setup</b></summary>

| | |
|---|---|
| **Landing pages (Pro)** | **AI call (Pro)** |
| ![Landing pages (Pro)](docs/screens/29-landing-pages.png) | ![AI call (Pro)](docs/screens/30-ai-call.png) |
| **AI writer (Pro)** | **5-minute setup** |
| ![AI writer (Pro)](docs/screens/31-ai-writer.png) | ![5-minute setup](docs/screens/34-setup-wizard.png) |

</details>

## Licence

GPLv2 or later. See [LICENSE](LICENSE).
