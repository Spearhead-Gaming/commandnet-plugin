# Rolling the migrations out to staging

This is what to run on a **staging** install that has a copy of production data. It covers the 13
migrations from `20260920140000` to `20260920260000` and the code that goes with them. Do production
the same way once staging has passed. `docs/merge-and-test-plan.md` says what has been tested and what
this run still has to settle.

Nothing here has been run on a real install. Steps 4 and 5 are where real data can still surprise
the tests, so read their output before going on.

## Before you start

- [ ] **Confirm the target is staging.** Look at the database the install points at (`DATABASE_URL`
      in its `.env.local`, or wherever your host sets it) and check the host and database name are
      staging's, not production's. Every command below changes that database.
- [ ] Staging runs the same PHP (8.4 or newer) and Forumify (1.3.x) versions as production, on a
      copy of production data.
- [ ] **Back up the database**, and note which plugin version is installed now:

      ```bash
      mysqldump --single-transaction --routines <staging-database> > staging-before-command-net.sql
      composer show majesticdev/commandnet-plugin
      ```

- [ ] **Save the AWOL numbers as they are now** (run in a SQL client, keep the output):

      ```sql
      SELECT type, title, COUNT(*) AS entries
      FROM service_record
      WHERE title IN ('Flagged AWOL', 'Returned to Active')
      GROUP BY type, title;

      SELECT COUNT(*) AS soldiers_awol FROM soldier_profile WHERE status = 'awol';
      ```

      Before the migrations, those entries are filed under the type `assignment`. After them they
      should all be `awol`.

## 1. Update the plugin

Update `majesticdev/commandnet-plugin` to the current `master` the way you normally deploy it (for
example `composer update majesticdev/commandnet-plugin`), then reload the plugins from composer:

```bash
bin/console forumify:plugins:refresh
```

Check in the admin panel's plugin list that Command Net is there and active.

## 2. See what will run

```bash
bin/console doctrine:migrations:list
```

Expect the pending ones to be some or all of the 13 from `Version20260920140000` on. If some already
show as executed, someone ran them before; carry on with the pending ones.

Read the SQL before it runs, especially the two that touch existing rows:

```bash
bin/console doctrine:migrations:migrate --dry-run
```

`Version20260920140000` adds `soldier_profile.awol_auto_flagged`, marks soldiers who are AWOL and have a
"Flagged AWOL" entry, and changes those entries from type `assignment` to `awol`.
`Version20260920260000` then clears that mark for soldiers whose latest AWOL entry is a return to
Active, meaning they were set AWOL again by an admin.

## 3. Run the migrations

```bash
bin/console doctrine:migrations:migrate --no-interaction
bin/console cache:clear
```

If one fails, stop. Do not re-run past it; restore the backup (see the end) and tell me the error.

## 4. Check the AWOL data

Run the same two queries as before:

```sql
SELECT type, title, COUNT(*) AS entries
FROM service_record
WHERE title IN ('Flagged AWOL', 'Returned to Active')
GROUP BY type, title;

SELECT COUNT(*) AS soldiers_awol FROM soldier_profile WHERE status = 'awol';
```

- Every "Flagged AWOL" and "Returned to Active" entry should now be type `awol`, and the counts
  should match what you saved.
- `soldiers_awol` should be unchanged.

Then look at who was marked as flagged by detection:

```sql
SELECT sp.id, u.username,
       (SELECT sr.title FROM service_record sr
         WHERE sr.soldier_id = sp.id AND sr.type = 'awol'
           AND sr.title IN ('Flagged AWOL', 'Returned to Active')
         ORDER BY sr.id DESC LIMIT 1) AS latest_awol_entry
FROM soldier_profile sp
JOIN user u ON u.id = sp.user_id
WHERE sp.status = 'awol' AND sp.awol_auto_flagged = 1;
```

Every row should show `Flagged AWOL` as the latest entry. Any other value is a soldier the
migration got wrong; note them and stop before going on.

Soldiers who are AWOL but not in that list were either set AWOL by an admin or have no history. They
stay AWOL until an admin clears them, which is intended.

Finally:

```bash
bin/console doctrine:schema:validate
```

It will report differences for tables belonging to the calendar plugin and to Forumify's own
`UserNotificationSettings`; those are not from this plugin. Anything naming a `command_net` table or a
column of `soldier_profile` or `service_record` is a problem.

## 5. Try it

The full list is in `docs/merge-and-test-plan.md`. Do at least these on staging:

- [ ] Give the new permissions to the right roles (the list is at the end of the plan), then open
      the roster, a personnel file, the operations page and the admin Personnel list as a member
      who has some of them, to see that what you can do matches.
- [ ] Turn on AWOL detection under Admin, Command Net, AWOL Settings, and file attendance for a
      soldier on a test operation. Check the AWOL flag, the record on their file, the AWOL role if you
      set one, and the notification they receive (in the site and, if you use it, by email or
      Discord: this is the part the tests could not check).
- [ ] Turn on Report In under Report In Settings, then run it once by hand:

      ```bash
      bin/console command-net:report-in:run-checks
      ```

      The first run only records a baseline; nobody should be flagged.
- [ ] Check the scheduler is running on this install. The task should be listed:

      ```bash
      bin/console debug:scheduler
      ```

      Whether the worker that runs it is up depends on your setup. To see it run on its own, add a
      soldier with no report in on file and check the next morning that they have a "Last Report In"
      date (the baseline the check records). The task is scheduled for 08:00 with a random delay of up
      to 30 minutes.
- [ ] Load `/squad.xml` with a real Arma client if you use it. The tests check it against its DTD
      but not against a real client.

## Rolling back

Restore the database from the backup and put the previous plugin version back. Every migration has a
`down()`, but the tables added by the newer migrations lose their data if you use it, and
`Version20260920260000` has nothing to undo, so the backup is the safe way back:

```bash
mysql <staging-database> < staging-before-command-net.sql
```

## What to send back

The before and after numbers from step 4, any rows the last query in step 4 flagged as wrong, the
output of `doctrine:schema:validate` lines that mention this plugin, and anything that behaved
differently from the plan in step 5. I can fix what turns up and add a test for it.
