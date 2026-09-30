const SniperPrivacy = {
  sensitive: key => /password|passwd|authorization|cookie|token|secret|api[-_]?key|csrf|otp/i.test(key),
  redact(value, depth = 0) {
    if (depth > 30) return '[depth limit]';
    if (Array.isArray(value)) return value.map(item => this.redact(item, depth + 1));
    if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([key, child]) => [key, this.sensitive(key) ? '[REDACTED]' : this.redact(child, depth + 1)]));
    if (typeof value === 'string') {
      try { const parsed = JSON.parse(value); if (parsed && typeof parsed === 'object') return JSON.stringify(this.redact(parsed, depth + 1)); } catch (_) {}
      return value.replace(/((?:password|passwd|token|secret|api_key|csrf|otp)=)[^&\s]*/gi, '$1[REDACTED]');
    }
    return value;
  }
};
