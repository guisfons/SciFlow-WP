# SciFlow WP — Case Study

> **Custom WordPress Plugin** · Scientific Article Submission & Peer Review Workflow · Role-Based Access Control · WooCommerce Payment Integration

![WordPress](https://img.shields.io/badge/WordPress-6.0+-21759B?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?logo=php&logoColor=white)
![WooCommerce](https://img.shields.io/badge/WooCommerce-Integrated-96588A?logo=woocommerce&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)
![MIT License](https://img.shields.io/badge/License-MIT-green)

<!-- TODO: Add screenshot of the article submission form (frontend author view) here -->

---

## 1. Project Overview

SciFlow WP is a production-ready WordPress plugin implementing a complete scientific article submission and peer review system. Developed for academic publishing clients — specifically the Enfrute and Semco conferences — the plugin transforms a standard WordPress installation into a structured editorial platform managing the full article lifecycle: author submission, multi-reviewer evaluation, certificate issuance, and publication.

The plugin is built to WordPress Plugin API standards with a PSR-4-compatible autoloader, custom post types, a granular role-based permissions model, WooCommerce integration for submission fees, and a custom certificate generation module.

<!-- TODO: Add screenshot of the admin dashboard showing submissions pipeline here -->

---

## 2. The Problem

Academic conferences managing paper submissions face a predictable operational challenge: the workflow is complex, involves multiple stakeholders with different access levels (authors, reviewers, editors), and must enforce strict sequential state transitions.

The clients were managing this through email and spreadsheets, which created:

- **No access isolation:** Authors could see reviewer feedback prematurely.
- **No state enforcement:** No technical mechanism prevented premature certificate issuance.
- **Manual fee management:** Submission fees required manual bank transfer reconciliation.
- **No scalability:** Administrative overhead scaled linearly with submission volume.

---

## 3. The Solution & Architecture

SciFlow WP is a self-contained WordPress plugin with a modular OOP structure and a custom autoloader mapping class names to a well-organized directory hierarchy.

### Plugin Directory Structure

```
sciflow-wp/
├── sciflow-wp.php          # Bootstrap: autoloader, hooks, activation
├── includes/
│   ├── post-types/         # CPT registration (articles, reviews)
│   ├── roles/              # Custom role definitions and capability maps
│   ├── workflow/           # Status manager (article lifecycle state machine)
│   ├── ranking/            # Reviewer score aggregation and ranking
│   ├── email/              # Automated notifications (author/reviewer/editor)
│   ├── payment/            # WooCommerce integration for submission fees
│   ├── certificates/       # PDF certificate generation on approval
│   └── upload/             # Secure manuscript file upload handling
├── admin/                  # Admin panel UI (article list, review assignment)
└── public/                 # Frontend assets for submission forms
```

### Article Lifecycle State Machine

The `SciFlow_Status_Manager` enforces a strict state machine:

```
submitted → em_avaliacao (Under Review) → aprovado (Approved) → Certificate Issued
                                        └→ reprovado (Rejected) → Author Notified
```

Administrators can revert from `em_avaliacao` to `submitted` while preserving all reviewer scores, notes, and the complete message history.

### Role-Based Access Control

| Role | Capabilities |
|---|---|
| `sciflow_author` | Submit articles, view own submissions, upload manuscripts |
| `sciflow_reviewer` | View assigned articles, submit scores and feedback |
| `sciflow_editor` | Assign reviewers, manage status transitions |
| `sciflow_admin` | Full access, certificate issuance, reports |

---

## 4. Technologies Used

- **CMS & Backend:** WordPress 6.0+, PHP 7.4+ (PSR-4 autoloader)
- **Database:** MySQL — CPTs, post meta, custom review data tables
- **E-Commerce:** WooCommerce — programmatic order creation, payment gating
- **PDF Generation:** Custom certificate generation module
- **Email:** WordPress `wp_mail()` — transactional notifications at each workflow stage
- **Frontend:** Shortcode API — submission and status-check forms on any page

---

## 5. Design Process & UI/UX

The plugin's UI was designed around three distinct mental models: the submitting author (clean linear frontend form), the evaluating reviewer (minimal structured scoring form with assignment isolation), and the managing editor (WordPress admin with colour-coded status indicators and bulk actions).

<!-- TODO: Add screenshot of the reviewer evaluation form here -->
<!-- TODO: Add screenshot of the editor's article management dashboard here -->
<!-- TODO: Add screenshot of a generated participation certificate here -->

---

## 6. Project Outcomes

- **End-to-end automation:** Every stage from fee collection to certificate delivery is automated, eliminating all manual administrative steps.
- **Access isolation:** Role-based controls enforce strict data separation — reviewers see only their assigned papers, authors cannot see reviewer feedback until publication.
- **State integrity:** The status machine prevents invalid state transitions at the application layer.
- **Scalability:** Arbitrary concurrent submissions without incremental administrative overhead.
- **Audit trail:** Every state transition, score, and payment event is persisted and queryable.
- **WooCommerce compatibility:** Payment infrastructure inherits the full WooCommerce ecosystem — multiple gateways, order management, and refund handling.
