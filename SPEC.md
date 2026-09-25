# Glamorous — SPEC.md
## Two-Company ERPNext Implementation (Creations + Delights)

**Version:** 1.0.0-draft  
**Status:** Ready for implementation  
**Stack:** Frappe Framework + ERPNext (v15+) + Custom App `eventbiz`  
**Site strategy:** Single shared Frappe site, two Companies  
**Domain:** www.glamorous.mw → `/creations/*`, `/delights/*`  
**Repo root:** `E:\Development\Glamorous`  
**Custom app name:** `eventbiz`

---

## 0. Critical Principle (normative)

> **Shared software and customer identity, but strictly separated legal / financial / stock operations.**

This means:

1. ONE Frappe site + ONE `eventbiz` app + ONE website.
2. TWO ERPNext Companies with separate GL, AR/AP, bank/cash, warehouses, stock, sales, purchases, tax, reports.
3. ONE shared Customer master (same `Customer` identity can transact with both companies).
4. Every transactional document MUST have exactly one `company`.
5. Website route determines default `company` via configuration, never hard-coded in multiple places.

No implementation that mixes GL/stock across companies is acceptable.

---

## 1. Architecture Overview

```text
www.glamorous.mw
│
├── / , /creations/shop, /products, /product/..., /cart, /checkout
│   → Company = Glamorous Creations
│
└── /delights/cakes, /fritters, /catering, /rentals, /events, /request
    → Company = Glamorous Delights

Shared Frappe Site
└── eventbiz (custom app)
    └── ERPNext Core
        ├── Company: Glamorous Creations (accounts, stock, sales, purchases)
        ├── Company: Glamorous Delights  (accounts, stock, sales, purchases)
        └── Shared: Customer identity + portal
```

### 1.1 App responsibilities

| Layer | Owned by | Responsibility |
|-------|----------|----------------|
| Frappe | framework | auth, roles, permissions, website, background jobs |
| ERPNext Core | standard | Company, Item, Customer, Supplier, Warehouse, Stock Entry, Sales Order, Sales Invoice, Purchase Order, Purchase Invoice, Payment Entry, Quotation, POS, UOM, Price List |
| `eventbiz` | custom | Business Context config, Customer Enquiry, Glamorous Event, Event Quotation, Cake Order, Rental Booking, Rental Return, Payment Verification, website controllers for `/creations` + `/delights`, customer dashboard `My Glamorous`, reports |

Do NOT fork ERPNext doctypes. Extend via custom fields / custom doctypes / server scripts in `eventbiz` only.

---

## 2. Companies & Master Data

### 2.1 Companies to create (via ERPNext setup, seeded by fixtures)

| # | Company Name (exact) | Abbr | Default Currency | Country | Purpose |
|---|----------------------|------|------------------|---------|---------|
| 1 | Glamorous Creations | GC | MWK | Malawi | Retail baking supplies |
| 2 | Glamorous Delights | GD | MWK | Malawi | Bakery production, catering, rentals, events |

Each Company gets its own:

- Chart of Accounts (from standard Malawi template, then accountant-reviewed)
- General Ledger
- AR / AP
- Sales / Purchase taxes and charges templates
- Bank / Cash / Mobile Money accounts (see §7)
- Warehouses (see §3)
- Cost Centers: `Main - GC`, `Main - GD` minimum. Do NOT use cost centres to simulate companies.
- Price Lists: `Creations Retail - GC`, `Delights Services - GD`
- POS Profiles (Creations only in MVP)
- Tax Templates (VAT/exempt per accountant advice)

Acceptance: posting a Sales Invoice for GC must not appear in GD trial balance and vice versa.

### 2.2 Users & Roles (MVP)

Standard ERPNext roles + these `eventbiz` roles:

| Role | Description |
|------|-------------|
| `Creations Staff` | Shop/POS/Creations stock only |
| `Delights Operations` | Events, bakery, catering, rental dispatch/return |
| `Delights Sales` | Enquiries, events, quotations, cake orders |
| `Accounts Manager` | Both companies: invoices, payments, reconciliation, reports |
| `System Manager` | Full (founders/dev only) |
| `Customer` (portal) | Website user, sees only own records |

Permission matrix — see §10.

---

## 3. Warehouse Model (MVP — do not over-create)

Create exactly these 5 active warehouses in Phase 1. Add more only on proven operational need.

| Warehouse Name (exact) | Company | Parent Group | Purpose |
|------------------------|---------|--------------|---------|
| `CREATIONS - SHOP - GC` | Glamorous Creations | `Creations Premises - GC` | Retail shelf / POS |
| `CREATIONS - STORE - GC` | Glamorous Creations | `Creations Premises - GC` | Bulk / receiving |
| `DELIGHTS - BAKERY - GD` | Glamorous Delights | `Delights Premises - GD` | Ingredients + finished cakes/fritters |
| `DELIGHTS - CATERING - GD` | Glamorous Delights | `Delights Premises - GD` | Catering consumables, food stock |
| `DELIGHTS - RENTAL - GD` | Glamorous Delights | `Delights Premises - GD` | Rental equipment domicile (reservation state managed by Rental Booking, not stock sale) |

