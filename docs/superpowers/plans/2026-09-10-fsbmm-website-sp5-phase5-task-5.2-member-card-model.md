# SP5 Phase 5 Task 5.2 — `MemberCard` Model Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans.

**Goal:** Membuat `app/Models/MemberCard.php` sesuai checklist Task 5.2 (fillable, casts, 3 relasi, konstanta status) dengan gaya konvensi repo.

**Architecture:** Model-only, tanpa service/scope. Fillable mengikuti kolom migrasi 5.1; relasi `member()`, `organization()`, `creator()`; konstanta `STATUS_ACTIVE`/`STATUS_REVOKED` untuk dipakai service (6.1) + seeder (5.3).

**Tech Stack:** Laravel 12 Eloquent.

## Global Constraints
- Gaya repo: `casts()` method-style, relasi `belongsTo` polos, `HasFactory`, fillable array.
- Commit hanya file model + plan kanonik.
- Anotasi append-only; bahasa Indonesia; commit style `feat(sp5):`/`docs(sp5):`.
- Verifikasi: `composer test` → 316/1, `pint --test app/Models/MemberCard.php` → passed.

---

### Task 1: Tulis model `MemberCard` (commit `feat`)

**Files:**
- Create: `app/Models/MemberCard.php`

- [ ] **Step 1:** Tulis model (liat plan inline: HasFactory; konstanta STATUS_ACTIVE='aktif'/STATUS_REVOKED='dicabut'; fillable 9 field; casts issued_at/revoked_at => datetime; member()/organization() belongsTo; creator() => belongsTo(User::class, 'created_by')).
- [ ] **Step 2:** `vendor/bin/pint --test app/Models/MemberCard.php`; `php artisan test` → 316/1.
- [ ] **Step 3:** Commit `feat(sp5): task 5.2 MemberCard model — fillable, casts, member/organization/creator relations, status constants`

---

### Task 2: Tick + anotasi (commit `docs`)

**Files:**
- Modify: `docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md` Task 5.2 (~baris 627–634)

- [ ] **Step 1:** Tick 4 item + anotasi `(** executed @2026-09-10 ... **)` (hash feat+docs; gaya casts method-style, `creator()` pola `recorded_by`, HasFactory, cast datetime; suite 316/1).
- [ ] **Step 2:** `composer test` → 316/1; status bersih.
- [ ] **Step 3:** Commit `docs(sp5): task 5.2 MemberCard model annotations + checklist`