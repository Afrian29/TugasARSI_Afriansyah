# Deskripsi Screenshot Aplikasi "Perencana Proyek"

Dokumen ini berisi deskripsi masing-masing tangkapan layar dari aplikasi **Perencana Proyek** (berjalan di `127.0.0.1:8000`), sebuah aplikasi web manajemen proyek berbasis mesin penjadwalan CPM (Critical Path Method) dengan antarmuka dalam Bahasa Indonesia (tersedia pilihan bahasa EN/ID).

Proyek contoh yang digunakan: **Website Redesign** (klien: Acme Corp), jadwal 2026-10-12 s.d. 2026-12-15.

---

## 1. Daftar Proyek — `/`
<img width="1920" height="1080" alt="Screenshot (574)" src="https://github.com/user-attachments/assets/fae2f7d5-1916-42ca-af3b-72511b6d02d0" />

<img width="1920" height="1080" alt="Screenshot (584)" src="https://github.com/user-attachments/assets/e1414064-a57b-47a0-8304-fc3fa55b2985" />

**Halaman utama** yang menampilkan seluruh proyek dalam bentuk tabel.

- Judul halaman: **Proyek**, dengan tombol **+ Proyek baru** di kanan atas.
- Kolom tabel: Nama, Organisasi, Tugas, Anggota, Selesai proyek, serta aksi **Ubah** dan **Hapus**.
- Proyek yang tampil:
  | Nama | Organisasi | Tugas | Anggota | Selesai |
  |---|---|---|---|---|
  | Security Test | — | 1 | 2 | 2026-10-19 |
  | ZZ E2E Journey Test | E2E QA Org | 8 | 1 | 2026-01-22 |
  | Benchmark 500 | Benchmark | 500 | 10 | 2026-06-01 |
  | Website Redesign | Acme Corp | 9 | 6 | 2026-12-15 |
  | Website Redesign (duplikat) | Acme Corp | 9 | 6 | 2026-12-15 |
- Catatan: terdapat data uji (E2E, benchmark 500 tugas, security test) dan dua entri "Website Redesign" yang identik.
- Footer: "Jadwal dihitung oleh mesin CPM bawaan. Tanggal, slack, dan jalur kritis diperbarui otomatis setiap ada perubahan."

---

## 2. Ringkasan Proyek — `/projects/1`
<img width="1920" height="1080" alt="Screenshot (575)" src="https://github.com/user-attachments/assets/9b2f966e-b717-45e4-a04d-86e91018fd82" />



**Tab Ringkasan** (dashboard proyek).

- Header proyek: nama, organisasi, rentang jadwal, dan navigasi tab: Ringkasan, Tugas, Kalender, Anggota, Gantt, Beban kerja, PERT.
- Empat kartu statistik: **Tugas = 9**, **Anggota = 6**, **Dependensi = 8**, **Total durasi = 47 hari kerja**.
- Kartu **Ringkasan jadwal**: Mulai proyek 2026-10-12, Selesai proyek 2026-12-15.
- Kartu **Ringkasan jadwal** kedua (tampak duplikat) berisi tombol pintasan **Gantt**, **PERT**, **Tugas**.
- Kartu **Tugas jalur kritis** (ikon peringatan) dengan daftar tugas dan tanggalnya: Requirements Gathering, Frontend Development, Design Mockups, Backend API, Development, Database Setup, Testing, Deployment.
- Catatan: kartu "Ringkasan jadwal" muncul dua kali — kemungkinan bug tampilan/duplikasi komponen.

---

## 3. Daftar Tugas — `/projects/1/tasks`
<img width="1920" height="1080" alt="Screenshot (576)" src="https://github.com/user-attachments/assets/e34cc465-ae81-4e75-b289-2820f9d7a8eb" />
**Tab Tugas**, tabel manajemen tugas.

