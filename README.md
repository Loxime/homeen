# Homeen

Homeen is a private productivity application combining notes, reusable tags, tasks, an infinite Pomodoro loop, and progression analytics.

## LOCAL environment

Development is performed on the VM named **LOCAL**. The production VPS is intentionally not used during development.

### First start

From the repository root:

```bash
# [LOCAL] ~/Harpocrat
./scripts/local-bootstrap.sh
```

The bootstrap script refuses to create secrets unless `.gitignore` is already present. It generates a random `APP_SECRET`, PostgreSQL password and `ACCESS_KEY`, writes them only to the ignored `.env`, verifies the ignore rule, builds the Docker stack, waits for the API/database and frontend to answer, applies migrations through the PHP entrypoint and runs the complete quality gate. It prints the generated `ACCESS_KEY` at the end.

To choose your own alphanumeric access key on the first run:

```bash
# [LOCAL]
HOMEEN_ACCESS_KEY='My-Local-Key-1234' ./scripts/local-bootstrap.sh
```

Open <http://localhost:8080> and authenticate with `ACCESS_KEY`.

### Quality gate

```bash
# [LOCAL]
make quality
```

### Test suite

```bash
# [LOCAL]
make test
```

### Repository setup

If the current directory already contains only `.git`, keep it. Do not clone over it. Configure the remote instead:

```bash
# [LOCAL] ~/Harpocrat
git remote add origin https://github.com/Loxime/homeen.git
git branch -M main
```

If `origin` already exists, use `git remote set-url origin https://github.com/Loxime/homeen.git`.

## Functional scope

- Notes: quick create, edit, duplicate, pin/unpin, per-note color, archive, trash and restore; automatic permanent deletion after 30 days.
- Reusable colored tags shared by notes and tasks; multiple tags can be attached to each note or task.
- Keep-style note experience: pinned/other sections, quick card actions, first-image card previews, inline text composer, image-note shortcut and task-list shortcut.
- Note views: grid, list and randomized whiteboard.
- Private image library with reusable image assets attachable to notes, including shared Project notes.
- Projects with members, invitations, owner/admin/member roles, configurable workflow stages, shared notes and a persistent task board.
- Tasks can belong either to a note or directly to a Project workflow stage; priority, status, position, optional start/due dates, reusable tags, completion state, and content up to 4000 characters.
- Project task boards support persistent cross-column drag and drop plus explicit workflow stage management.
- Global search across personal note title/content, collections and tags, plus personal task content and tags.
- Infinite Pomodoro: configurable work duration (minimum 5 minutes, no maximum), fixed 5-minute break, saved presets, quick relaunch, tab countdown, audible phase transitions.
- Statistics: Pomodoro sessions, start/stop times, focused time, task completions, most-completed tag, notes, active application time, daily evolution and period comparisons.
- No reminders or notifications.
- Private access gate backed by `ACCESS_KEY`; the secret is never sent to the browser after authentication.

See `docs/architecture/README.md` for the technical model and metric definitions and `docs/development/VALIDATION.md` for the delivery validation gate.
