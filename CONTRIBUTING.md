# Contributing to Multitron

Thank you for your interest in contributing to Multitron! This document provides guidelines and instructions for contributing.

## Code of Conduct

Be respectful, professional, and constructive in all interactions.

## How to Contribute

### Reporting Bugs

1. Check if the bug has already been reported in [Issues](https://github.com/riki137/multitron/issues)
2. If not, create a new issue with:
    - Clear, descriptive title
    - Detailed description of the bug
    - Steps to reproduce
    - Expected vs actual behavior
    - PHP version and environment details
    - Minimal code example if possible

### Suggesting Features

1. Check existing [Issues](https://github.com/riki137/multitron/issues) and [Pull Requests](https://github.com/riki137/multitron/pulls)
2. Create an issue describing:
    - The problem you're trying to solve
    - Your proposed solution
    - Any alternatives you've considered
    - Examples of how it would be used

### Submitting Pull Requests

1. **Fork and Clone**
   ```bash
   git clone https://github.com/YOUR-USERNAME/multitron.git
   cd multitron
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Create a Branch**
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/your-bug-fix
   ```

4. **Make Your Changes**
    - Follow existing code style (PSR-2)
    - Add tests for new features
    - Update documentation as needed
    - Keep commits focused and atomic

5. **Run Tests**
   ```bash
   vendor/bin/phpunit
   vendor/bin/phpstan analyze src --level=9
   vendor/bin/phpcs src --standard=PSR2 -n
   ```

6. **Commit Your Changes**
   ```bash
   git commit -m "Brief description of changes"
   ```

   Good commit message examples:
    - `Add support for custom IPC adapters`
    - `Fix memory leak in ProgressClient`
    - `Update Laravel integration documentation`

7. **Push and Create PR**
   ```bash
   git push origin feature/your-feature-name
   ```
   Then open a Pull Request on GitHub.

## Development Guidelines

### Code Style

- Follow PSR-2 coding standard
- Use strict types: `declare(strict_types=1);`
- Use typed properties where possible
- Prefer `readonly` for immutable properties
- Prefer `final` classes
- Document complex types with PHPDoc

### Testing

- Write tests for new features
- Ensure existing tests pass
- Aim for good code coverage
- Test both success and failure cases

### Documentation

- Update README.md if adding user-facing features
- Update relevant integration docs (Symfony, Laravel, Nette, Native)
- Add PHPDoc comments for public APIs
- Include code examples for complex features

### Architecture

- Maintain separation of concerns
- Use dependency injection
- Follow existing patterns in the codebase
- Keep backwards compatibility in mind

## Questions?

Feel free to open an issue for any questions about contributing.

Thank you for making Multitron better! 🚀

