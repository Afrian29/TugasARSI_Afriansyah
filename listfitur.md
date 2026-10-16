# List Fitur — Project Scheduling Application

Daftar semua fitur yang **sudah ada** di project saat ini, dikelompokkan per modul dan dirujuk ke ID requirements di PRD (`docs/PRD.md`). Status: ✅ sudah diimplementasi · ⏳ ditunda (lihat bagian terakhir).

---

## 1. Project Management (PRJ)

- ✅ **PRJ-1** — Buat project: `name` wajib (divalidasi, error message jelas), `organization`, `description` opsional.
- ✅ **PRJ-1 AC** — Kalender kerja default otomatis dibuat saat project dibuat (Mon–Fri, 8 jam/hari).
- ✅ **PRJ-2** — Daftar project, buka, edit, dan hapus project dengan konfirmasi (soft delete).
- ✅ Halaman ringkasan project (`/projects/{id}`): tanggal mulai, selesai, dan total durasi proyek.

## 2. Working Calendar (CAL)

- ✅ **CAL-1** — Atur hari kerja per minggu dan jam kerja per hari.
- ✅ **CAL-2** — Hari weekend otomatis dihitung sebagai hari non-kerja (diturunkan dari hari kerja).
- ✅ **CAL-3** — Tambah/edit/hapus hari libur khusus (nama, tanggal, dan rentang tanggal opsional).
- ✅ **CAL-4** — Import template hari libur nasional Indonesia (1 klik, tanpa duplikat saat diulang).
- ✅ **CAL-5** — Setiap perubahan kalender memicu recalculation seluruh jadwal (via observer).

## 3. Task Management (TSK)

- ✅ **TSK-1** — Tambah task: nama, tanggal mulai, durasi (hari kerja), prioritas (Low/Medium/High/Critical), deskripsi.
- ✅ **TSK-1 AC** — Durasi harus positif; start date di hari non-kerja otomatis digeser ke hari kerja berikutnya; end date dihitung dari durasi + kalender.
- ✅ **TSK-2** — Edit, hapus (dengan konfirmasi), dan urutkan task (pindah naik/turun).
- ✅ **TSK-3** — Sub-aktivitas: task bisa di-nest di bawah parent task (hirarki).
- ✅ **TSK-4** — Parent task dihitung otomatis dari children (earliest start → latest finish), durasi parent tidak diinput manual.
- ✅ **TSK-5** — Milestone (durasi 0), ditandai bentuk belah ketupat ◆ di list, Gantt, dan PERT.
- ✅ **TSK-6** — Percent complete (0–100) dengan progress bar di task list dan overlay di Gantt bar.
- ✅ **TSK-7 (sebagian)** — Indent/outdent untuk mengubah level task.

## 4. Task Dependencies (DEP)

- ✅ **DEP-1** — Satu atau lebih predecessor per task.
- ✅ **DEP-2** — Finish-to-Start (FS).
- ✅ **DEP-3** — Start-to-Start (SS).
- ✅ **DEP-4** — Lag/lead dalam hari kerja, termasuk lag negatif.
- ✅ **DEP-5** — Circular dependency dicegah sebelum save, dengan pesan yang menyebutkan nama cycle-nya (plus defense-in-depth di engine).
- ✅ **DEP-6** — Banyak predecessor ⇒ start date diambil dari constraint terakhir (max).

## 5. Resource / Team Management (RES)

- ✅ **RES-1** — CRUD team member: nama, kontak (email/phone), role, salary.
- ✅ **RES-2** — Edit & hapus member; peringatan jika member masih punya assignment.
- ✅ **RES-3** — Cuti/hari libur personal per member (rentang tanggal + catatan), CRUD lengkap.
- ✅ **RES-4** — Salary dienkripsi at-rest (custom `EncryptedInteger` cast), disembunyikan dari serialisasi (`$hidden`), tidak pernah di-log, hanya disimpan (tidak dipakai hitung biaya).
- ✅ **RES-5** — Toggle sembunyikan/tampilkan field salary di view (preferensi session).

## 6. Task Assignment (ASN)

- ✅ **ASN-1** — Assign lebih dari satu member ke satu task.
- ✅ **ASN-2** — Nama member tampil di daftar task (dan di Gantt bar).
- ✅ **ASN-3** — Peringatan otomatis jika member sedang cuti atau punya task lain yang overlap dengan rentang task ini.
- ✅ **ASN-5** — Filter jadwal/task per member (di task list dan Gantt).

## 7. Visualization

### Gantt Chart (GNT)

- ✅ **GNT-1** — Daftar task di kiri + bar pada timeline kalender di kanan.
- ✅ **GNT-2** — Panah antar bar untuk dependency; weekend & hari libur di-shading.
- ✅ **GNT-3** — Zoom timeline: day, week, month.
- ✅ **GNT-4** — Bentuk khusus untuk parent task dan milestone; garis "today".
- ✅ **CPM-2** — Task kritis disorot di Gantt (warna + border tebal + penanda ⚠, bukan hanya warna — aksesibel).
- ✅ Overlay percent complete pada bar, marker ⏰ untuk konflik cuti di legend.

