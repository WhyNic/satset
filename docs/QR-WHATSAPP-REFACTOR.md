# SatSet QR and WhatsApp

## Implementation contract

- Laravel remains the application framework.
- `/r/{restaurant:slug}` is the public, stable outlet entry point.
- Opening a page or following a WhatsApp link never creates a ticket.
- Only a confirmed chatbot conversation creates a ticket.
- `party_size` is guests in one group, not the number of tickets.
- Outlet slugs stay stable when names change so printed QR codes remain valid.
- Existing restaurants, tickets and historical migrations are preserved.
- Database additions use forward migrations. Only the retired subscription
  table is removed.
- The webhook verifies the raw request signature before reading messages.
- Incoming provider message IDs are unique and processed transactionally with
  conversation state.
- Queue changes and outgoing message records are committed before HTTP delivery.
- Outgoing messages have an event key so repeated queue actions do not generate
  the same notification.
- Only sanitized delivery errors are stored; credentials never enter logs.

## Verification plan

Use an isolated in-memory SQLite database and `Http::fake()` for automated
tests. Cover public outlet pages, QR generation, deep-link context, chatbot
states, party size limits, confirmations, duplicate delivery, queue numbering,
wait estimates, notification thresholds, authentication and API failures.

Inspect customer mobile and desktop views and the operational admin pages.
Run additive migrations on the local database only after reviewing their SQL.
Do not seed or reset the local database.
