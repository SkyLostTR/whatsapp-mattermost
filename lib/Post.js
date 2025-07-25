import Text from './Text.js';
import Emoji from './Emoji.js';
import Mention from './Mention.js';
import Media from './Media.js';

export default class Post {
  constructor(day, time, user, content) {
    this.day = day;
    this.time = time;
    this.user = user;
    this.content = this.processContent(content);
  }

  processContent(content) {
    const result = [];
    for (const entry of this.splitOnEmoji(content)) {
      if (entry instanceof Text) {
        result.push(...this.stripMedia(entry));
      } else {
        result.push(entry);
      }
    }
    return result;
  }

  splitOnEmoji(content) {
    const res = [];
    let c = content.replace(/\xf0\x9f\x8f\xbb|\xf0\x9f\x8f\xbc/g, '');
    c = c.replace(/\xe2.{2}\xe2.{2}\xef.{2}/g, '');
    const emojiRegex = /(\xf0\x9f.{2}|\xe2.{2}\xef.{2})/;
    const parts = c.split(emojiRegex);
    for (let i = 0; i < parts.length; i++) {
      if (i % 2 === 0) {
        const txt = parts[i];
        if (txt) {
          const mentionSplit = txt.split(/@(\w+)/);
          if (mentionSplit.length > 1) {
            res.push(new Text(mentionSplit[0]));
            res.push(new Mention(mentionSplit[1]));
            res.push(...this.splitOnEmoji(mentionSplit[2]));
          } else {
            res.push(new Text(txt));
          }
        }
      } else {
        res.push(new Emoji(parts[i]));
      }
    }
    return res;
  }

  stripMedia(textObj) {
    const m = /(.*)\xe2\x80\x8e([^\s]*) \(.*\)/.exec(textObj.getContent());
    if (m) {
      const res = [];
      if (m[1]) res.push(new Text(m[1]));
      res.push(new Media(m[2]));
      return res;
    }
    return [textObj];
  }

  append(line) {
    const c = this.processContent(line);
    this.content = [...this.content, new Text('\n'), ...c];
  }
}
