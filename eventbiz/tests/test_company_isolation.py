"""Company isolation guardrail test (SPEC T1/T8). No Frappe needed."""

from eventbiz.permissions import COMPANY_SCOPE


def test_scopes_are_disjoint_except_accounts():
    creations = set(COMPANY_SCOPE["Creations Staff"])
    delights_ops = set(COMPANY_SCOPE["Delights Operations"])
    assert creations.isdisjoint(delights_ops)
    assert set(COMPANY_SCOPE["Accounts Manager"]) == {"Glamorous Creations", "Glamorous Delights"}


def test_all_doctype_json_have_company():
    import json
    from pathlib import Path

    root = Path(__file__).resolve().parents[1] / "doctype"
    skip = {"event_quotation_service", "rental_booking_item", "rental_return_item"}
    for d in root.iterdir():
        if not d.is_dir() or d.name in skip:
            continue
        data = json.loads((d / f"{d.name}.json").read_text())
        fields = {f["fieldname"] for f in data["fields"]}
        assert "company" in fields or "company_context" in fields, f"{d.name} missing company field"
