// assets/js/config.js

const CONFIG = {
  // Gunakan 'host' untuk merekam melalui tunnel publik.
  MODE: 'local',

  URLS: {
    local: 'http://localhost:8000',
    host: 'https://sniper.fahra.my.id'
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
