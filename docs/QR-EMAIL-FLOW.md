# SatSet QR and Email Flow

## Implementation contract

- Laravel remains the application framework.
- `/r/{restaurant:slug}` is the public, stable outlet entry point. Printed QR codes point here.
- Opening a page never creates a ticket. Only an explicit submit of the join form
  (`/r/{restaurant:slug}/join`) creates one.
- `restaurants.queue_mode` decides the flow:
  - `seating` asks for party size and an email. It talks about seats and tables.
  - `pickup` asks only for an email, stores one order (`party_size` 1), and talks about
    collecting food at the counter.
  - Customer screens, emails, polling, and admin screens follow the same mode.
  - The mode cannot change while the outlet has active tickets.
- `party_size` is guests in one group, not the number of tickets.
- An email may hold at most `EMAIL_ALERTS_MAX_ACTIVE_PER_EMAIL` active tickets per outlet
  (default 3). Waiting and called tickets count as active.
- Contact details are cleared from a ticket when it is completed, skipped, or cancelled.
  Admin screens show contacts masked.
- Email alerts are queued jobs. Each `(ticket, event)` pair is claimed once, so an event
  is sent at most once. A failed send releases the claim and retries. A claim left unsent
  for more than 10 minutes by a crashed worker is reclaimed atomically.
- Threshold alerts are checked on every queue change and on every join:
  - 2 or 3 people ahead: "tinggal N antrean lagi", sent once. N is the real count when the email is rendered.
  - exactly 1 person ahead: "tinggal 1 antrean lagi", sent once.
  - A customer who joins already inside that window gets the alert at once.
  - Each alert is checked again at send time. If the count has moved outside its window,
    it is settled without sending. A "called" alert is dropped if the customer is no longer called.
  - A customer who is next (0 ahead) gets no threshold alert, only the "called" email.
- `EMAIL_ALERTS_ENABLED=false` stops all sending without affecting the queue.
- A total SMTP outage never blocks joining or any queue action.
- Failed jobs are pruned daily, since their payload can hold an email address.
- Outlet slugs stay stable when names change, so printed QR codes remain valid.
- Database changes use forward migrations only. Existing tables are not dropped.

## Verification plan

Automated tests run on an in-memory SQLite database with a mailer that can be faked, or
with a seeded flaky SMTP transport for chaos runs (`tests/Support/FlakyMailTransport.php`).

- Join contract: validation, closed outlets, per-email cap, rate limit.
- Event delivery: thresholds fire once per ticket, cancellation notice precedes the email
  clearing, outdated alerts are settled.
- Fault injection: SMTP refused at the socket level, a total outage during joining,
  stale claims, and duplicate deliveries.
- Randomized sequences: 300 operations per seed, with invariants checked after every step.
- Customer and staff HTTP paths, and UX contracts for both modes.

Visual checks run in headless Edge at phone width (390 px) and desktop width. Screenshots
cover the outlet, join, and ticket pages, and the admin queue and restaurant pages.

Do not seed or reset the local database. Review migration SQL before running it locally.