- Tombol **+ Tugas baru** dan filter dropdown **Anggota** ("Semua anggota").
- Kolom: Tugas, Prioritas, Anggota bertugas, Durasi, Mulai, Selesai, Slack, Progres, dan aksi (Ubah, pindah naik/turun, indent/outdent, Hapus).
- Struktur tugas (hierarki):
  - Requirements Gathering — Tinggi — Alice Johnson — 5 hari (10-12 → 10-16)
  - Design Mockups — Tinggi — Alice Johnson — 8 hari (10-19 → 10-28)
  - **Development** (tugas induk) — Kritis — Bob Smith, Carol White — 15 hari (10-29 → 11-30)
    - Frontend Development — Kritis — 8 hari
    - Backend API — Kritis — 10 hari
    - Database Setup — Sedang — 5 hari
  - Testing — Sedang — Dave Brown — 7 hari (12-01 → 12-09)
  - Deployment — Sedang — Frank Miller — 3 hari (12-10 → 12-14)
  - Sign-off (milestone ◆) — Tinggi — 0 hari (12-15)
- Semua tugas bernilai **slack 0** (merah) dengan ikon peringatan jalur kritis, progres 0%.
- Petunjuk keyboard: panah untuk berpindah sel, Enter untuk mengubah, Delete untuk menghapus tugas.

---

## 4. Form "Buat tugas" (Modal) — `/projects/1/tasks`
<img width="1920" height="1080" alt="Screenshot (582)" src="https://github.com/user-attachments/assets/c20397be-a378-47f9-b4e7-5f8e3c63b01b" />


**Dialog modal pembuatan tugas baru** di atas halaman Tugas.

- **Nama** (wajib).
- **Mulai (opsional)** — date picker; jika kosong, tugas ditempatkan otomatis oleh pendahulunya (tanggal mulai non-hari kerja digeser ke hari kerja berikutnya).
- **Durasi** (wajib, default 1) — dalam hari kerja; gunakan 0 untuk milestone.
- **Prioritas** (wajib, default "Sedang") dan **Progres (%)** (default 0).
- **Tugas induk** — dropdown, default "— Tingkat atas —"; sub-kegiatan bersarang hingga 4 tingkat, tanggal induk dihitung dari anak-anaknya.
- **Deskripsi** — textarea.
- **Anggota bertugas** — checkbox untuk Alice Johnson, Bob Smith, Carol White, Dave Brown, Eve Davis, Frank Miller, masing-masing dengan kolom alokasi `%` (1–100%, default 100%).
- Bagian bawah (terpotong) kemungkinan berisi pengaturan dependensi dan tombol simpan.

---

## 5. Kalender Proyek — `/projects/1/calendar`
<img width="1920" height="1080" alt="Screenshot (577)" src="https://github.com/user-attachments/assets/7e9caa07-7a87-4e25-8e97-491bcb73cb66" />![Uploading Screenshot (582).png…]()


**Tab Kalender**, pengaturan waktu kerja dan hari libur.

**Panel kiri — Waktu kerja (CAL-1/CAL-2):**
- **Hari kerja**: checkbox Sen–Jum tercentang; Sab dan Min tidak (akhir pekan).
- **Jam kerja per hari**: 8.
- Tombol **Simpan kalender** (setiap perubahan menghitung ulang seluruh jadwal).
- **Template hari libur nasional (CAL-4)**: pilih tahun (2026) dan tombol **Impor template**. Catatan: template hanya kenyamanan, tanggal hari keagamaan harus diverifikasi terhadap daftar SKB resmi.

**Panel kanan — Hari libur (CAL-3):**
- Form: **Nama**, **Tanggal**, **Tanggal akhir (opsional)**, tombol **Tambah**.
- Status saat ini: "Belum ada hari libur."

---

## 6. Anggota Tim — `/projects/1/resources`
<img width="1920" height="1080" alt="Screenshot (578)" src="https://github.com/user-attachments/assets/03f7b9ee-2a27-4ccc-8825-9e4e6a2ce30b" />

**Tab Anggota**, daftar sumber daya manusia.

- Tombol **+ Anggota baru** dan tombol **Sembunyikan gaji**.
- Kartu per anggota berisi nama, jabatan (badge), email, telepon, gaji, jumlah tugas, serta bagian **Cuti / hari libur** dengan tautan **+ Tambah cuti**.
- Contoh:
  - **Alice Johnson** — UX Designer — gaji 123,456 — 2 tugas — belum ada cuti.
  - **Bob Smith** — Frontend Developer — 1 tugas — cuti 2026-03-15 → 2026-03-17 (Conference attendance).
  - **Carol White** — Backend Developer — 1 tugas.
- Setiap kartu memiliki aksi **Ubah** dan **Hapus**.

---

