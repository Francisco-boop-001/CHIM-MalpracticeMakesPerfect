(function () {
  'use strict';

  const root = document.getElementById('brainwash-root');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  if (!root || !window.BrainwashEditor) return;

  async function request(operation, payload) {
    const response = await fetch('api.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ operation, payload }),
    });
    let body;
    try { body = await response.json(); } catch (_) { throw new Error('Brainwash returned an unreadable response.'); }
    if (!response.ok || body?.ok !== true) throw new Error(body?.error?.message || 'Brainwash is unavailable right now.');
    return body.data;
  }

  window.BrainwashEditor.mount(root, { request });
}());
