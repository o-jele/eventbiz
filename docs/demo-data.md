# Demo data (dev/test only)

Load once on a dev database **after** `install.php`:

```bash
mysql glamorous < public_html/app/database/seed-demo.sql
```

**Never run this on production** — production gets a fresh `install.php` run.
`seed-demo.sql` is not referenced by the installer and is not in the deploy bundle path.

## Logins (all password `DemoPass123!`)

| Email | Role | Try |
|-------|------|-----|
| `accounts@glamorous.mw` | Accounts Manager | Staff → Payments (verify), Reports, Transfers |
| `sales@glamorous.mw` | Delights Sales | Enquiries → Convert, Events → New quotation → Convert |
| `ops@glamorous.mw` | Delights Ops | Rentals (confirm → dispatch → return), Bakery/Catering boards |
| `jane@demo.mw` | Customer | My Glamorous, declare a deposit, approve the demo quotation |
| `shop@glamorous.mw` (`ShopPass123!`) | Creations Staff | Counter sale, Items |

## What's seeded

- 25 catalogue items (ingredients, tools, packaging, rental gear, menus, services), published with stock
- Customers Jane Banda + Chikondi Phiri, supplier Lilongwe Millers
- Demo event "Demo Wedding - Banda" (Enquiry) with a Draft quotation (chairs + catering + delivery)
- Draft rental booking (200 chairs + 25 tables) — Draft holds no stock

## Suggested tour

1. Shop: `/creations/shop` → cart → checkout → success page.
2. Jane: log in → approve nothing yet → `/declare?against=rental:N` after staff confirm a booking.
3. Sales: Events → open demo event → New quotation → Convert.
4. Ops: Rentals → confirm demo booking → dispatch → return with damage.
5. Accounts: verify a declared payment, check Reports.