Rules:

- Warehouse `company` field MUST match owning Company. No cross-company stock posting.
- Warehouse groups `Creations Premises - GC` and `Delights Premises - GD` are groups (`is_group=1`), company-specific.
- Inter-warehouse transfer across companies is FORBIDDEN via Stock Entry. Use intercompany sale/purchase (§6).
- Rental equipment Items live in `DELIGHTS - RENTAL - GD` but are NOT sold. Availability = `owned_qty - reserved_qty - maintenance_qty`. Enforced by `eventbiz` rental engine, not by ERPNext stock ledger alone.

Future (not MVP): `Receiving Area`, `Dispatch / Returns Area` as sub-locations or additional warehouses if volume justifies.

---

## 4. Item Model (use ERPNext `Item`, no parallel product tables)

### 4.1 Creations products (retail goods)

Use standard `Item`:

- Required: `item_code` (SKU), `item_name`, `item_group` (e.g. `Flour`, `Baking Tools`, `Packaging`), `stock_uom`, `is_stock_item=1`, `is_sales_item=1`, `is_purchase_item=1`, `valuation_method=FIFO`, `default_warehouse`, website fields.
- Company-specific defaults via `Item Default` child table: one row per Company (`company`, `default_warehouse`, `default_price_list`).
- Website: `show_in_website=1`, `website_image`, `description`, `standard_selling_rate`.
- Custom fields (via `eventbiz` fixtures, on `Item`):
  - `custom_business_unit`: Select `Creations | Delights | Shared` (default per route)
  - `custom_featured`: Check
  - `custom_reorder_level_display`: Float (informational; use ERPNext `auto reorder` for logic)

### 4.2 Delights model — three Item types

All are ERPNext `Item` with different flags:

| Type | Examples | `is_stock_item` | `is_sales_item` | `is_purchase_item` | `is_fixed_asset` | Notes |
|------|----------|-----------------|-----------------|--------------------|------------------|-------|
| Stock product | cake, cupcake, fritter, samosa | 1 | 1 | 1 (ingredients) | 0 | Finished goods via Manufacture/Stock Entry or Purchase |
| Rental product | chair, table, tent, plate, glass, cooker | 1* | 1 (rental charge) | 1 | 0 or 1** | *Stock-tracked for qty-on-hand; availability managed by rental engine. See §8. |
| Service | catering per-head, delivery, setup, staffing, decoration | 0 | 1 | 0 | 0 | Non-stock, `is_stock_item=0` |

** Decide with accountant whether high-value rental assets (tents, cookers) are Fixed Assets. MVP default: stock-tracked Items + reservation logic; revisit fixed-asset register in Phase 6.

Item Groups (seed):

```text
Creations
├── Baking Ingredients
├── Baking Tools & Equipment
└── Packaging

Delights - Bakery
├── Cakes
├── Fritters & Snacks
└── Bakery Ingredients

Delights - Catering
├── Catering Menus
└── Catering Services

Delights - Rental
├── Furniture
├── Cookware & Serving
└── Tents & Decor

Services
├── Delivery & Setup
└── Event Staffing
```

---

## 5. Customer Model (shared identity, company-specific transactions)

- Use single ERPNext `Customer` doctype for group-wide identity.
- Required fields: `customer_name`, `mobile_no` (custom mandatory for MW), `email_id`, `customer_group` (default `Retail / Events`), `territory` (default `Malawi`).
- Custom fields on `Customer` (via fixtures):
  - `custom_primary_phone`: Data (mandatory)
  - `custom_whatsapp`: Data
  - `custom_preferred_contact`: Select `Phone | WhatsApp | Email`
- Transactions (`Sales Order`, `Sales Invoice`, `Payment Entry`, `Quotation`, `Rental Booking`, etc.) each carry exactly one `company`. Same `customer` link may appear under GC and GD documents — this is intended.
- Portal user links to one `Customer` via `Portal User` / Contact. Customer dashboard aggregates across both companies but each row shows its `company`.
- Do NOT create `Jane Banda - GC` and `Jane Banda - GD` duplicates. Dedupe on phone + name.

---

## 6. Intercompany Transactions (explicit, no silent moves)

Scenario: Delights needs 20kg flour physically owned by Creations.

Forbidden: direct Stock Entry from `CREATIONS - STORE - GC` to `DELIGHTS - BAKERY - GD`.

Required (MVP manual, using standard ERPNext):

