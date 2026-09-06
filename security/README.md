# 🚀 Running Nexora Security Dashboard

## Step 1: Start XAMPP

Open **XAMPP Control Panel** and start:

- ✅ Apache
- ✅ MySQL

---

## Step 2: Open Human Care

Open your browser and navigate to:

```
http://localhost/vscode
```

Login as an **Administrator**.

---

## Step 3: Open Nexora Dashboard

Navigate to:

```
Admin Dashboard
        ↓
Security Dashboard
```

or directly open:

```
http://localhost/vscode/admin/security_dashboard.php
```

---

## Step 4: Start Gmail Threat Scanner

Open a terminal:

```bash
cd security

streamlit run gmail_scanner_page.py --server.port 8501
```

Open:

```
http://localhost:8501
```

---

## Step 5: Start URL Risk Analyzer

Open another terminal:

```bash
cd security

streamlit run url_checker_page.py --server.port 8502
```

Open:

```
http://localhost:8502
```

---



## Access URLs

| Module | URL |
|---------|-----|
| Human Care | http://localhost/vscode |
| Nexora Dashboard | http://localhost/vscode/admin/security_dashboard.php |
| Gmail Scanner | http://localhost:8501 |
| URL Scanner | http://localhost:8502 |

---

## Services Required

| Service | Status |
|----------|--------|
| Apache | ✅ Running |
| MySQL | ✅ Running |
| Human Care | ✅ Running |
| Gmail Scanner | ✅ Running |
| URL Scanner | ✅ Running |