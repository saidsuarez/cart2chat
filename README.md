# Cart2Chat - WooCommerce WhatsApp Orders

Cart2Chat helps stores sell personalized products in WooCommerce with WhatsApp-first order flows.

It supports:
- Custom product types and customization fields
- Conditional field logic
- Product-page WhatsApp order flow
- Checkout WhatsApp payment method flow
- Customer/shipping form builder for WhatsApp product-page flow
- Design settings for form UI and button colors

## Plugin Info
- Name: `Cart2Chat - WooCommerce WhatsApp Orders`
- Version: `0.1.0`
- Author: `Pinxel`
- Text domain: `cart2chat`

## Requirements
- WordPress
- WooCommerce
- PHP compatible with your WordPress/WooCommerce stack

## Installation
1. Download the plugin `.zip` from GitHub Releases.
2. In WordPress admin, go to `Plugins > Add New > Upload Plugin`.
3. Upload the `.zip` and activate the plugin.
4. Go to `Cart2Chat` in the admin sidebar.

## Quick Start
1. Open `Cart2Chat > General`.
2. Choose usage mode:
   - Product page only
   - Checkout only
   - Both
3. Configure your WhatsApp number and message templates.
4. Save changes.
5. Open `Cart2Chat > Catalog & Fields` and create product types and fields.
6. Edit WooCommerce products and assign a Cart2Chat product type in the `Cart2Chat` product tab.

## Main Flows

### 1) Product page flow (no checkout required)
- Customer opens a product.
- Customer fills personalization fields.
- Customer clicks **Order via WhatsApp**.
- Cart2Chat sends a structured WhatsApp message with:
  - Customer/shipping data
  - Payment preference
  - Product customization summary

### 2) Checkout flow (cart-based)
- Customer adds one or more products to cart.
- At checkout, customer selects the Cart2Chat WhatsApp payment method.
- Cart2Chat creates a WooCommerce order and generates a WhatsApp message with the full order summary.

## Catalog & Fields
In `Cart2Chat > Catalog & Fields`, you can:
- Create, sort, and remove product types
- Create, sort, and remove fields per type
- Use field types: `text`, `number`, `textarea`, `select`, `file`, `email`, `tel`
- Add select options with the UI builder
- Configure conditional visibility (`Show only if...`)

You can keep the catalog empty if you want to start from a clean setup.

## WhatsApp Form Builder (Product Page Flow)
In `Cart2Chat > General`, you can configure the customer form used by the product-page WhatsApp flow:
- Add/edit/remove fields
- Mark required fields
- Reorder with Up/Down controls
- Configure select options

This form is shown when usage mode includes **Product page**.

## Design
In `Cart2Chat > Design`, you can configure:
- Visual style (flat/modern for containers and fields)
- Light/dark theme for containers and fields
- Button background and text colors
- WhatsApp icon visibility
- Product/checkout button labels

Typography and base button style are inherited from the active theme.

## Order Management
- WooCommerce remains the source of truth for orders.
- Cart2Chat adds internal order control fields for process tracking in admin.

## Internationalization (i18n)
- Native language: English
- Spanish translations included (`es_ES`)
- Text domain: `cart2chat`

## Support
- Developed by Pinxel
- Support (EN): `support@pinxel.co`
- Soporte (ES): `soporte@pinxel.co`

## Donations
If you want to support development with a donation, a PayPal link will be published soon.

## Roadmap (Post-MVP)
- Public stable release process
- Automated packaging and release notes
- Expanded docs and troubleshooting guides
- Broader compatibility testing matrix
