# Requirements coverage

| Requirement | Implementation |
|---|---|
| Add / modify note | Notes API + editor |
| Delete note | Soft-delete to trash |
| Duplicate note | Note duplication endpoint; tasks copied incomplete |
| Archive note | Archive/unarchive endpoints |
| Create / modify / delete label | Labels API + labels page |
| Modify label color | Hex color picker + backend/database validation |
| One label maximum per note | Nullable `note.label_id` foreign key |
| Grid display | Notes grid |
| List display | Notes list |
| Random whiteboard | Randomized/jittered board with shuffle |
| Create / delete task | Task API + note editor |
| Task belongs to one note | Non-null `task.note_id`; cascade on purge |
| Many tasks per note | One-to-many database relationship |
| Task max 255 characters | HTML maxlength, backend validation, database CHECK |
| Pomodoro work time configurable | Integer input and saved preset |
| Work minimum 5 minutes | Frontend, backend and database CHECK |
| No arbitrary maximum | No max attribute/business maximum |
| Fixed 5-minute break | Constant in backend and frontend math |
| Infinite work/break loop | Timestamp-derived cycle calculation |
| Stop manually | Stop endpoint/button |
| Timer in browser tab | Dynamic `document.title` globally |
| Transition sound | Web Audio beep on phase changes |
| Sidebar notes/labels/pomodoro/statistics | Main application shell |
| Quick create/relaunch Pomodoro | Sidebar quick-start button |
| Save unique session duration | Unique `pomodoro_preset.work_minutes` |
| Trash purge every 30 days | Daily scheduler + `app:trash:purge` |
| No reminder | No reminder/notification subsystem |
| Top search | Top bar + PostgreSQL ILIKE search |
| Dockerized | PHP, PostgreSQL, Vite, Nginx, scheduler services |
| `.env` variables | Root `.env`; `.env.example` committed |
| Secrets excluded from Git | `.gitignore` ignores `.env*` except example |
| LOCAL development distinction | README + LOCAL documentation |
| `ACCESS_KEY` private access | Server-side session access gate + rate limit + CSRF |
| Pomodoro session count/start/stop | Persistent session table/history/statistics |
| Tasks checked | Immutable completion events/statistics |
| Most resolved label | Completion-event label snapshot aggregation |
| Note count | Statistics summary |
| Time spent in application | Active usage heartbeat, cross-tab leader |
| Daily statistics | Daily aggregation table/chart |
| Monthly statistics | Month selector and summary |
| Previous-month evolution | API percentage comparisons |
| SonarQube-like stats view | Quality gate strip, KPI tiles, trend chart, daily log |
