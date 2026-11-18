# Changelog

## v1.1.2 - 2026-11-18

- Redx: parcels still in transit are no longer counted as returned. `total` and
  `success_rate` cover delivered and returned parcels only; the response now also
  carries `booked` (Redx's full count), `in_transit`, `return_source`
  (`reported` from Redx's return figure, or `assumed` when none was sent) and
  `customer_segment`. Previously a customer with several parcels on the way
  showed a low success rate and could be rejected by a consumer's risk scoring.
- Courier result cache keys are versioned (`courier:v2:...`) so results computed
  with the old counting are not served after upgrading.
- PHPUnit added as a dev dependency with unit tests for the Redx parser
  (`composer test`).
