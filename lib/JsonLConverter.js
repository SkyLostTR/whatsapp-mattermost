import fs from 'fs';
import Text from './Text.js';
import Emoji from './Emoji.js';
import Mention from './Mention.js';
import Media from './Media.js';
import PhoneNumber from './PhoneNumber.js';

export default class JsonLConverter {
  constructor(team, channel) {
    this.team = team;
    this.channel = channel;
    this.maxMessageLength = parseInt(process.env.MAX_MESSAGE_LENGTH || '16000', 10);
    if (!this.maxMessageLength || this.maxMessageLength > 16383) this.maxMessageLength = 16000;
  }

  splitLongMessage(message) {
    if (Buffer.byteLength(message, 'utf8') <= this.maxMessageLength) return [message];
    const lines = message.split('\n');
    const parts = [];
    let current = '';
    for (let line of lines) {
      const test = current ? current + '\n' + line : line;
      if (Buffer.byteLength(test, 'utf8') > this.maxMessageLength) {
        if (current) {
          parts.push(current + '\n\n... (continued)');
          current = '';
        }
        if (Buffer.byteLength(line, 'utf8') > this.maxMessageLength) {
          const chunk = line.match(new RegExp(`.{1,${this.maxMessageLength}}`, 'g')) || [];
          for (let i = 0; i < chunk.length; i++) {
            const part = chunk[i];
            if (i === 0 && parts.length > 0) parts.push('(continued) ...\n\n' + part + '\n\n... (continued)');
            else if (i === chunk.length - 1) current = '(continued) ...\n\n' + part;
            else parts.push('(continued) ...\n\n' + part + '\n\n... (continued)');
          }
        } else {
          current = (parts.length > 0 ? '(continued) ...\n\n' : '') + line;
        }
      } else {
        current = test;
      }
    }
    if (current) parts.push(current);
    return parts;
  }

  toArray(userMap, phoneMap, emojiMap, chat) {
    const res = [ { type: 'version', version: '1.1.0' } ];
    for (const p of chat.getPosts()) {
      const username = userMap.get(p.user);
      const textParts = [];
      for (const c of p.content) {
        if (c instanceof Text) textParts.push(c.getContent());
        else if (c instanceof Emoji) textParts.push(emojiMap.get(Buffer.from(c.getContent()).toString('hex')));
        else if (c instanceof Mention) textParts.push('@' + c.getUsername());
        else if (c instanceof Media) textParts.push(c.getContent());
        else if (c instanceof PhoneNumber) textParts.push('@' + phoneMap.get(c.getContent()));
      }
      const message = textParts.join('');
      for (const part of this.splitLongMessage(message)) {
        res.push({
          type: 'post',
          post: {
            team: this.team,
            channel: this.channel,
            user: username,
            message: part,
            create_at: new Date(`${p.day} ${p.time}`).getTime()
          }
        });
      }
    }
    return res;
  }

  toJsonL(userMap, phoneMap, emojiMap, chat) {
    const arr = this.toArray(userMap, phoneMap, emojiMap, chat);
    return arr.map(o => JSON.stringify(o)).join('\n');
  }
}
