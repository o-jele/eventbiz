# Rental Deposit Accounting SOP (MVP)

Per SPEC §8.4 / §15. Accountant to confirm exact account names.

1. Quotation sets `deposit_required` (free amount, no fixed %).
2. Customer pays deposit → `Payment Entry` posts to **liability** `Customer Rental Deposits - GD`, NOT `Rental Income - GD`.
3. On `Rental Return` inspection:
   - Good → no charge, equipment back to Available.
   - Damaged → post to `Rental Damage & Shortage Charges - GD` + repair flag.
   - Missing → charge `custom_replacement_rate`, post to same charges account, Material Issue write-off with approval.
   - Cleaning → charge as agreed.
4. Settle: `refund_due = deposit_received - damage - missing - cleaning - forfeited` (see `settle_deposit()`).
5. Refund via `Payment Entry` (Pay) or apply to final invoice via `Journal Entry`. Forfeit only per signed terms.

Never recognise deposit as revenue on receipt.
