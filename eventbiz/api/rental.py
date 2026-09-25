"""Rental engine: availability, confirm, dispatch, return (SPEC §8)."""

from datetime import date


def overlaps(a_start, a_end, b_start, b_end) -> bool:
    return max(a_start, b_start) <= min(a_end, b_end)


def available_qty(*, on_hand: int, overlapping_reserved: int, maintenance: int = 0) -> int:
    return max(0, on_hand - overlapping_reserved - maintenance)


def validate_rental_booking(doc, method=None) -> None:
    """Frappe validate hook: dates, deposit present, availability hint.

    Full availability needs DB; unit-testable pure part lives here.
    """
    items = list(getattr(doc, "rental_items", None) or getattr(doc, "items", None) or [])
    if not items:
        _throw("Rental Booking needs at least one item row.")
    ev = getattr(doc, "event_date", None)
    ret = getattr(doc, "return_date_expected", None)
    if ev and ret and ret < ev:
        _throw("Expected Return must be on/after Event Date.")
    if getattr(doc, "deposit_required", None) is None:
        _throw("Deposit Required must be set from the quotation (no fixed %).")


def on_rental_booking_submit(doc, method=None) -> None:
    from eventbiz.permissions import validate_company_access

    validate_company_access(doc)


def validate_rental_return(doc, method=None) -> None:
    rows = list(getattr(doc, "items", None) or [])
    if not rows:
        _throw("Rental Return needs one row per dispatched item.")
    for r in rows:
        disp = int(getattr(r, "qty_dispatched", 0) or 0)
        good = int(getattr(r, "qty_good", 0) or 0)
        dmg = int(getattr(r, "qty_damaged", 0) or 0)
        miss = int(getattr(r, "qty_missing", 0) or 0)
        if good + dmg + miss != disp:
            _throw(
                f"Row {getattr(r, 'item', '?')}: good({good})+damaged({dmg})"
                f"+missing({miss}) must equal dispatched({disp})."
            )


def settle_deposit(*, deposit_received: float, damage: float = 0.0,
                   missing: float = 0.0, cleaning: float = 0.0,
                   forfeited: float = 0.0) -> dict:
    """Pure deposit settlement (T4). Never posts GL — caller creates Journal/Payment."""
    applied = damage + missing + cleaning + forfeited
    refund = max(0.0, (deposit_received or 0.0) - applied)
    return {"applied": applied, "refund_due": refund}


def _throw(msg: str) -> None:
    try:
        import frappe

        frappe.throw(msg)
    except ImportError:
        raise ValueError(msg)
