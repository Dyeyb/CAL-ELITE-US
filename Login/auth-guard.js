/**
 * auth-guard.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Place this script early in <head> on every protected page.
 * It runs synchronously before the DOM renders — preventing any page flash.
 *
 * USER pages  → add:  <script src="../Login/auth-guard.js"></script>
 * ADMIN pages → add:  <script src="../Login/auth-guard.js" data-role="admin"></script>
 */
(function () {
  var user = null;
  try {
    user = JSON.parse(sessionStorage.getItem('user') || 'null');
  } catch (e) { }

  // ── No session → send to login ────────────────────────────────────────────
  if (!user || !user.user_id) {
    // Determine relative path back to Login/ based on script src
    var scripts = document.getElementsByTagName('script');
    var src = scripts[scripts.length - 1].src || '';
    var base = src.substring(0, src.lastIndexOf('/') + 1) || '../Login/';
    window.location.replace(base + 'login.html');
    return;
  }

  // ── Admin-only pages → non-admin users get bounced to homepage ────────────
  var scripts2 = document.getElementsByTagName('script');
  var thisScript = scripts2[scripts2.length - 1];
  var requiredRole = thisScript.getAttribute('data-role') || '';

  if (requiredRole === 'admin' && user.user_type !== 'admin') {
    window.location.replace('../Homepage/index.html');
    return;
  }

  // ── Expose user globally for pages that need it ────────────────────────────
  window.__authUser = user;
})();
