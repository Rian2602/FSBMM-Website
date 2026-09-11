# SP5 Phase 5 Task 5.4 — Card Tests (+ minimal `MemberCardService`)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans.

**Goal:** `tests/Feature/MemberCardTest.php` + (deviasi scope) `app/Support/MemberCardService.php` minimal, suite hijau.

**Deviasi scope (disetujui user @2026-09-10):** Pull-forward minimal `MemberCardService` ke 5.4 sebagai enforcer nyata §9.6 (one-active auto-revoke, nonaktif guard, cross-tenant). Task 6.1 lanjut `validateToken` + formalisasi; 6.2 delapan service tests tetap.

**Architecture:** Eloquent + SPL exception (`AuthorizationException` cross-tenant, `DomainException` nonaktif); tak ada factory baru.

## Global Constraints
- Komit feat = service + test dalam SATU commit (coupling jujur). 
- Cross-tenant guard di SERVICE (#9 + §11.4 member-auth); UI Phase 7 translating nanti.
- `create` (bukan firstOrCreate) di service issue; DB unique 5.1 safety net.
- Verifikasi: pint; `php artisan test` → 325/1; `composer test`.
- Bahasa Indonesia; commit `feat(sp5):`/`docs(sp5):`; anotasi append-only.

---

### Task 1: Service minimal + MemberCardTest (commit `feat`)

**Files:**
- Create: `app/Support/MemberCardService.php`
- Create: `tests/Feature/MemberCardTest.php`

- [ ] Service: `issue()` (guardEligible → auto-revoke aktif lama → create; collision max-3 → RuntimeException), `revoke()` (+org guard), `reissue()` (revoke + issue), `resolveCurrentCard()`, `generateCardNumber()`, `generateVerificationToken()`, `generateUniqueCardNumber()` (ponytail race note), `guardEligible()`.
- [ ] Test 9 method snake_case → lihat mapping tabel di plan presentasi.
- [ ] Verifikasi pint + `--filter MemberCardTest` 9 passed + suite 325/1.
- [ ] Commit `feat(sp5): task 5.4 MemberCardTest + minimal MemberCardService (issue/revoke/reissue incl. one-active rule)`

---

### Task 2: Tick + anotasi (commit `docs`)

**Files:**
- Modify: `docs/superpowers/plans/2026-09-08-fsbmm-website-sp5-operational-reporting.md` Task 5.4 (~baris 706–718)

- [ ] Tick 9 item + anotasi: hash; deviasi scope; mapping §11.4→test; pilihan exception SPL; race ceiling; direct-create duplikat = constraint DB 5.1; suite 325/1.
- [ ] `composer test`; status bersih.
- [ ] Commit `docs(sp5): task 5.4 MemberCardTest annotations + checklist`