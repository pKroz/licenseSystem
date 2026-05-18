/**
 * SCRUM-22 - Implementar cierre de sesión
 * Elimina el token y redirige al login
 */

function logout() {
  // Eliminar token y datos de sesión
  localStorage.removeItem('token');
  localStorage.removeItem('user');
  sessionStorage.removeItem('token');
  sessionStorage.removeItem('user');

  // Redirigir al login
  window.location.href = '/login.html';
}

// Ejemplo de uso en cualquier página:
// <button onclick="logout()">Cerrar sesión</button>
