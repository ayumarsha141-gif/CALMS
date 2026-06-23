# CALMS — Career Adaptive Learning Management System

## 📌 Website Name

**CALMS (Career Adaptive Learning Management System)**

---

# 📖 Short Description

CALMS merupakan platform digital yang dirancang untuk membantu mahasiswa Informatika Universitas Mataram dalam merencanakan dan mempersiapkan karir sejak dini. Sistem ini menyediakan berbagai fitur pendukung seperti analisis kesenjangan kompetensi (Skill Gap Analysis), Career Roadmap, simulasi peluang lolos rekrutmen menggunakan metode Monte Carlo, rekomendasi laboratorium tugas akhir, rekomendasi sertifikasi, serta informasi tren industri untuk membantu mahasiswa meningkatkan kesiapan menghadapi dunia kerja.

---

# 👥 Team Members & Responsibilities

| Nama                         | NIM         | Module Responsibility      | Main Contributions                                                                                                                                                                                      |
| ---------------------------- | ----------- | -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Gusti Ayu Marsha Widyaswari  | F1D02410007 | Mahasiswa Module Developer | Fullstack, Mengembangkan fitur Dashboard Mahasiswa, Skill Gap Analysis, Career Roadmap, Recruitment Simulation, Laboratory Recommendation, Industry Insight, Certification Recommendation, dan Profile Management. |
| Winona Andien Jihan Habbibah | F1D02410027 | Dosen Module Developer     | Fullstack, Mengembangkan fitur Dashboard Dosen, Student Monitoring, Skill Report Review, Laboratory Review, dan Recruitment Simulation Report.                                                                     |
| Nadin Mufida                 | F1D02410128 | Admin & System Developer   | Fullstack, Mengembangkan Authentication System, Session Management, Dashboard Admin, User Management, Database Management, dan konfigurasi sistem.                                                                 |

---

# 👤 Website Users / Actors

## 1. Mahasiswa

### Features

* Login & Register
* Dashboard Mahasiswa
* Skill Gap Analysis
* Career Roadmap
* Recruitment Simulation (Monte Carlo)
* Laboratory Recommendation
* Recommended Certifications
* Industry Insight
* Academic Score Input
* Profile Management

---

## 2. Dosen

### Features

* Login
* Dashboard Dosen
* Student Monitoring
* Skill Report Review
* Laboratory Recommendation Review
* Recruitment Simulation Report
* Academic Guidance Support

---

## 3. Admin

### Features

* Login
* Dashboard Admin
* User Management
* Master Data Management
* System Monitoring

---

# 🗂️ Sitemap / Menu Structure

## Public

* Home
* Login
* Register

---

## Mahasiswa

* Dashboard
* Skill Gap Analysis
* Career Roadmap
* Recruitment Simulation
* Recommended Certifications
* Laboratory Recommendation
* Industry Insight
* Input Nilai
* Profile

---

## Dosen

* Dashboard
* Student Monitoring
* Skill Reports
* Laboratory Reviews
* Simulation Reports

---

## Admin

* Dashboard
* User Management
* Master Data

---

# 📁 Project Structure

```text
CALMS
│
├── config
│   └── database.php
│
├── includes
│   ├── auth_guard.php
│   ├── sidebar.php
│   ├── sidebar_dosen.php
│   └── sidebar_admin.php
│
├── pages
│   ├── user
│   │   ├── dashboard.php
│   │   ├── skill_gap.php
│   │   ├── career_roadmap.php
│   │   ├── simulation.php
│   │   ├── lab_recommendation.php
│   │   ├── certifications.php
│   │   ├── industry_insight.php
│   │   ├── input_nilai.php
│   │   └── profile.php
│   │
│   ├── dosen
│   │   ├── dashboard_dosen.php
│   │   ├── dosen_monitoring.php
│   │   ├── dosen_skill_report.php
│   │   ├── dosen_lab_review.php
│   │   └── dosen_simulation_report.php
│   │
│   └── admin
│       ├── dashboardAdmin.php
│       └── admin_master.php
│
├── login.php
├── register.php
├── logout.php
└── README.md
```

---

# 🛠️ Tech Stack

## Frontend

* HTML5
* CSS3
* JavaScript

## Backend

* PHP Native

## Database

* MySQL

## Development Tools

* Visual Studio Code
* XAMPP
* GitHub

---

# 🗄️ Database Configuration

## DBMS Used

```sql
MySQL
```

## Database Name