## 7. Form "Tambah anggota tim" (Modal) — `/projects/1/resources`
<img width="1920" height="1080" alt="Screenshot (583)" src="https://github.com/user-attachments/assets/0466c6b4-b7dd-40ac-9c99-6a819b6d36e7" />

**Dialog modal** untuk menambah anggota baru.

- Field: **Nama** (wajib), **Email**, **Telepon**, **Jabatan**, **Gaji**.
- Catatan di bawah Gaji: disimpan terenkripsi; tidak pernah diekspor ke JSON atau log; tidak dipakai untuk biaya di MVP.
- Tombol **Batal** dan **Simpan**.

---

## 8. Bagan Gantt — `/projects/1/gantt`
<img width="1920" height="1080" alt="Screenshot (579)" src="https://github.com/user-attachments/assets/1600ae25-0c2d-4d4b-9861-72c7c4bc7ce1" />

**Tab Gantt**, visualisasi linimasa tugas.

- Kontrol zoom: **Hari**, **Minggu**, **Bulan**; filter **Anggota**.
- Legenda: Kritis (merah, ikon peringatan), Induk (biru tua), Milestone (◆), Bukan hari kerja (kuning), Anggota cuti selama tugas ini (ikon alarm), Hari ini (garis putus-putus merah).
- Daftar tugas di kiri (dengan indentasi untuk subtugas), batang waktu di kanan; semua batang berwarna merah karena berada di jalur kritis.
- Panah dependensi antar-batang, serta label nama penanggung jawab (mis. Alice Johnson) di samping batang.
- Akhir pekan ditandai dengan latar berbeda; area timeline dapat di-scroll horizontal.

---

## 9. Diagram PERT — `/projects/1/pert`
<img width="1920" height="1080" alt="Screenshot (581)" src="https://github.com/user-attachments/assets/6ae20ebf-9f06-4ce3-9a67-54180eb2b11e" />

**Tab PERT**, diagram jaringan tugas.

- Kontrol zoom (−, 100%, +) dengan petunjuk: geser/scroll untuk navigasi, klik simpul untuk melihat rinciannya.
- Simpul tugas berbingkai merah (kritis) menampilkan: nama, durasi, ES (Early Start), EF (Early Finish), Slack, dan status "Kritis".
- Alur: Requirements Gathering → Design Mockups → Development → (lanjut), serta Frontend Development → Backend API → Database Setup; panah berlabel **FS** (Finish-to-Start).
- Panel kanan **Rincian tugas**: "Pilih simpul untuk melihat LS/LF dan slack."

---

## 10. Bagan Beban Kerja — `/projects/1/resource-chart`
<img width="1920" height="1080" alt="Screenshot (580)" src="https://github.com/user-attachments/assets/7627102f-9914-48cd-bda0-5efd83e92ad2" />

**Tab Beban kerja**, histogram penggunaan sumber daya.

- Zoom: **Hari**, **Minggu**, **Bulan**; filter Anggota, rentang tanggal **Dari/Sampai**, dan **Reset filter**.
- Legenda: beban per periode, **kapasitas terlampaui** (merah + ikon peringatan), hari cuti dengan tugas tetap ditugaskan (ikon alarm), bukan hari kerja (kuning); "Tanpa batang = kapasitas menganggur"; klik batang untuk melihat tugas di baliknya.
- Baris: Alice Johnson, Bob Smith, Carol White, Dave Brown, Eve Davis, Frank Miller, dan **Tugas tanpa penugasan**.
- Alice Johnson memiliki batang 100% selama hari kerja, dengan **2 hari berwarna merah** (kapasitas terlampaui) di sekitar tanggal 24–25. Dave Brown, Frank Miller, dan Alice ditandai ikon peringatan.
- Baris "Tugas tanpa penugasan" terisi batang 100% panjang sejak sekitar akhir Oktober.
- Garis merah putus-putus menandai **Hari ini**.

---

## Ringkasan Fitur

| Fitur | Tab / Halaman |
|---|---|
| Daftar & kelola proyek | `/` |
| Statistik & jalur kritis | Ringkasan |
| CRUD tugas, hierarki, dependensi | Tugas |
| Hari kerja, jam kerja, hari libur | Kalender |
| Tim, gaji terenkripsi, cuti | Anggota |
| Linimasa visual | Gantt |
| Beban & kapasitas sumber daya | Beban kerja |
| Diagram jaringan CPM/PERT | PERT |

