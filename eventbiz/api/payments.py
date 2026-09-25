"""Manual payment verification (SPEC §7.2, acceptance T3).

Customer submit creates a Declaration (no GL). Accounts approve → creates
Payment Entry and links it. Never auto-post on submit.
"""

APPROVER_ROLE = "Accounts Manager"


def on_payment_declaration_submit(doc, method=None) -> None:
    # Submit moves Submitted → Pending Verification; GL posting happens only
    # in approve_payment_declaration by Accounts Manager.
    if getattr(doc, "status", None) == "Submitted":
        doc.status = "Pending Verification"


def approve_payment_declaration(doc, approver_roles: list) -> dict:
    """Return Payment Entry skeleton. Caller saves + links it, sets Approved."""
    if APPROVER_ROLE not in (approver_roles or []) and "System Manager" not in (approver_roles or []):
        raise PermissionError("Only Accounts Manager can approve payments.")
    if getattr(doc, "status", "") not in ("Submitted", "Pending Verification"):
        raise ValueError(f"Cannot approve from status {getattr(doc, 'status', '')!r}.")
    return {
        "doctype": "Payment Entry",
        "payment_type": "Receive",
        "company": doc.company,
        "party_type": "Customer",
        "party": doc.customer,
        "paid_amount": doc.amount,
        "received_amount": doc.amount,
        "mode_of_payment": doc.payment_method,
        "reference_no": doc.payment_reference,
        "reference_date": doc.payment_date,
    }
