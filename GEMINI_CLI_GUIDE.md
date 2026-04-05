# Gemini CLI Project Guide

This guide provides common commands and workflows for Gemini CLI when working on this project.

## Common Tasks

### Running Tests
Use the following command to run the full test suite:
```bash
composer run tests
```

To reload autoloading and rerun tests:
```bash
composer run reload
```

### Debugging & Profiling
To run tests with Xdebug enabled for debugging:
```bash
composer run debug-tests
```

To profile tests with Xdebug:
```bash
composer run profile
```

### Development Server
To start the application in development mode (with Xdebug):
```bash
composer run dev
```

To start the application in standard mode:
```bash
composer run serve
```

## Project Structure Notes

- **Autoloading:** The project uses Composer's classmap for `./inc/class/`, `./src/`, and `./tests/`. If new classes are added, you may need to run `composer dump-autoload`.
- **Global Functions:** Core helper functions are located in `./src/global.function.php`.
- **Entry Point:** The primary entry point for all web requests is `router.php`.
- **Tests:** Test cases are located in the `tests/` directory and use PHPUnit 9.5.

## Working with Git
- **Configuration Files:** Avoid modifying or committing files in `config.dev/`. They should be kept as `assume-unchanged` locally.
- **Branches:** Always target the `dev` branch for initial development before moving to `staging` or `main`.
