// ============================================================
//  NutriTrack - Módulo central de datos
//  Tienda y Canjes ahora usan la BD (APIs PHP), no localStorage
//  Incluir en todos los HTMLs: <script src="nt-store.js"></script>
// ============================================================

const NTStore = (() => {

    // ── Sesión ──────────────────────────────────────────────
    function getSession() {
        return {
            user: localStorage.getItem('nutritrack_user'),
            role: localStorage.getItem('nutritrack_role'),
            uid:  parseInt(localStorage.getItem('nutritrack_uid') || '0'),
        };
    }

    function setSession(user, role, uid) {
        localStorage.setItem('nutritrack_user', user);
        localStorage.setItem('nutritrack_role', role);
        localStorage.setItem('nutritrack_uid',  uid);
    }

    function clearSession() {
        localStorage.removeItem('nutritrack_user');
        localStorage.removeItem('nutritrack_role');
        localStorage.removeItem('nutritrack_uid');
    }

    function requireAuth(expectedRole) {
        const { user, role } = getSession();
        if (!user) { location.href = 'login.html'; return false; }
        if (expectedRole === 'user'  && role === 'admin') { location.href = 'admin-panel.html'; return false; }
        if (expectedRole === 'admin' && role !== 'admin') { location.href = 'dashboard.html';   return false; }
        return true;
    }

    // ── Base URL de la API ──────────────────────────────────
    const API = '/api';

    // ── Helpers de fecha ────────────────────────────────────
    function today()     { return new Date().toISOString().split('T')[0]; }

    function diasAtras(n) {
        const d = new Date();
        d.setDate(d.getDate() - n);
        return d.toISOString().split('T')[0];
    }

    function nombreDia(fechaStr) {
        const dias = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
        return dias[new Date(fechaStr + 'T12:00:00').getDay()];
    }

    function nombreDiaLargo(fechaStr) {
        const dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        return dias[new Date(fechaStr + 'T12:00:00').getDay()];
    }

    // ── LOGIN ────────────────────────────────────────────────
    async function login(username, password) {
        try {
            const res  = await fetch(`${API}/login.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ username, password }),
            });
            const data = await res.json();
            if (data.ok) setSession(data.usuario.username, data.usuario.rol, data.usuario.id);
            return data;
        } catch (e) {
            return { ok: false, error: 'No se pudo conectar con el servidor. Verifica que XAMPP esté corriendo.' };
        }
    }

    // ── DASHBOARD ────────────────────────────────────────────
    async function getDashboard() {
        const { uid } = getSession();
        if (!uid) return { ok: false, error: 'Sin sesión' };
        try {
            const res = await fetch(`${API}/dashboard.php?usuario_id=${uid}`);
            return await res.json();
        } catch (e) { return { ok: false, error: 'Error de conexión' }; }
    }

    // ── RESUMEN DEL DÍA ──────────────────────────────────────
    async function getResumenDia() {
        const { uid } = getSession();
        if (!uid) return { ok: false, error: 'Sin sesión' };
        try {
            const res = await fetch(`${API}/resumen_dia.php?usuario_id=${uid}`);
            return await res.json();
        } catch (e) { return { ok: false, error: 'Error de conexión' }; }
    }

    // ── HISTORIAL ────────────────────────────────────────────
    async function getHistorial(soloSemana = 0, tipo = '') {
        const { uid } = getSession();
        if (!uid) return { ok: false, error: 'Sin sesión' };
        try {
            let url = `${API}/historial.php?usuario_id=${uid}&semana=${soloSemana}`;
            if (tipo) url += `&tipo=${encodeURIComponent(tipo)}`;
            const res = await fetch(url);
            return await res.json();
        } catch (e) { return { ok: false, error: 'Error de conexión' }; }
    }

    // ── AGREGAR REGISTRO DE COMIDA ───────────────────────────
    async function addRegistro(nombreLibre, tipoComida, porcion, hora, fotoBase64 = null) {
        const { uid } = getSession();
        if (!uid) return { ok: false, error: 'Sin sesión' };
        try {
            const body = {
                usuario_id:   uid,
                nombre_libre: nombreLibre,
                tipo_comida:  tipoComida,
                porcion,
                hora: hora || new Date().toTimeString().slice(0, 5),
            };
            if (fotoBase64) body.foto_base64 = fotoBase64;
            const res = await fetch(`${API}/registro.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(body),
            });
            return await res.json();
        } catch (e) { return { ok: false, error: 'Error de conexión' }; }
    }

    // ── DATOS DEL USUARIO ────────────────────────────────────
    async function getUsuario() {
        const { user } = getSession();
        if (!user) return { ok: false, error: 'Sin sesión' };
        try {
            const res = await fetch(`${API}/usuario.php?username=${encodeURIComponent(user)}`);
            return await res.json();
        } catch (e) { return { ok: false, error: 'Error de conexión' }; }
    }

    async function getPuntos() {
        const data = await getUsuario();
        return data.ok ? data.usuario.puntos : 0;
    }

    async function getRacha() {
        const data = await getUsuario();
        return data.ok ? data.usuario.racha_dias : 0;
    }

    function calcMultiplicador(rachaDias) {
        return Math.min(2.0, 1.0 + Math.floor(rachaDias / 10) * 0.1);
    }

    // ════════════════════════════════════════════════════════
    //  TIENDA — 100% base de datos
    // ════════════════════════════════════════════════════════

    /** Devuelve todos los artículos (admin) */
    async function getItems() {
        try {
            const res = await fetch(`${API}/tienda_items.php`);
            const data = await res.json();
            return data.ok ? data.items : [];
        } catch (e) { return []; }
    }

    /** Devuelve solo artículos activos (vista usuario) */
    async function getItemsActivos() {
        try {
            const res = await fetch(`${API}/tienda_items.php?activos=1`);
            const data = await res.json();
            return data.ok ? data.items : [];
        } catch (e) { return []; }
    }

    async function addItem(nombre, desc, costo, emoji) {
        try {
            const res = await fetch(`${API}/tienda_items.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ nombre, desc, costo, emoji }),
            });
            return await res.json();
        } catch (e) { return { ok: false, error: 'Error de conexión' }; }
    }

    async function updateItem(id, nombre, desc, costo, emoji) {
        try {
            const res = await fetch(`${API}/tienda_items.php`, {
                method:  'PUT',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ id, nombre, desc, costo, emoji }),
            });
            return await res.json();
        } catch (e) { return { ok: false, error: 'Error de conexión' }; }
    }

    async function toggleItem(id) {
        try {
            const res = await fetch(`${API}/tienda_toggle.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ id }),
            });
            return await res.json();
        } catch (e) { return { ok: false, error: 'Error de conexión' }; }
    }

    async function deleteItem(id) {
        try {
            const res = await fetch(`${API}/tienda_items.php`, {
                method:  'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ id }),
            });
            return await res.json();
        } catch (e) { return { ok: false, error: 'Error de conexión' }; }
    }

    // ════════════════════════════════════════════════════════
    //  CANJES — 100% base de datos
    // ════════════════════════════════════════════════════════

    /** Canjes del usuario actual */
    async function getCanjes() {
        const { uid } = getSession();
        if (!uid) return [];
        try {
            const res = await fetch(`${API}/canjes.php?usuario_id=${uid}`);
            const data = await res.json();
            return data.ok ? data.canjes : [];
        } catch (e) { return []; }
    }

    /** Todos los canjes (para admin) */
    async function getCanjesTodos() {
        try {
            const res = await fetch(`${API}/canjes.php?todos=1`);
            const data = await res.json();
            return data.ok ? data.canjes : [];
        } catch (e) { return []; }
    }

    /** Canjes pendientes (para el badge del admin) */
    async function getCanjesPendientes() {
        const canjes = await getCanjesTodos();
        return canjes.filter(c => c.estado === 'pendiente');
    }

    /** Realizar un canje */
    async function canjear(articuloId) {
        const { uid } = getSession();
        if (!uid) return { ok: false, error: 'Sin sesión' };
        try {
            const res = await fetch(`${API}/canjes.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ usuario_id: uid, articulo_id: articuloId }),
            });
            return await res.json();
        } catch (e) { return { ok: false, error: 'Error de conexión' }; }
    }

    /** Marcar canje como entregado (admin) */
    async function marcarEntregado(id) {
        try {
            const res = await fetch(`${API}/canjes.php`, {
                method:  'PUT',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ id }),
            });
            return await res.json();
        } catch (e) { return { ok: false, error: 'Error de conexión' }; }
    }

    // ── Init ─────────────────────────────────────────────────
    // Ya no hace nada (no hay localStorage que inicializar para la tienda)
    function init() {}

    // API pública
    return {
        init,
        today, diasAtras, nombreDia, nombreDiaLargo,
        // Sesión
        getSession, setSession, clearSession, requireAuth,
        // Auth
        login,
        // APIs async
        getDashboard, getResumenDia, getHistorial, addRegistro,
        getUsuario, getPuntos, getRacha, calcMultiplicador,
        // Tienda (ahora async/BD)
        getItems, getItemsActivos, addItem, updateItem, toggleItem, deleteItem,
        // Canjes (ahora async/BD)
        getCanjes, getCanjesTodos, getCanjesPendientes, canjear, marcarEntregado,
    };
})();

NTStore.init();