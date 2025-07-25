#!/usr/bin/env node
import fs from 'fs';
import path from 'path';
import fetch from 'node-fetch';
import archiver from 'archiver';
import { config } from 'dotenv';
import {
  WhatsAppChat,
  JsonLConverter,
  WhatsAppUserMap,
  WhatsAppPhoneMap,
  WhatsAppEmojiMap
} from '../lib/index.js';

config();

function parseMappings(str) {
  const map = new Map();
  if (!str) return map;
  const parts = str.split(';');
  for (const part of parts) {
    const m = part.match(/"(.*?)"="(.*?)"/);
    if (m) map.set(m[1], m[2]);
  }
  return map;
}

function createImportZip(zipPath, jsonlData, mediaDir = '') {
  return new Promise((resolve, reject) => {
    const output = fs.createWriteStream(zipPath);
    const archive = archiver('zip');
    output.on('close', () => resolve(true));
    archive.on('error', err => reject(err));
    archive.pipe(output);
    archive.append(jsonlData, { name: 'data.jsonl' });
    if (mediaDir && fs.existsSync(mediaDir)) {
      const files = fs.readdirSync(mediaDir);
      for (const f of files) {
        const ext = path.extname(f).toLowerCase();
        if (['.jpg','.jpeg','.png','.gif','.mp4','.webp','.pdf','.doc','.docx','.opus','.aac','.m4a'].includes(ext)) {
          archive.file(path.join(mediaDir, f), { name: 'data/' + f });
        }
      }
    }
    archive.finalize();
  });
}

async function importToMattermost(jsonlData, mattermostUrl, token, mediaDir = '') {
  const tempZip = path.join(process.cwd(), `import_${Date.now()}.zip`);
  await createImportZip(tempZip, jsonlData, mediaDir);
  const formData = new FormData();
  formData.append('filesize', fs.statSync(tempZip).size.toString());
  formData.append('importFrom', 'slack');
  formData.append('file', new Blob([fs.readFileSync(tempZip)]), 'import.zip');

  const res = await fetch(`${mattermostUrl.replace(/\/$/, '')}/api/v4/imports`, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` },
    body: formData
  });
  fs.unlinkSync(tempZip);
  if (!res.ok) {
    console.error('Import failed', await res.text());
    return false;
  }
  console.log('Import successful');
  return true;
}

async function importPostsDirectly(posts, mattermostUrl, token, teamName, channelName) {
  const baseUrl = mattermostUrl.replace(/\/$/, '') + '/api/v4';
  const headers = { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' };
  let res = await fetch(`${baseUrl}/teams/name/${teamName}`, { headers });
  if (!res.ok) { console.error('Failed to get team'); return false; }
  const team = await res.json();
  res = await fetch(`${baseUrl}/teams/${team.id}/channels/name/${channelName}`, { headers });
  if (!res.ok) { console.error('Failed to get channel'); return false; }
  const channel = await res.json();
  let success = 0;
  for (const [i, post] of posts.entries()) {
    res = await fetch(`${baseUrl}/posts`, {
      method: 'POST',
      headers,
      body: JSON.stringify({ channel_id: channel.id, message: post.post.message, create_at: post.post.create_at })
    });
    if (res.ok) {
      success++;
      process.stdout.write(`Posted ${i + 1}/${posts.length}\r`);
    } else {
      console.error(`\nFailed to post message ${i + 1}`);
    }
    await new Promise(r => setTimeout(r, 100));
  }
  console.log(`\nSuccessfully imported ${success} of ${posts.length}`);
  return success > 0;
}

async function main() {
  const userMap = new WhatsAppUserMap();
  for (const [name, user] of parseMappings(process.env.USER_MAPPINGS || '').entries()) {
    userMap.add(name, user);
  }
  const phoneMap = new WhatsAppPhoneMap();
  for (const [phone, user] of parseMappings(process.env.PHONE_MAPPINGS || '').entries()) {
    phoneMap.add(phone, user);
  }
  const emojiMap = new WhatsAppEmojiMap();

  const chat = new WhatsAppChat(process.env.WHATSAPP_CHAT_FILE);
  const converter = new JsonLConverter(process.env.MATTERMOST_TEAM_NAME, process.env.MATTERMOST_CHANNEL_NAME);

  console.log('Choose import method:');
  console.log('1. Direct API import');
  console.log('2. Individual post import');
  console.log('3. Save to file only');
  process.stdout.write('Enter choice (1-3): ');
  const choice = await new Promise(res => {
    process.stdin.resume();
    process.stdin.once('data', d => res(d.toString().trim()));
  });

  if (choice === '1') {
    const json = converter.toJsonL(userMap, phoneMap, emojiMap, chat);
    if (!await importToMattermost(json, process.env.MATTERMOST_URL, process.env.MATTERMOST_API_TOKEN, chat.getMediaFolder())) {
      console.log('API import failed, saving to file...');
      await createImportZip(process.env.IMPORT_ZIP_PATH, json, chat.getMediaFolder());
    }
  } else if (choice === '2') {
    const posts = converter.toArray(userMap, phoneMap, emojiMap, chat);
    if (!await importPostsDirectly(posts, process.env.MATTERMOST_URL, process.env.MATTERMOST_API_TOKEN, process.env.MATTERMOST_TEAM_NAME, process.env.MATTERMOST_CHANNEL_NAME)) {
      console.log('Direct import failed, saving to file...');
      const json = converter.toJsonL(userMap, phoneMap, emojiMap, chat);
      await createImportZip(process.env.IMPORT_ZIP_PATH, json, chat.getMediaFolder());
    }
  } else {
    const json = converter.toJsonL(userMap, phoneMap, emojiMap, chat);
    await createImportZip(process.env.IMPORT_ZIP_PATH, json, chat.getMediaFolder());
  }
}

main();
