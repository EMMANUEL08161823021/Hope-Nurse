# Hope Nurse – Online Examination System

This is a web-based online examination application designed to manage exams, questions, and student assessments in an academic setting. It supports admin-controlled exam creation and monitoring, as well as secure student participation with timed exams and autosave.

---

## Login credentials for testing
Admin 
email: emmanueloguntolu48@gmail.com
password: password

Student
email: josh@gmail.com
password: emmanuel

## Technology Stack

**Backend**
- PHP 
- MySQL
- PDO (PHP Data Objects)

**Frontend**
- HTML5
- Bootstrap 5
- Vanilla JavaScript

**Development Environment**
- XAMPP (recommended for Windows)
- Apache Web Server

---

## Project Structure

hope-nurse/
└── public
   ├── assets/ # JS, CSS, Bootstrap,
├── src/
│ ├── admin/ # Admin dashboard, exam management, question management, student management
│ ├── student/ # Student dashboard, instructions, exam pages, results
│ ├── api/ # API endpoints (start attempt, autosave, submit exam)
│ ├── auth/ # Login, register, logout
│ ├── config/ # Database connection (db.php)
│ ├── middleware/ # Authentication & role guards
│ ├── constants/ # Shared headers, footers, helpers
│ 
├── migrations/ # SQL schema files
├── scripts/ # Migration runner scripts
└── README.md


---

## Features Completed

### Authentication
- Admin and Student roles
- Secure login & logout
- Password hashing with `password_hash()`

### Admin Features
- Admin dashboard overview
- Create, edit, view results on each exam, and delete exams
- Manage exam status (draft / in progress / closed)
- Add, edit, and delete questions and options
- View student attempts and scores
- Release or hide exam results
- Manage students (activate, block, change program)

### Student Features
- Student dashboard
- View available exams by program/course
- Exam instructions page
- Timed exams with countdown
- Autosave answers during exams
- Submit exam manually or automatically on timeout
- View results after admin release

### Exam Engine
- Question types:
  - Single choice
  - Multiple choice
  - True / False
  - Short answer
- Auto grading for objective questions
- Attempt tracking with duration and timestamps

---

## Setup Instructions

### 1. Prerequisites
- PHP 7.4 or higher
- MySQL 
- XAMPP (recommended)

### 2. Clone Repository
```bash
git clone https://github.com/EMMANUEL08161823021/Hope-Nurse.git
cd hope-nurse
