# AGRIGUARD

Laravel + MySQL farm app. Install **PHP 8.2+**, **Composer**, **Node/npm**, **MySQL**. For weather ML, add **Python 3.10+** (64-bit on Windows).

## Setup

1. **Clone and PHP/JS deps**

   ```bash
   git clone <repo-url> && cd agriguard
   composer install && npm install
   cp .env.example .env
   php artisan key:generate
   ```

   Windows: `Copy-Item .env.example .env` instead of `cp`.

2. **MySQL** — create database `agriguard`, then in `.env` set `DB_*`, `APP_URL=http://127.0.0.1:8000`, and `MAIL_MAILER=log` for local mail. API keys in `.env` are optional.

3. **Python** (weather predictions use `python/predict.py` + `python/model/xgboost_weather_model.pkl`)

   From the project root, create `.venv` and install packages:

   ```bash
   python3 -m venv .venv               # Windows: py -3 -m venv .venv
   source .venv/bin/activate           # Windows: .\.venv\Scripts\Activate.ps1
   pip install -r python/requirements.txt
   ```

   Copy model files into `python/model/` if they are missing.

   **macOS/Linux:** add one line to `.env` (the app defaults to a Windows Python path):

   ```env
   AGRIWEATHER_PYTHON_BIN=/absolute/path/to/agriguard/.venv/bin/python
   ```

   Check it loads: `./.venv/bin/python python/verify_model_load.py` → should print `MODEL_LOAD_OK`.  
   Windows: `.\.venv\Scripts\python.exe python\verify_model_load.py`

4. **Database and front-end**

   ```bash
   php artisan migrate --seed
   php artisan storage:link
   npm run build
   php artisan serve
   ```

   Open **http://127.0.0.1:8000**

5. **Historical weather** — after `migrate`, load rows from the CSV in the repo (run from project root):

   ```bash
   php artisan historical-weather:import storage/app/public/historical_weather.csv
   ```

   To replace all existing rows first: add `--truncate`.

   ```bash
   php artisan historical-weather:import storage/app/public/historical_weather.csv --truncate
   ```

   Use another file by passing its path instead.

## Railway (Docker)

This repo uses **`railway.toml`** (Dockerfile build, **`startCommand`** = `agriguard-start`). Migrations, seed, and historical CSV import run **inside the container** after **`docker/wait-for-laravel-db.php`** — not in Railway’s pre-deploy phase.

1. **Clear the pre-deploy command in the dashboard** (Service → **Settings** → **Deploy** → **Pre-deploy command**): leave it **empty** and save. If `php artisan migrate` is set there, it often runs while MySQL is still starting and fails with connection refused / timed out.
2. Link **Variables** from your MySQL service (**Reference** `MYSQL_*` / `DATABASE_URL` into `DB_*` or use `DATABASE_URL` as Laravel expects).
3. Redeploy after pushing so the image includes **`docker/agriguard-start.sh`** and **`docker/wait-for-laravel-db.php`**.

Optional tuning (variables): `AGRIGUARD_POST_TCP_SLEEP` (seconds after DB wait, default 15), `AGRIGUARD_DB_WAIT_ATTEMPTS`, `AGRIGUARD_DB_WAIT_SLEEP`.

## Default login (after seed)

| Email | Password |
|-------|----------|
| `admin@agriguard.com` | `admin123` |

## If something breaks

| Problem | Try |
|---------|-----|
| App key | `php artisan key:generate` |
| Missing uploads | `php artisan storage:link` |
| Blank styles/scripts | `npm run build` |
| Database | MySQL running; fix `DB_*` in `.env` |
| Railway migrate timeout | Empty **Pre-deploy command** in Railway; use repo `railway.toml`; see **Railway** section above |
| Python / predictions | `.venv` at repo root, `pip install -r python/requirements.txt`, model in `python/model/`, set `AGRIWEATHER_PYTHON_BIN` on Mac/Linux, run `verify_model_load.py` |
