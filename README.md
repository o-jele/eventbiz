# Glamorous Eventbiz

Custom Frappe app for www.glamorous.mw — see `SPEC.md` (normative).

- Single site, two Companies: `Glamorous Creations` (GC) + `Glamorous Delights` (GD).
- Shared Customer identity, strictly separated GL / stock.
- `eventbiz/business_context.py` is the ONLY place that maps website routes → Company.

## Dev

```bash
bench get-app eventbiz <this-repo-url>
bench --site <site> install-app eventbiz
bench --site <site> migrate
bench --site <site> run-tests --app eventbiz
```

Phases: see `SPEC.md` §17.
