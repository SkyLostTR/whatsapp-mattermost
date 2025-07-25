export default class WhatsAppUserMap {
  constructor() {
    this.map = new Map();
  }

  add(waUser, mmUser) {
    this.map.set(waUser, mmUser);
  }

  get(waUser) {
    if (this.map.has(waUser)) return this.map.get(waUser);
    throw new Error(`Unknown user ${waUser}`);
  }

  count() {
    return this.map.size;
  }
}
