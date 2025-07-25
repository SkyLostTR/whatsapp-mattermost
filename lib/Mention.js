export default class Mention {
  constructor(username) {
    this.username = username;
  }
  getUsername() {
    return this.username;
  }
  toString() {
    return this.username;
  }
}
