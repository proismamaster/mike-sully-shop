# MikeSullyShop

A full-featured e-commerce website themed after **Monsters, Inc.**, the beloved Pixar film. The name merges **Mike Wazowski** and **Sulley**, the two main characters from Monstropolis.

## Description

MikeSullyShop is a **full-stack** e-commerce platform built entirely in vanilla PHP (no MVC framework). The application sells official merchandise from the fictional city of Monstropolis (plushies, t-shirts, Funko Pops, mugs, backpacks, socks, keychains) with a playful, brand-consistent tone of voice.

It includes both the public-facing storefront (catalog, cart, checkout) and a complete **admin panel** for product, order, and sales management.

## Goals

- Build a **fully functional and professional** e-commerce with all features expected of a modern online store
- Ensure **security** in transactions and data handling (password hashing, email OTP, prepared statements)
- Provide a **complete admin panel** for warehouse, order, and sales management
- Demonstrate full-stack skills through a concrete, well-designed project

## Key Features

### Public Storefront
- **Product catalog** with infinite scroll, text search, and category filters
- **Product cards** with hover image gallery, "New Collection" badge, discount percentages
- **Product detail** with Bootstrap carousel for multiple images, breadcrumbs, quantity selector, stock availability
- **Wishlist** synced to database for logged-in users, session-based for guests
- **Shopping cart** with 30-minute timer, visible countdown, AJAX dynamic updates, auto stock restoration on expiry
- **Checkout** with shipping form, three payment methods (Card, PayPal, Cash), simulated OTP
- **User profile** with data editing and OTP-protected password change
- **Order history** with product details, order cancellation (only when in "preparation" status)

### Authentication & Security
- **Two-step registration** with email OTP verification (6-digit code, 10-minute expiry)
- **Login** with automatic redirect (admin → warehouse, customer → home)
- Passwords stored with `password_hash()` (bcrypt)
- **Prepared statements** against SQL injection
- Server-side input validation

### Admin Panel
- **Warehouse management**: product CRUD, categories, multiple image upload with drag-and-drop (SortableJS), "New Collection" toggle
- **Order management**: filterable and sortable table, status updates (preparation → shipped → delivered), automatic email notification
- **Sales reports**: KPI dashboard (revenue, best-selling product, new customers), AJAX customer reports

### System
- **Auto-migration**: MySQL schema is automatically created and updated on startup
- **Email system**: PHPMailer + Gmail SMTP with branded HTML templates, all emails logged in `emails_outbox`
- **Soft delete** for products (trash without physical deletion)
- **Real-time inventory**: immediate stock deduction on cart add, restoration on expiry/removal

## Technologies

| Category | Technology |
|---|---|
| **Backend** | PHP (vanilla, no framework) |
| **Database** | MySQL / MariaDB |
| **Frontend CSS** | Bootstrap 5.3.2, Bootstrap Icons 1.11.3 |
| **Font** | Inter (Google Fonts) |
| **JavaScript** | Vanilla JS (ES6+), Fetch API, IntersectionObserver |
| **Libraries** | SortableJS (admin drag-and-drop) |
| **Email** | PHPMailer 6.12 via Composer |
| **SMTP** | Gmail (TLS, App Password) |
| **Security** | password_hash/verify, prepared statements, htmlspecialchars, filter_var |

## Project Structure

```
mike-sully-shop/
├── homePage.php              # Home / product catalog
├── loginPage.php             # Login
├── registrationPage.php      # Registration with OTP
├── cart.php                  # Cart with timer
├── productDetail.php         # Product detail
├── shippingPage.php          # Checkout
├── confirmPage.php           # Order confirmation
├── wishlist.php              # Wishlist
├── ordini.php                # Order history
├── profilo.php               # User profile
├── gdpr.php                  # Privacy policy
├── adminMagazzino.php         # Admin: warehouse
├── adminOrdini.php           # Admin: orders
├── adminVendite.php          # Admin: sales reports
├── assets/
│   ├── css/                  # Stylesheets
│   ├── js/                   # JavaScript (cart, wishlist)
│   └── img/                  # Product images and logo
└── php/
    ├── db_connection.php      # MySQL connection
    ├── site_bootstrap.php     # Schema auto-migration
    ├── mailer.php             # Email sending
    ├── auth_check.php         # Access control middleware
    ├── order_functions.php    # Order business logic
    └── vendor/                # PHPMailer (Composer)
```

## Future Improvements

- **Real payment integration** with Stripe or PayPal API
- **Shipping gateway** with courier APIs (BRT, Poste Italiane, DHL)
- **Product reviews and ratings**
- **Multi-language support** (i18n)
- **Dark mode** theme toggle
- **Password reset** via email
- **Coupon / discount code system**
- **CSV export** for admin reports
- **Docker containerization** for simplified deployment
- **Server-side pagination** for large catalogs
