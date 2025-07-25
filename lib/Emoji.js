export default class Emoji {
  constructor(emoji) {
    this.emoji = emoji;
    this.bin = Buffer.from(emoji).toString('hex');
    console.error('an Emoji ' + emoji + this.bin);
  }

  getContent() {
    return this.emoji;
  }

  getBinary() {
    return this.bin;
  }
}