1. `Sales Order` + `Sales Invoice` (Company=GC, customer=`Glamorous Delights` as Internal Customer) + `Delivery Note` out of Creations warehouse.
2. `Purchase Order` + `Purchase Receipt` + `Purchase Invoice` (Company=GD, supplier=`Glamorous Creations` as Internal Supplier) into Delights warehouse.
3. Optional: use ERPNext `Inter Company` invoice creation (`make_inter_company_transaction`) where available.

Setup required:

- Create `Customer` record `Glamorous Delights - Intercompany` (represents GD to GC) with `is_internal_customer=1`, `represents_company=Glamorous Delights`.
- Create `Supplier` record `Glamorous Creations - Intercompany` with `is_internal_supplier=1`, `represents_company=Glamorous Creations`.
- Price: at cost or agreed transfer price (accountant to confirm; default = valuation rate + handling).

Custom automation: DEFERRED. Only if frequency justifies, add `eventbiz` server action `create_intercompany_transfer` in Phase 6+. MVP = manual with documented SOP.

---

## 7. Payments — Manual First (no gateway in MVP)

### 7.1 Payment methods (Select, consistent everywhere)

```text
Cash
Bank Transfer
Mobile Money
Other Manual
```

Maps to per-company Mode of Payment → Account:

| Company | Mode of Payment | Account |
|---------|-----------------|---------|
| GC | Cash | `Cash - GC` |
| GC | Bank Transfer | `Bank Account - GC` (exact name per CoA) |
| GC | Mobile Money | `Mobile Money - GC` |
| GD | Cash | `Cash - GD` |
| GD | Bank Transfer | `Bank Account - GD` |
| GD | Mobile Money | `Mobile Money - GD` |

Exact GL account names to be confirmed by accountant; fixtures must reference by `account_name` variable, not hard-coded IDs.

### 7.2 Payment Verification workflow (custom doctype)

New DocType: `Customer Payment Declaration` (module `Eventbiz`, `istable=0`, submittable).

Purpose: customer says “I paid”, accounts verifies before `Payment Entry`.

Fields:

| Fieldname | Label | Type | Req | Notes |
|-----------|-------|------|-----|-------|
| `company` | Company | Link: Company | Y | Must match invoice company |
| `customer` | Customer | Link: Customer | Y | |
| `reference_doctype` | Against Document Type | Select: `Sales Invoice | Sales Order | Rental Booking | Event Quotation | Cake Order` | Y | |
| `reference_name` | Against Document | Dynamic Link (`reference_doctype`) | Y | |
| `amount` | Amount Declared | Currency | Y | MWK |
| `payment_method` | Payment Method | Select (see §7.1) | Y | |
| `payment_reference` | Payer Reference | Data | Y | bank ref / mobile TxID |
| `payment_date` | Payment Date | Date | Y | |
| `proof_attachment` | Proof of Payment | Attach Image | N | photo/screenshot/PDF |
| `sender_account_detail` | Sender Account / Number | Data | N | last digits only |
| `status` | Status | Select: `Submitted \| Pending Verification \| Approved \| Rejected` | Y | default `Submitted` |
| `verified_by` | Verified By | Link: User | N | set on approve/reject |
| `payment_entry` | Payment Entry | Link: Payment Entry | N | set on approve |
| `remarks` | Remarks | Small Text | N | |

Workflow (Frappe Workflow, not just status):

```text
Submitted → Pending Verification (on submit) → Approved | Rejected
```

- Only `Accounts Manager` can Approve/Reject.
- On Approve: server script creates `Payment Entry` (company, customer, amount, mode_of_payment, reference) and links it. Declaration becomes read-only.
- Never auto-create Payment Entry on customer submit.

---

## 8. Rental Engine (core custom subsystem)

### 8.1 DocType: `Rental Booking` (submittable)

| Fieldname | Label | Type | Req | Notes |
|-----------|-------|------|-----|-------|
| `company` | Company | Link: Company | Y | default `Glamorous Delights`, read-only after submit |
| `customer` | Customer | Link: Customer | Y | |
| `event` | Event | Link: Glamorous Event | N | link if part of event |
| `booking_date` | Booking Date | Date | Y | default today |
| `event_date` | Event / Use Date | Date | Y | |
| `return_date_expected` | Expected Return | Date | Y | |
| `fulfilment_method` | Fulfilment | Select: `Customer Pickup \| Delivery` | Y | default Pickup |
| `delivery_address` | Delivery Address | Small Text | N | req if Delivery |
| `contact_person` / `contact_phone` | Contact | Data | N | req if Delivery |
| `delivery_charge` | Delivery Charge | Currency | N | service item |
| `rental_items` | Items | Table: Rental Booking Item | Y | min 1 row |
| `rental_total` | Rental Charges | Currency | Y | sum of items, read-only |
| `grand_total` | Total (incl delivery/setup) | Currency | Y | read-only |
| `deposit_required` | Deposit Required | Currency | Y | from quotation, NO fixed % |
| `deposit_received` | Deposit Received | Currency | N | via Payment Entry link total |
| `balance_due` | Balance Due | Currency | — | `grand_total - deposit_received` (allow deposit applied) |
| `status` | Status | Select: `Draft \| Quoted \| Deposit Pending \| Confirmed \| Dispatched \| At Customer \| Return Due \| Returned \| Completed \| Cancelled` | Y | workflow-driven |
| `payment_status` | Payment Status | Select | — | derived |

