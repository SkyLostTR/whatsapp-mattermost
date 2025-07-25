# Security Policy

## Supported Versions

We actively support the following versions with security updates:

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |
| 1.1.0   | :white_check_mark: |

## Reporting a Vulnerability

We take security seriously. If you discover a security vulnerability, please follow these steps:

### How to Report

1. **Do NOT** create a public GitHub issue for security vulnerabilities
2. Send an email to the project maintainer with details about the vulnerability
3. Include the following information in your report:
   - Description of the vulnerability
   - Steps to reproduce the issue
   - Potential impact
   - Suggested fix (if any)

### What to Expect

- **Response Time**: We aim to respond to security reports within 48 hours
- **Updates**: We will keep you informed about the progress of fixing the vulnerability
- **Disclosure**: Once the vulnerability is fixed, we will coordinate responsible disclosure

### Security Best Practices

When using this tool:

1. **Never commit sensitive data** like WhatsApp exports or API tokens to version control
2. **Use environment variables** for configuration sensitive data
3. **Validate input data** before processing WhatsApp exports
4. **Secure your Mattermost instance** with proper authentication and HTTPS
5. **Keep dependencies updated** by regularly running `composer update`

### Data Privacy

This tool processes WhatsApp chat exports which may contain sensitive personal information:

- Ensure you have permission to migrate the chat data
- Be aware of data protection regulations (GDPR, etc.)
- Securely delete temporary files after migration
- Use encrypted connections when communicating with Mattermost servers

## Security Features

- Input validation for chat data
- Secure API communication with Mattermost
- Temporary file cleanup
- Rate limiting for API calls
