# Advanced Order Manager

This plugin extends WooCommerce's native order system to improve internal workflows for e-commerce operations.

---

## ⚙️ Architecture Overview

The Advanced Order Manager plugin is built on a modular, service-oriented architecture that extends classical MVC concepts and adapts them to the WordPress development model. Its structure is designed for scalability, testability, and maintainability, ensuring each component serves a single, well-defined responsibility.

At the foundation lies the data layer, which is composed of model classes representing entities such as tasks, fields, and options, and a set of repository classes that encapsulate data persistence logic. The repositories abstract direct database operations, allowing the rest of the system to interact with data through a clean, object-oriented API rather than raw SQL or WordPress queries.

Above the data layer, the service layer acts as the core of the business logic. Managed through the central ServiceContainer, it serves as the plugin's dependency injection and lifecycle manager, responsible for registering, instantiating, and caching all major services and repositories. This layer coordinates complex operations that involve multiple data sources or entities, ensuring a consistent and reusable workflow throughout the plugin.

The API layer—implemented using the WordPress REST API framework—serves as the communication bridge between the plugin's backend and its user interfaces or external integrations. These REST controllers expose structured endpoints that allow the plugin's internal services to be accessed programmatically, either from within the WordPress admin or by third-party systems. Unlike a traditional MVC controller, this layer acts more as an integration interface, decoupling the core logic from any specific presentation technology.

On top of the stack lies the presentation layer, which provides flexible rendering options. The plugin supports React + TypeScript-based dynamic interfaces. React components consume REST endpoints for interactive experiences, while WooCommerce email classes deliver notifications for task lifecycle events.


Together, these elements form a layered, loosely coupled system where:
- Repositories handle persistence,
- Services implement business logic,
- APIs expose controlled access to data and operations,
- Emails deliver notifications based on task lifecycle events,
- and UI layers deliver flexible, extensible presentation.

---

## 🧩 Layered Architecture Diagram

                  ┌──────────────────────────────────────┐
                  │          Presentation Layer          │
                  │──────────────────────────────────────│
                  │ - React + TypeScript admin interface │
                  └──────────────────────────────────────┘
                                      │
                                      ▼
                  ┌──────────────────────────────────────┐
                  │             API Layer                │
                  │──────────────────────────────────────│
                  │ - Custom REST API endpoints          │
                  └──────────────────────────────────────┘
                                      │
                                      ▼
                  ┌──────────────────────────────────────┐
                  │            Service Layer             │
                  │──────────────────────────────────────│
                  │ - Business logic orchestration       │
                  │ - Coordinates repositories & models  │
                  │ - Managed by ServiceContainer (DI)   │
                  └──────────────────────────────────────┘
                                      │
                                      ▼
                  ┌──────────────────────────────────────┐
                  │              Data Layer              │
                  │──────────────────────────────────────│
                  │ - Models: Task, TaskField, etc.      │
                  │ - Repositories handle persistence    │
                  │ - Abstraction over DB & WP data APIs │
                  └──────────────────────────────────────┘


---

## 🧱 Layer Summary

| Layer            | Description                                                                           |
|------------------|---------------------------------------------------------------------------------------|
| **Core**         | Handles dependency injection, installation, logging, and environment checks.          |
| **Models**       | Represents entities like `Task`, `TaskField`, `CustomOrderStatus`, and `Fulfillment`. |
| **Repositories** | Provides CRUD operations and data access abstraction for models.                      |
| **Services**     | Implements business logic for tasks, fulfillments, custom statuses, and emails.       |
| **REST**         | Exposes REST API endpoints for frontend and external integrations.                    |
| **Admin**        | Renders admin UI using React + TypeScript applications.                               |

---

## 🔍 Detailed Component Breakdown

### 1. Core Components
Provide the foundation for plugin bootstrapping, lifecycle management, and dependency resolution.
They initialize the plugin environment, ensure prerequisites are met, and register key services and repositories.
- **ServiceContainer.php**: Manages the registration and resolution of plugin services and repositories, acting as a lightweight dependency injection container that centralizes object instantiation.
- **Install.php**: Manages database table creation, version-based migrations, and initial data setup.
- **DependencyChecker.php**: Verifies required dependencies (e.g., WooCommerce) and PHP/WordPress version compatibility.
- **Logger.php**: A static logging utility that wraps WooCommerce's `WC_Logger`, providing convenience methods for all log levels.

📁 `includes/Core/`

### 2. Models Layer
Defines the plugin's data entities and how they represent real-world concepts (e.g., tasks, task fields, options).
Each model describes its structure, validation, and relationships with other entities.

📁 `includes/Models/`

### 3. Repositories Layer
Implements the Repository Pattern to abstract data access and CRUD operations.
Repositories provide a fluent, chainable query API that hides direct database interactions and ensures consistent persistence logic.
- **BaseRepository.php**: Abstract class for common repository functionality, including a fluent query builder and per-repository static caching.
- **RepositoryRegistry.php**: Globally binds repositories to their corresponding models, simplifying resolution and dependency management.

📁 `includes/Repositories/`

### 4. Services Layer
Encapsulates the plugin's business logic and acts as the orchestrator between models, repositories, and REST endpoints.
This layer ensures that workflow operations remain centralized and reusable.

📁 `includes/Services/`

### 5. REST API Endpoints
Implements the API communication layer, exposing internal logic to both the frontend and external systems.
All endpoints are prefixed with `wc/v3/wpo/aom/`.

📁 `includes/REST/`

### 6. Admin UI & Frontend Apps
Handles the presentation layer for administrators.
The frontend is built with React + TypeScript. Each feature has its own admin screen class.

📁 `includes/Admin/` (PHP screens) and the root-level `src/` (React/TypeScript apps)

---

## 🛠️ Development Setup

### Prerequisites
- PHP 7.4+
- WordPress 6.7+
- WooCommerce 8.2+
- Node.js & npm
- Composer

### Installation Steps
1. Clone the repository into your WordPress `wp-content/plugins` directory.
2. Run `composer install` in the plugin root to install PHP dependencies.

### Build Frontend

All React applications share a single build configuration:
```bash
# Run from the plugin root (where package.json lives)
npm install
npm run build      # Production build
npm run dev        # Development build
```

Build output is compiled to `includes/Admin/assets/js/`.