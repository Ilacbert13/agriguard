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

**`railway.toml`** builds from the Dockerfile and sets **`startCommand`** to **`php artisan serve`** only (no migrate on boot). That avoids Railway racing MySQL during pre-deploy / complicated start scripts.

1. **Dashboard → Deploy → Pre-deploy command:** leave **empty**.
2. **Dashboard → Deploy → Custom Start Command:** leave **empty** so **`railway.toml`** applies, or set explicitly to the same as in **`railway.toml`** (`php artisan serve --host 0.0.0.0 --port $PORT` via `sh -c` — see file).
3. Link **Variables** from your MySQL service (**Reference** `MYSQL_*` / `DATABASE_URL`).

**Migrations (manual):** after the service is running and MySQL is reachable, open **Railway Shell** on the web service and run:

```bash
php artisan migrate --force && php artisan db:seed --force && php artisan historical-weather:import storage/app/public/historical_weather.csv
```

Other platforms (e.g. Render) still use the Dockerfile **`CMD`** (`agriguard-start`) if you do not override **`startCommand`** there.

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
