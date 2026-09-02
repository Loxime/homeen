# LOCAL development procedure

The development machine is the VM named **LOCAL**. Do not develop directly on the VPS.

## 1. Repository

The expected path is `~/Harpocrat`. If it already contains `.git` and nothing else, keep that Git metadata.

```bash
# [LOCAL]
cd ~/Harpocrat
git status
git remote -v
```

If no `origin` exists:

```bash
# [LOCAL]
git remote add origin https://github.com/Loxime/homeen.git
git branch -M main
```

## 2. Preferred one-command bootstrap

```bash
# [LOCAL]
./scripts/local-bootstrap.sh
```

This generates the ignored `.env`, builds the stack and runs the quality gate. The remaining sections document the same operations manually for troubleshooting.

## 3. Secrets (manual path)

`.gitignore` must exist before `.env`. Then:

```bash
# [LOCAL]
cp .env.example .env
nano .env
```

Replace all placeholder secrets. Verify that Git ignores the file:

```bash
# [LOCAL]
git check-ignore -v .env
```

## 4. Docker bootstrap

```bash
# [LOCAL]
docker compose config
docker compose up -d --build
docker compose ps
```

The backend entrypoint installs Composer dependencies and applies migrations. The frontend installs npm dependencies and launches Vite. Open `http://localhost:8080`.

## 5. Verification

```bash
# [LOCAL]
curl http://localhost:8080/api/health
make quality
```

Expected health response:

```json
{"status":"ok","database":"ok"}
```

## 6. First commit

Because the repository started empty, preserve the security-first history. If you want `.gitignore` to literally be the first commit, commit it before copying the rest of the project files. If all project files have already been copied, split the initial history with two commits:

```bash
# [LOCAL]
git add .gitignore
git commit -m "chore: protect local secrets"

git add .
git commit -m "feat: bootstrap Homeen local application"
```

The generated `backend/composer.lock` and `frontend/package-lock.json` created during first Docker startup should be committed. `.env` must remain untracked.
