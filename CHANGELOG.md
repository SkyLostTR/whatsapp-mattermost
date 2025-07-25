# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- GitHub Actions CI/CD pipeline
- Comprehensive documentation and contribution guidelines
- Issue and pull request templates
- Security policy and vulnerability reporting process
- Multiple import methods (bulk, individual, file export)
- Support for additional media file formats (opus, aac, m4a)
- Enhanced error handling and user feedback
- Rate limiting for API calls
- Improved emoji mapping system
- ZIP functionality test script
- Fork-specific repository information and branding

### Changed
- Updated README with comprehensive usage instructions
- Enhanced composer.json with better metadata and dependencies
- Improved code structure and documentation
- Updated all GitHub URLs to reflect SkyLostTR fork
- Added fork maintainer information

### Security
- Added input validation for chat data
- Secure handling of API tokens and sensitive data
- Temporary file cleanup after processing

## [1.0.0] - Initial Release

### Project History
This project has evolved through multiple contributors:
1. **Original**: [phosco/mattermost](https://github.com/phosco/mattermost) - Initial WhatsApp to Mattermost converter by André Rothe
2. **First Fork**: [witchi/whatsapp-mattermost](https://github.com/witchi/whatsapp-mattermost) - Enhanced version with improvements
3. **Current Fork**: [SkyLostTR/whatsapp-mattermost](https://github.com/SkyLostTR/whatsapp-mattermost) - This repository with GitHub integration and documentation

### Added
- Basic WhatsApp to Mattermost conversion functionality
- User and phone number mapping
- Emoji conversion support
- Media file handling
- JSONL export format
- Command-line interface

### Features
- Parse WhatsApp chat exports (text + media)
- Convert to Mattermost-compatible format
- Map WhatsApp users to Mattermost usernames
- Handle @mentions via phone numbers
- Convert emojis to Mattermost format
- Support for images, videos, and documents

## [1.1.0] - Release Date: 2025-07-25
