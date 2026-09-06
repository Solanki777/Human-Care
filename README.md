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

## ❓ Problem Statement

Traditional hospital management systems require users to manually navigate multiple pages for tasks such as booking appointments, retrieving patient records, approving doctors, and managing hospital operations.

These systems lack intelligent assistants capable of understanding natural language, automating repetitive workflows, and securely interacting with hospital data based on user roles.

The challenge was to build a **secure, AI-powered hospital management system** that enables Patients, Doctors, and Administrators to complete real-world tasks through conversational interactions — while maintaining strict role-based access control.

---

## 💡 Solution

Human Care integrates **MediMate AI**, a role-based multi-agent AI system that securely assists Patients, Doctors, and Hospital Administrators.

Instead of allowing unrestricted database access, each AI agent operates with predefined permissions, ensuring that users can only access information and perform actions authorized for their role.

The system combines conversational AI, secure backend APIs, and hospital workflows to automate healthcare operations while maintaining data privacy and security.

| Principle | Implementation |
|---|---|
| 🧩 Role isolation | Separate agent instances per user role |
| 🔐 Scoped data access | Agents query only permitted tables/records |
| ✅ Human-in-the-loop | Sensitive actions require explicit confirmation |
| 🛡️ Defense in depth | AI layer + backend validation + database constraints |

---

## 📸 AI Agent Demonstration

### 👤 Patient AI Assistant

MediMate's Patient Agent acts as a personal healthcare companion — helping patients manage their care journey through natural conversation instead of navigating multiple forms and menus.

**Key Capabilities**
- 📅 Book, cancel, and reschedule appointments conversationally
- 📋 Retrieve medical history, prescriptions, and profile details
- 🩺 Get doctor recommendations based on symptoms or department
- 📚 Ask health-related questions and receive educational guidance
- 🧭 Navigate hospital departments and services

<p align="center">
  <img width="629" height="874" alt="Patient AI Assistant declining an unauthorized database request and starting an appointment booking flow" src="https://github.com/user-attachments/assets/94e038b4-16a7-4199-a164-33641220e1d4">
  <img width="629" height="852" alt="Patient AI Assistant confirming appointment time and submitting the booking request" src="https://github.com/user-attachments/assets/c9b3463e-6b5f-4741-9ac5-8cb7bbd7c916">
</p>

<p align="center"><em>Patient AI Assistant refusing an out-of-scope destructive request, then guiding the patient through booking an appointment with a confirmation step before submission.</em></p>

---

### 👨‍💼 Administrator AI Assistant

The Admin Agent gives hospital administrators a conversational control center for operational oversight — from doctor verification to hospital-wide analytics — without manually digging through dashboards.

**Key Capabilities**
- 📊 Instant hospital-wide statistics and performance summaries
- 🩺 Review and approve/reject doctor registrations
- 📅 Approve, reject, and monitor appointments across the system
- 📈 Analyze doctor performance and patient engagement trends
- 📄 Generate operational and administrative reports

<p align="center">
  <img width="1587" height="848" alt="Admin AI Assistant refusing a destructive whole-database deletion request and requiring explicit confirmation" src="https://github.com/user-attachments/assets/40c9f89b-1b85-4373-84db-70350bf59ca4">
  <img width="1633" height="812" alt="Admin AI Assistant generating a full hospital statistics summary with pending items and approved doctors" src="https://github.com/user-attachments/assets/12c78b00-a40c-453c-a9ed-2865f595d687">
  <img width="1617" height="813" alt="Admin AI Assistant confirming and approving a pending appointment on request" src="https://github.com/user-attachments/assets/4e35aebb-192d-4947-bf1c-0634e629df11">
</p>

<p align="center"><em>Admin AI Assistant blocking a high-risk, irreversible action pending explicit confirmation, generating a real-time hospital summary, and approving a pending appointment only after admin confirmation.</em></p>

---

### 👨‍⚕️ Doctor AI Assistant

The Doctor Agent streamlines clinical workflows by giving doctors instant access to schedules, patient summaries, and prescription support — reducing time spent on administrative overhead.

