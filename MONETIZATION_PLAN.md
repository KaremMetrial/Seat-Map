# Seat Map Monetization Plan

## Current State

### ✅ Already Built

| Feature | Status | Details |
|---------|--------|---------|
| Templates | Done | VenueTemplate, TemplateController - create/manage layouts |
| Elements | Done | 12 types: seat, section, table, stage, shape, entrance, text, aisle, corridor, emergency_exit, standing_zone, toilet, zone |
| Zones | Done | TemplateZone, ZoneController - group elements |
| Events | Done | Event model + controller - dates/times |
| Bookings | Done | Full booking flow with lock mechanism |
| Pricing | Done | PricingService, PricingRule model - price calculation |
| Venues | Done | Venue model + controller |
| API Auth | Done | Laravel Sanctum |
| Public Seatmap | Done | Read-only endpoints for customers |
| Seat Locking | Done | 10-min locks for cart-like behavior |

### ❌ What's Missing

| Feature | Priority | Status |
|---------|----------|--------|
| Checkout/Payment | HIGH | No Stripe or payment integration |
| Set Prices API | HIGH | PricingService exists but no endpoints to configure prices |
| Ticket Types | HIGH | No VIP/General Admission tiers |
| Customer Auth | HIGH | Public can only view, can't buy |
| Email Notifications | MEDIUM | No ticket confirmations |
| Reports Dashboard | MEDIUM | No sales analytics for venues |
| Multi-tenant SaaS | MEDIUM | Single venue, need multi-venue support |
| Admin Panel | LOW | No frontend dashboard |

---

## Monetization Path

### Target Market
- Small theaters (50-500 seats)
- Local concert venues
- Conference halls
- Event spaces

### Revenue Model
- **Monthly subscription**: $49-199/month per venue
- **Per-booking fee**: 2-5% of transaction

### Phase 1: MVP (Weeks 1-4)
Focus: Get first 3-5 venues paying

### Phase 2: Growth (Weeks 5-8)
Focus: Add features that justify higher pricing

---

## Implementation Tasks

### Phase 1: Checkout Flow (Week 1-2)

#### Task 1.1: Customer Registration
```
Status: ❌ Not started
API: POST /api/v1/customers/register
API: POST /api/v1/customers/login
API: POST /api/v1/customers/logout
Models: Customer (new)
```
- Add customer registration/login
- JWT or Sanctum for customers
- Password reset flow

#### Task 1.2: Price Configuration API
```
Status: ⚠️ Partial - PricingService exists
API: GET /api/v1/events/{event}/pricing
API: POST /api/v1/events/{event}/pricing
API: POST /api/v1/sections/{section}/price
Models: TicketType (new), EventPricing (new)
```
- Create ticket types (VIP, General, Early Bird)
- Assign prices per section/zone
- Date-based pricing rules

#### Task 1.3: Checkout API
```
Status: ❌ Not started
API: POST /api/v1/checkout/initiate
API: POST /api/v1/checkout/complete
API: GET /api/v1/orders/{order}
Models: Order (new), OrderItem (new)
```
- Create order from booking
- Calculate total with fees
- Handle abandoned carts

#### Task 1.4: Payment Integration
```
Status: ❌ Not started
Service: PaymentService (new)
Provider: Stripe (recommended)
```
- Stripe checkout integration
- Webhook handling for payment events
- Refund processing

### Phase 2: Notifications & Reports (Week 3)

#### Task 2.1: Email Service
```
Status: ❌ Not started
Service: NotificationService
Mailable: TicketConfirmation, BookingReminder
```
- Send ticket confirmation with QR code
- Event reminders
- Receipt emails

#### Task 2.2: Dashboard API
```
Status: ❌ Not started
API: GET /api/v1/venues/{venue}/dashboard
API: GET /api/v1/venues/{venue}/reports/sales
API: GET /api/v1/venues/{venue}/reports/occupancy
```
- Today's revenue
- Tickets sold vs available
- Revenue by section/zone

### Phase 3: Multi-tenant SaaS (Week 4)

#### Task 3.1: Venue Subscription
```
Status: ❌ Not started
Models: Subscription, Plan
API: POST /api/v1/admin/subscriptions
```
- Plan tiers (Basic, Pro, Enterprise)
- Subscription status tracking
- Feature flags per plan

#### Task 3.2: Super Admin
```
Status: ❌ Not started
Middleware: CheckSuperAdmin
Routes: /api/v1/admin/*
```
- Manage all venues
- View platform analytics
- Handle billing

---

## How to Execute

### Step 1: Set Up Stripe
```bash
cd Api
composer require stripe/stripe-php
```
1. Create Stripe account
2. Get API keys
3. Add to .env:
   ```
   STRIPE_KEY=pk_test_...
   STRIPE_SECRET=sk_test_...
   STRIPE_WEBHOOK_SECRET=whsec_...
   ```

### Step 2: Create Customer Model
```php
// app/Models/Customer.php
<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Customer extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password'];
}
```

### Step 3: Create Order Models
```php
// app/Models/Order.php
// app/Models/OrderItem.php
```
- Order: customer_id, event_id, total, status, stripe_payment_id
- OrderItem: order_id, seat_id, price, ticket_type

### Step 4: Build Checkout Controller
```php
// app/Http/Controllers/Api/V1/CheckoutController.php
```

### Step 5: Integrate Stripe Checkout
```php
// In CheckoutController::initiate()
$session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card'],
    'line_items' => [...],
    'mode' => 'payment',
    'success_url' => url('/api/v1/checkout/success?session_id={CHECKOUT_SESSION_ID}'),
    'cancel_url' => url('/api/v1/checkout/cancel'),
]);
```

### Step 6: Webhook Handler
```php
// app/Http/Controllers/Api/V1/WebhookController.php
// Route: POST /api/v1/webhooks/stripe
```

### Step 7: Email Tickets
```bash
composer require symfony/mailer
```
- Generate unique ticket codes
- QR code generation
- PDF ticket attachment

---

## Quick Wins Checklist

- [ ] Add customer auth (register/login)
- [ ] Create ticket types API
- [ ] Add price to sections/zones
- [ ] Build checkout endpoint
- [ ] Integrate Stripe
- [ ] Handle webhook
- [ ] Send confirmation email
- [ ] Add dashboard stats
- [ ] Add subscription model
- [ ] Build admin panel

---

## Resources

- Stripe Docs: https://stripe.com/docs
- Laravel Cashier: https://laravel.com/docs/billing
- QR Code: https://github.com/Bacon/BaconQrCode

---

*Last Updated: 2026-05-13*