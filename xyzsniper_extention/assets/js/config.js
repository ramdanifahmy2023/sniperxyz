// assets/js/config.js

const CONFIG = {
  // Ubah ke 'host' jika ekstensi ingin diarahkan ke server online
  MODE: 'local',

  URLS: {
    local: 'http://localhost:8000',
    host: 'https://domain-anda.com' // Ganti dengan domain asli jika sudah online
  },

  // Mendapatkan Base URL sesuai mode yang aktif
  getBaseUrl() {
    return this.MODE === 'local' ? this.URLS.local : this.URLS.host;
  },

  // Mendapatkan URL API Endpoint secara dinamis
  getApiUrl(endpoint) {
    return `${this.getBaseUrl()}/api/${endpoint}`;
  }
};
