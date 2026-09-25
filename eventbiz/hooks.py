"""Frappe hooks for eventbiz — Glamorous two-company customisations."""

app_name = "eventbiz"
app_title = "Eventbiz"
app_publisher = "Glamorous"
app_description = "Glamorous Creations + Delights: events, rental, bakery, website"
app_version = "0.1.0"
required_apps = ["frappe", "erpnext"]

# Fixtures exported on `bench export-fixtures`. Companies/CoA are created via
# ERPNext setup (see SPEC §2); fixtures below seed everything else for Phase 1.
fixtures = [
    {"dt": "Role", "filters": [["name", "in", [
        "Creations Staff",
        "Delights Operations",
        "Delights Sales",
        "Accounts Manager",
    ]]]},
    "Item Group",
    "Warehouse",
    "Mode of Payment",
    "Custom Field",
    "Workflow",
    "Print Format",
]

doc_events = {
    "Customer Payment Declaration": {
        "on_submit": "eventbiz.api.payments.on_payment_declaration_submit",
    },
    "Rental Booking": {
        "validate": "eventbiz.api.rental.validate_rental_booking",
        "on_submit": "eventbiz.api.rental.on_rental_booking_submit",
    },
    "Rental Return": {
        "validate": "eventbiz.api.rental.validate_rental_return",
    },
    "Stock Entry": {
        # Guardrail T8: block silent cross-company warehouse moves.
        "validate": "eventbiz.permissions.validate_stock_entry_company",
    },
}

override_whitelisted_methods = {}
