# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress installation with custom KACHI plugins for an internal organizational system. The setup includes:

- WordPress core installation with custom authentication and management plugins
- Custom "Blank Theme" for minimal styling
- Internal API integration for organizational data
- Custom plugins for authentication, document management, navigation, and user management

### Git Repositories
- **Frontend (WordPress)**: Repository `dietpi` on `master` branch (`/mnt/dietpi`)
- **Backend (Haystack AI)**: Repository `haystack` on `master` branch (`/mnt/haystack`)

## Development Commands

### WordPress Development
```bash
# Start PHP development server (if needed)
php -S localhost:8000

# Database operations (requires MySQL/MariaDB)
mysql -u wordpress -p wordpress

# File permissions (if needed)
chmod 755 wp-content/
chmod 644 wp-config.php
```

### Backend Development (Haystack AI System)
```bash
# Navigate to backend directory
cd /mnt/haystack

# Docker Compose operations
docker-compose up -d              # Start all services
docker-compose down               # Stop all services
docker-compose logs haystack-app  # View application logs
docker-compose logs vllm-server   # View LLM server logs

# Development server (without Docker)
uvicorn main:app --host 0.0.0.0 --port 8001 --reload

# Task management
python task_manager_sqlite.py     # Direct task manager operations

# Container access
docker exec -it haystack-app bash # Access application container
docker exec -it vllm-server bash  # Access LLM server container
```

### Git Workflow
```bash
# Frontend WordPress (dietpi repository)
cd /mnt/dietpi
git status                        # Check status in dietpi repo
git add .                         # Stage changes
git commit -m "Your commit message"
git push dietpi master           # Push to dietpi remote master branch

# Backend Haystack AI (haystack repository)
cd /mnt/haystack
git status                        # Check status in haystack repo
git add .                         # Stage changes
git commit -m "Your commit message"
git push haystack master         # Push to haystack remote master branch

# Working across both repositories
# Remember: These are separate git repositories with custom remote names
# Changes in /mnt/dietpi affect the dietpi repo (remote: dietpi)
# Changes in /mnt/haystack affect the haystack repo (remote: haystack)
```

### Debug Mode
**WordPress**: Debug mode enabled with logging
- `WP_DEBUG` is set to true
- Debug logs are enabled (`WP_DEBUG_LOG`)
- Debug display is disabled for production

**Backend**: FastAPI with logging
- Logging level: INFO
- Task processing logs in SQLite database
- GPU utilization monitoring

## Architecture Overview

### Core Structure
- **WordPress Core**: Standard WordPress 6.x installation
- **Custom Theme**: `wp-content/themes/blank-theme/` - Minimal theme with basic styling
- **Custom Plugins**: Located in `wp-content/plugins/kachi-*` - Organization-specific functionality

### Key Custom Plugins

#### KACHI Authentication (`wp-content/plugins/kachi-authentication/`)
- **Version**: 1.3.0
- **Main Class**: `KAC_Login`
- **Features**:
  - Custom login/registration system using shortcodes
  - Employee ID-based registration with organizational hierarchy
  - Approval workflow for new users with "pending" role
  - Password strength validation (9+ chars, alphanumeric + special chars)
  - Organizational structure integration (department/facility selection)
- **Shortcodes**: `[kac_login]`, `[kac_register]`, `[kac_register_complete]`
- **Files**: Includes class files, assets (CSS/JS), templates

#### KACHI Document Manager (`wp-content/plugins/kachi-document-manager/`)
- **Version**: 3.0.2 
- **Main Class**: `ThreeChan_PDF_Manager`
- **Features**:
  - AI-based PDF/DOCX/PPTX/HWPX document upload and processing
  - Document splitting and tag management system
  - Internal network-only operation
  - Integration with Python backend API
- **Internal API**: Uses `THREECHAN_API_INTERNAL_URL` for document processing

#### KACHI AI Search (`wp-content/plugins/kachi-ai-search/`)
- **Version**: 3.0.3
- **Main Class**: `Kachi_Query_System`
- **Features**:
  - AI-powered natural language document search
  - Real-time streaming responses
  - Tag and document filtering
  - User permission-based access control
  - Facility definition auto-expansion
