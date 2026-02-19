# 🧪 Local Single-Machine Test Plan

**Pressbooks (Bedrock) + Moodle + LTI Advantage**

## 🎯 Goal

Verify end-to-end:

* LTI 1.3 launch
* Deep Linking
* AGS grade return
* Audit logging
  **on one machine**, safely and repeatably.

---

## 🖥️ Recommended Environment

### Option A (Recommended)

* **Ubuntu 22.04 / 24.04**
* Docker + Docker Compose
* 16 GB RAM (8 GB minimum)
* Any modern CPU

### Option B

* macOS + Docker Desktop
  (works, but Linux is easier for networking)

---

## 🧱 Architecture (Local)

```
Browser
  │
  ▼
Moodle (https://moodle.local)
  │  LTI 1.3
  ▼
Pressbooks (https://pressbooks.local)
  │
  └── Your LTI Platform Plugin
```

All containers on **one Docker network**.

---

## 🌐 Local Domains (important)

Add to `/etc/hosts`:

```text
127.0.0.1  moodle.local
127.0.0.1  pressbooks.local
```

LTI **will not work reliably** without stable hostnames.

---

## 🐳 Docker Compose Layout

Create a new folder:

```bash
mkdir lti-local-lab
cd lti-local-lab
```

### `docker-compose.yml`

```yaml
version: "3.9"

networks:
  lti-net:

services:
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
    networks: [lti-net]

  moodle:
    image: bitnami/moodle:5.1
    environment:
      MOODLE_DATABASE_HOST: mysql
      MOODLE_DATABASE_USER: root
      MOODLE_DATABASE_PASSWORD: root
      MOODLE_DATABASE_NAME: moodle
      MOODLE_SITE_NAME: "Moodle Local"
    ports:
      - "8080:8080"
    networks: [lti-net]

  pressbooks:
    image: pressbooks/pressbooks:latest
    environment:
      WORDPRESS_DB_HOST: mysql
      WORDPRESS_DB_USER: root
      WORDPRESS_DB_PASSWORD: root
      WORDPRESS_DB_NAME: pressbooks
    ports:
      - "8081:80"
    networks: [lti-net]
```

Start everything:

```bash
docker compose up -d
```

---

## 🔐 HTTPS (DO NOT SKIP)

LTI 1.3 **requires HTTPS**.

### Fastest way (local only)

```bash
sudo apt install mkcert
mkcert -install
mkcert moodle.local pressbooks.local
```

Use **Nginx or Caddy** as a reverse proxy to terminate TLS.

> If you want, I can give you a **drop-in Caddyfile** (simplest).

---

## 📦 Install Your Plugin in Pressbooks

Inside Pressbooks container:

```bash
docker exec -it pressbooks bash
cd /var/www/html/wp-content/plugins
git clone https://github.com/<you>/qbnox-lti-platform.git
```

Then:

* Network Admin → Plugins
* Activate **Pressbooks LTI Platform**

---

## 🎓 Moodle Configuration (LTI Tool)

### Moodle → Site administration → External tools

Create a **new LTI 1.3 tool**:

| Field              | Value                                               |
| ------------------ | --------------------------------------------------- |
| Tool name          | Pressbooks Local                                    |
| Tool URL           | `https://pressbooks.local`                          |
| LTI version        | LTI 1.3                                             |
| Public keyset URL  | `https://pressbooks.local/wp-json/pb-lti/v1/keyset` |
| Initiate login URL | `https://pressbooks.local/wp-json/pb-lti/v1/login`  |
| Redirect URI       | `https://pressbooks.local/wp-json/pb-lti/v1/launch` |

Save.

Copy:

* **Issuer**
* **Client ID**
* **Deployment ID**

---

## 🛠 Configure Pressbooks LTI Admin

Network Admin → **LTI Platforms**

1. Add Platform

   * Issuer → from Moodle
   * Client ID → from Moodle
   * Auth Login URL → Moodle OIDC URL
   * JWKS URL → Moodle keyset
   * Token URL → Moodle OAuth2 token endpoint

2. Add Deployment

   * Issuer
   * Deployment ID

3. Add Client Secret
   Network Admin → **LTI Client Secrets**

---

## ✅ Test Sequence (VERY IMPORTANT)

Follow **this exact order**:

### 1️⃣ Basic LTI Launch

* Moodle → Course
* Add External Tool
* Launch Pressbooks
* ✅ You should land in Pressbooks logged in

### 2️⃣ Deep Linking

* Add content → External Tool
* Select Pressbooks
* Pick content
* Save
* Launch again

### 3️⃣ AGS (Grades)

* Trigger score POST (manual or test hook)
* Moodle Gradebook → score appears
* Check:

  * Audit logs
  * Token cache
  * Scope enforcement

### 4️⃣ Failure Tests

* Wrong client_id → rejected
* Wrong deployment_id → rejected
* Replay launch → rejected
* Missing scope → rejected

These prove **security correctness**.

---

## 🔍 Where to Debug

### Pressbooks

```bash
wp-content/debug.log
Network Admin → LTI Audit
```

### Moodle

```bash
Site admin → Reports → Logs
```

---

## 🚨 Common Local Gotchas

| Problem       | Cause               |
| ------------- | ------------------- |
| Redirect loop | Missing HTTPS       |
| Invalid aud   | Wrong client_id     |
| JWKS failure  | Moodle URL mismatch |
| Nonce error   | Browser refresh     |
| Cookie issues | SameSite + HTTPS    |

---

## 🧠 Golden Rule for Local LTI

> If it works **locally on one machine**,
> it will work **anywhere**.

