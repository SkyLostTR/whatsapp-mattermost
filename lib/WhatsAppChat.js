import fs from 'fs';
import path from 'path';

export default class WhatsAppChat {
  constructor(chatFile) {
    this.chatDir = fs.existsSync(chatFile) ? path.dirname(chatFile) : process.cwd();
    this.chat = this.importChat(chatFile);
  }

  importChat(chatFile) {
    const data = fs.readFileSync(chatFile, 'utf8');
    const lines = data.split(/\r?\n/);
    const posts = [];
    let post = null;

    const messagePattern = /(\d{2}\.\d{2}\.\d{4}) (\d{2}:\d{2}) - ([^:]*): (.*)/;
    const systemPattern = /(\d{2}\.\d{2}\.\d{4}) (\d{2}:\d{2}) - (.*)/;

    for (let line of lines) {
      line = line.trim();
      if (!line) continue;
      let m = line.match(messagePattern);
      if (m) {
        if (post) posts.push(post);
        post = { day: m[1], time: m[2], user: m[3], content: [m[4]] };
        continue;
      }
      m = line.match(systemPattern);
      if (m) {
        if (post) posts.push(post);
        post = { day: m[1], time: m[2], user: 'System', content: [m[3]] };
        continue;
      }
      if (post) {
        post.content.push('\n' + line);
      }
    }
    if (post) posts.push(post);
    return posts;
  }

  getPosts() {
    return this.chat;
  }

  getMediaFolder() {
    return this.chatDir;
  }
}

