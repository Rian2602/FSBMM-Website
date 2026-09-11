## Deskripsi

<!-- Ringkas apa yang dilakukan PR ini dan mengapa. Tautkan isu/plan bila ada
     (mis. closes #12, plan `docs/superpowers/plans/...`). -->

## Jenis perubahan

- [ ] feat — fitur baru
- [ ] fix — perbaikan bug/keamanan (`security` bukan type commit — pakai `fix`/`chore`)
- [ ] refactor — perubahan internal tanpa mengubah perilaku
- [ ] docs — dokumentasi
- [ ] test — penambahan/perbaikan tes
- [ ] chore — tugas repo/tooling
- [ ] ci/build — pipeline

## Checklist sebelum merge

- [ ] Commit memakai format conventional: `type(scope): description`
- [ ] Tepat satu area menyentuh plan/spec — baca plan terkait dulu
      (`docs/superpowers/plans|specs/`) dan tak jebol konvensi yang dicatatnya
- [ ] `composer verify` hijau (test suite + PHPStan + Pint)
- [ ] Perubahan frontend: `npm run build` berhasil tanpa error
- [ ] Tidak menambah resource/kolom PII individu di sisi federasi `/admin`
- [ ] Audit trail baru memakai deskripsi bebas PII
- [ ] Tidak menyentuh batas panel (`/admin` vs `/panel-sba`) secara silang
- [ ] Sertifikat/kartu/verifikasi: rute baru dideklarasikan di atas catch-all
- [ ] Dekripsi password/secret tidak ikut commit (`FSBMM_*` env saja)

## Catatan untuk reviewer

<!-- Hal yang perlu perhatian khusus saat review. -->