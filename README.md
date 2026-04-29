# ⬢ Hive ERP

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![Next.js](https://img.shields.io/badge/Next.js-15.x-000000?style=for-the-badge&logo=next.js)](https://nextjs.org)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php)](https://www.php.net)
[![Docker](https://img.shields.io/badge/Docker-Enabled-2496ED?style=for-the-badge&logo=docker)](https://www.docker.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)

**Hive** is a high-performance, modular monolith ERP system engineered for modern enterprises. Designed with a focus on scalability and regional compliance (specifically the Ethiopian financial landscape), Hive unifies your entire business operation into a single, cohesive ecosystem.

---

## ✨ Core Pillars

### 🧩 Modular Monolith Architecture
Hive combines the simplicity of a monolith with the scalability of microservices. Each business domain (Finance, HR, Supply Chain) lives in its own isolated module, ensuring clean boundaries and easy maintenance.

### 🇪🇹 Localized & Compliant
Built-in integrations for Ethiopian financial ecosystems, including **ERCA** tax compliance, **Telebirr**, **CBE**, **Chapa**, and **Arifpay**. Fully aligned with **INSA** and **NBE** security standards.

### ⚡ Cutting-Edge Performance
Leveraging **Laravel Octane** and **RoadRunner**, Hive delivers sub-millisecond response times. Real-time notifications and live data syncing are powered by **Laravel Reverb** (WebSockets).

---

## 🛠️ Business Ecosystem (Modules)

Hive is not just an ERP; it's a collection of specialized business modules that work in perfect harmony:

- **💳 Finance & Fintech:** Automated ledger management, VAT calculations, and native payment gateway integrations.
- **👥 Identity & HR:** Advanced RBAC (Role-Based Access Control), 2FA, and unified user profiles.
- **📦 Supply Chain:** Intelligent warehouse routing, multi-branch inventory syncing, and automated reorder triggers.
- **🚛 Logistics & Freight:** Real-time fleet tracking and transport management.
- **💬 Collaboration:** Integrated internal Chat and Mailbox systems for seamless team communication.
- **⚙️ Workflow:** Dynamic business process automation and approval chains.

---

## 🚀 Tech Stack

### Backend (The Engine)
- **Framework:** Laravel 12.x
- **Server:** RoadRunner / Laravel Octane
- **Real-time:** Laravel Reverb (WebSockets)
- **Queue:** Laravel Horizon (Redis-backed)
- **Search:** Laravel Scout / Meilisearch
- **Database:** PostgreSQL (Multi-tenant schema isolation)

### Frontend (The Interface)
- **Framework:** Next.js 15 (App Router)
- **State:** TanStack Query & Zustand
- **UI Components:** Radix UI, Shadcn/UI, Lucide
- **Styling:** Tailwind CSS 4.x
- **Animations:** Framer Motion

---

## 🏗️ Architecture Overview

The codebase follows a strict modular structure:

- `backend/app/`: The framework shell (infrastructure, middleware).
- `backend/Modules/*`: Domain-specific business logic (Controllers, Models, Events).
- `frontend/app/`: Main application routing and core components.
- `frontend/modules/*`: Frontend counterparts to backend modules.

---

## 🚦 Getting Started

### Prerequisites
- Docker & Docker Compose
- Node.js (for local frontend dev)
- PHP 8.2+ (for local backend dev)

### Quick Start (Docker)

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Techiveet/Hive-Erp.git
   cd hive
   ```

2. **Environment Setup:**
   ```bash
   cp .env.example .env
   # Update your database and app credentials in .env
   ```

3. **Launch the Stack:**
   ```bash
   docker compose up -d
   ```

The application will be available at `http://localhost`.

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

<p align="center">
  Developed with ❤️ by <b>Techive Technology Solutions</b>
</p>
