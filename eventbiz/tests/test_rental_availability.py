"""Rental pure-logic tests (SPEC T4/T5). No Frappe needed."""

from datetime import date

import pytest

from eventbiz.api.rental import available_qty, overlaps, settle_deposit, validate_rental_booking, validate_rental_return


class Obj:
    def __init__(self, **kw):
        self.__dict__.update(kw)


def test_overlaps():
    assert overlaps(date(2026, 12, 12), date(2026, 12, 14), date(2026, 12, 13), date(2026, 12, 15))
    assert not overlaps(date(2026, 12, 12), date(2026, 12, 14), date(2026, 12, 15), date(2026, 12, 16))


def test_available_qty():
    assert available_qty(on_hand=300, overlapping_reserved=300) == 0
    assert available_qty(on_hand=300, overlapping_reserved=10) == 290


def test_no_fixed_deposit_pct():
    # T4: arbitrary deposits must validate (no % rule).
    for total, dep in [(900_000, 300_000), (2_500_000, 750_000)]:
        doc = Obj(rental_items=[Obj()], event_date=date(2026, 12, 12),
                  return_date_expected=date(2026, 12, 14), deposit_required=dep)
        validate_rental_booking(doc)  # must not raise


def test_return_qty_must_balance():
    ok = Obj(items=[Obj(item="Chair", qty_dispatched=10, qty_good=8, qty_damaged=1, qty_missing=1)])
    validate_rental_return(ok)
    bad = Obj(items=[Obj(item="Chair", qty_dispatched=10, qty_good=8, qty_damaged=1, qty_missing=0)])
    with pytest.raises(Exception):
        validate_rental_return(bad)


def test_settle_deposit():
    r = settle_deposit(deposit_received=300_000, damage=20_000, missing=0, cleaning=5_000)
    assert r == {"applied": 25_000, "refund_due": 275_000}
