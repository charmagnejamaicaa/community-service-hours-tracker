# Backend Setup: Node.js + Express + MySQL

## 1. Put the files in the `backend` folder
Place these 7 files **directly inside** `backend/` (no subfolders):

```
community-service-hours-tracker/
  backend/
    package.json
    server.js
    db.js
    logger.js
    hoursService.js
    hoursService.test.js
    schema.sql
```

Do not delete the `.gitkeep` file, or do, since the folder now has real files.

## 2. Install Node.js
Download the LTS version from https://nodejs.org, then restart VS Code. Check it:
```
node --version
```
You need version 18 or newer.

## 3. Install the packages
In the VS Code terminal:
```
cd backend
npm install
```
This creates `node_modules/` (already ignored by `.gitignore`, so it won't be uploaded).

## 4. Run the tests (no database needed)
```
npm test
```
You should see `pass 13` and `fail 0`.

## 5. Set up MySQL
1. Start MySQL (for example, start **MySQL** in XAMPP).
2. Open `schema.sql` and run it: in phpMyAdmin go to the **SQL** tab, paste the file's content, then click **Go**. In MySQL Workbench, open the file and click the lightning bolt.
3. This creates the `service_hours` database with fictional sample data.

## 6. Create your `.env` file
In the `backend` folder, copy `.env.example` (from the main folder) and name the copy `.env`. Set:
```
PORT=3000
DATABASE_URL=mysql://root:@localhost:3306/service_hours
```
Format: `mysql://USER:PASSWORD@HOST:PORT/DATABASE`. XAMPP's default is user `root` with an empty password. If your MySQL has a password, put it between `:` and `@`.

**Never commit `.env`.** The `.gitignore` blocks it, but check `git status` before committing.

## 7. Start the server
```
npm start
```
You should see a JSON log line with `"event":"server_started"`.

## 8. Try it in your browser
- http://localhost:3000/api/health  -> `{"status":"ok", ...}`
- http://localhost:3000/api/students/1/progress  -> progress for Sample Student One

Expected for student 1: required 40, approved 22.5, pending 4.5, remaining 17.5.
Student 2 has a deadline that has passed, so their status is `OVERDUE`.
Student 99 gives a `404 NOT_FOUND` error. `/api/students/abc/progress` gives a `400`.

Take screenshots of these for your evidence.

## 9. Commit and push
```
cd ..
git add .
git status
git commit -m "feat: add remaining-hours calculation, progress endpoint, and tests"
git push
```
Check `git status` first: `.env` and `node_modules` must NOT appear in the list.

## What this covers for Week 13
- Remaining-hours calculation, tested (13 tests)
- Validation rules from the API contract
- Structured JSON logs with a reference ID
- Error format from the API contract
- First endpoint: `GET /api/students/:id/progress`

## Not built yet
- Submit/verify/approve endpoints
- Notification sender, retry logic, and the two failure demonstrations
- Scheduled daily job
- Flutter screens
