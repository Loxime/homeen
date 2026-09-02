# Homeen architecture

## Product boundaries

Homeen is a single-user private productivity application. It intentionally does not implement accounts, reminders, deadlines, push notifications, or calendar integration.

## Domain model

- `label`: reusable classification with a six-digit hexadecimal color.
- `note`: text note with zero or one label, archive timestamp and trash timestamp.
- `task`: belongs to exactly one note; maximum 255 characters.
- `pomodoro_preset`: unique work duration in minutes. Work duration is at least 5 minutes and has no application-defined upper bound.
- `pomodoro_session`: one launch-to-stop interval. Only one session may run at once.
- `activity_event`: immutable event log used for historical progress metrics.
- `app_usage_session`: browser-visible usage session start/stop log.
- `app_usage_slice`: short active-time slices used for accurate daily/monthly aggregation.

## Note lifecycle

`active -> archived -> active` and `active|archived -> trash -> active`. A daily scheduler permanently deletes notes that have remained in trash for at least 30 days. Tasks cascade-delete only when the note is permanently purged. Deleting a label sets the note label to null.

Duplicating a note copies title, content, label and task texts. Duplicated tasks are deliberately reset to incomplete so the copy represents a new actionable note.

## Pomodoro semantics

A session is the complete interval between Start and Stop. It loops forever:

`WORK(work_minutes) -> BREAK(5 minutes) -> WORK(work_minutes) -> ...`

The timer is derived from `started_at`, not by decrementing a counter. This makes background-tab throttling harmless to timer correctness. The browser title displays the current remaining time and phase. A short Web Audio beep is generated on phase changes.

Saved presets are unique by `work_minutes`. Starting an already-known duration updates its `last_used_at` instead of creating a duplicate preset.

## Statistics definitions

- **Pomodoro sessions**: sessions whose `started_at` is inside the selected month.
- **Focus time**: focused seconds that overlap the selected period, even when a session crosses midnight or a month boundary.
- **Tasks checked**: `TASK_COMPLETED` activity events in the selected period.
- **Most resolved label**: label snapshot associated with the largest number of task-completion events in the selected period. The name is copied into event metadata so later label renames/deletions do not rewrite history.
- **Notes created**: `NOTE_CREATED` events in the selected period.
- **Current notes**: notes currently not in trash.
- **Application time**: active seconds recorded by the browser usage tracker while the app is visible and the user has interacted within the last two minutes. A cross-tab leader lock prevents multiple open tabs from double-counting time.
- **Work rate / focus efficiency**: `focus_seconds / (focus_seconds + fixed_break_seconds) * 100`. This metric is bounded from 0 to 100 and does not depend on whether the Homeen tab itself is visible while the focus session runs.
- **Month evolution**: `(current - previous) / previous * 100`. When the previous value is zero and the current value is nonzero, the API returns `null`, displayed as `New`.

Daily statistics use the timezone configured by `APP_TIMEZONE`.

## Access gate

`ACCESS_KEY` exists only in the server environment. The browser submits it once to `/api/access/login`; the backend compares it and creates an HttpOnly, SameSite=Strict server session. The key is not returned to the browser. Mutating API calls also require a random session-scoped CSRF header. Login attempts are rate-limited to five per minute per client IP.

## Search

The classic search endpoint is integrated into `GET /api/notes?q=...`. PostgreSQL `ILIKE` matches note title/content, task content and label name. The note state (`active`, `archived`, `trash`) remains an explicit scope.