### PERT / Network Diagram (PRT)

- ✅ **PRT-1** — Task sebagai node yang dihubungkan panah dependency, layout otomatis kiri→kanan.
- ✅ **PRT-2** — Node menampilkan nama, durasi, start/end, dan panel detail ES/EF/LS/LF + slack.
- ✅ **PRT-3** — Pan, zoom (in/out/reset), dan klik node untuk detail task.
- ✅ Node kritis disorot (border tebal + ⚠ + label "Critical").

## 8. Critical Path Analysis (CPM)

- ✅ **CPM-1** — Kalkulasi critical path penuh (forward pass + backward pass) di `ScheduleEngine`, memperhitungkan durasi, dependency, dan kalender kerja.
- ✅ **CPM-2** — Task kritis (slack = 0) disorot di Gantt, PERT, dan task list.
- ✅ **CPM-3** — Slack/float per task ditampilkan (kolom Slack di task list, panel PERT).
- ✅ **CPM-4** — Tanggal selesai project dan total durasi project ditampilkan.
- ✅ **CPM-5** — Beberapa critical path sekaligus didukung (jatuh otomatis dari perhitungan).
- ✅ **CPM-6** — Highlighting otomatis ter-update setiap kali data berubah (observer → recalculation).

## 9. Scheduling Engine (PRD §8)

- ✅ Durasi dihitung dalam hari kerja; hari non-kerja & libur dilewati.
- ✅ Start date = max(input user, constraint predecessor + lag).
- ✅ End date = start + durasi hari kerja − 1.
- ✅ Parent task: start = anak paling awal, end = anak paling akhir.
- ✅ Dependency ke/dari parent di-expand ke semua leaf task di bawahnya.
- ✅ Satu entry point recalculation (`ScheduleEngine::calculate()`), pure PHP tanpa Eloquent/HTTP.
- ✅ Semua nilai komputed (effective dates, slack, critical flag) di-persist ke database.
- ✅ Graph tidak pernah bisa tersimpan dalam keadaan cycle.
- ✅ Reference test case: ≥ 5 jaringan task dihitung tangan, mencakup SS, lag negatif, holiday, dan milestone.

## 10. Localization & Accessibility

- ✅ UI dua bahasa: **English + Indonesia** (`resources/lang/{en,id}/`), switcher di header, pilihan disimpan di session, default English (PRD §9).
- ✅ Keyboard navigation di task list: panah untuk pindah sel (kolom tetap), **Enter/Space** = edit, **Delete** = hapus; ada hint di bawah tabel.
- ✅ Aksesibilitas: `scope="col"` pada header, `role="grid"`, `aria-label` pada tombol ikon, ARIA progressbar, kontras teks memadai.
- ✅ Critical path tidak dibedakan hanya lewat warna (penanda ⚠ + ketebalan garis).

## 11. Performance

- ✅ Benchmark 500 task di MySQL (`php artisan benchmark:seed-500`) — semua path < 1 detik (target PRD §9):
  - Recalculation: ~280 ms · Task list render: ~130 ms · Gantt render: ~265 ms.
- ✅ Optimasi query: bulk `UPDATE … CASE` menggantikan 500 save per-baris; N+1 di task list & Gantt diganti 3 bulk query.

## 12. Security & Quality

- ✅ Salary terenkripsi at-rest, tidak di-serialisasi, tidak di-log (RES-4).
- ✅ `composer audit` 0 advisories · `npm audit` 0 vulnerabilities.
- ✅ Quality gates: **Laravel Pint**, **Larastan (PHPStan)**, **Pest** (83 test passing) — termasuk run terpisah terhadap MySQL (`composer test:mysql`).
- ✅ Endpoint `/health` untuk health check.

---

## ⏳ Belum diimplementasi (ditunda / di luar scope MVP)

| Fitur | ID PRD | Alasan |
|---|---|---|
| Export Gantt/PERT ke PDF atau image | GNT-6, PRT-4 | Butuh dependency baru (keputusan D-18), ide di `docs/SUGGESTIONS.md` |
| Drag bar Gantt untuk ubah tanggal / gambar dependency | GNT-5 | Sama — butuh dependency baru |
| Dependency FF dan SF | DEP-7 | PRD P2 |
| Duplicate project sebagai template | PRJ-3 | PRD P2 |
| Copy/paste task | TSK-7 | Ditunda (D-20) |
| Import hari libur dari file resmi | CAL-4 (versi file) | Versi template statis sudah ada |
| Peringatan beban kerja berdasarkan leave saat scheduling (resource calendar) | ASN-4 | PRD P2 — peringatan ASN-3 sudah ada |
| Filter member di PERT | — | PRD P1 belum diminta untuk PERT |
| Biaya project dari gaji, resource leveling, baseline, progress tracking, notifikasi, kolaborasi real-time, login/multi-user, import MS Project | — | Non-goals MVP (PRD §3 / §7) |
| Autosave | §9 Reliability | Form tersimpan saat submit (bukan autosave ketik-sambil-jalan) |
