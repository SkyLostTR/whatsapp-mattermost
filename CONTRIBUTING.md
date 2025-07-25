# Contributing to WhatsApp to Mattermost Converter

Thank you for your interest in contributing to this project! We welcome contributions from the community.

## How to Contribute

### Reporting Issues

1. Check if the issue already exists in the [issue tracker](../../issues)
2. If not, create a new issue with:
   - Clear description of the problem
   - Steps to reproduce
   - Expected vs actual behavior
   - Environment details (PHP version, OS, etc.)

### Submitting Changes

1. **Fork** the repository
2. **Create a branch** for your feature or bugfix:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **Make your changes** following the coding standards below
4. **Test your changes** thoroughly
5. **Commit your changes** with clear, descriptive messages
6. **Push** to your fork and **submit a pull request**

## Coding Standards

### PHP Standards

- Follow PSR-4 autoloading standard
- Use PSR-12 coding style
- Include proper PHPDoc comments
- Use meaningful variable and function names
- Handle errors gracefully

### Code Structure

- Keep functions focused and single-purpose
- Use dependency injection where appropriate
- Validate all inputs
- Follow existing code patterns

### Example Code Style

```php
<?php
declare(strict_types=1);

namespace de\phosco\mattermost\whatsapp;

/**
 * Handles WhatsApp chat processing
 */
class ChatProcessor
{
    private string $chatPath;
    
    /**
     * @param string $chatPath Path to WhatsApp export file
     */
    public function __construct(string $chatPath)
    {
        $this->chatPath = $chatPath;
    }
    
    /**
     * Process chat messages
     * 
     * @return array Processed messages
     */
    public function processMessages(): array
    {
        // Implementation here
    }
}
```

## Testing

- Test your changes with different WhatsApp export formats
- Verify Mattermost import functionality
- Test error handling scenarios
- Ensure no sensitive data is logged

## Documentation

- Update README.md if adding new features
- Include inline comments for complex logic
- Update examples if changing configuration

## Pull Request Guidelines

### Before Submitting

- [ ] Code follows the project's coding standards
- [ ] All tests pass
- [ ] Documentation is updated
- [ ] No sensitive data in commits
- [ ] Commit messages are clear and descriptive

### PR Description Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Manual testing completed
- [ ] Works with different WhatsApp formats
- [ ] Mattermost import verified

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
```

## Development Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/SkyLostTR/whatsapp-mattermost.git
   cd whatsapp-mattermost
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   - Copy example configuration
   - Update user/phone mappings
   - Set Mattermost connection details

4. **Test the setup**
   ```bash
   php src/convert.php
   ```

## Questions?

- Open an issue for general questions
- Check existing documentation
- Review the code for examples

Thank you for contributing! 🎉

version: 1.1.0
