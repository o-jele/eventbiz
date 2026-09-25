"""Event + Event Quotation helpers (SPEC §9)."""


def quotation_totals(services: list) -> dict:
    subtotal = sum(float(s.get("amount", 0) or 0) for s in services)
    return {"subtotal": subtotal, "grand_total": subtotal}


def balance_due(*, grand_total: float, deposit_required: float) -> float:
    return max(0.0, (grand_total or 0.0) - (deposit_required or 0.0))