# Project Scheduling App

A project scheduling web application built with **Laravel 13**, **Livewire 4**, and **Pest**. It supports work-breakdown structures, working calendars, Finish-to-Start dependencies with lag, resource allocation, CPM critical-path calculation, Gantt charts, and PERT analysis. The UI is available in **English and Indonesian**.

See [`PRD-Project-Scheduling-App.md`](PRD-Project-Scheduling-App.md) and [`docs/PRD.md`](docs/PRD.md) for requirements, and the `docs/` folder for decision records, suggestions, and the traceability matrix.

## Requirements

- PHP >= 8.3 (with `pdo_mysql`, `sqlite3`, `mbstring`, `openssl` extensions)
- Composer
- MySQL 8 / MariaDB 10.4+ (XAMPP is fine) — for the app database
- Node.js + npm (only needed if you change front-end assets)

## Installation

```sh
composer install
cp .env.example .env        # skip if .env already exists
php artisan key:generate
php artisan migrate          # after the database exists (see below)
npm install && npm run build # only if assets changed; built assets are committed
```

Or run everything in one step:

```sh
composer setup
```

### Database (XAMPP MySQL)

`.env` is pre-configured for XAMPP's MySQL:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=project_scheduler
DB_USERNAME=project_scheduler
```

Create the databases and a dedicated user (run in phpMyAdmin, **not** as root):

```sql
CREATE DATABASE IF NOT EXISTS project_scheduler
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS project_scheduler_test
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'project_scheduler'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON `project_scheduler`.* TO 'project_scheduler'@'localhost';
GRANT ALL PRIVILEGES ON `project_scheduler_test`.* TO 'project_scheduler'@'localhost';
FLUSH PRIVILEGES;
```

Then confirm connectivity:

```sh
php artisan migrate:status
```

## Running the app

### Option A — Laravel dev server

```sh
php artisan serve
```

Open <http://127.0.0.1:8000> — the home page lists projects.

### Option B — XAMPP / Apache

1. Start **Apache** (and MySQL) from the XAMPP control panel.
2. Keep this project inside `htdocs` (e.g. `C:\xampp\htdocs\proyekArsi`).
3. Apache serves the `public/` directory — either:
   - visit <http://localhost/proyekArsi/public>, or
   - add a vhost pointing its `DocumentRoot` at `C:\xampp\htdocs\proyekArsi\public`
     and set `<Directory "C:\xampp\htdocs\proyekArsi"> AllowOverride All </Directory>`.
4. Ensure `mod_rewrite` is enabled (it is by default in XAMPP) so `public/.htaccess` routes requests to `index.php`.

The home page is rendered in English by default; use the **EN / ID** switcher in the top navigation to change language (the choice is stored in the session).

## Tests & checks

```sh
composer test        # Pest against in-memory SQLite (default, no DB setup needed)
composer test:mysql  # Pest against MySQL database `project_scheduler_test`
composer lint        # Pint code style check
composer analyse     # Larastan static analysis
composer check       # test + lint + analyse (run this before committing)
```

`phpunit.xml` uses SQLite `:memory:` by default; `phpunit.mysql.xml` switches the same suite to MySQL via `DB_DATABASE=project_scheduler_test` (credentials come from `.env`).

## Project layout

```
app/Domain/Scheduling/   Pure scheduling engine (working calendar, CPM, dependencies)
app/Services/Schedule/   Recalculation + dependency validation (application services)
app/Livewire/            UI components (projects, tasks, calendar, resources, gantt, pert)
app/Models/              Eloquent models
resources/lang/en|id     English and Indonesian translations
docs/                    PRD, DECISIONS, SUGGESTIONS, TRACEABILITY
tests/                   Pest unit + feature tests
```

## Documentation

- [`docs/PRD.md`](docs/PRD.md) — full requirement list (PRJ, TSK, CAL, DEP, ASN, RES, CPM, GNT, PRT)
- [`docs/DECISIONS.md`](docs/DECISIONS.md) — architectural decision records
- [`docs/SUGGESTIONS.md`](docs/SUGGESTIONS.md) — improvement backlog
- [`docs/TRACEABILITY.md`](docs/TRACEABILITY.md) — requirement → code → test matrix
