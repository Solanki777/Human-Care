# 🏥 Human Care – AI-Powered Smart Hospital Management System

![PHP](https://img.shields.io/badge/PHP-8.x-blue)
![Python](https://img.shields.io/badge/Python-3.x-yellow)
![FastAPI](https://img.shields.io/badge/FastAPI-009688)
![MySQL](https://img.shields.io/badge/MySQL-Database-blue)
![Groq AI](https://img.shields.io/badge/Google-Groq_AI-orange)
![License](https://img.shields.io/badge/License-Educational-green)

> AI-powered Smart Hospital Management System with autonomous multi-role AI agents and intelligent cybersecurity. Built for **Agentic Arena 2026 – Build the Future with AI Agents**.

---

## 📖 Overview

**Human Care** integrates traditional hospital management with autonomous AI agents and intelligent cybersecurity. Built using **PHP, MySQL, Python, FastAPI, Streamlit, and Google Groqi AI**, the platform enables secure, intelligent, and automated healthcare services for patients, doctors, and hospital administrators.

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
`Google Groqi AI` · `Prompt Engineering` · `Tool-Based AI Architecture` · `Role-Based AI Agents`

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

Add your **Google Groqi API Key** to the AI configuration file or environment variables.

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

## 🚀 Future Enhancements

- 🎤 Voice-based AI assistant
- 🌍 Multilingual conversations
- 🩺 AI symptom triage
- 📄 Medical report analysis
- 🧠 RAG-based medical knowledge
- 📈 Predictive health analytics
- 💊 Medicine reminder system
- 📹 Telemedicine integration
- 🚨 Emergency detection agent
- 📑 AI discharge summary generator
- ⌚ Wearable device integration
- ☁️ Cloud deployment
- 📱 Mobile application
- 🏥 Multi-hospital support

---

## 👥 Team

### Solanki Mahesh Bharatbhai — Team Lead · AI Security & Agent Developer

Responsibilities: Overall project architecture · MediMate AI development · Nexora security architecture · AI agent development · Login threat detection · Gmail phishing scanner · URL risk analyzer · AI decision engine · FastAPI backend · Documentation

### Chudasama Parth Maheshbhai — Full Stack Developer

Responsibilities: Human Care development · PHP backend · MySQL database · Frontend development · Security integration · Testing · Documentation

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
