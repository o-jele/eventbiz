"""Business Context — the ONLY route → Company mapping (SPEC §11).

Business Context = Company + Location + Business Unit.

Every website controller MUST call `get_business_context(path)` and set
`company` on created docs. No hard-coded company strings elsewhere.
"""

import re

GLAMOROUS_CREATIONS = "Glamorous Creations"
GLAMOROUS_DELIGHTS = "Glamorous Delights"

BUSINESS_CONTEXTS = {
    "creations": {
        "key": "creations",
        "company": GLAMOROUS_CREATIONS,
        "default_warehouse": "CREATIONS - SHOP - GC",
        "business_unit": "Creations Retail",
    },
    "delights": {
        "key": "delights",
        "company": GLAMOROUS_DELIGHTS,
        "default_warehouse": "DELIGHTS - BAKERY - GD",
        "business_unit": "Delights Events",
    },
}

# Ordered: first match wins.
ROUTE_MAP = [
    (re.compile(r"^/(creations|shop|products|product|cart|checkout)(/|$)"), "creations"),
    (re.compile(r"^/(delights|cakes|fritters|catering|rentals|events|request)(/|$)"), "delights"),
]

WAREHOUSE_BY_SERVICE = {
    # Delights sub-location routing (SPEC §3). Keys match Event Quotation service_type.
    "Bakery": "DELIGHTS - BAKERY - GD",
    "Catering": "DELIGHTS - CATERING - GD",
    "Rental": "DELIGHTS - RENTAL - GD",
}


def get_context_key(path: str) -> str:
    """Return 'creations' | 'delights' for a URL path. Defaults to creations for '/'."""
    p = (path or "/").lower().rstrip("/") or "/"
    if p == "/":
        # Homepage itself is neutral; callers needing a company must pass
        # an explicit sub-path. Default here keeps old links working.
        return "creations"
    for pattern, key in ROUTE_MAP:
        if pattern.match(p):
            return key
    raise ValueError(f"No Business Context for path: {path!r}")


def get_business_context(path: str) -> dict:
    """Return a copy of the Business Context dict for a URL path."""
    return dict(BUSINESS_CONTEXTS[get_context_key(path)])


def warehouse_for(company: str, service_type: str | None = None) -> str:
    """Resolve default warehouse for a company (+ optional Delights service)."""
    if company == GLAMOROUS_CREATIONS:
        return BUSINESS_CONTEXTS["creations"]["default_warehouse"]
    if service_type in WAREHOUSE_BY_SERVICE:
        return WAREHOUSE_BY_SERVICE[service_type]
    return BUSINESS_CONTEXTS["delights"]["default_warehouse"]
