# How to Run — SkillSwap — Peer-to-Peer Skill Exchange Platform

Assumes prerequisites in `PREREQUISITES.md` are already done. No Composer step — this project has no dependencies.

## One-time setup

```
mysql -u root -P 3306 < database/schema.sql
```
This creates the `peer_skill_exchange` database, tables, and demo seed data. This same command is also the wipe command — just re-run it any time to reset the data (session timestamps are `NOW()`-relative, so a fresh import also refreshes which sessions look upcoming/past).

## Start MySQL

Open **XAMPP Control Panel** and make sure **MySQL** is running.

## Run the app

From this project's folder:
```
php -S localhost:8016 -t public public/index.php
```
(`-t public` is required — without it, static assets under `public/assets` 404.)

## Open it

Visit `http://localhost:8016/index.php?route=login` and sign in with any of the demo credentials in `README.md` under "Default Login Credentials".

## Stop the app

`Ctrl+C` in the terminal running the `php -S` command.