Child table: `Rental Booking Item`

| Fieldname | Type | Notes |
|-----------|------|-------|
| `item` | Link: Item (rental group only) | |
| `qty_requested` | Int, >0 | |
| `rate` | Currency | per unit per event (MVP flat, not per-day unless required) |
| `amount` | Currency | `qty*rate` |
| `warehouse` | Link: Warehouse | default `DELIGHTS - RENTAL - GD` |

Validations:

- `event_date < return_date_expected`.
- Availability check on validate + on submit (pessimistic): `available = on_hand - Σ overlapping confirmed bookings - maintenance`. Overlap = `[event_date, return_date_expected]` intersects existing `Confirmed|Dispatched|At Customer` bookings for same item.
- `deposit_required` is free input (from quotation). No global % constant.
- Status transitions enforced by Workflow (see §8.3).

### 8.2 DocType: `Rental Return` (submittable, one per booking, multiple rows)

| Fieldname | Label | Type | Notes |
|-----------|-------|------|-------|
| `rental_booking` | Rental Booking | Link, Y | must be `At Customer|Return Due|Dispatched` |
| `company` | Company | Link, Y | copied, read-only |
| `customer` | Customer | Link, Y | copied |
| `return_date_actual` | Actual Return Date | Date, Y | |
| `items` | Table: Rental Return Item | Y | one row per booked item |
| `damage_charges` | Currency | sum of damage rows |
| `missing_charges` | Currency | sum of missing rows |
| `cleaning_charges` | Currency | manual |
| `deposit_applied` | Currency | amount of deposit applied to charges |
| `refund_due` | Currency | `deposit_received - applied - forfeited`, ≥0 |
| `status` | Select: `Draft \| Inspected \| Settled \| Completed` | workflow |

Child: `Rental Return Item`

| Field | Type | Notes |
|-------|------|-------|
| `item` | Link: Item | copied |
| `qty_dispatched` | Int | copied |
| `qty_good` | Int | |
| `qty_damaged` | Int | + `damage_charge` per row |
| `qty_missing` | Int | + `missing_charge` per row |
| `remarks` | Small Text | |

Rules: `qty_good+qty_damaged+qty_missing == qty_dispatched`. Damaged → optionally create maintenance flag / Stock Entry to `Maintenance` (deferred; MVP = flag + charge). Missing → charge at replacement rate (Item custom field `custom_replacement_rate`).

### 8.3 Rental lifecycle states

```text
Available → Reserved (on Confirmed booking)
Reserved → Dispatched → At Customer → Return (Inspected)
  → Good → Available
  → Damaged → Maintenance/Repair → Available (+ charge)
  → Missing → Charge (no return to stock)
```

Implement as `status` on `Rental Booking` + `Rental Return.status`, plus a read-only availability report (SQL) — NOT as ERPNext stock ledger movements for reservation. Physical dispatch/return MAY create Stock Entries within GD only if accountant requires; default MVP = no stock ledger movement for rental dispatch, only for loss/damage write-off via Stock Entry (Material Issue) with approval.

### 8.4 Deposit accounting (normative)

- `Deposit Required` is a liability, NOT revenue.
- GL mapping (accountant to confirm names):
  - Rental Revenue → income account `Rental Income - GD`
  - Customer Deposit → liability `Customer Rental Deposits - GD`
  - Damage/Missing/Cleaning → income `Rental Damage & Shortage Charges - GD`
- MVP posting: Deposit `Payment Entry` → liability account (via `paid_to` override or `Advance` allocation). On return: apply via `Journal Entry` or `Payment Entry` refund/forfeit per SOP. `eventbiz` MUST NOT auto-post revenue on deposit receipt.
- Fixture a documented SOP in `docs/accounting-rental-deposits.md`.

---

## 9. Events & Services

### 9.1 DocType: `Glamorous Event` (submittable, central container)

| Fieldname | Label | Type | Req | Notes |
|-----------|-------|------|-----|-------|
| `company` | Company | Link | Y | always `Glamorous Delights` in MVP |
| `customer` | Customer | Link | Y | |
| `event_name` | Event Name | Data | Y | e.g. `Wedding - Banda Family` |
| `event_type` | Event Type | Select: `Wedding \| Funeral \| Corporate \| Party \| Function \| Other` | Y | |
| `event_date` | Event Date | Date | Y | |
| `venue` | Venue / Location | Data | Y | + `venue_address` Small Text |
| `guest_count` | Guests | Int | N | drives catering qty |
| `contact_person` / `contact_phone` | Contact | Data | N | |
| `catering_order` | Catering | Link: Catering Order* | N | created on demand |
| `cake_order` | Cake | Link: Cake Order | N | |
| `rental_booking` | Rental | Link: Rental Booking | N | |
| `status` | Status | Select: `Enquiry \| Quoted \| Deposit Pending \| Confirmed \| In Preparation \| Delivered \| Returns Pending \| Settled \| Completed \| Cancelled` | Y | workflow |
| `notes` | Notes | Small Text | N | |