- **Shortcode**: `[kachi_query]`
- **Backend**: Proxy API support with internal network integration

#### KACHI Navigation (`wp-content/plugins/kachi-navigation/`)
- **Version**: 2.0.0
- **Main Class**: `SMM_Core` (Sidebar Menu Manager)
- **Features**:
  - Automatic sidebar menu application to specific pages
  - Page-specific navigation management
  - AJAX functionality for dynamic menu updates
- **Files**: Includes admin interface and shortcode functionality

#### KACHI User Management (`wp-content/plugins/kachi-user-management/`)
- **Version**: 1.2.0
- **Main Class**: `User_Approval_System`
- **Features**:
  - User account approval system
  - Integration with authentication plugin
  - Shortcode support for user management interfaces
  - AJAX handlers for user operations
- **Shortcodes**: Various user management shortcodes

#### KACHI Analytics (`wp-content/plugins/kachi-analytics/`)
- **Version**: 2.2.0
- **Main Class**: `StatisticsDashboardPlugin`
- **Features**:
  - Document statistics visualization dashboard
  - Python backend API integration for analytics
  - AJAX-based data loading and chart rendering
  - Settings management for analytics configuration

#### KACHI App Menu (`wp-content/plugins/kachi-app-menu/`)
- **Version**: 2.0.0
- **Main Class**: `GTM_Core` (Google-style app menu)
- **Features**:
  - Google-style app menu for all frontend pages
  - Automatic menu display across site
  - Frontend admin template integration
  - Shortcode support for menu customization

#### KACHI Update (`wp-content/plugins/kachi-update/`)
- **Version**: 2.0.0
- **Main Class**: `VersionNoticePlugin`
- **Features**:
  - Version update announcement management
  - Database table for update notices
  - Custom post type for update management
  - Frontend admin interface for update posting

#### KACHI Facility Definition (`wp-content/plugins/kachi-facility-definition/`)
- **Version**: 1.2.0
- **Main Class**: `FacilityDefinition` (inferred)
- **Features**:
  - Facility and location definition management
  - Integration with other KACHI plugins
  - AJAX functionality for facility management
  - Shortcode support for facility displays
  - Frontend admin interface

### Backend Architecture (Haystack AI System)

The system includes a sophisticated AI-powered backend located at `/mnt/haystack`:

#### Core Components
- **FastAPI Application** (`main.py`): Main API server running on port 8001
- **Qdrant Vector Database**: Document embeddings storage (port 6333)
- **vLLM Server**: LLM inference server with GPU support (port 8080)
- **Task Manager**: SQLite-based async task processing system

#### Docker Architecture
The backend runs as a multi-container Docker Compose setup:

```yaml
services:
  qdrant:           # Vector database for document embeddings
  vllm-server:      # LLM inference with NVIDIA GPU support
  haystack-app:     # Main FastAPI application
```

#### API Endpoints

**Document Management**:
- `POST /upload-pdf/` - Upload and process documents (PDF/DOCX/PPTX/HWPX)
- `GET /list-documents/` - List documents with permission filtering
- `POST /check-duplicates/` - Check for duplicate documents
- `DELETE /delete-documents/` - Delete documents by IDs

**AI Query System**:
- `GET /query-stream/` - Streaming AI responses with document context
- WebSocket support for real-time communication

**Analytics**:
- `GET /statistics/` - Document and usage statistics
- Background upload processing with task tracking

#### Document Processing Pipeline
1. **Upload**: Multi-format document upload (PDF, DOCX, PPTX, HWPX)
2. **Processing**: Text extraction, section splitting, table extraction
3. **Embedding**: KURE-v1 Korean embedding model for vector search
4. **Storage**: Qdrant vector database with metadata
5. **Query**: Semantic search with LLM-generated responses

#### Permission System
- **Sosok-based access control**: Organization-level permissions
- **Site-based filtering**: Department/facility-level access
- **Admin override**: Full system access for administrators
- Integration with WordPress user roles and metadata

#### AI Models
- **Embedding Model**: KURE-v1 (Korean-optimized)
- **LLM**: A.X-3.1-Light model via vLLM
- **GPU Acceleration**: NVIDIA CUDA support

### Internal API Integration

