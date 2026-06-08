// ============================================================
//  NutriTrack - Módulo central de datos
//  Ahora conecta con las APIs PHP reales en lugar de solo localStorage
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
    // Si tus archivos están en htdocs/nutritrack/, la API está en api/
const API = '/api';

    // ── Helpers de fecha ────────────────────────────────────
    function today() {
        return new Date().toISOString().split('T')[0];
    }

    function diasAtras(n) {
        const d = new Date();
        d.setDate(d.getDate() - n);
        return d.toISOString().split('T')[0];
    }

    function nombreDia(fechaStr) {
        const dias = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
        const d = new Date(fechaStr + 'T12:00:00');
        return dias[d.getDay()];
    }

    function nombreDiaLargo(fechaStr) {
        const dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        const d = new Date(fechaStr + 'T12:00:00');
        return dias[d.getDay()];
    }

    // ── LOGIN (llama a la API real) ──────────────────────────
    async function login(username, password) {
        try {
            const res = await fetch(`${API}/login.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ username, password }),
            });
            const data = await res.json();
            if (data.ok) {
                setSession(data.usuario.username, data.usuario.rol, data.usuario.id);
            }
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
            const res  = await fetch(`${API}/dashboard.php?usuario_id=${uid}`);
            return await res.json();
        } catch (e) {
            return { ok: false, error: 'Error de conexión' };
        }
    }

    // ── RESUMEN DEL DÍA ──────────────────────────────────────
    async function getResumenDia() {
        const { uid } = getSession();
        if (!uid) return { ok: false, error: 'Sin sesión' };
        try {
            const res  = await fetch(`${API}/resumen_dia.php?usuario_id=${uid}`);
            return await res.json();
        } catch (e) {
            return { ok: false, error: 'Error de conexión' };
        }
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
        } catch (e) {
            return { ok: false, error: 'Error de conexión' };
        }
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
                porcion:      porcion,
                hora:         hora || new Date().toTimeString().slice(0, 5),
            };
            if (fotoBase64) body.foto_base64 = fotoBase64;

            const res  = await fetch(`${API}/registro.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(body),
            });
            return await res.json();
        } catch (e) {
            return { ok: false, error: 'Error de conexión' };
        }
    }

    // ── DATOS DEL USUARIO ────────────────────────────────────
    async function getUsuario() {
        const { user } = getSession();
        if (!user) return { ok: false, error: 'Sin sesión' };
        try {
            const res  = await fetch(`${API}/usuario.php?username=${encodeURIComponent(user)}`);
            return await res.json();
        } catch (e) {
            return { ok: false, error: 'Error de conexión' };
        }
    }

    // ── Puntos (lectura rápida desde resumen) ────────────────
    async function getPuntos() {
        const data = await getUsuario();
        return data.ok ? data.usuario.puntos : 0;
    }

    async function getRacha() {
        const data = await getUsuario();
        return data.ok ? data.usuario.racha_dias : 0;
    }

    // ── Multiplicador (cálculo local, igual que en PHP) ──────
    function calcMultiplicador(rachaDias) {
        const nivel = Math.floor(rachaDias / 10);
        return Math.min(2.0, 1.0 + nivel * 0.1);
    }

    // ── Items de tienda (sigue en localStorage por ahora) ────
    function _lsGet(k)    { return JSON.parse(localStorage.getItem('nt_' + k) || 'null'); }
    function _lsSet(k, v) { localStorage.setItem('nt_' + k, JSON.stringify(v)); }

    function initItems() {
        if (!_lsGet('items')) {
            _lsSet('items', [
                { id: 1, nombre: 'Día libre (cheat meal)', desc: 'Cancela puntos negativos de una comida trampa', costo: 500, emoji: '🎟️', activo: true },
                { id: 2, nombre: 'Bebida extra',           desc: 'Válido por un café o té en la cafetería',       costo: 250, emoji: '☕',  activo: true },
                { id: 3, nombre: 'Badge Nutri-Master',     desc: 'Insignia dorada para tu perfil',                costo: 150, emoji: '🏅', activo: true },
            ]);
        }
        if (!_lsGet('canjes'))        _lsSet('canjes', []);
        if (!_lsGet('next_canje_id')) _lsSet('next_canje_id', 100);
        if (!_lsGet('next_item_id'))  _lsSet('next_item_id',  100);
    }

    function getItems()        { return _lsGet('items') || []; }
    function getItemsActivos() { return getItems().filter(i => i.activo); }
    function saveItems(items)  { _lsSet('items', items); }

    function addItem(nombre, desc, costo, emoji) {
        const items = getItems();
        let id = _lsGet('next_item_id') || 100;
        items.push({ id: id++, nombre, desc, costo: parseInt(costo), emoji, activo: true });
        _lsSet('items', items);
        _lsSet('next_item_id', id);
    }

    function updateItem(id, nombre, desc, costo, emoji) {
        const items = getItems().map(i =>
            i.id === id ? { ...i, nombre, desc, costo: parseInt(costo), emoji } : i
        );
        _lsSet('items', items);
    }

    function toggleItem(id) {
        const items = getItems().map(i => i.id === id ? { ...i, activo: !i.activo } : i);
        _lsSet('items', items);
        return items.find(i => i.id === id);
    }

    function deleteItem(id) {
        _lsSet('items', getItems().filter(i => i.id !== id));
    }

    // ── Canjes ──────────────────────────────────────────────
    function getCanjes()           { return _lsGet('canjes') || []; }
    function getCanjesPendientes() { return getCanjes().filter(c => c.estado === 'pendiente'); }

    async function canjear(itemId) {
    const item = getItems().find(i => i.id === itemId);
    if (!item) return { ok: false, error: 'Artículo no encontrado' };

    // Leer puntos reales desde la BD
    const userData = await getUsuario();
    if (!userData.ok) return { ok: false, error: 'No se pudo verificar puntos' };

    const pts = userData.usuario.puntos;
    if (pts < item.costo) return { ok: false, error: 'Puntos insuficientes' };

    const { uid } = getSession();
    const now = new Date();

    // Guardar canje en localStorage
    const canjes = getCanjes();
    let id = _lsGet('next_canje_id') || 100;
    const canje = {
        id: id++,
        item_id: itemId,
        nombre: item.nombre,
        emoji:  item.emoji,
        costo:  item.costo,
        fecha:  now.toLocaleDateString('es-MX'),
        hora:   now.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }),
        timestamp: now.toISOString(),
        estado: 'pendiente',
    };
    canjes.push(canje);
    _lsSet('canjes', canjes);
    _lsSet('next_canje_id', id);

    // Descontar puntos en BD con puntos negativos directos
    try {
        const res = await fetch(`${API}/registro.php`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                usuario_id:   uid,
                nombre_libre: `Canje: ${item.nombre}`,
                tipo_comida:  'Merienda',
                porcion:      'Normal',
                hora:         now.toTimeString().slice(0, 5),
                puntos_override: -item.costo,
            }),
        });
        const data = await res.json();
        if (!data.ok) return { ok: false, error: data.error || 'Error al registrar canje' };

        // Leer puntos actualizados desde BD
        const userActualizado = await getUsuario();
        const ptsNuevos = userActualizado.ok ? userActualizado.usuario.puntos : pts - item.costo;

        return { ok: true, canje, puntos: ptsNuevos };
    } catch (e) {
        return { ok: false, error: 'Error de conexión' };
    }
}

    function marcarEntregado(id) {
        const canjes = getCanjes().map(c => c.id === id ? { ...c, estado: 'entregado' } : c);
        _lsSet('canjes', canjes);
    }

    // ── Inicialización ───────────────────────────────────────
    function init() {
        initItems();
    }

    // API pública
    return {
        init,
        today, diasAtras, nombreDia, nombreDiaLargo,
        // Sesión
        getSession, setSession, clearSession, requireAuth,
        // Auth
        login,
        // APIs async (devuelven Promise)
        getDashboard,
        getResumenDia,
        getHistorial,
        addRegistro,
        getUsuario,
        getPuntos,
        getRacha,
        calcMultiplicador,
        // Tienda (localStorage)
        getItems, getItemsActivos, saveItems, addItem, updateItem, toggleItem, deleteItem,
        getCanjes, getCanjesPendientes, canjear, marcarEntregado,
    };
})();

NTStore.init();