# SP5 Phase 5 Task 5.1 — `member_cards` Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans.

**Goal:** Membuat tabel `member_cards` (migration-only) sesuai checklist Task 5.1 plan kanonik + spec §9.2–9.4, dengan tata letak kolom/FK/index mengikuti konvensi SP3.

**Architecture:** Migration schema-only, tanpa model/service (Task 5.2/5.3/5.4 menyusul). `card_number` & `verification_token` unik global; `organization_id` wajib untuk tenant-scoping.

**Tech Stack:** Laravel 12 Schema Builder; SQLite (test) / MySQL (prod).

## Global Constraints
- Nama file migrasi LITERAL: `database/migrations/2026_09_08_000001_create_member_cards_table.php`. Jangan ubah.
- Konvensi: `foreignId()->constrained()`; `status` string + komentar (bukan enum) — departure kecil, dicatat anotasi.
- Commit hanya file migrasi + plan kanonik.
- Verifikasi: `composer test`, `vendor/bin/pint --test`, `migrate:fresh --seed` DB dev.
- Bahasa Indonesia; commit style `feat(sp5):`/`docs(sp5):`; anotasi append-only.

---

### Task 1: Tulis migrasi `member_cards` (commit `feat`)

**Files:**
- Create: `database/migrations/2026_09_08_000001_create_member_cards_table.php`

- [ ] **Step 1:** Tulis migrasi (lihat plan inline: id, organization_id FK restrict, member_id FK cascade, card_number unique, verification_token unique, status string default aktif, issued_at/revoked_at datetime nullable, revocation_reason nullable, created_by FK users nullOnDelete, timestamps, index org/member/status).
- [ ] **Step 2:** `php artisan migrate:fresh --seed` (smoke); `php artisan test` → 316/1; `vendor/bin/pint --test` pada file.
- [ ] **Step 3:** Commit `feat(sp5): task 5.1 member_cards migration — schema, FK, unique card_number/token, indexes`

---

### Task 2: Tick + anotasi (commit `docs`)

**Files:**
- Modify: `docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md` Task 5.1 (~baris 618–625)

- [ ] **Step 1:** Tick 4 item + anotasi `(** executed @2026-09-10 ... **)` (hash, konvensi, filename literal, suite 316/1).
- [ ] **Step 2:** `composer test` → 316/1; status bersih.
- [ ] **Step 3:** Commit `docs(sp5): task 5.1 member_cards migration annotations + checklist`