**IMPORTANT**: WordPress is the primary user interface and must proxy all backend requests. All user interactions should flow through WordPress, which then routes backend requests to the Haystack AI system.

The WordPress frontend integrates with the backend API:
- **Internal API URL**: `http://192.168.10.101:8001`
- **Authentication**: WordPress user metadata (sosok/site)
- **Routing**: All backend services (`/mnt/haystack`) must be accessed through WordPress proxy endpoints

### Database Configuration

- Database: `wordpress`
- User: `wordpress`
- Host: `localhost`
- Charset: `utf8mb4`

### Security Features

- SSL forwarding support for proxy environments
- Nonce verification in custom forms
- Role-based access control with custom "pending" role
- Admin area restrictions for subscribers

## Development Guidelines

### Plugin Development
- Follow WordPress coding standards
- Use proper sanitization and validation
- Implement proper nonce verification
- Store settings using WordPress Options API
- Use ACF (Advanced Custom Fields) when available, fallback to user meta

### Plugin Version Management
- **ALWAYS increment the plugin version number when making ANY changes to plugin files**
- Update both the plugin header version (`Version: X.Y.Z`) and constant definitions
- Version format: MAJOR.MINOR.PATCH (semantic versioning)
- Update corresponding version in CLAUDE.md documentation
- Examples of version increments:
  - Bug fixes: 2.2.0 → 2.2.1
  - New features: 2.2.0 → 2.3.0  
  - Breaking changes: 2.2.0 → 3.0.0

### Theme Development
- The blank theme provides minimal structure
- Custom styles should be added via plugin assets or child theme
- Responsive design is implemented in custom plugins

### File Structure
```
# WordPress Frontend (/mnt/dietpi)
wp-content/
├── plugins/
│   ├── kachi-authentication/    # Login/registration system
│   ├── kachi-document-manager/  # Document management
│   ├── kachi-navigation/        # Navigation system
│   ├── kachi-user-management/   # User management
│   ├── kachi-analytics/         # Analytics
│   ├── kachi-app-menu/          # App menu
│   ├── kachi-ai-search/         # AI search
│   ├── kachi-update/            # Update system
│   ├── kachi-facility-definition/ # Facility management
│   ├── code-snippets/           # Code snippets plugin
│   ├── custom-css-js/           # Custom CSS/JS
│   └── members/                 # Membership plugin
├── themes/
│   └── blank-theme/             # Minimal custom theme
└── uploads/                     # Media uploads

# Backend API (/mnt/haystack)
haystack/
├── api/
│   ├── documents.py             # Document management endpoints
│   ├── upload.py                # File upload processing
│   ├── query.py                 # AI query endpoints
│   ├── statistics.py            # Analytics endpoints
│   ├── background_upload.py     # Async upload processing
│   └── websocket_handler.py     # WebSocket communication
├── util/
│   ├── embedding.py             # Document embedding utilities
│   ├── docx.py                  # DOCX processing
│   ├── pptx.py                  # PowerPoint processing
│   ├── hwpx.py                  # HWP document processing
│   ├── translator.py            # Translation utilities
│   └── pdf/                     # PDF processing modules
├── data/
│   └── tasks.db                 # SQLite task database
├── main.py                      # FastAPI application
├── task_manager_sqlite.py       # Task management system
├── llama_server_generator.py    # LLM client interface
└── docker-compose.yml           # Container orchestration
```

### User Roles
- `pending`: Users awaiting admin approval
- `subscriber`: Approved regular users  
- `administrator`: Full access

### Shortcode Usage

#### Authentication System
- `[kac_login logo="url" redirect="url"]` - Login form with custom logo and redirect
- `[kac_register logo="url" redirect="url"]` - Registration form
- `[kac_register_complete background="url" image="url"]` - Registration completion page

#### AI Search System
- `[kachi_query]` - AI-powered document search interface

#### Other Plugin Shortcodes
- Various user management shortcodes from KACHI User Management
- Navigation and menu shortcodes from KACHI Navigation and App Menu
- Facility definition display shortcodes from KACHI Facility Definition

## Environment Notes

- PHP memory limit: 10024M
- Max execution time: 6000 seconds
- Korean language support enabled
- Internal network deployment