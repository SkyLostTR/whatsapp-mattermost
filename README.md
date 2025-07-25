# WhatsApp to Mattermost Converter (Node.js)

Convert WhatsApp chat exports into a Mattermost compatible import format. This version is published as an npm package and can be installed with `npm install -g @keeftraum/whatsapp-mattermost`.

## Installation

```bash
npm install -g @keeftraum/whatsapp-mattermost
```

Create a `.env` file based on `.env.sample` and configure your Mattermost server and file paths.

## Usage

```bash
whatsapp-mattermost
```

The CLI will ask which import method to use and will either upload the data to Mattermost or generate an `import.zip` file.

## License

GPL-3.0-or-later