* `Catering Order` may be MVP-light: use `Sales Order` + custom fields OR dedicated DocType if caterings need production steps. Decision: **MVP = dedicated `Catering Order` DocType** (simple, avoids overloading Sales Order).

### 9.2 DocType: `Catering Order` (submittable)

| Fieldname | Type | Notes |
|-----------|------|-------|
| `company` | Link, Y | GD |
| `customer` | Link, Y | |
| `event` | Link: Glamorous Event, N | |
| `service_date` | Date, Y | |
| `guest_count` | Int, Y | |
| `menu` | Link: Item (Catering Menus group), Y | e.g. `Wedding Menu A` |
| `qty` | Int | default = guest_count |
| `rate_per_head` | Currency | from Price List |
| `amount` | Currency | qty*rate |
| `delivery_setup_charge` | Currency | |
| `status` | Select: `Draft\|Confirmed\|In Preparation\|Delivered\|Invoiced\|Completed\|Cancelled` | |

Production: MVP manual (kitchen ticket print). No MRP. Ingredients deducted via `Stock Entry (Manufacture)` manually if tracked, else expensed.

### 9.3 DocType: `Cake Order` (submittable — covers cakes + fritters custom requests)

| Fieldname | Label | Type | Req | Notes |
|-----------|-------|------|-----|-------|
| `company` | Company | Link | Y | GD |
| `customer` | Customer | Link | Y | |
| `event` | Event | Link: Glamorous Event | N | |
| `order_type` | Type | Select: `Cake \| Fritters \| Other Bakery` | Y | |
| `product` | Base Product | Link: Item | Y | e.g. `5-Tier Wedding Cake` base |
| `quantity` | Qty | Int/Float | Y | |
| `required_date` | Required Date | Date/Datetime | Y | |
| `customization` | Customization Notes | Text | N | flavour, message, colours |
| `reference_image` | Reference Image | Attach Image | N | uploaded from website |
| `quotation` | Quotation | Link: Event Quotation / Quotation | N | |
| `price` | Agreed Price | Currency | N | set after quotation |
| `deposit_required` / `deposit_received` | | Currency | N | same liability rule as rental |
| `fulfilment_method` | | Select Pickup/Delivery | Y | + delivery fields as in §8.1 |
| `production_status` | | Select: `Pending \| Scheduled \| In Production \| Ready \| Delivered \| Cancelled` | Y | for bakery board |
| `status` | | Select: `Draft \| Awaiting Quotation \| Quoted \| Confirmed \| In Production \| Ready \| Delivered \| Invoiced \| Completed \| Cancelled` | Y | workflow |

Website “Request a custom product” creates `Cake Order` with `status=Awaiting Quotation`, NOT a Sales Order.

### 9.4 DocType: `Event Quotation` (submittable — main Delights sales tool)

Wraps standard ERPNext `Quotation` OR standalone? **Decision: standalone `Event Quotation` that can generate standard `Quotation` + `Sales Order`.** Rationale: multi-service (rental+catering+cake+delivery) in one polished screen; standard Quotation line structure is too flat for event UX.

| Fieldname | Type | Notes |
|-----------|------|-------|
| `company` | Link GD, Y | |
| `customer` | Link, Y | |
| `event` | Link: Glamorous Event, Y | auto-creates Event if blank (prompt) |
| `event_date`, `venue`, `guest_count` | copied/display | |
| `services` | Table: Event Quotation Service, Y | rental/catering/cake/other rows |
| `subtotal` | Currency, read-only | |
| `delivery_setup_total` | Currency | |
| `grand_total` | Currency | |
| `deposit_required` | Currency, Y | salesperson input |
| `balance_due` | Currency | grand - deposit |
| `valid_until` | Date | |
| `status` | Select: `Draft\|Sent\|Approved\|Rejected\|Converted\|Expired` | workflow |
| `sales_order` / `quotation` | Link | created on Convert |

Child: `Event Quotation Service`

| Field | Type | Notes |
|-------|------|-------|
| `service_type` | Select: `Rental\|Catering\|Bakery\|Delivery\|Setup\|Staffing\|Other` | |
| `item` | Link: Item | |
| `description` | Small Text | e.g. `300× chairs` |
| `qty` | Float | |
| `rate` | Currency | |
| `amount` | Currency | |

Actions (server):

