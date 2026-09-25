"""Business Context routing tests (SPEC T2). No Frappe needed."""

from eventbiz.business_context import get_business_context, warehouse_for


def test_creations_routes():
    for p in ["/creations", "/creations/shop", "/shop", "/products", "/cart", "/checkout"]:
        assert get_business_context(p)["company"] == "Glamorous Creations", p


def test_delights_routes():
    for p in ["/delights", "/delights/cakes", "/catering", "/rentals", "/events", "/request"]:
        assert get_business_context(p)["company"] == "Glamorous Delights", p


def test_warehouse_routing():
    assert warehouse_for("Glamorous Creations") == "CREATIONS - SHOP - GC"
    assert warehouse_for("Glamorous Delights", "Rental") == "DELIGHTS - RENTAL - GD"
    assert warehouse_for("Glamorous Delights", "Catering") == "DELIGHTS - CATERING - GD"
