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

**`railway.toml`** expects **Dockerfile builds** (`builder = DOCKERFILE`). On the service:

1. **Build → Builder:** choose **Docker** / build from **Dockerfile**, **not** Railpack — the image installs PHP/Composer/Node and copies the app; **Vite runs when the container starts** (`npm install && npm run build`), then **`php artisan serve`** (see **`railway.toml`** `startCommand`).
2. **Deploy → Pre-deploy command:** leave **completely empty**. Do **not** chain `npm`/`serve`/`migrate` here.
3. **Deploy → Custom Start Command:** leave **empty** so **`railway.toml`** `startCommand` applies.

First boot can take longer while **`npm install`** runs; **`healthcheckTimeout`** in **`railway.toml`** is set to **600** seconds. **`APP_URL`** must be your real Railway HTTPS URL so `@vite`/`asset()` don’t point at `127.0.0.1`.

**Migrations without Railway Shell:** temporarily change **`startCommand`** in **`railway.toml`** to  
`/usr/local/bin/agriguard-start`  
(that script waits for DB, runs **`docker/agriguard-db-setup.php`**, then serves). Switch back to **`serve`** only after DB is seeded if you prefer.

**Migrations with Shell (if your plan includes it):**

```bash
php artisan migrate --force && php artisan db:seed --force && php artisan historical-weather:import storage/app/public/historical_weather.csv
```

Other platforms (e.g. Render) use the Dockerfile **`CMD`** (`agriguard-start`) unless overridden.

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
| Railway DB / migrate | Empty **Pre-deploy** & **Custom Start** (use `railway.toml`); migrate via **Shell** — see **Railway** section |
| Python / predictions | `.venv` at repo root, `pip install -r python/requirements.txt`, model in `python/model/`, set `AGRIWEATHER_PYTHON_BIN` on Mac/Linux, run `verify_model_load.py` |