- `generate_quotation_pdf` (print format `Event Quotation`)
- `convert_to_sales` → creates: `Rental Booking` (for rental rows), `Catering Order`, `Cake Order`, standard ERPNext `Sales Order` + `Sales Invoice` skeleton per Company rules. Must be atomic-ish; on failure roll back with error log.
- Deposit from this DocType flows to each child booking/order `deposit_required`.

---

## 10. Customer Enquiry Inbox (lightweight)

DocType: `Customer Enquiry` (non-submittable, `istable=0`).

| Fieldname | Label | Type | Req | Notes |
|-----------|-------|------|-----|-------|
| `company_context` | Company | Link: Company | Y | inferred from route, editable by staff |
| `enquiry_type` | Type | Select: `General \| Wedding \| Catering \| Rental \| Cake \| Product` | Y | |
| `customer` | Customer | Link | N | linked on triage if known |
| `name` / `phone` / `email` | Contact | Data | Y (phone or email) | website anonymous allowed |
| `subject` | Subject | Data | Y | |
| `message` | Message | Text | Y | |
| `event_date` | Event Date | Date | N | |
| `source` | Source | Select: `Website \| Walk-in \| Phone \| WhatsApp \| Referral` | Y | default Website |
| `status` | Status | Select: `Open \| Assigned \| Quoted \| Converted \| Closed \| Spam` | Y | default Open |
| `assigned_to` | Assigned To | Link: User | N | |
| `linked_event` / `linked_quotation` | Links | Dynamic | N | set on convert |

Flow: Website → Enquiry → Staff triage → Convert to `Glamorous Event` / `Cake Order` / `Sales Order` (Creations) / `Rental Booking`. Never auto-create ERP financial docs from anonymous web input.

---

## 11. Business Context (configuration concept)

> `Business Context = Company + Location + Business Unit`

Implementation: **NOT a new DocType in MVP.** Implement as:

1. Single config file in `eventbiz`: `eventbiz/business_context.py` (dict) + Site Config / Website Settings.
2. Route map:

| Route prefix | `company` | Default warehouse | Business unit |
|--------------|-----------|-------------------|---------------|
| `/creations`, `/shop`, `/products`, `/cart`, `/checkout` (Creations) | `Glamorous Creations` | `CREATIONS - SHOP - GC` | Creations Retail |
| `/delights`, `/cakes`, `/fritters`, `/catering`, `/rentals`, `/events`, `/request` | `Glamorous Delights` | `DELIGHTS - BAKERY - GD` (or RENTAL/CATERING per service) | Delights Events |

3. Every `eventbiz` website controller MUST call `get_business_context(path)` and set `company` on created docs. No hard-coded company strings in controllers except via this helper.
4. Future branches (Blantyre, Mzuzu) = new entries in same map, no redesign.

```python
# eventbiz/business_context.py (illustrative)
BUSINESS_CONTEXTS = {
  "creations": {"company": "Glamorous Creations", "default_warehouse": "CREATIONS - SHOP - GC"},
  "delights":  {"company": "Glamorous Delights",  "default_warehouse": "DELIGHTS - BAKERY - GD"},
}
ROUTE_MAP = [
  (r"^/(creations|shop|products|product|cart|checkout)", "creations"),
  (r"^/(delights|cakes|fritters|catering|rentals|events|request)", "delights"),
]
```

---

## 12. Website Architecture (single site, two storefronts)

Routes (Frappe Website / Portal):

```text
/                          → Glamorous homepage (both brands)
/creations                 → Creations landing
/creations/shop            → catalogue (ERPNext Item, show_in_website)
/creations/products        → alias
/creations/product/<slug>  → detail
/creations/cart|checkout   → Buy-now checkout (Sales Order, Company=GC)

/delights                  → Delights landing
/delights/cakes            → cake gallery + Request form → Cake Order
/delights/fritters         → fritter menu + Request
/delights/catering         → catering menus + event form → Enquiry/Event
/delights/rentals          → equipment catalogue + availability calendar → Rental Booking (Quoted)
/delights/events           → event gallery + enquiry
/delights/request          → generic service request → Customer Enquiry

/my-glamorous              → Customer dashboard (login required)
  ├── Orders (both companies, company badge per row)
  ├── Events, Quotations, Rental Bookings, Cake Orders
  ├── Invoices, Payments (+ Declare Payment button)
  └── Profile
```

Transaction types:

- **A. Buy now** (Creations, known price + stock): Cart → Checkout → `Sales Order` (GC) → `Sales Invoice` on payment confirm → Pickup/Delivery.
- **B. Request custom product** (cakes): Form + image + date → `Cake Order` (`Awaiting Quotation`) + `Customer Enquiry` link → staff quotes.
- **C. Request service** (catering/rental): Event details form → `Customer Enquiry` → `Glamorous Event` → `Event Quotation`.

Customer never needs to know Company; backend sets it. Every dashboard row displays brand badge (Creations/Delights) for clarity.

---

