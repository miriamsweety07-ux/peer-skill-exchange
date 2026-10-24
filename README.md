# SkillSwap — Peer-to-Peer Skill Exchange Platform

A peer-to-peer skill exchange platform: Members list skills they can teach
and skills they want to learn from a shared taxonomy, browse/search for a
teaching match, schedule one-on-one sessions with each other, and chat
within a session — a single dual learner/teacher Member role, no separate
student/tutor account type.

## 1. Overview & Objective

Most "find a tutor" platforms assume a one-directional relationship: a
teacher and a student. SkillSwap models something closer to how people
actually trade skills informally — everyone is a Member who can both teach
and learn. A Member lists what they can teach (with a self-rated
proficiency) and what they want to learn, both picked from a shared,
admin-curated skill taxonomy rather than free text. From there, matching
(who teaches what I want to learn), scheduling (with server-side
double-booking protection), and a private per-session chat are all built
on a small, explicit set of rules that are easy to defend in a viva: live
queries instead of stale cached flags, an explicit session state machine
instead of a free-text status, and structural (query-level) privacy for
chat instead of a UI that merely hides a link.

## 2. Tech Stack

- **Backend**: Plain PHP 8.2+ (custom MVC, no framework) — PDO/MySQL, native sessions
- **Frontend**: Bootstrap 5 (CDN), "Community" burnt-orange/teal theme per
  `GROUNDRULES.md` §0 — the two brand colors are semantic, not decorative:
  **ember (burnt orange) = what you offer** (skills you teach, and every
  primary action you initiate), **teal = what you receive** (skills you
  want to learn, and a session the other side has confirmed), on warm stone
  neutrals; `Manrope` (headings) + `Nunito Sans` (body) via Google Fonts —
  vanilla JS only for small UX touches (alert auto-dismiss, the inline
  "request a session" toggle, and the session chat's AJAX poll), no build
  step, no framework
- **Database**: MySQL/MariaDB (InnoDB, foreign keys enforced)
- **Server**: PHP's built-in server for local dev (`php -S`), Apache/XAMPP-compatible

## 3. Database — Table List

| Table | Purpose |
|---|---|
| `users` | Both personas (`role`: super_admin / member), bcrypt password hash, active/inactive status, bio |
| `skills` | Shared skill taxonomy (name + category); `is_active` flag (deactivate, never delete) |
| `member_skills` | One row per (member, skill, teach/learn direction); `proficiency_level` only on `teach` rows |
| `sessions` | One-on-one scheduled session between two members for one skill; explicit `status` state machine |
| `messages` | Private per-session chat thread |

See `database/schema.sql` for full column definitions, foreign keys, and seed data.

### Entity relationships (simplified)

```
users ──< member_skills >── skills
  │            (teach / learn, per skill)
  │
  ├──< sessions (requester_id, recipient_id) >── skills
  │
  └──< messages (sender_id) >── sessions
```

### Matching — computed live, never stored

A Member's "Matches" view (`MemberSkill::matchesFor()`) is a straight SQL
join: this Member's `member_skills` rows where `type = 'learn'`, joined
against every OTHER active Member's `member_skills` rows where
`type = 'teach'` on the same `skill_id`. A second, smaller query checks the
reverse direction (does that other Member want anything THIS Member
teaches?) purely to set a `mutual` flag on the result — it never adds or
removes rows. Nothing here is cached or precomputed: the moment either
Member edits their skill list, the very next page load reflects it,
exactly the same never-trust-a-stale-flag principle this suite uses for
project #7's overdue EMIs, #10's overstay flag, and #14's budget-overrun
alerts.

### Session overlap — rejected server-side on accept, not just hidden in the UI

`Session::hasConfirmedOverlap($userId, $start, $end)` checks whether a
Member already has a CONFIRMED session whose time range intersects the
proposed one. `Session::transition()` calls this for BOTH participants
before allowing a `requested → confirmed` transition — if either
participant already has a conflicting confirmed session, the accept is
rejected and the session stays `requested`. This only runs at the
`accept` step (not at request-creation time), matching how a real
double-booking would actually occur: two people can hold overlapping
*requests* simultaneously, but only one of those requests can ever become
*confirmed*.

The `accept` transition itself runs inside `Session::acceptTransactionally()`:
one transaction that takes `SELECT ... FOR UPDATE` locks on both
participants' `users` rows (in a fixed ascending-id order, so two
concurrent accepts sharing both participants can't deadlock each other)
and on the session row itself, before re-running the overlap check and
writing `status`. That closes the plain read-then-write race the original
version had — two near-simultaneous `accept` requests for a recipient's
two overlapping pending sessions (or a requester's two overlapping pending
sessions from different recipients) can no longer both read "no confirmed
overlap yet" and both commit; the second one is forced to wait for the
first to commit, then re-checks and correctly sees the first one's now-
confirmed overlap.

**Verified live** (2026-09-12) against real MariaDB, the same way projects
#3, #16, #19 and #20 in this suite verified their own mutexes: two pending
requests sharing the same recipient on an identical time window, accepted
simultaneously from two *separate* `php -S` processes (so the race can't be
serialized away by a single-threaded dev server), repeated 5 times —
exactly one session reached `confirmed` on every run, with the loser
correctly left at `requested`.

The same locked transaction also re-checks, before writing, that **both
participants' accounts are still active** and that the **proposed start
time hasn't already passed** — see "Account status and stale requests"
below.

### Account status and stale requests — re-checked on every write, not just at login

Three related rules, all enforced server-side and all reflected in the UI
rather than only hidden by it:

1. **Account status is re-read from the database on every protected
   request** (`Auth::requireLogin()`), not just inside `Auth::attempt()`.
   Checking it only at login meant a Member deactivated by the Admin
   mid-session kept full access — requesting, accepting, chatting — until
   they happened to log out. They are now force-logged-out on their very
   next page load with an explanatory message.
2. **A session can't be confirmed into or out of a deactivated account.**
   `acceptTransactionally()` re-reads both participants' status under the
   same `FOR UPDATE` lock it already takes, so deactivation can't race the
   accept. The other participant sees a notice on the session instead of a
   dead Accept button. Seeded fixture: session id `18` is a pending request
   from Manoj Pandey (`member18`, deliberately inactive) to Rohan Verma —
   Rohan can open it and decline it, but never confirm it.
3. **A request whose proposed slot has already passed can't be accepted.**
   `request()` always rejected a start time in the past, but a request left
   unanswered *until* after its own slot went by was still acceptable —
   confirming a session into the past, which was then immediately
   "Mark Completed"-able. The window is now re-validated at accept time too.

A Member also can't remove a skill from their **teach** list while a
`requested` or `confirmed` session for that exact skill is still live
(`Session::activeCountsByTeachSkill()`); the profile row shows an
"N live sessions" lock in place of the Remove button, and a forged POST is
rejected with the dependent count named. Deactivating a Member is never
*blocked* (an admin must always be able to remove a bad actor), but the
admin is told how many live sessions the deactivation strands.

### Session status — a small explicit state machine, not a free-text field

`Session::transition($sessionId, $actorId, $action)` is the single entry
point for every status change (`accept` / `decline` / `cancel` /
`complete`). It checks, in order: is the actor actually a participant?
is the current status valid for this action? is the actor the correct
participant for this action (only the recipient can accept/decline; either
participant can cancel/complete)? for `complete`, has the scheduled end
time actually passed? An invalid combination returns an error and the
database is never touched — no `UPDATE` is even attempted for a rejected
transition.

### Chat privacy — structural, not just a hidden link

Every message read/write goes through `Session::findForParticipant($id,
$userId)` first, which returns `null` unless `$userId` is literally the
session's `requester_id` or `recipient_id`. `SessionController::detail()`,
`messagesPoll()`, and `messagesSend()` all call this before touching the
`Message` model at all — a Member guessing another pair's session id gets
a 404 (or an empty JSON `{"error":"Not found"}` from the poll endpoint),
never a peek at the schedule or a single chat row. On the Admin side,
`AdminController` and every `admin/*` view never reference
`messages.body` or `sessions.notes` anywhere in their SQL or their PHP —
grep-verified (see §8) — so a session's actual content is never even
fetched into PHP on the Admin's code path, the same query-level privacy
pattern this suite uses for project #13's Wellness Coordinator and project
#15's Studio Overview.

## 4. Feature List

See `FEATURES.md` for the full persona-by-feature breakdown. In short:

- **Super Admin**: activate/deactivate Member accounts, manage the skills
  taxonomy (add / rename+recategorize / deactivate — never delete, once a
  skill has member history), a platform-wide stats dashboard (active
  members, sessions by status, most-requested skills) built entirely from
  counts and joins
- **Member**: maintain a bio and two skill lists (taught, with
  proficiency; wanted) against the shared taxonomy, browse/search the
  skill directory to see who teaches what, see a live "Matches" list
  (with a mutual-exchange badge), request/accept/decline/cancel/complete
  sessions under the state machine above, and chat privately within their
  own sessions

## 5. Default Login Credentials

All Member demo accounts use the same password shown below. Session
timestamps are seeded relative to `CURDATE()`, so the upcoming/pending/
past session views always look live however long after import the demo
actually happens.

| Role | Email | Password |
|---|---|---|
| Super Admin | `admin@skillswap.test` | `Admin@123` |
| Member (Rohan Verma) | `member1@skillswap.test` | `Member@123` |
| Member (Sneha Iyer) | `member2@skillswap.test` | `Member@123` |
| Member (Arjun Mehta) | `member3@skillswap.test` | `Member@123` |
| Member (Kavya Nair) | `member4@skillswap.test` | `Member@123` |
| Member (Aditya Kulkarni) | `member5@skillswap.test` | `Member@123` |
| ... | `member6@skillswap.test` … `member17@skillswap.test` | `Member@123` |
| Member (Manoj Pandey, **deliberately inactive**) | `member18@skillswap.test` | `Member@123` |

**Rohan Verma (`member1`) and Sneha Iyer (`member2`) are the deliberately
traceable mutual-exchange pair** for the viva demo: Rohan teaches Python
(which Sneha wants) and Sneha teaches Spanish (which Rohan wants) — each
shows up on the other's Matches list with the "Mutual Exchange" badge.
Kavya Nair (`member4`) wanting Java, matched against Arjun Mehta
(`member3`) and Aakash Nambiar (`member16`) who both teach Java but want
neither of Kavya's taught skills, is the paired **non-mutual** example.

Session id `3` (Kavya Nair ↔ Arjun Mehta, CONFIRMED, 2 days from import at
09:00–10:30) is the seeded overlap-check fixture — any new session touching
either of their schedules inside that window will be rejected on accept.

## 6. Setup Instructions

1. Copy the `peer-skill-exchange/` folder wherever you serve PHP from, or
   use the built-in server directly (below).
2. Start MySQL/MariaDB. On this suite's reference machine it listens on
   the default **port 3306** — see the top-level `CLAUDE.md` for
   why. Adjust `config/database.php` if your MySQL is on a different
   host/port.
3. Import the schema (creates the database, tables, and demo seed data):
   ```
   mysql -u root -P 3306 < database/schema.sql
   ```
   or import `database/schema.sql` via phpMyAdmin.
4. Serve the app. From the project folder:
   ```
   php -S localhost:8016 -t public public/index.php
   ```
   (`-t public` matters — without it, static assets under `public/assets`
   404 while the HTML still renders, since the built-in server's document
   root defaults to the directory PHP was launched from otherwise.)
5. Visit `http://localhost:8016/index.php?route=login` and sign in with
   any of the demo credentials above.

This project has no `composer.json` — no email/PDF/Excel dependency was
needed, so there's no `composer install` step.

## 7. Folder Structure

```
peer-skill-exchange/
  public/
    index.php             Front controller — all routes registered here
    assets/css/style.css  "Community" burnt-orange/teal theme (on top of Bootstrap 5)
    assets/js/main.js     Alert auto-dismiss, inline request-session toggle, chat AJAX poll
  app/
    controllers/          AuthController, AdminController, MemberController, SessionController
    models/                User, Skill, MemberSkill, Session, Message — one class per table (member_skills owns the matching query, sessions owns the state machine + overlap check)
    views/
      partials/            header.php, footer.php, navbar.php, flash.php
      auth/, admin/, member/, errors/
  core/                    Router, Database (PDO singleton), Auth, Csrf, Validator, helpers.php
  config/database.php      DB credentials (local dev defaults)
  database/schema.sql      Full schema + seed/demo data
```

Follows the suite's standard plain-PHP MVC layout (`GROUNDRULES.md` §1) —
no framework deviation for this project. Session scheduling and chat are
grouped into one `SessionController` rather than split by persona, since
"act on a session" is identical logic regardless of whether the acting
Member happens to be the original requester or the recipient — participant
membership (not role) is what the SQL actually checks.

## 8. Anticipated Viva Questions

**Why is matching computed live instead of stored in a `matches` table?**
A stored match table would need to be recalculated every time any Member
edits their taught or wanted skills — miss one recalculation and it lies.
Since the underlying join (`member_skills` against itself on `skill_id`)
is a cheap, indexed query, there's no real cost to computing it fresh on
every page load. It's the same reasoning this suite applies to project
#7's overdue EMI flag, #10's overstay flag, and #14's budget-overrun
alert: a flag is only trustworthy if it's recomputed at read time, not
written once and hoped to stay correct.

**Walk through the overlap check.** `Session::hasConfirmedOverlap($userId,
$start, $end, $excludeId)` runs
`WHERE status='confirmed' AND (requester_id = ? OR recipient_id = ?) AND
scheduled_start < ? AND scheduled_end > ?` — the classic interval-overlap
condition (`A.start < B.end AND A.end > B.start`). `Session::transition()`
calls this for BOTH the requester and the recipient before allowing a
`requested → confirmed` transition; if either already has a conflicting
CONFIRMED session, the whole accept is rejected with an explicit error and
the row's status is never touched. It intentionally only fires at accept
time, not at request-creation time, since two *requests* are allowed to
overlap (only one can ever actually become confirmed) — that mirrors how
a real calendar conflict is discovered, at the moment of confirming, not
at the moment of asking.

**Why a state-machine method instead of just letting a controller set
`status` directly?** Because "set status" isn't really one operation —
`requested → confirmed` needs the overlap check and requires the actor to
be the recipient; `confirmed → completed` requires the scheduled time to
have passed; every transition requires the actor to actually be one of
the two participants. Centralizing all of that in
`Session::transition($id, $actorId, $action)` means a controller can never
accidentally skip a check — there's exactly one code path that writes to
`sessions.status`, and it's `switch`-cased over `$action`, not `if`-chained
across three controllers.

**How is chat kept private, concretely?** `Session::findForParticipant($id,
$userId)` returns `null` unless `$userId` matches `requester_id` or
`recipient_id` on that row — every one of `SessionController`'s
detail/poll/send actions calls this before touching the `Message` model at
all, so a forged session id for a pair you're not in fails at the
`Session` lookup, before a single `messages` row is ever queried. This was
verified live: a non-participant polling or POSTing to another pair's
session id gets a 404 (or an empty poll response), and grep confirms
`AdminController` and every `admin/*` view never reference `messages.body`
or `sessions.notes` in the first place — the Admin's stats dashboard is
physically incapable of leaking chat content because it never fetches it.

**Why can a skill only be deactivated, not deleted?** Deleting a skill
that already has `member_skills` or `sessions` history would either
cascade-delete real profile/session data or leave orphaned references —
either way, a Member's past sessions and skill history would break.
`is_active = 0` just hides it from the "add to profile" picker and the
directory search going forward, while every historical row keeps reading
its name correctly forever (see skill id 22, "Adobe Flash Animation", in
the seed data — deactivated, but still attached to a real teach row).

**Why does a Member's request need to specify a skill the recipient
already teaches, rather than any skill at all?** `SessionController::
request()` checks `MemberSkill::exists($recipientId, $skillId, 'teach')`
before creating the row — requesting a session for a skill the other
Member never listed as something they teach doesn't make sense as a
teaching request, so it's rejected server-side with a clear error rather
than silently creating a session nobody can actually deliver.

**How are passwords and CSRF handled?** `password_hash()`/
`password_verify()` (bcrypt), never plaintext. Every state-changing form
carries a per-session CSRF token verified via `Csrf::requireValid()`
before anything touches the database — verified live with both a missing
and a forged token, both rejected with HTTP 419. Login regenerates the
session ID (`session_regenerate_id(true)`) to prevent session fixation.
Members can self-register (`AuthController::register()`); the form has no
role field at all, so a registrant can only ever create a `member` account
- there is no code path from the public register form to `super_admin`.
Super Admin stays admin-provisioned/seeded only. Registration validates
name/email/password server-side (`Validator`, 8-character password
minimum, password/confirmation match, `User::emailExists()` uniqueness
check) and carries the same CSRF protection as every other state-changing
form; new accounts are active immediately, matching this suite's
CampusConnect (project #11) precedent of no email-verification step.