```sql
calms_db
```

## Default Port

```sql
3306
```

## Database Connection Example

```php
<?php

$conn = mysqli_connect(
    "localhost",
    "root",
    "",
    "calms_db"
);

if(!$conn){
    die("Connection Failed");
}

?>
```

---

# 📋 Main Database Tables

## 1. users

Menyimpan data akun pengguna sistem.

| Field    | Type         |
| -------- | ------------ |
| id       | INT (PK)     |
| fullname | VARCHAR(100) |
| email    | VARCHAR(100) |
| password | VARCHAR(255) |
| role     | ENUM         |

---

## 2. mahasiswa_profiles

Menyimpan informasi profil mahasiswa.

| Field         | Type         |
| ------------- | ------------ |
| id            | INT (PK)     |
| user_id       | INT (FK)     |
| nim           | VARCHAR(20)  |
| semester      | INT          |
| target_career | VARCHAR(100) |

---

## 3. skills

Menyimpan daftar skill yang digunakan dalam analisis.

| Field          | Type         |
| -------------- | ------------ |
| id             | INT (PK)     |
| skill_name     | VARCHAR(100) |
| industry_level | INT          |

---

## 4. student_skills

Menyimpan tingkat kemampuan mahasiswa pada setiap skill.

| Field         | Type     |
| ------------- | -------- |
| id            | INT (PK) |
| student_id    | INT (FK) |
| skill_id      | INT (FK) |
| student_level | INT      |

---

## 5. labs

Menyimpan data laboratorium yang tersedia.

| Field       | Type         |
| ----------- | ------------ |
| id          | INT (PK)     |
| lab_name    | VARCHAR(100) |
| description | TEXT         |

---

## 6. simulations

Menyimpan hasil simulasi rekrutmen mahasiswa.

| Field             | Type      |
| ----------------- | --------- |
| id                | INT (PK)  |
| student_id        | INT (FK)  |
| probability_score | FLOAT     |
| created_at        | TIMESTAMP |

---

# 🚀 Main Features

## 1. Skill Gap Analysis

Menganalisis kesenjangan kompetensi mahasiswa terhadap kebutuhan industri sehingga mahasiswa mengetahui skill yang perlu ditingkatkan.

## 2. Career Roadmap

Memberikan panduan pengembangan kompetensi berdasarkan target karir mahasiswa.

## 3. Recruitment Simulation

Melakukan simulasi peluang lolos rekrutmen menggunakan pendekatan Monte Carlo.

## 4. Laboratory Recommendation

Merekomendasikan laboratorium tugas akhir berdasarkan minat dan kompetensi mahasiswa.

## 5. Certification Recommendation

Menyediakan rekomendasi sertifikasi yang relevan dengan kebutuhan industri.

## 6. Industry Insight

Menyajikan informasi mengenai tren industri dan perkembangan teknologi terkini.

## 7. Student Monitoring

Membantu dosen memantau perkembangan kompetensi mahasiswa.

## 8. User Management

Memungkinkan admin mengelola data pengguna dan sistem.

---

# 📊 Project Status

✅ Functional Prototype Completed

### Implemented Features

* Authentication & Authorization
* Session Management
* Skill Gap Analysis
* Career Roadmap
* Recruitment Simulation
* Laboratory Recommendation
* Certification Recommendation
* Industry Insight
* Student Monitoring
* User Management

---

# 🔮 Future Development

* AI-Based Career Recommendation
* Real-Time Industry Data Integration
* Mobile Application Version
* Alumni Tracking System
* Advanced Career Analytics Dashboard

---

# 🎯 Project Goals

CALMS bertujuan untuk membantu mahasiswa Informatika Universitas Mataram dalam memahami kebutuhan industri, mengidentifikasi kompetensi yang perlu ditingkatkan, serta menyusun strategi pengembangan karir yang lebih terarah. Sistem ini diharapkan dapat meningkatkan kesiapan kerja mahasiswa melalui pendekatan berbasis data, monitoring perkembangan kompetensi, dan rekomendasi pengembangan diri yang adaptif.

##  Penggunaan AI dalam Kode 

Kami menggunakan AI sebagai alat bantu dalam beberapa bagian pengembangan, di antaranya:

- *Skill Gap Analysis (skill_gap.php)*: AI membantu menyusun query SQL untuk menghitung 
  perbandingan level skill mahasiswa dengan standar industri, serta logika pengelompokan 
  kategori gap (rendah/sedang/tinggi).