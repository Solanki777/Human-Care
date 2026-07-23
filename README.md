# 🏥 Human Care – AI-Powered Smart Hospital Management System

deployed link : http://humancare.fwh.is/

![PHP](https://img.shields.io/badge/PHP-8.x-blue)
![Python](https://img.shields.io/badge/Python-3.x-yellow)
![FastAPI](https://img.shields.io/badge/FastAPI-009688)
![MySQL](https://img.shields.io/badge/MySQL-Database-blue)
![Groq AI](https://img.shields.io/badge/Groq_AI-orange)
![License](https://img.shields.io/badge/License-Educational-green)

> AI-powered Smart Hospital Management System with autonomous multi-role AI agents and intelligent cybersecurity. Built for **Agentic Arena 2026 – Build the Future with AI Agents**.

---

## 🏆 Recognition & Showcase

### 🤖 Agentic Arena 2026

**Human Care with MediMate AI** was developed and submitted for **Agentic Arena 2026 – Build the Future with AI Agents**, organized by **TechVerse Solutions**.

🏅 **Recognized among the Top 61 Unique Participants** out of **800+ participants**.

🥇 **Achieved Rank 54 among 800+ participants.**

<p align="center">
  <img src="https://github.com/user-attachments/assets/8eeb847c-1738-47aa-8292-b2e7ea4ed043" width="500">
</p>

---

### 🛡️ STEMSpire 2026

The project was further enhanced with **Nexora – Autonomous AI Cybersecurity Agent** and submitted for **STEMSpire 2026 – STEM Innovation & Prototype Showcase**, organized by **Gujarat Technological University (GTU)**.

This version combines **AI-powered healthcare assistance** with **autonomous cybersecurity**, providing intelligent protection against modern cyber threats.

### ✨ Key Highlights

- 🛡️ **Nexora** – Autonomous AI Cybersecurity Agent
- 🤖 **MediMate AI** – Intelligent Healthcare Assistant
- 🔐 AI-Based Login Threat Detection
- 🎯 Credential Stuffing Detection
- 🚨 Password Spraying Detection
- 📧 AI-Powered Gmail Phishing Detection
- 🌐 AI-Based URL Risk Analysis
- ⚡ Autonomous Decision Engine
- 🚫 Automatic IP Blocking & Admin Email Alerts

## 📖 Overview

**Human Care** integrates traditional hospital management with autonomous AI agents and intelligent cybersecurity. Built using **PHP, MySQL, Python, FastAPI, Streamlit, and Groqi AI**, the platform enables secure, intelligent, and automated healthcare services for patients, doctors, and hospital administrators.

| Feature | Description |
|---|---|
| 🤖 Role-based AI Agents | Specialized agents for patients, doctors, and admins |
| 🛡️ Nexora Security | Autonomous AI cybersecurity with real-time threat detection |
| 💬 MediMate AI | Intelligent healthcare assistant powered by Groq AI |
| 📊 Hospital Analytics | Real-time dashboards and operational insights |
| 🔒 Role-Based Access | Secure, scoped data access per user role |
| ⚡ Real-Time DB Ops | Live appointment management and record updates |

---

## 🌟 Key Features

### 👤 Patient Portal

- Secure registration & login
- Online appointment booking, cancellation & rescheduling
- Medical history & digital prescriptions
- Real-time doctor chat
- Health education resources
- AI patient assistant
- Hospital navigation & profile management

### 👨‍⚕️ Doctor Portal

- Doctor registration with admin verification workflow
- Appointment management & patient medical records
- Digital prescription management & consultation notes
- Doctor-patient chat
- AI clinical assistance
- Patient visit summary generation

### 👨‍💼 Admin Portal

- Doctor verification & approval
- User management & appointment monitoring
- Hospital analytics & activity logs
- Education management & hospital configuration
- AI administrative assistant
- Security dashboard

---

## 🤖 MediMate AI – Autonomous Multi-Agent System

Three specialized AI agents, each tailored to a specific user role, securely accessing only authorized data.

### 👤 Patient Agent

| Capability | Description |
|---|---|
| Appointment management | Book, cancel, and reschedule appointments |
| Medical records | Retrieve history, prescriptions, and profile data |
| Doctor recommendations | Suggest doctors based on department or need |
| Health education | Answer health FAQs and provide resources |
| Navigation | Guide through hospital departments |

### 👨‍⚕️ Doctor Agent

| Capability | Description |
|---|---|
| Schedule view | Today's and upcoming appointments |
| Patient summaries | Medical history retrieval and summarization |
| Prescription assistance | Medicine suggestions and clinical guidance |
| Visit summaries | Auto-generated post-consultation notes |
| Appointment completion | Mark and manage completed visits |

> **Note:** AI recommendations support medical professionals and do not replace clinical judgment.

### 👨‍💼 Admin Agent

| Capability | Description |
|---|---|
| Dashboard summary | Hospital-wide statistics at a glance |
| Doctor verification | Approve or reject doctor registrations |
| Appointment management | Approve, reject, and monitor appointments |
| Analytics | Doctor performance and patient analytics |
| Reports | Operational reports and hospital statistics |

> All administrative actions require user confirmation before execution.

---

## 🛡️ Nexora – Autonomous AI Cybersecurity Agent

Nexora continuously monitors the hospital system for threats and automatically responds to suspicious activities.

- AI-based login threat detection
- Brute force, credential stuffing & password spraying detection
- Gmail phishing scanner & URL risk analyzer
- Autonomous IP blocking with risk scoring engine
- AI decision engine with real-time security dashboard
- Admin email alerts & comprehensive threat logging

---

## 🧠 AI Workflow

```
User Login
    │
    ▼
Role Identification (Patient / Doctor / Admin)
    │
    ▼
Appropriate AI Agent
    │
    ▼
Intent Understanding
    │
    ▼
Secure Database Access
    │
    ▼
Action Execution / Retrieval
    │
    ▼
Intelligent AI Response
```

---

## 📊 System Architecture

```
              Users
    ┌──────────┼──────────┐
    │          │          │
Patients    Doctors    Admins
    │          │          │
    └──────────┼──────────┘
               │
      Human Care Web System
               │
  ┌────────────┼────────────┐
  │            │            │
  ▼            ▼            ▼
MediMate AI  Hospital DB  Nexora Security
  │            │            │
  └────────────┼────────────┘
               │
     Intelligent Responses
  + Autonomous Threat Protection
```

---

## ⚙️ Technology Stack

### Frontend
`HTML5` · `CSS3` · `Bootstrap` · `JavaScript` · `AJAX`

### Backend
`PHP 8.x` · `Python 3.x` · `FastAPI` · `Streamlit`

### AI & Machine Learning
`Groqi AI` · `Prompt Engineering` · `Tool-Based AI Architecture` · `Role-Based AI Agents`

### Database
`MySQL`

### Security
`OAuth2 Authentication` · `Gmail API` · `AI Threat Detection` · `Risk Scoring Engine` · `Session Management` · `SQL Injection Protection`

---

## ✅ Hospital Management Modules

- [x] Patient Management
- [x] Doctor Management
- [x] Appointment Management
- [x] Medical Records
- [x] Prescription Management
- [x] Online Consultation
- [x] Chat System
- [x] Health Education
- [x] AI Patient Assistant
- [x] AI Doctor Assistant
- [x] AI Admin Assistant
- [x] AI Cybersecurity (Nexora)
- [x] Hospital Analytics
- [x] Security Dashboard

---

## 📂 Project Structure

```
Human-Care/
│
├── admin/
├── doctor/
├── patient/
│
├── ai/
│   ├── medimate_ai/
│   ├── patient_agent/
│   ├── doctor_agent/
│   ├── admin_agent/
│   └── tools/
│
├── security/
│   ├── nexora/
│   ├── phishing_scanner/
│   ├── login_monitor/
│   ├── url_analyzer/
│   └── decision_engine/
│
├── api/
├── config/
├── classes/
├── includes/
├── database/
├── uploads/
├── assets/
├── scripts/
├── styles/
└── README.md
```

---

## 🚀 Installation Guide

### 1. Clone the repository

```bash
git clone https://github.com/Solanki777/Human-Care.git
```

### 2. Start XAMPP

Start **Apache** and **MySQL** from the XAMPP control panel.

### 3. Create the database

```sql
CREATE DATABASE human_care;
```

### 4. Import the database

```
database/reg.sql
```

*(Or import the provided SQL dump if multiple databases are included.)*

### 5. Configure database credentials

Update `config/database.php` with your MySQL credentials.

### 6. Configure AI

Add your **Groqi API Key** to the AI configuration file or environment variables.

### 7. Run the FastAPI backend

```bash
uvicorn main:app --reload
```

### 8. (Optional) Run the Streamlit AI dashboard

```bash
streamlit run app.py
```

### 9. Launch the application

```
http://localhost/Human-Care
```

---

## 🔒 Security Highlights

| Category | Features |
|---|---|
| Authentication | Role-based auth, session management, user authorization |
| Threat Detection | AI login monitoring, brute force, credential stuffing, password spraying |
| Content Security | Gmail phishing scanner, URL threat analysis |
| Response | Automatic IP blocking, risk scoring engine, AI decision engine |
| Logging | Security event logging, real-time alerts, admin notifications |
| Data Protection | SQL injection protection, secure database queries |

---

## 👥 Team

### Solanki Mahesh Bharatbhai — Team Lead · AI Security & Agent Developer

Responsibilities: Overall project architecture · MediMate AI development · Nexora security architecture · AI agent development · Login threat detection · Gmail phishing scanner · URL risk analyzer · AI decision engine · FastAPI backend · Documentation

---

## 🎯 Project Objectives

- Improve hospital workflow efficiency
- Enhance patient experience with AI assistance
- Support doctors with intelligent clinical tools
- Automate administrative operations
- Protect hospital infrastructure using autonomous AI cybersecurity
- Demonstrate secure, role-based AI agents capable of real-world healthcare automation

---

## 📜 License

This project was developed for **educational, research, and innovation purposes** as part of **Agentic Arena 2026 – Build the Future with AI Agents**.

© 2026 **Human Care | MediMate AI | Nexora AI Security** · All Rights Reserved.
