export default class WhatsAppPhoneMap {
  constructor() {
    this.map = new Map();
  }

  add(phone, mmUser) {
    this.map.set(phone, mmUser);
  }

  get(phone) {
    if (this.map.has(phone)) return this.map.get(phone);
    return 'unknown-user';
  }

  count() {
    return this.map.size;
  }
}
