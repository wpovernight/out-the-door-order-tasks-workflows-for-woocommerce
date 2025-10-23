# Advanced Order Manager

This plugin extends WooCommerce’s native order system to improve internal workflows for e-commerce operations.  

---

## ⚙️ Architecture Overview

The Advanced Order Manager plugin is built on a modular, service-oriented architecture that extends classical MVC concepts and adapts them to the WordPress development model. Its structure is designed for scalability, testability, and maintainability, ensuring each component serves a single, well-defined responsibility.

At the foundation lies the data layer, which is composed of model classes representing entities such as tasks, fields, and options, and a set of repository classes that encapsulate data persistence logic. The repositories abstract direct database operations, allowing the rest of the system to interact with data through a clean, object-oriented API rather than raw SQL or WordPress queries.

Above the data layer, the service layer acts as the core of the business logic. Managed through the central ServiceContainer, it serves as the plugin’s dependency injection and lifecycle manager, responsible for registering, instantiating, and caching all major services and repositories. This layer coordinates complex operations that involve multiple data sources or entities, ensuring a consistent and reusable workflow throughout the plugin.

The API layer—implemented using the WordPress REST API framework—serves as the communication bridge between the plugin’s backend and its user interfaces or external integrations. These REST controllers expose structured endpoints that allow the plugin’s internal services to be accessed programmatically, either from within the WordPress admin or by third-party systems. Unlike a traditional MVC controller, this layer acts more as an integration interface, decoupling the core logic from any specific presentation technology.

On top of the stack lies the presentation layer, which provides flexible rendering options. The plugin supports both React + TypeScript-based dynamic interfaces and traditional PHP/HTML templates, enabling developers to choose the most appropriate approach for each admin screen. React components typically consume REST endpoints for interactive experiences, while PHP views can directly render information from services for simpler, lightweight admin pages. This hybrid approach allows the plugin to combine modern, component-driven UX with classic WordPress admin patterns.


Together, these elements form a layered, loosely coupled system where:
- Repositories handle persistence,
- Services implement business logic, 
- APIs expose controlled access to data and operations, 
- and UI layers deliver flexible, extensible presentation.

This architecture results in a scalable and future-proof framework for advanced order and task management, combining modern development paradigms with the reliability and extensibility of WordPress.

---

## 🧩 Layered Architecture Diagram

                  ┌──────────────────────────────────────┐
                  │          Presentation Layer          │
                  │──────────────────────────────────────│
                  │ - React + TypeScript admin interface │
                  │ - PHP/HTML admin screens             │
                  │ - WordPress admin hooks & pages      │
                  └──────────────────────────────────────┘
                                      │
                                      ▼
                  ┌──────────────────────────────────────┐
                  │             API Layer                │
                  │──────────────────────────────────────│
                  │ - WordPress REST API endpoints       │
                  │ - Exposes structured JSON responses  │
                  │ - Enables external & UI integration  │
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

| Layer            | Description                                                                          |
|------------------|--------------------------------------------------------------------------------------|
| **Core**         | Handles dependency injection, installation routines, and environment checks.         |
| **Models**       | Represents entities like `Task`, `TaskField`, and `TaskFieldValue`.                  |
| **Repositories** | Provides CRUD operations and data access abstraction for models.                     |
| **Services**     | Implements business logic, e.g., managing tasks and custom workflows.                |
| **REST**         | Exposes REST API endpoints for frontend and external integrations.                   |
| **Admin**        | Renders admin UI for features and settings. Includes TypeScript + React application. |

---

## 🔍 Detailed Component Breakdown

### 1. Core Components
Provide the foundation for plugin bootstrapping, lifecycle management, and dependency resolution.
They initialize the plugin environment, ensure prerequisites are met, and register key services and repositories.
- **ServiceContainer.php**: Manages the registration and resolution of plugin services and repositories, acting as a lightweight dependency injection container that centralizes object instantiation.
- **Install.php**: Manage database table creation, upgrades, and initial setup.
- **DependencyChecker.php**: Verifies required dependencies (e.g., WooCommerce) and PHP/WordPress version compatibility.

📁 `includes/Core/`

### 2. Models Layer
Defines the plugin’s data entities and how they represent real-world concepts (e.g., tasks, task fields, options).
Each model describes its structure, validation, and relationships with other entities.
- **BaseModel.php**: Abstract base class that provides shared logic for data hydration, serialization, and relationship handling.

📁 `includes/Models/`

### 3. Repositories Layer
Implements the Repository Pattern to abstract data access and CRUD operations.
Repositories provide a fluent, chainable query API that hides direct database interactions and ensures consistent persistence logic.
- **BaseRepository.php**: Abstract class for common repository functionality, including query building.
- **RepositoryRegistry.php**: Globally binds repositories to their corresponding models, simplifying resolution and dependency management.

📁 `includes/Repositories/`

### 4. Services Layer
Encapsulates the plugin’s business logic and acts as the orchestrator between models, repositories, and REST endpoints.
This layer ensures that workflow operations (like task creation, field synchronization, and option updates) remain centralized and reusable.

📁 `includes/Services/`

### 5. REST API Endpoints
Implements the API communication layer, exposing internal logic to both the frontend and external systems.

📁 `includes/REST/`

### 6. Admin UI
Handles the presentation layer for administrators.
This layer supports both React + TypeScript applications for dynamic interfaces and traditional PHP/HTML views for simpler screens.
Each screen resides in its own subdirectory.

📁 `includes/Admin/`

### 7. Enums and Types
Defines enumerations, constants, and type definitions used across the plugin to maintain consistency and prevent hard-coded values.
These definitions improve type safety and readability throughout the codebase.

📁 `includes/Enums/`

---

## 🛠️ Development Setup

### Prerequisites
- PHP 7.4+
- Node.js
- npm
- Composer

### Installation Steps
1. Clone the repository into your WordPress `wp-content/plugins` directory.
2. Run `composer install` in the plugin root to install PHP dependencies.

### Build Frontend

To build frontend react apps, navigate to the respective directories and run the following commands:
```bash
# Change the directory to your desired one.
cd includes/Admin/js/app-directory
npm install
npm run build
```