## 13. Delivery Model (pickup + manual delivery, no fleet)

Fields on `Sales Order`, `Cake Order`, `Rental Booking`, `Catering Order`:

- `fulfilment_method`: `Customer Pickup | Delivery`
- If Delivery: `delivery_address`, `contact_person`, `contact_phone`, `preferred_delivery_datetime`, `delivery_notes`, `delivery_charge`, `delivery_status`:
  ```text
  Not Required | Pending Arrangement | Arranged | Out for Delivery | Delivered
  ```
- MVP: manual status updates by staff. No driver app, GPS, route optimisation.

---

## 14. Permissions (company-aware, Frappe roles primary)

| Capability | Creations Staff | Delights Sales | Delights Operations | Accounts Manager |
|------------|-----------------|----------------|---------------------|------------------|
| GC Sales / POS / GC stock | Y | — | — | Y (both) |
| GD Events / Quotations / Cake | — | Y | Y (ops view) | Y |
| Rental Booking create | — | Y | Y | Y |
| Rental Dispatch/Return post | — | — | Y | Y (review) |
| Approve Payment Declaration | — | — | — | Y only |
| Post / edit GL, Journal, Payment Entry | — | — | — | Y only |
| View other company’s GL/stock | — | — | — | Y only |
| Intercompany docs | — | — | — | Y only |

Enforcement:

- Role Permissions on each custom DocType with `company` match condition where applicable.
- Server-side `validate_company_access(doc, user)` in `eventbiz/permissions.py` for sensitive writes (payments, returns, intercompany, convert actions). Never rely on client-side hiding alone.
- Accounts Manager is the ONLY role with both companies’ accounting scope.

---

## 15. Accounting Notes (to confirm with accountant)

- Currency MWK only in MVP. No multi-currency.
- VAT treatment per Malawi rules — confirm exempt vs taxable per product/service; encode in Item Tax Templates.
- Rental deposits → liability (`Customer Rental Deposits - GD`), never income on receipt.
- Forfeited/damage/missing/cleaning charges → income on settlement via Journal/Payment allocation.
- Bank/Mobile reconciliation via standard ERPNext Bank Reconciliation.
- Month-end: per-company P&L + Balance Sheet; group view via consolidated report (sum, with intercompany elimination note if intercompany used).

---

## 16. Reports (Phase 6)

Standard ERPNext + these `eventbiz` query/script reports:

1. `Creations P&L` / `Delights P&L` (per-company, standard, favourited)
2. `Group Sales Summary` (by company, brand badge)
3. `Inventory — Creations` / `Inventory — Delights`
4. `Rental Utilization` (by item, % days booked, revenue per asset)
5. `Event Profitability` (Event → revenue − catering/bakery/rental costs − delivery/setup)
6. `Bakery Profitability` (Cake/Fritter orders margin)
7. `Customer Balances` (AR ageing per company + combined view)
8. `Payment Reconciliation` (Declared vs Approved vs Payment Entry)

---

## 17. Phased Scope (normative MVP cut)

- [ ] **Phase 1 — ERP foundation:** 2 Companies, CoA, warehouses (§3), Customer master (§5), roles, suppliers, purchasing, basic stock, per-company cash/bank/mobile accounts, Modes of Payment.
- [ ] **Phase 2 — Creations:** catalogue (Item), POS profile, purchasing, Sales Order→Invoice→Payment, pickup/manual delivery, website catalogue + cart/checkout (Buy-now only).
- [ ] **Phase 3 — Delights core:** `Glamorous Event`, `Event Quotation`, `Cake Order`, `Catering Order`, `Customer Enquiry`, bakery production_status board, fritters flow.
- [ ] **Phase 4 — Rental:** `Rental Booking` + availability check, deposit (liability), dispatch, `Rental Return` (good/damage/missing), charges, maintenance flag.
- [ ] **Phase 5 — Website integration:** homepage, `/creations`, `/delights`, auth, all request forms, `My Glamorous` dashboard, `Customer Payment Declaration` upload.
- [ ] **Phase 6 — Reporting:** §16 + intercompany SOP + deposit accounting SOP + UAT + go-live.

### Explicitly NOT in MVP

Delivery fleet, driver tracking, GPS, online payment gateway, subscriptions, mobile app, loyalty, advanced CRM, seating planner, procurement automation, AI forecasting, multi-currency, custom GL, warehouse automation.

---

## 18. `eventbiz` Repo Layout (to scaffold)

