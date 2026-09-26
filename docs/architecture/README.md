# Homeen architecture

## Product boundaries

Homeen is a private productivity application. It intentionally does not implement reminders, push notifications, or calendar integration.

## Domain model

- `tag`: user-owned reusable classification with a six-digit hexadecimal color.
- `note`: text note with archive/trash timestamps, optional collection, and zero or more tags through `note_tag`.
- `task`: belongs to exactly one note; content up to 4000 characters, priority, status, position, optional start/due dates, and zero or more tags through `task_tag`.
- `pomodoro_preset`: unique work duration in minutes. Work duration is at least 5 minutes and has no application-defined upper bound.
- `pomodoro_session`: one launch-to-stop interval. Only one session may run at once.
- `activity_event`: immutable event log used for historical progress metrics.
- `app_usage_session`: browser-visible usage session start/stop log.
- `app_usage_slice`: short active-time slices used for accurate daily/monthly aggregation.

The legacy `label` table and `note.label_id` column are retained temporarily as a rollback compatibility layer. They are no longer exposed by the application API or frontend.

## Note lifecycle

`active -> archived -> active` and `active|archived -> trash -> active`. A daily scheduler permanently deletes notes that have remained in trash for at least 30 days. Tasks and `note_tag` associations cascade-delete only when the note is permanently purged. Deleting a tag removes its note/task associations without deleting either object.

Duplicating a note copies title, content, collection, note tags, task metadata, task dates and task tags. Duplicated tasks are deliberately reset to `todo` and incomplete so the copy represents a new actionable note.

## Pomodoro semantics

A session is the complete interval between Start and Stop. It loops forever:

`WORK(work_minutes) -> BREAK(5 minutes) -> WORK(work_minutes) -> ...`

The timer is derived from `started_at`, not by decrementing a counter. This makes background-tab throttling harmless to timer correctness. The browser title displays the current remaining time and phase. A short Web Audio beep is generated on phase changes.

Saved presets are unique by `work_minutes`. Starting an already-known duration updates its `last_used_at` instead of creating a duplicate preset.

## Statistics definitions

- **Pomodoro sessions**: sessions whose `started_at` is inside the selected month.
- **Focus time**: focused seconds that overlap the selected period, even when a session crosses midnight or a month boundary.
- **Tasks checked**: `TASK_COMPLETED` activity events in the selected period.
- **Most resolved tag**: tag snapshot associated with the largest number of task-completion events in the selected period. Completion events snapshot the tags linked to the task or its note so later tag renames/deletions do not rewrite history. Legacy `labelName` event metadata remains readable for historical compatibility.
- **Notes created**: `NOTE_CREATED` events in the selected period.
- **Current notes**: notes currently not in trash.
- **Application time**: active seconds recorded by the browser usage tracker while the app is visible and the user has interacted within the last two minutes. A cross-tab leader lock prevents multiple open tabs from double-counting time.
- **Work rate / focus efficiency**: `focus_seconds / (focus_seconds + fixed_break_seconds) * 100`. This metric is bounded from 0 to 100 and does not depend on whether the Homeen tab itself is visible while the focus session runs.
- **Month evolution**: `(current - previous) / previous * 100`. When the previous value is zero and the current value is nonzero, the API returns `null`, displayed as `New`.

Daily statistics use the timezone configured by `APP_TIMEZONE`.

## Access gate

`ACCESS_KEY` exists only in the server environment. The browser submits it once to `/api/access/login`; the backend compares it and creates an HttpOnly, SameSite=Strict server session. The key is not returned to the browser. Mutating API calls also require a random session-scoped CSRF header. Login attempts are rate-limited to five per minute per client IP.

## Search

Scoped note search remains available through `GET /api/notes?q=...`, matching note title/content, note tags and task content. Global search uses `GET /api/search?q=...` and returns separate note/task results; it also matches collection names and reusable tags. Trashed notes are excluded from global search.
