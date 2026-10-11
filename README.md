# Cartlum – Abandoned Cart Recovery for WooCommerce

![Cartlum](.wordpress-org/banner-1544x500.png)

Most abandoned-cart plugins send an email. In many markets customers check out with a phone number and never read email, so the cart is lost. **Cartlum is built phone-first**: it saves the cart the moment a phone number is typed at checkout, then helps you win it back by SMS, phone call, WhatsApp and email.

## Download

**[⬇ Download the latest version](https://github.com/Claudefarid/cartlum-free/releases/latest)**: on the release page, download the file named `cartlum-x.y.z.zip`. Don't use "Source code".

## Install

1. In WordPress, go to **Plugins → Add New → Upload Plugin**.
2. Choose the `cartlum-x.y.z.zip` file you downloaded and click **Install Now**, then **Activate**.
3. Open **Cartlum** in the admin menu. Under **Messages → SMS**, choose your SMS gateway (eSMS, Twilio or any HTTP SMS API) and send yourself a test SMS.

To update, upload the new zip the same way and choose **Replace current with uploaded**. Your settings and carts are kept.

Requires WordPress 6.2+, WooCommerce 8.0+ and PHP 7.4+.

## Features

- **Saves the cart as the phone number is typed**: classic checkout, block checkout and CartFlows.
- **Save-cart popup**: asks for the number when a shopper is about to leave, or after "Add to cart".
- **Phone-first checkout**: mobile number first; Bangla digits accepted.
- **Buy now**, plus **WhatsApp** and **Call to order** buttons.
- **SMS, WhatsApp, email and calls**:
  - one-tap restore links and one-time coupons;
  - designed reminder emails;
  - an automatic reminder with quiet hours and a test mode.
- **Fake-order protection**:
  - blocks numbers, IP addresses (and ranges) and devices;
  - repeat-order limits;
  - an SMS code for risky cash-on-delivery orders;
  - a Fake Orders page.
- **Order SMS and tracking**, and a phone-number login for My Account.
- **Honest reports**: money recovered (compared with the period before), what works per channel, and the most-abandoned products.
- **CSV export**, and **webhooks** for Zapier, Make and Pabbly.
- **Bangla included.**

## Screenshots

| Dashboard | A cart |
|---|---|
| ![Dashboard](.wordpress-org/screenshot-1.png) | ![Cart](.wordpress-org/screenshot-2.png) |
| **Phone-first checkout** | **Reminder email** |
| ![Checkout](.wordpress-org/screenshot-3.png) | ![Email](.wordpress-org/screenshot-4.png) |

## Licence

GPLv2 or later. See [LICENSE](LICENSE).
