"""Website controllers: cart, enquiries, cake/rental requests (SPEC §12).

Every creator sets company via business_context — never hard-coded.
"""

from eventbiz.business_context import get_business_context


def company_for_path(path: str) -> str:
    return get_business_context(path)["company"]


def create_customer_enquiry(*, path: str, enquiry_type: str, name: str,
                            phone: str = "", email: str = "",
                            subject: str = "", message: str = "",
                            event_date=None, source: str = "Website") -> dict:
    """Build (not save — caller saves via frappe) an enquiry doc dict."""
    ctx = get_business_context(path)
    if not (phone or email):
        raise ValueError("phone or email is required")
    return {
        "doctype": "Customer Enquiry",
        "company_context": ctx["company"],
        "enquiry_type": enquiry_type,
        "name1": name,
        "phone": phone,
        "email": email,
        "subject": subject or f"{enquiry_type} enquiry from website",
        "message": message,
        "event_date": event_date,
        "source": source,
        "status": "Open",
    }


def create_buy_now_order(*, path: str, customer: str, items: list) -> dict:
    """Buy-now (Creations) Sales Order skeleton. Stock/pricing validated server-side."""
    ctx = get_business_context(path)
    if ctx["key"] != "creations":
        raise ValueError("Buy-now checkout is only valid under /creations routes")
    if not items:
        raise ValueError("cart is empty")
    return {
        "doctype": "Sales Order",
        "company": ctx["company"],
        "customer": customer,
        "order_type": "Shopping Cart",
        "items": [
            {"item_code": i["item_code"], "qty": i.get("qty", 1),
             "warehouse": ctx["default_warehouse"]}
            for i in items
        ],
    }
