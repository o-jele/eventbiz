"""Payment verification tests (SPEC T3). No Frappe needed."""

import pytest

from eventbiz.api.payments import approve_payment_declaration


class Obj:
    def __init__(self, **kw):
        self.__dict__.update(kw)


def test_only_accounts_can_approve():
    doc = Obj(company="Glamorous Creations", customer="Jane Banda", amount=300_000,
              payment_method="Bank Transfer", payment_reference="TX123",
              payment_date="2026-12-01", status="Pending Verification")
    with pytest.raises(PermissionError):
        approve_payment_declaration(doc, ["Creations Staff"])
    pe = approve_payment_declaration(doc, ["Accounts Manager"])
    assert pe["company"] == "Glamorous Creations"
    assert pe["paid_amount"] == 300_000
    assert pe["doctype"] == "Payment Entry"
