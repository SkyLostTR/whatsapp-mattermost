# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- Media attachments missing after import (#2):
  - attachment paths are now relative to the archive's `data/` folder; Mattermost prefixes `data/`
    itself, so the previous `data/<file>` paths pointed to `data/data/<file>`
  - posts sent in the same minute (e.g. several photos) got the same `create_at` and Mattermost
    merged them into one post; every post now gets a unique, order preserving timestamp
  - media markers are detected in Android and iOS exports in any language (e.g. `(dosya ekli)`,
    `<ekli: ...>`), with or without the invisible LRM marker
  - media files are found in sub folders, case-insensitively and regardless of Unicode normalisation
  - every referenced file is packaged, not only a fixed list of extensions
  - unresolved media is reported and kept as text instead of being dropped
- Bulk API import (method 1) now uses the upload session + `import_process` job API
- Individual post import (method 2) uploads media and no longer fails on the version line
- @mentions were dropped from messages; phone number mentions are mapped via `PHONE_MAPPINGS`
- A message consisting of `0` stopped parsing of the rest of the chat

### Added
- Command line options `--input`, `--media`, `--output`, `--team`, `--channel`, `--method`
- The exported WhatsApp `.zip` can be used as input directly
- Support for iOS and month-first/12h export formats, `DATE_ORDER` and `TIMEZONE` settings
- `composer test` and media import regression tests in CI

### Changed
- TLS certificates are verified by default; set `MATTERMOST_SKIP_TLS_VERIFY=true` to disable
- Mattermost URL and token are only required for the API import methods

## [1.1.0] - Release Date: 2025-07-25

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
1. **Original**: [witchi/whatsapp-mattermost](https://github.com/witchi/whatsapp-mattermost) - Enhanced version with improvements
2. **Fork**: [SkyLostTR/whatsapp-mattermost](https://github.com/SkyLostTR/whatsapp-mattermost) - This repository with GitHub integration and documentation

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