**Key Capabilities**
- 🗓️ View today's and upcoming appointment schedules
- 🧾 Retrieve and summarize patient medical history
- 💊 Get AI-assisted medicine and clinical guidance suggestions
- 📝 Auto-generate post-consultation visit summaries
- ✅ Mark appointments as completed and manage consultations

<p align="center">
  <img width="611" height="622" alt="Doctor AI Assistant listing tomorrow's appointments and patient details on request" src="https://github.com/user-attachments/assets/b132ec7c-e282-4309-8621-2e8040a140d7">
  <img width="566" height="610" alt="Doctor AI Assistant suggesting possible medicines for a patient based on appointment reason, with a clinical judgment disclaimer" src="https://github.com/user-attachments/assets/3cda5cf4-981b-4adf-8211-72296765a404">
  <img width="601" height="418" alt="Doctor AI Assistant marking an appointment as completed" src="https://github.com/user-attachments/assets/9959e342-f2af-491b-8809-bd9372e90a18">
</p>

<p align="center"><em>Doctor AI Assistant surfacing schedule and patient details, offering clinical medicine suggestions with an explicit disclaimer, and marking a consultation as completed.</em></p>

---

## 🔒 AI Security Design

Giving an AI agent unrestricted access to a hospital database is a serious risk — a single ambiguous prompt or adversarial input could expose sensitive medical records, modify appointments without authorization, or expose the system to injection-style attacks. Human Care was designed to eliminate that risk by architecture, not by hoping the model behaves correctly.

**Why unrestricted access is dangerous**
An AI agent with open-ended database access effectively becomes a single point of failure. It could be manipulated into leaking another patient's records, executing unintended writes, or bypassing business rules that a traditional application layer would normally enforce.

**Why role-based AI agents are safer**
Each MediMate agent — Patient, Doctor, and Admin — is bound to a distinct, narrowly-scoped toolset. A Patient Agent simply has no tool capable of reading another patient's records or approving a doctor, because that capability doesn't exist in its permission set. Security is enforced structurally, not just through prompting.

**How confirmation protects sensitive actions**
Actions with real-world consequences — cancelling an appointment, approving a doctor, modifying records — require explicit user confirmation before execution. This human-in-the-loop checkpoint prevents the AI from autonomously performing irreversible or high-impact operations.

**Least-privilege by design**
Every agent is granted the minimum set of tools and data access required to fulfill its role — nothing more. This mirrors the principle of least privilege used in traditional backend security, applied directly to AI tool architecture.

**Protecting patient privacy**
Medical data access is scoped at the query level, ensuring agents can only retrieve records tied to the authenticated user's identity and role. Combined with session validation and backend authorization checks, patient data remains isolated even if the conversational layer is probed or misused.

---

## 💡 Engineering Insights

Building MediMate AI inside a real hospital management system surfaced engineering challenges that go far beyond wiring an LLM to a chatbot UI.

**The core challenge**
The hardest problem wasn't making the AI conversational — it was making it *trustworthy* in a domain where mistakes have real consequences. Balancing natural, flexible conversation with strict, predictable access control required rethinking how the AI layer talks to the backend.

**Why role-based AI was chosen**
Early designs considered a single general-purpose agent with broad database access. This was rejected in favor of three isolated agents because role separation maps naturally to how hospitals already operate, and because it drastically reduces the blast radius of any single agent misbehaving or being manipulated.

**Why secure tool execution matters**
Rather than letting the AI freely query the database, every capability is exposed as a discrete, pre-defined "tool" with fixed inputs, outputs, and validation. This turns the AI from an open-ended database client into a constrained function-caller — predictable, auditable, and easy to reason about.

**Why AI should never execute arbitrary SQL**
Allowing a language model to generate and run raw SQL is one of the most common and dangerous anti-patterns in AI system design. It reintroduces the exact injection and privilege-escalation risks that decades of backend engineering have worked to eliminate. Human Care instead routes every AI action through vetted, parameterized backend functions — the model decides *intent*, the backend enforces *execution*.

**Lessons learned**
- Treat AI agents as untrusted clients of your backend, not as trusted internal services.
- Tool-level permission boundaries are more reliable than prompt-level instructions.
- Human confirmation for high-impact actions is a cheap, effective safety net.
- Security and usability aren't trade-offs when the architecture is designed correctly from the start.

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