```text
apps/eventbiz/
├── eventbiz/
│   ├── __init__.py
│   ├── hooks.py
│   ├── business_context.py        # §11
│   ├── permissions.py             # company-aware checks
│   ├── api/
│   │   ├── website.py             # cart, enquiry, cake/rental requests
│   │   ├── rental.py              # availability, confirm, dispatch, return
│   │   ├── events.py              # event + quotation convert
│   │   └── payments.py            # declaration approve → Payment Entry
│   ├── doctype/
│   │   ├── customer_enquiry/
│   │   ├── glamorous_event/
│   │   ├── event_quotation/ (+ event_quotation_service/)
│   │   ├── cake_order/
│   │   ├── catering_order/
│   │   ├── rental_booking/ (+ rental_booking_item/)
│   │   ├── rental_return/ (+ rental_return_item/)
│   │   └── customer_payment_declaration/
│   ├── print_format/
│   │   ├── event_quotation.html
│   │   └── rental_booking.html
│   ├── workflow/
│   │   └── workflows.json         # payment verification, rental, event
│   ├── fixtures/
│   │   ├── item_group.json, warehouse.json (seed names only; companies via setup)
│   │   ├── role.json, custom_field.json, mode_of_payment.json
│   │   └── print_format.json
│   └── www/
│       ├── index.html             # glamorous homepage
│       ├── creations/ delights/ my-glamorous/
├── docs/
│   ├── accounting-rental-deposits.md
│   ├── intercompany-sop.md
│   └── delivery-sop.md
└── tests/
    └── test_rental_availability.py, test_company_isolation.py, ...
```

Conventions: `fieldname` snake_case; DocType labels as in §§7–10; every custom DocType has `company` (except child tables); every server write validates `company` via `permissions.py`.

---

## 19. Acceptance Tests (must pass for go-live)

### T1 — Company isolation
1. Create `Sales Invoice` for GC + one for GD same Customer.
2. Assert GC Trial Balance excludes GD invoice and vice versa.
3. Assert GC user (`Creations Staff`) cannot open GD `Rental Booking` (permission error).

### T2 — Website company context
1. POST Buy-now via `/creations/checkout` → assert `Sales Order.company == Glamorous Creations` and warehouse starts with `CREATIONS`.
2. POST cake request via `/delights/cakes` → assert `Cake Order.company == Glamorous Delights`.
3. Grep `eventbiz/www` + `api/website.py`: no hard-coded company except `business_context.py`.

### T3 — Manual payment verification
1. Customer declares Bank Transfer MWK 300,000 + proof → `Customer Payment Declaration.status == Pending Verification`, NO `Payment Entry` exists.
2. Accounts approves → `Payment Entry` created with correct company/mode, declaration links it.
3. Reject path leaves no GL posting.

### T4 — Rental deposit (no fixed %)
1. Create Booking A total 900,000 deposit 300,000; Booking B total 2,500,000 deposit 750,000 — both save (no % validation error).
2. Deposit Payment → liability account, NOT `Rental Income`.
3. Return with 2 damaged chairs → damage charge posted, refund = deposit − charges; stock available increments only by good qty.

### T5 — Rental availability
1. Book 300 chairs `2026-12-12 → 2026-12-14` Confirmed.
2. Attempt overlapping booking same item/qty exceeding on-hand → blocked with clear message.
3. Non-overlapping dates → allowed.

### T6 — Event container
1. Create Event `Wedding - Banda 2026-12-12`, guest 300, link catering + rental + cake.
2. `Event Quotation` totals = sum of service rows; Convert creates linked `Rental Booking` + `Catering Order` + `Cake Order` + `Sales Order`.
3. Dashboard `My Glamorous` shows all under one customer with correct brand badges.

### T7 — Delivery
1. Sales Order with `fulfilment_method=Delivery` requires address/phone/date before submit.
2. Status flows `Pending Arrangement → Arranged → Out for Delivery → Delivered`; Pickup orders show `Not Required`.

### T8 — Intercompany guardrail
1. Attempt Stock Entry GC warehouse → GD warehouse → blocked with message directing to intercompany sale/purchase SOP.
2. Intercompany flour 20kg via SO/SI + PO/PR/PI posts correctly in each company’s ledger.

---

## 20. Open Questions for Accountant (block Phase 1 close)

1. Exact CoA names for Cash / Bank / Mobile Money per company.
2. VAT applicability per item group (retail goods vs catering vs rental vs cake).
3. Transfer pricing for intercompany stock moves (cost vs cost+%).
4. Whether high-value rental assets go to Fixed Asset register.
5. Deposit forfeit terms to encode in print formats / Ts&Cs.

---

## 21. Definition of Done (MVP)

- [ ] All §§2–14 implemented on a clean site with `eventbiz` installed; no manual DB hacks.
- [ ] Fixtures seed roles, warehouses, item groups, modes of payment, custom fields, workflows, print formats.
- [ ] All T1–T8 pass (automated where feasible: `test_company_isolation`, `test_rental_availability`, `test_payment_verification`).
- [ ] SOPs in `docs/` written and staff walkthrough recorded.
- [ ] `SPEC.md` version bumped and changelog appended on any scope change.

---

*End of SPEC v1.0.0-draft. Next step: scaffold `eventbiz` app per §18 and implement Phase 1 fixtures.*
