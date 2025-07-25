# WhatsApp to Mattermost Converter

[![CI](https://github.com/SkyLostTR/whatsapp-mattermost/workflows/CI/badge.svg)](https://github.com/SkyLostTR/whatsapp-mattermost/actions)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)

A robust tool to convert WhatsApp chat exports into Mattermost-compatible format, allowing seamless migration of chat history with media attachments.

> **Note**: This is a fork of [witchi/whatsapp-mattermost](https://github.com/witchi/whatsapp-mattermost) with enhanced features and improved documentation.

## ✨ Features

- 📱 **WhatsApp Export Processing**: Parse WhatsApp chat exports (text + media)
- 🔄 **Multiple Import Methods**: Bulk import via API or individual post migration
- 📎 **Media Support**: Import images, videos, documents, and audio files
- 👥 **User Mapping**: Map WhatsApp users to Mattermost usernames
- 📞 **Phone Number Mapping**: Handle @mentions via phone numbers
- 😀 **Emoji Conversion**: Convert WhatsApp emojis to Mattermost format
- 🔧 **Flexible Output**: Generate import files or direct API import

### Prerequisites

- PHP 7.4 or higher
- Composer
- PHP Zip extension (for creating import packages)
- WhatsApp chat export (text file with optional media folder)

### Installation

1. **Clone the repository**
   ```powershell
   git clone https://github.com/SkyLostTR/whatsapp-mattermost.git
   cd whatsapp-mattermost
   ```

2. **Install dependencies with Composer**
   ```powershell
   composer install
   ```
   Or, if you have PHP installed globally:
   ```powershell
   php composer.phar install
   ```

3. **Check PHP Zip extension** (optional but recommended)
   ```powershell
   php test_ziparchive.php
   ```

## ⚙️ Configuration

### Environment Setup

1. **Copy the sample environment file**
   ```powershell
   Copy-Item .env.sample .env
   ```

2. **Edit the `.env` file** with your configuration:
   ```env
   # Mattermost Configuration
   MATTERMOST_URL=https://your-mattermost-server.com
   MATTERMOST_API_TOKEN=your-api-token-here
   MATTERMOST_TEAM_NAME=your-team-name
   MATTERMOST_CHANNEL_NAME=your-channel-name

   # File Paths (use double backslashes for Windows paths)
   WHATSAPP_CHAT_FILE="C:\\path\\to\\your\\whatsapp\\chat\\export.txt"
   IMPORT_ZIP_PATH="C:\\path\\to\\save\\import.zip"

   # User Mappings (format: "Display Name"="username")
   USER_MAPPINGS='"John Doe"="john.doe";"Jane Smith"="jane.smith"'

   # Phone Number Mappings with Country Code (format: "phone_number"="username")
   PHONE_MAPPINGS='"905555555"="john.doe";"0987654321"="jane.smith"'
   ```

> **Important**: The `.env` file contains sensitive information and is automatically ignored by git. Never commit it to version control.

## 📋 Usage

### Step 1: Export WhatsApp Chat

1. Open WhatsApp on your phone
2. Go to the chat you want to export
3. Tap on chat name → Export Chat
4. Choose "Include Media" for complete migration
5. Save the export to your computer

### Step 2: Configure Environment

Make sure your `.env` file is properly configured (see Configuration section above).

### Step 3: Run the Converter

```powershell
php src/convert.php
```

Choose from three import methods:
1. **Bulk Import** - Uses Mattermost's import API (recommended)
2. **Individual Posts** - Posts messages one by one via API
3. **File Export** - Creates import package for manual upload

## 🔧 Configuration Details

### User Mapping

Configure user mappings in your `.env` file using the `USER_MAPPINGS` variable:

```env
USER_MAPPINGS="WhatsApp Display Name"="mattermost-username";"Another User"="another.user"
```

**Finding WhatsApp Names:**
- Open your exported chat text file
- Look for names after timestamps (e.g., `[25/07/2025, 13:45:32] John Doe: Hello`)

### Phone Number Mapping

For @mentions in WhatsApp (which use phone numbers), configure the `PHONE_MAPPINGS` variable:

```env
PHONE_MAPPINGS="1234567890"="mattermost-username";"0987654321"="another.user"
```

**Phone Number Format:**
- Use the exact format from your WhatsApp export
- Usually includes country code (e.g., `491635552056` for Germany)
- Check your export file for the exact format used

### Mattermost API Token

To get your Mattermost API token:

1. Log into your Mattermost instance
2. Go to **Account Settings** → **Security** → **Personal Access Tokens**
3. Create a new token with appropriate permissions
4. Copy the token to your `.env` file

### Media Files

Supported media types:
- Images: JPG, JPEG, PNG, GIF, WebP
- Videos: MP4
- Audio: Opus, AAC, M4A
- Documents: PDF, DOC, DOCX

## 🔄 Import Methods

### Method 1: Bulk Import (Recommended)

Uses Mattermost's bulk import API for faster processing:

- ✅ Faster import for large chats
- ✅ Preserves message timestamps
- ✅ Handles media attachments
- ❌ Requires admin permissions
- ⚠️ Posts appear under the account performing the import, not original sender names

### Method 2: Individual Posts

Posts messages one by one using the regular API:

- ✅ Works with standard user permissions
- ✅ Good for smaller chats
- ❌ Slower for large chats
- ❌ May hit rate limits
- ⚠️ Posts appear under the account performing the import, not original sender names

### Method 3: File Export

Creates an import package for manual upload:

- ✅ Works offline
- ✅ Can be imported later
- ✅ Good for review before import
- ✅ Correctly attributes posts to original sender names (when imported via Mattermost import tools)
- ❌ Manual upload required

## 🖥️ Mattermost CLI Import Guide

For advanced users or when the API methods don't work, you can use Mattermost's command-line tool (`mmctl`) to import your data directly.

### Prerequisites

1. **Download mmctl**: Get the latest version from [Mattermost releases](https://github.com/mattermost/mmctl/releases)
2. **Admin access**: You need system admin privileges on your Mattermost instance
3. **Import package**: Use Method 3 (File Export) to create your import.zip

### Authentication

#### Option 1: Direct Authentication
```bash
# Authenticate with your Mattermost server
mmctl auth login https://your-mattermost-server.com --name myserver
# Enter your admin username and password when prompted
```

#### Option 2: Local Mode (Docker installations)
```bash
# If running Mattermost in Docker, use local mode
mmctl --local auth login
```

#### Option 3: Access Token
```bash
# Use an admin personal access token
mmctl auth login https://your-mattermost-server.com --name myserver --access-token YOUR_ADMIN_TOKEN
```

### Import Process

#### Standard Installation
```bash
# Navigate to your import package location
cd /path/to/your/import/

# Import the package (bypasses file upload, processes locally)
mmctl --local import process --bypass-upload --extract-content import.zip

# Alternative: Upload and import
mmctl import upload import.zip
mmctl import list available  # Find your import job ID
mmctl import process <job-id>
```

#### Docker Installation

**Method 1: Copy to container**
```bash
# Copy import package to container
docker cp import.zip mattermost-server:/tmp/

# Execute import inside container
docker exec -it mattermost-server mmctl --local import process --bypass-upload --extract-content /tmp/import.zip
```

**Method 2: Volume mount**
```bash
# If you have volume mounted, place import.zip in the mounted directory
# Then run from inside container
docker exec -it mattermost-server mmctl --local import process --bypass-upload --extract-content /mattermost/import.zip
```

**Method 3: Direct CLI access**
```bash
# If mmctl is available on host system
mmctl --local import process --bypass-upload --extract-content ./import.zip
```

### Docker Compose Example

If using docker-compose, add this to your workflow:

```yaml
# In your docker-compose.yml, ensure volumes are mapped
volumes:
  - ./imports:/mattermost/imports
```

Then:
```bash
# Place your import.zip in the ./imports directory
cp import.zip ./imports/

# Run import
docker-compose exec app mmctl --local import process --bypass-upload --extract-content /mattermost/imports/import.zip
```

### Import Verification

```bash
# Check import job status
mmctl import list

# View import job details
mmctl import job show <job-id>

# Monitor server logs during import
mmctl logs tail
```

### Common mmctl Import Issues

**Permission denied:**
```bash
# Ensure you're authenticated as admin
mmctl user list --system-admin

# Check your authentication
mmctl auth current
```

**File not found:**
```bash
# Verify file path and permissions
ls -la /path/to/import.zip
```

**Import validation errors:**
```bash
# Use dry-run to check import without processing
mmctl import validate import.zip

# Check the import.zip structure
unzip -l import.zip
```

### Performance Tips

- **Large imports**: Use `--bypass-upload` for faster processing
- **Network issues**: Use local mode when possible
- **Memory**: Monitor server resources during import
- **Batching**: For very large chats, consider splitting the export

### Example Complete Workflow

```bash
# 0. Install dependencies
composer install

# 1. Generate import package
php src/convert.php
# Choose option 3 (File Export)

# 2. Authenticate with Mattermost
mmctl auth login https://your-server.com --name production

# 3. Validate import (optional)
mmctl import validate import.zip

# 4. Process import
mmctl --local import process --bypass-upload --extract-content import.zip

# 5. Verify import
mmctl import list
```

## 😀 Emoji Handling

WhatsApp and Mattermost use different emoji formats. The tool includes automatic emoji conversion:

**How it works:**
- WhatsApp exports emojis as Unicode sequences
- The tool maps them to Mattermost emoji names (`:emoji-name:`)
- Common emojis are pre-mapped in `WhatsAppEmojiMap`

**Adding custom emoji mappings:**
```php
$emojiMap = new WhatsAppEmojiMap();
$emojiMap->add("🎉", ":tada:");
$emojiMap->add("❤️", ":heart:");
```

**Finding unmapped emojis:**
- Check the error log for Unicode sequences
- Compare with Mattermost's emoji picker
- Add mappings to `WhatsAppEmojiMap` class

## 🛠️ Troubleshooting

### Common Issues

**"Unknown user" error:**
- Check WhatsApp display names in your export file
- Add missing users to the user mapping
- Ensure exact name matching (case-sensitive)

**"Unknown telephone number" error:**
- Look for @mentions in your chat export
- Add phone numbers to the phone mapping
- Use exact format from export (including country code)

**Import fails:**
- Verify Mattermost URL and API token
- Check team and channel names
- Ensure you have proper permissions

**Missing media files:**
- Ensure media folder is in the same directory as chat export
- Check supported file formats
- Verify file permissions

### Debug Mode

Enable verbose output by modifying the script:
```php
// Add at the top of convert.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📁 File Structure

```
whatsapp-mattermost/
├── src/
│   ├── convert.php              # Main conversion script
│   └── de/phosco/mattermost/whatsapp/
│       ├── WhatsAppChat.php     # Chat parser
│       ├── JsonLConverter.php   # Format converter
│       ├── WhatsAppUserMap.php  # User mapping
│       ├── WhatsAppPhoneMap.php # Phone mapping
│       └── WhatsAppEmojiMap.php # Emoji mapping
├── composer.json                # Dependencies
└── README.md                    # This file
```

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### Development Setup

```powershell
git clone https://github.com/SkyLostTR/whatsapp-mattermost.git
cd whatsapp-mattermost
composer install
```

### Running Tests

```powershell
# Check PHP syntax
Get-ChildItem -Path src -Filter "*.php" -Recurse | ForEach-Object { php -l $_.FullName }

# Test ZIP functionality
php test_ziparchive.php
```

## 📄 License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.

## 🔒 Security

Please review our [Security Policy](SECURITY.md) for information about reporting vulnerabilities and data privacy considerations.

## 📚 Resources

- [Mattermost Import Documentation](https://docs.mattermost.com/administration/bulk-export.html)
- [WhatsApp Export Guide](https://faq.whatsapp.com/1180414079177245)
- [Mattermost API Documentation](https://api.mattermost.com/)

## 📞 Support

- 🐛 [Report Issues](https://github.com/SkyLostTR/whatsapp-mattermost/issues)
- 💡 [Request Features](https://github.com/SkyLostTR/whatsapp-mattermost/issues/new?template=feature_request.md)
- ❓ [Ask Questions](https://github.com/SkyLostTR/whatsapp-mattermost/issues/new?template=support_question.md)

---

**Attribution:**
- Originally created by [witchi](https://github.com/witchi) ([witchi/whatsapp-mattermost](https://github.com/witchi/whatsapp-mattermost))
- Maintained and improved by [SkyLostTR](https://github.com/SkyLostTR)

Version 1.1.0
