# MINI-CEX

> Plataforma de evaluación clínica para estudiantes de medicina basada en el instrumento MINI-CEX (Mini Clinical Evaluation Exercise).

## 📋 Stack Tecnológico

| Componente | Tecnología |
|---|---|
| **Backend** | CodeIgniter 4.7 — PHP 8.2+ |
| **Base de datos** | MySQL (`bd_minicex`) |
| **Autenticación admin** | Sesiones PHP |
| **API (Android)** | REST endpoints con sincronización offline |
| **Correos** | PHPMailer (SMTP) |
| **Reportes PDF** | FPDF personalizado + TCPDF |
| **Frontend** | JavaScript vanilla, CSS |

## 🚀 Instalación para desarrollo local

### Requisitos

- PHP 8.2+
- MySQL 8.0+
- Composer
- XAMPP / WAMP / Laragon o similar

### Pasos

```bash
# 1. Clonar el repositorio
git clone https://github.com/yire033-code/minicex-web.git
cd minicex-web

# 2. Instalar dependencias de Composer
composer install

# 3. Configurar credenciales (copiar y editar)
cp config.example.php config.php
cp smtp_config.example.php smtp_config.php

# 4. Editar config.php con tus credenciales de DB, admin y SMTP
#    - DB_HOST, DB_USER, DB_PASS, DB_NAME
#    - ADMIN_USERNAME, ADMIN_PASSWORD_HASH
#    - SMTP_HOST, SMTP_USERNAME, SMTP_PASSWORD, etc.

# 5. Opcional: definir un evaluador de desarrollo para setup.php
# export MINICEX_SEED_EVALUATOR_EMAIL='evaluador@example.com'
# export MINICEX_SEED_EVALUATOR_PASSWORD='elige-una-clave-local'

# 6. Crear la base de datos (operación destructiva)
php setup.php

# 7. ¡Listo! Acceder desde el navegador:
#    http://localhost/minicex
```

## 🔐 Credenciales locales

El repositorio no incluye contraseñas predeterminadas. Genera el hash del panel de administración localmente y guárdalo como `ADMIN_PASSWORD_HASH` en `config.php`:

```bash
php -r "echo password_hash('elige-una-clave-local', PASSWORD_DEFAULT), PHP_EOL;"
```

`setup.php` solo crea un evaluador de desarrollo cuando se proporcionan las variables de entorno indicadas arriba.

## 📁 Estructura del proyecto

```
minicex/
├── api/                  # API REST + FPDF personalizado
│   ├── fpdf/             # Librería FPDF
│   └── pdf_generator.php # Generador de reportes PDF
├── app/                  # CodeIgniter 4
│   ├── Config/           # Configuración del framework
│   ├── Controllers/      # Controladores
│   │   ├── AdminController.php   # Panel admin
│   │   ├── ApiController.php     # API REST
│   │   ├── HomeController.php    # Landing
│   │   └── ReportController.php  # Reportes
│   └── Views/            # Vistas
├── includes/             # Utilidades compartidas
│   ├── auth.php
│   ├── data_fetcher.php
│   ├── email_sender.php
│   └── post_handlers.php
├── parts/                # Partial views (PHP includes)
├── config.php            # 🔴 Credenciales (NO se sube a Git)
├── config.example.php    # Template de configuración
├── setup.php             # Script de creación de BD
└── AGENTS.md             # Guía de arquitectura del proyecto
```

## 🌐 API (Android App)

La API REST está documentada en [`API.md`](API.md) (visible en GitHub) y también en `/api-docs` (en el servidor). Soporta:

- `POST /api/auth/login` — Autenticación
- `GET /api/students` — Listar alumnos
- `POST /api/sync/students` — Sincronizar alumnos
- `GET/POST /api/sync/evaluations` — Sincronizar evaluaciones
- `POST /api/sync/process_queue` — Sincronización offline bidireccional

➡️ **[Ver documentación completa de la API →](API.md)**

## 📄 Licencia

MIT — CodeIgniter 4 starter app.
