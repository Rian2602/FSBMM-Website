# SP5 Phase 5 Task 5.3 — `MemberCardSeeder` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans.

**Goal:** Membuat `database/seeders/MemberCardSeeder.php` (1 kartu aktif untuk demo member, 1 kartu dicabut untuk demo member lain) + registrasi di `DatabaseSeeder`.

**Architecture:** Seeder idempotent (`firstOrCreate` keyed `card_number`), org demo `spm-kecap-bango`, `created_by` = demo sba_admin org (fallback null). Generation inline (service 6.1 menyerap nanti). Tanpa factory.

**Tech Stack:** Laravel 12 Seeder.

## Global Constraints
- Registrasi setelah `MemberDataSeeder` di `DatabaseSeeder::run()`.
- `firstOrCreate` — ~20 test memakai `$this->seed()` penuh.
- Status via konstanta `MemberCard::STATUS_ACTIVE/STATUS_REVOKED`; `revoked_at`/`revocation_reason` diisi untuk kartu dicabut.
- Verifikasi: `composer test` → 316/1; pint; `migrate:fresh --seed` smoke + tinker count.
- Bahasa Indonesia; commit `feat(sp5):`/`docs(sp5):`; anotasi append-only.

---

### Task 1: Tulis `MemberCardSeeder` + registrasi (commit `feat`)

**Files:**
- Create: `database/seeders/MemberCardSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1:** Tulis seeder (org demo guard; `$org->members()->orderBy('id')`; `$creator` sba_admin; card1 aktif member[0], card2 dicabut member[1] + revoked_at + reason; gen inline `FSBMM-YYYY-<8 alnum>` + `bin2hex(random_bytes(32))`).
- [ ] **Step 2:** Sisip `MemberCardSeeder::class` setelah `MemberDataSeeder::class`.
- [ ] **Step 3:** Verifikasi — pint --test; `migrate:fresh --seed`; tinker count → 2 (1 aktif / 1 dicabut); `php artisan test` → 316/1.
- [ ] **Step 4:** Commit `feat(sp5): task 5.3 MemberCardSeeder — demo active+revoked cards, register in DatabaseSeeder`

---

### Task 2: Tick + anotasi (commit `docs`)

**Files:**
- Modify: `docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md` Task 5.3 (~baris 636–642)

- [ ] **Step 1:** Tick 3 item + anotasi (hash feat+docs; org demo & guard; firstOrCreate idempotent; gen inline → refactor service 6.1; tanpa factory YAGNI; suite 316/1 hijau).
- [ ] **Step 2:** `composer test`; status bersih.
- [ ] **Step 3:** Commit `docs(sp5): task 5.3 MemberCardSeeder annotations + checklist`