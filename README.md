# CALMS — Career Adaptive Learning Management System

## 📌 Website Name
CALMS (Career Adaptive Learning Management System)

---

# 📖 Short Description
CALMS adalah platform ekosistem digital adaptif yang dirancang untuk membantu mahasiswa Informatika Universitas Mataram dalam mempersiapkan karir sejak semester awal hingga memasuki dunia kerja. Sistem ini berfungsi sebagai “GPS Karir Adaptif” yang menyediakan roadmap pembelajaran personal, analisis skill gap, simulasi peluang lolos rekrutmen menggunakan metode Monte Carlo, rekomendasi Laboratorium Tugas Akhir, serta insight tren industri berbasis data real-time seperti LinkedIn dan portal lowongan kerja. :contentReference[oaicite:0]{index=0}

---

# 👥 Team Members & Responsibilities

| Nama | NIM | Role | Responsibilities |
|------|------|------|------|
| Gusti Ayu Marsha Widyaswari | F1D02410007 | Frontend Developer & System Analyst | Mendesain antarmuka website, membuat dashboard frontend, analisis kebutuhan sistem, dokumentasi UI/UX |
| Winona Andien Jihan Habbibah | F1D02410027 | Backend Developer | Mengembangkan backend system, authentication, API integration, database connection |
| Nadin Mufida | F1D02410128 | Database Designer & Documentation | Mendesain database, membuat ERD, struktur tabel, dan dokumentasi sistem |

---

# 👤 Website Users / Actors

## 1. Mahasiswa
### Features
- Login & Register
- Dashboard Skill Gap
- Career Roadmap
- Monte Carlo Recruitment Simulation
- Lab Recommendation
- Upload CV
- Upload Academic Transcript
- Recommended Certifications
- Industry Insight
- Career Progress Monitoring

---

## 2. Dosen Wali / Pengelola Kurikulum
### Features
- Login
- Student Career Monitoring
- KRS Recommendation Validation
- Student Skill Gap Monitoring
- Lab Compatibility Review
- Academic Guidance Dashboard
- Monitoring Career Readiness

---

## 3. Admin
### Features
- User Management
- Industry Trend Management
- System Monitoring
- Career Readiness Reports
- Data Integration Management
- Course & Certification Management
- Laboratory Mapping Management

---

# 🗂️ Sitemap / Menu Structure

## Public
- Home
- About CALMS
- Features
- Contact

---

## Mahasiswa
- Dashboard
- Skill Gap Analysis
- Career Roadmap
- Recruitment Simulation
- Recommended Certifications
- Lab Recommendation
- Industry Insight
- Profile

---

## Dosen
- Dashboard
- Student Monitoring
- Academic Recommendation
- Student Reports
- Career Analytics

---

## Admin
- Dashboard
- User Management
- Industry Data Management
- Reports
- System Settings
- Database Monitoring

---

# 🛠️ Tech Stack

## Frontend
- HTML5
- CSS3
- JavaScript

## Backend
- PHP Native

## Database
- MySQL

## Development Tools
- Visual Studio Code
- XAMPP
- GitHub

---

# 🗄️ DBMS Configuration

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

# 📋 Table Specifications

## 1. users

| Field | Type | Description |
|------|------|------|
| id | INT (PK) | User ID |
| fullname | VARCHAR(100) | Full Name |
| email | VARCHAR(100) | Email Address |
| password | VARCHAR(255) | User Password |
| role | ENUM | mahasiswa / dosen / admin |

---

## 2. mahasiswa_profiles

| Field | Type | Description |
|------|------|------|
| id | INT (PK) | Profile ID |
| user_id | INT (FK) | User Reference |
| nim | VARCHAR(20) | Student ID |
| semester | INT | Current Semester |
| target_career | VARCHAR(100) | Career Goal |

---

## 3. skills

| Field | Type | Description |
|------|------|------|
| id | INT (PK) | Skill ID |
| skill_name | VARCHAR(100) | Skill Name |
| industry_level | INT | Industry Standard Score |

---

## 4. student_skills

| Field | Type | Description |
|------|------|------|
| id | INT (PK) | Data ID |
| student_id | INT (FK) | Student Reference |
| skill_id | INT (FK) | Skill Reference |
| student_level | INT | Student Skill Score |

---

## 5. labs

| Field | Type | Description |
|------|------|------|
| id | INT (PK) | Lab ID |
| lab_name | VARCHAR(100) | Laboratory Name |
| description | TEXT | Lab Description |

---

## 6. simulations

| Field | Type | Description |
|------|------|------|
| id | INT (PK) | Simulation ID |
| student_id | INT (FK) | Student Reference |
| probability_score | FLOAT | Recruitment Probability |
| created_at | TIMESTAMP | Simulation Date |

---

# 🚀 Main Features

- Dynamic Career Roadmap
- Skill Gap Analysis
- Monte Carlo Recruitment Simulation
- Lab Alignment & Recommendation
- Industry Trend Integration
- Certification Recommendation
- Career Readiness Dashboard
- Academic & Industry Mapping

---

# 🔮 Future Development

- LinkedIn API Integration
- ATS CV Checker
- AI-Based Recommendation System
- Mobile Application Version
- Real-Time Industry Analytics
- Alumni Career Tracking System

---

# 📊 Project Status
🚧 Currently Under Development

---

# 🎯 Project Goals
CALMS bertujuan untuk membantu mahasiswa Informatika Universitas Mataram mengurangi kebingungan roadmap karir, meningkatkan kesiapan kerja, mengurangi skill gap dengan industri, serta menyediakan sistem monitoring karir berbasis data dan simulasi adaptif. :contentReference[oaicite:1]{index=1}
