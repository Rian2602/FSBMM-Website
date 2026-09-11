# FSBMM Website — Phase 4: Kapasitas Anggota & Akuntabilitas

Follows the ad-hoc public-enhancement phases (1: visual/interactive; 2: exports +
search + notifications; 3: member cards + public verification). Phase 4 closes the
remaining "professional federation" gaps that the phase-1..3 review flagged:

- **Sertifikat e-learning** — a course completer currently has no proof of capacity.
- **Jejak audit (audit trail)** — admin actions leave no accountable record.
- **Operasi massal (batch actions)** — SBA admins edit members/complaints one by one.
- **Robust PDF export** — `FederationReportGenerator` hard-depends on the
  `wkhtmltopdf` binary; the production/dev box has none, so the "Unduh Laporan PDF"
  button 500s.

Constraints inherited from `AGENTS.md`: federation-side (`/admin`) surfaces must stay
**PII-free** (aggregates only); SBA panel stays tenant-scoped at the panel layer;
custom Filament pages are registered explicitly in `->pages()` (+ pretty routes via
`Panel::authenticatedRoutes()`); new public routes go **above** the `{page:slug}`
catch-all.

## Checklist

### A. Sertifikat e-learning
- [x] A1 Migration `course_certificates` (user_id + course_id unique, `certificate_number`
      unique, `verification_token` unique 64, `final_score` nullable, `issued_at`,
      `revoked_at`/`revocation_reason` nullable).
- [x] A2 `App\Models\CourseCertificate` (relations, `isRevoked()`).
- [x] A3 `App\Support\CertificateService` — `issue()` (requires published + complete,
      idempotent), `forUserCourse()`, `validateToken()`, best-final-quiz `finalScore()`.
- [x] A4 `App\Support\QrCodeRenderer` — real QR SVG via committed `bacon/bacon-qr-code`
      (no JS-side fake QR).
- [x] A5 `CertificatePrintController` (shared by both panels) + panel-authenticated
      route `/certificates/{record}/print` issuing on first print.
- [x] A6 Public `/verifikasi/sertifikat/{token}` (above the catch-all) + verify view.
- [x] A7 Print view `public/certificates/print.blade.php` (A4 landscape, QR, print button).
- [x] A8 "Kursus Saya" + course detail (admin + SBA) expose the certificate action.

### B. Jejak audit
- [x] B1 Migration `audit_logs` (+ indexes) and `App\Models\AuditLog`.
- [x] B2 `App\Support\AuditLogger::record()` — denormalised actor snapshot, org id,
      no PII in descriptions.
- [x] B3 Hooks: `MemberObserver`, `ComplaintObserver` (status transition),
      `MemberCardService` (issue/revoke), export generation.
- [x] B4 `/admin` `AuditTrailPage` (super_admin only, PII-free).
- [x] B5 `/panel-sba` `AuditTrailPage` (tenant-scoped).

### C. Operasi massal (SBA)
- [x] C1 Members: bulk activate / deactivate / delete (observer keeps `member_count`).
- [x] C2 Complaints: bulk process / resolve / delete.
- [x] C3 Dues: bulk delete (+ single audit entry for the batch).

### D. Robust PDF
- [x] D1 `FederationReportGenerator` falls back to a print-ready HTML artefact when
      Snappy/`wkhtmltopdf` is unavailable instead of throwing.

### E. Verification
- [x] E1 `CertificateTest`, `AuditTrailTest`, `SbaBatchActionTest`, `FederationReportExportTest`.
- [x] E2 `vendor/bin/pint` clean, `composer test` → **378 passed** (was 338).

## Out of scope (recorded follow-up)
- Certificate **revocation UI** — schema + `validateToken()` already honour
  `revoked_at`; no federation screen revokes certificates yet.
- Federation-side certificate registry (super_admin list of issued certificates).

## Execution log

### Deviations / discoveries

1. **`reports/federation-summary.blade.php` was reading a variable that never
   existed.** The template used `$upcomingEvents` while
   `FederationReportGenerator::gatherData()` returns `upcoming_events`, so
   `view()->render()` threw `Undefined variable` — the dashboard's "Unduh Laporan
   PDF" button could never have worked (masked by also needing the missing
   `wkhtmltopdf` binary). Fixed to `$upcoming_events`; `FederationReportExportTest`
   locks the whole export path now.
2. **Certificate cards cannot nest an `<a>`.** The "Kursus Saya" card was one big
   anchor, so the new certificate link was invalid HTML. The card is now a `div`
   wrapping an inner link plus the certificate action (both panels).
3. **`ExportDownloadController` had a `BinaryFileResponse` return type.** The HTML
   fallback artefact is served inline, so the type-hint was dropped and `.html`
   responses get `Content-Type: text/html`.
4. **Member card print QR was decorative.** Phase 3's card-back QR was a hashed JS
   pattern that encoded nothing. Replaced with the real `QrCodeRenderer` SVG so a
   printed card verifies when scanned (no test depended on the old markup).
5. **Audit descriptions are deliberately PII-free.** `member.*` entries record only
   the changed *column names*; `complaint.*` record only status transitions. The
   `/admin` trail is super_admin-only AND asserts it never renders a member name
   (`AuditTrailTest::test_admin_audit_page_never_exposes_member_names`).
6. **Deliberately no certificate revocation UI** — see out-of-scope above;
   `validateToken()` honours `revoked_at` and is covered by a test that sets it
   directly.
7. **Bulk-action record resolution is already tenant-safe** — Filament resolves
   selected ids through `$table->getQuery()`, i.e. the resource's scoped query.
   Both a member and a complaint cross-tenant probe assert no writes happen.

### Test evidence

```
Tests:    378 passed (1313 assertions)
Duration: 53.91s
```

New suites: `CertificateTest` (12), `AuditTrailTest` (9), `SbaBatchActionTest` (5),
`FederationReportExportTest` (1).
