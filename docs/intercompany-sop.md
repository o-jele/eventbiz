# Intercompany SOP (MVP — manual, SPEC §6)

Scenario: Delights needs flour owned by Creations.

FORBIDDEN: Stock Entry from `CREATIONS - STORE - GC` to any `DELIGHTS - * - GD` warehouse
(enforced by `validate_stock_entry_company`).

REQUIRED:
1. GC: Sales Order → Sales Invoice → Delivery Note. Customer = `Glamorous Delights - Intercompany`
   (`is_internal_customer=1`, `represents_company=Glamorous Delights`).
2. GD: Purchase Order → Purchase Receipt → Purchase Invoice. Supplier = `Glamorous Creations - Intercompany`
   (`is_internal_supplier=1`, `represents_company=Glamorous Creations`).
3. Price at cost/valuation rate + handling unless accountant advises otherwise.
4. Reconcile monthly; eliminate intercompany balances in group view.
