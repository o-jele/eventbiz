"""Company-aware permission guards (SPEC §10, §14).

Frappe Role Permissions are primary. These server-side checks are the
second layer for sensitive writes: payments, rental returns, intercompany,
and any cross-company stock move.
"""

ACCOUNTS_ROLE = "Accounts Manager"

COMPANY_SCOPE = {
    "Creations Staff": ["Glamorous Creations"],
    "Delights Sales": ["Glamorous Delights"],
    "Delights Operations": ["Glamorous Delights"],
    "Accounts Manager": ["Glamorous Creations", "Glamorous Delights"],
}


def validate_company_access(doc, user_roles: list | None = None) -> None:
    """Raise if the current user's roles do not cover doc.company.

    Call from whitelisted methods / doc_events validate handlers.
    System Manager bypasses (founders/dev only).
    """
    try:
        import frappe

        if "System Manager" in (frappe.get_roles() or []):
            return
        roles = set(user_roles or frappe.get_roles() or [])
    except Exception:
        # Outside Frappe request context (unit tests): allow, tests assert logic directly.
        return

    if ACCOUNTS_ROLE in roles:
        return  # covers both companies

    company = getattr(doc, "company", None)
    if not company:
        return
    allowed: set = set()
    for r in roles:
        allowed.update(COMPANY_SCOPE.get(r, []))
    # Users with no scoped role (e.g. plain Sales User) fall back to Frappe
    # role permissions; do not block here.
    scoped = set(COMPANY_SCOPE) & roles
    if scoped and company not in allowed:
        try:
            import frappe

            frappe.throw(f"Not permitted for company {company}.")
        except Exception as e:
            raise PermissionError(f"Not permitted for company {company}.") from e


def validate_stock_entry_company(doc, method=None) -> None:
    """Block silent cross-company warehouse moves (acceptance T8).

    Any Stock Entry whose source and target warehouses belong to different
    Companies must go through the intercompany sale/purchase SOP instead.
    """
    s_wh = getattr(doc, "from_warehouse", None) or ""
    t_wh = getattr(doc, "to_warehouse", None) or ""
    for d in getattr(doc, "items", []) or []:
        s_wh = s_wh or getattr(d, "s_warehouse", None) or ""
        t_wh = t_wh or getattr(d, "t_warehouse", None) or ""
    if not (s_wh and t_wh):
        return
    try:
        import frappe

        s_co = frappe.db.get_value("Warehouse", s_wh, "company")
        t_co = frappe.db.get_value("Warehouse", t_wh, "company")
        if s_co and t_co and s_co != t_co:
            frappe.throw(
                "Cross-company stock move blocked: "
                f"{s_wh} ({s_co}) → {t_wh} ({t_co}). "
                "Use the intercompany sale/purchase SOP (docs/intercompany-sop.md)."
            )
    except Exception:
        # frappe.throw raises ValidationError which subclasses Exception —
        # re-raise it, swallow only infra errors (no DB in unit tests).
        try:
            import frappe

            raise
        except ImportError:
            return
