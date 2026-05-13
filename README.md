# Wireninja Accelerator

Build premium Laravel & Filament applications with enterprise standards in seconds.

## Installation Preamble (MANDATORY)

To ensure a clean and conflict-free installation, you **MUST** follow these steps exactly:

1. **Create a fresh Laravel project**:
   ```bash
   laravel new my-app --vue --pest --bun
   cd my-app
   ```

2. **Sterilize the project** (Remove default vanilla configuration):
   ```bash
   rm .env
   rm database/database.sqlite
   rm database/migrations/*.php
   ```

3. **Require the package**:
   ```bash
   composer require wireninja/accelerator:"^1.1" -W
   ```

4. **Run the Interactive Installer**:
   ```bash
   php artisan accelerator:install
   ```

## Key Features
- **Enterprise User Engine**: Replaces default users with a more robust model (OAuth ready, Telegram ID, etc.).
- **Smart Environment**: Auto-generates production-ready `.env` settings.
- **Industrial Design**: Forces high-density, monochromatic UI standards for Filament.
- **Performance Stack**: Pre-configured Reverb and Octane (Swoole).
- **Auto-Localization**: Ready-to-use Indonesian (id) translations.
