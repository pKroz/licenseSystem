var EPS = [
  ["GET",    "/users",        "Listar usuarios",        "administrador"],
  ["POST",   "/users",        "Crear usuario",          "administrador"],
  ["PUT",    "/users/:id",    "Actualizar usuario",     "administrador"],
  ["DELETE", "/users/:id",    "Eliminar usuario",       "administrador"],
  ["GET",    "/clients",      "Listar clientes",        "Autenticado"],
  ["POST",   "/clients",      "Crear cliente",          "administrador, vendedor"],
  ["PUT",    "/clients/:id",  "Actualizar cliente",     "administrador, vendedor"],
  ["DELETE", "/clients/:id",  "Eliminar cliente",       "administrador"],
  ["GET",    "/products",     "Listar productos",       "Autenticado"],
  ["POST",   "/products",     "Crear producto",         "administrador"],
  ["GET",    "/licenses",     "Listar licencias",       "Autenticado"],
  ["POST",   "/licenses",     "Crear licencia",         "administrador, vendedor"],
  ["GET",    "/licenses/:id", "Detalle y historial",    "Autenticado"],
  ["PUT",    "/licenses/:id", "Actualizar licencia",    "administrador, vendedor"],
  ["DELETE", "/licenses/:id", "Cancelar licencia",      "administrador"],
  ["GET",    "/dashboard",    "Estadisticas",           "Autenticado"],
  ["GET",    "/audit",        "Logs de auditoria",      "administrador, auditor"]
];

var BG = { GET:"#DBEAFE", POST:"#D1FAE5", PUT:"#FEF3C7", DELETE:"#FEE2E2" };
var FG = { GET:"#1E40AF", POST:"#065F46", PUT:"#92400E", DELETE:"#991B1B" };

window.addEventListener('DOMContentLoaded', function() {
  var tbody = document.getElementById('ep-table');
  if (tbody) {
    tbody.innerHTML = EPS.map(function(e) {
      var bg = BG[e[0]] || "#F1F5F9";
      var fg = FG[e[0]] || "#0F172A";
      var badge = '<span style="background:' + bg + ';color:' + fg + ';padding:2px 8px;border-radius:4px;font-size:0.72rem;font-weight:700;font-family:monospace;">' + e[0] + '</span>';
      return '<tr><td>' + badge + '</td><td><code style="font-size:0.8rem;">' + e[1] + '</code></td><td style="font-size:0.875rem;">' + e[2] + '</td><td style="font-size:0.75rem;color:#64748B;">' + e[3] + '</td></tr>';
    }).join('');
  }

  var btn = document.getElementById('test-btn');
  if (!btn) return;
  btn.addEventListener('click', async function() {
    var resultEl = document.getElementById('test-result');
    var key = document.getElementById('test-key').value.trim();
    var apiKey = document.getElementById('test-apikey').value.trim();
    var domain = document.getElementById('test-domain').value.trim();
    if (!key || !apiKey) { alert('License key y API key son requeridos.'); return; }
    btn.disabled = true;
    btn.textContent = 'Validando...';
    resultEl.style.display = 'none';
    try {
      var res = await fetch('http://localhost/licenseflow/api/validate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-API-Key': apiKey },
        body: JSON.stringify({ license_key: key, domain: domain || undefined })
      });
      var data = await res.json();
      var ok = data.valid;
      resultEl.style.display = 'block';
      resultEl.innerHTML =
        '<div style="background:' + (ok ? '#ECFDF5' : '#FEF2F2') + ';color:' + (ok ? '#065F46' : '#991B1B') + ';padding:0.875rem 1rem;border-radius:8px;margin-bottom:0.5rem;">' +
        (ok ? 'Licencia valida' : 'Licencia invalida') + ' - ' + data.message + '</div>' +
        '<pre style="background:#0F172A;color:#E2E8F0;border-radius:8px;padding:1rem;font-size:0.78rem;overflow-x:auto;">' + JSON.stringify(data, null, 2) + '</pre>';
    } catch(err) {
      resultEl.style.display = 'block';
      resultEl.innerHTML = '<div style="background:#FEF2F2;color:#991B1B;padding:0.875rem;border-radius:8px;">Error: ' + err.message + '</div>';
    }
    btn.disabled = false;
    btn.textContent = 'Validar';
  });
});