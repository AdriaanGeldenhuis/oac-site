-- Optional, one-off clean-up (diary "Light Through Glass" pass).
--
-- Older versions of the diary copied entries into calendar_events
-- ("Add to calendar"). Nothing shows those copies any more: the calendar
-- reads `diaries` directly (own entries only) and admin/api/ampte/calendar.php
-- already hides them. The diary now removes a copy itself when its entry is
-- edited or deleted; this clears the rest in one go, so the private text is
-- no longer duplicated in another table.
--
-- Back up first. Safe to run more than once.

START TRANSACTION;

DELETE ce
FROM calendar_events ce
JOIN diaries d ON d.calendar_event_id = ce.id AND d.user_id = ce.user_id;

UPDATE diaries
SET calendar_event_id = NULL
WHERE calendar_event_id IS NOT NULL;

COMMIT;
