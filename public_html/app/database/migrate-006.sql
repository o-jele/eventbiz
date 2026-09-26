-- Migrate 006: activity-feed timestamp on rental bookings.

ALTER TABLE rental_bookings
  ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
