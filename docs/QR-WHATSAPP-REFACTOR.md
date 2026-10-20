# SatSet QR and WhatsApp

## Implementation contract

- Laravel remains the application framework.
- `/r/{restaurant:slug}` is the public, stable outlet entry point.
- Opening a page or following a WhatsApp link never creates a ticket.
- Only a confirmed chatbot conversation or an explicit submit of the email join
  form (`/r/{restaurant:slug}/join`) creates a ticket. Opening the join form never does.
- Email alerts go through a queued job with a unique `(ticket, event)` claim, so
  each event is sent at most once. Failed sends release the claim and retry. The
  kill switch is `EMAIL_ALERTS_ENABLED`.
- `party_size` is guests in one group, not the number of tickets.
- `restaurants.queue_mode` decides the flow. `seating` asks for party size and talks
  about seats. `pickup` asks only for an email, stores one order, and talks about
  collecting food at the counter. Admin screens and customer screens follow the same
  mode.
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
