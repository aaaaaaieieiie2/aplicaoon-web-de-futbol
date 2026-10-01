/* ============================================================
db.js — CAPA DE DATOS MySQL (XAMPP) ✔ MIGRADO + FIX CACHÉ
La migración de localStorage corre UNA SOLA VEZ (evita que los
padres borrados "revivan" y que los datos no se borren bien).
============================================================ */
window.DB = (function () {
  'use strict';
  const API = 'api/api.php?op=';

  const cache = {
    usuarios: [], evaluaciones: [], asistencias: [], encuestas: [],
    playerStats: [], trofeosCustom: [], trofeosOcultos: [], trofeosEditados: {},
    evalGlobal: null, textos: {}, plantillas: {}, staff: null, torneo: null,
    categorias: null, jugadoresReciclados: [], pagosClub: [], comprobantes: [],
    pagosPadre: [], pagosPlataforma: []
  };

  function post(op, payload) {
    return fetch(API + op, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload || {})
    }).then(r => r.json()).then(res => {
      if (res && res.error) console.error('[DB MySQL] ' + op + ': ' + res.error);
      return res;
    }).catch(e => console.error('[DB MySQL] fallo de red en ' + op, e));
  }

  const listo = fetch('api/bootstrap.php')
    .then(r => r.json())
    .then(data => {
      if (data && data.error) { console.error('[DB MySQL] ' + data.error); return; }
      Object.keys(cache).forEach(k => { if (data[k] !== undefined) cache[k] = data[k]; });
      migrarLegacy();
    })
    .catch(e => console.error('[DB MySQL] No se pudo cargar. Visita api/install.php una vez.', e));

  function L(k) { try { const r = localStorage.getItem('nico_' + k); return r ? JSON.parse(r) : null; } catch (e) { return null; } }

  /* 🐬 FIX: solo migra UNA VEZ. Después manda MySQL al 100%. */
  function migrarLegacy() {
    if (localStorage.getItem('nico_migrado_ok')) return;
    const tex = L('textos');
    if (tex) Object.keys(tex).forEach(k => { cache.textos[k] = tex[k]; post('textos/guardar', { clave: k, html: tex[k] }); });
    const pla = L('plantillas');
    if (pla) Object.keys(pla).forEach(cat => {
      if (Array.isArray(pla[cat]) && pla[cat].length) {
        cache.plantillas[cat] = normalizarPlantilla(cat, pla[cat]);
        post('plantillas/guardar', { catKey: cat, lista: cache.plantillas[cat] });
      }
    });
    const tor = L('torneo');     if (tor && !cache.torneo)     { cache.torneo = tor;      post('documentos/guardar', { key: 'torneo', value: tor }); }
    const eg  = L('evalGlobal'); if (eg && !cache.evalGlobal)  { cache.evalGlobal = eg;   post('documentos/guardar', { key: 'eval_global', value: eg }); }
    const st  = L('staff');      if (st && st.length && !cache.staff)      { cache.staff = st;      post('staff/guardar', { lista: st }); }
    const ct  = L('categorias'); if (ct && ct.length && !cache.categorias) { cache.categorias = ct; post('categorias/guardar', { lista: ct }); }
    const us  = L('usuarios');   if (us && us.length) us.forEach(u => {
      if (!cache.usuarios.find(x => x.id === u.id)) cache.usuarios.push(u);
      post('usuarios/guardar', u);
    });
    const soloSiVacio = (clave, arr, fn) => { const leg = L(clave); if (leg && leg.length && !arr.length) { cache[clave] = leg; leg.forEach(fn); } };
    soloSiVacio('evaluaciones', cache.evaluaciones, x => post('evaluaciones/guardar', x));
    soloSiVacio('asistencias', cache.asistencias, x => post('asistencias/guardar', x));
    soloSiVacio('encuestas', cache.encuestas, x => post('encuestas/guardar', x));
    soloSiVacio('playerStats', cache.playerStats, x => post('stats/guardar', x));
    soloSiVacio('jugadoresReciclados', cache.jugadoresReciclados, x => post('reciclados/guardar', x));
    soloSiVacio('pagosClub', cache.pagosClub, x => post('pagosClub/agregar', x));
    soloSiVacio('comprobantes', cache.comprobantes, x => post('comprobantes/agregar', x));
    soloSiVacio('pagosPadre', cache.pagosPadre, x => post('pagosPadre/agregar', x));
    soloSiVacio('pagosPlataforma', cache.pagosPlataforma, x => post('pagosPlataforma/agregar', x));
    const tc = L('trofeosCustom'); if (tc && tc.length && !cache.trofeosCustom.length) { cache.trofeosCustom = tc; tc.forEach(t => post('trofeosCustom/guardar', t)); }
    const to = L('trofeosOcultos'); if (to && to.length && !cache.trofeosOcultos.length) { cache.trofeosOcultos = to; to.forEach(id => post('trofeosOcultos/agregar', { id })); }
    const te = L('trofeosEditados'); if (te && Object.keys(te).length && !Object.keys(cache.trofeosEditados).length) { cache.trofeosEditados = te; Object.keys(te).forEach(id => post('trofeosEditados/guardar', te[id])); }
    localStorage.setItem('nico_migrado_ok', '1');
  }

  function login(usuario, password) {
    return cache.usuarios.find(u => u.usuario === usuario && u.password === password) || null;
  }
  
  function guardarEvaluacion(ev) {
    const t = [...cache.evaluaciones];
    const i = t.findIndex(x => x.catKey === ev.catKey && x.playerId === ev.playerId);
    ev.fecha = new Date().toLocaleDateString('es-PA');
    if (i >= 0) t[i] = ev; else t.push(ev);
    cache.evaluaciones = t;
    post('evaluaciones/guardar', ev);
  }
  
  function obtenerEvaluacion(catKey, playerId) {
    return cache.evaluaciones.find(x => x.catKey === catKey && x.playerId === playerId) || null;
  }
  
  function marcarAsistencia(reg) {
    const t = [...cache.asistencias];
    const i = t.findIndex(x => x.catKey === reg.catKey && x.fecha === reg.fecha && x.playerId === reg.playerId);
    if (i >= 0) t[i] = reg; else t.push(reg);
    cache.asistencias = t;
    post('asistencias/guardar', reg);
  }
  
  function quitarAsistencia(catKey, fecha, playerId) {
    cache.asistencias = cache.asistencias.filter(x => !(x.catKey === catKey && x.fecha === fecha && x.playerId === playerId));
    post('asistencias/quitar', { catKey, fecha, playerId });
  }
  
  function obtenerAsistencias(catKey, playerId) {
    return cache.asistencias.filter(x => x.catKey === catKey && x.playerId === playerId);
  }
  
  function guardarEncuesta(enc) {
    enc.fecha = new Date().toLocaleDateString('es-PA');
    cache.encuestas = [...cache.encuestas, enc];
    post('encuestas/guardar', enc);
  }
  
  function obtenerEncuestas() { return cache.encuestas; }
  
  function registrarPadre(nombre, usuario, password, hijoCat, hijoId) {
    if (cache.usuarios.find(u => u.usuario === usuario)) return { ok: false, msg: 'El usuario ya existe' };
    const maxId = cache.usuarios.length > 0 ? Math.max(...cache.usuarios.map(u => u.id)) : 0;
    const nuevoId = Math.max(10, maxId) + 1;
    const nuevo = { id: nuevoId, usuario, password, rol: 'padre', nombre, hijoCat: hijoCat || null, hijoId: hijoId || null };
    
    // FIX CACHÉ: Reasignación directa del array para que Alpine detecte el cambio instantáneamente
    cache.usuarios = [...cache.usuarios, nuevo];
    post('usuarios/guardar', nuevo);
    return { ok: true, msg: 'Cuenta familiar creada y vinculada' };
  }
  
  function obtenerPadres() { return cache.usuarios.filter(u => u.rol === 'padre'); }
  
  function eliminarPadre(id) {
    cache.usuarios = cache.usuarios.filter(u => !(u.rol === 'padre' && u.id === id));
    post('usuarios/eliminar', { id, rol: 'padre' });
    return true;
  }
  
  function actualizarPadre(id, data) {
    const t = [...cache.usuarios];
    const i = t.findIndex(x => x.rol === 'padre' && x.id === id);
    if (i >= 0) { 
      t[i] = Object.assign({}, t[i], data); 
      cache.usuarios = t;
      post('usuarios/guardar', t[i]); 
    }
    return true;
  }
  
  function actualizarStats(catKey, playerId, stats) {
    const t = [...cache.playerStats];
    const i = t.findIndex(x => x.catKey === catKey && x.playerId === playerId);
    const reg = { catKey, playerId, goles: stats.goles, asistencias: stats.asistencias, mvp: stats.mvp, updatedAt: new Date().toISOString() };
    if (i >= 0) t[i] = reg; else t.push(reg);
    cache.playerStats = t;
    post('stats/guardar', reg);
  }
  
  function obtenerStats(catKey, playerId) {
    return cache.playerStats.find(x => x.catKey === catKey && x.playerId === playerId) || null;
  }
  
  function obtenerTrofeosCustom() { return cache.trofeosCustom; }
  
  function guardarTrofeoCustom(t) { 
    cache.trofeosCustom = [...cache.trofeosCustom, t]; 
    post('trofeosCustom/guardar', t); 
  }
  
  function eliminarTrofeoCustom(id) { 
    cache.trofeosCustom = cache.trofeosCustom.filter(t => t.id !== id); 
    post('trofeosCustom/eliminar', { id }); 
  }
  
  function obtenerTrofeosOcultos() { return cache.trofeosOcultos; }
  
  function ocultarTrofeoBase(id) { 
    if (!cache.trofeosOcultos.includes(id)) { 
      cache.trofeosOcultos = [...cache.trofeosOcultos, id]; 
      post('trofeosOcultos/agregar', { id }); 
    } 
  }
  
  function obtenerTrofeosEditados() { return cache.trofeosEditados || {}; }
  
  function guardarTrofeoEditado(t) { 
    cache.trofeosEditados = Object.assign({}, cache.trofeosEditados, { [t.id]: t });
    post('trofeosEditados/guardar', t); 
  }
  
  function obtenerEvalGlobal() { return cache.evalGlobal; }
  function guardarEvalGlobal(lista) { cache.evalGlobal = lista; post('documentos/guardar', { key: 'eval_global', value: lista }); }
  function obtenerTextos() { return cache.textos || {}; }
  function guardarTexto(clave, html) { cache.textos[clave] = html; post('textos/guardar', { clave, html }); }
  
  function normalizarPlantilla(catKey, lista) {
    if (!Array.isArray(lista)) return [];
    const vistos = new Map();
    lista.forEach(p => {
      const clave = String(p.dorsal);
      const prev = vistos.get(clave);
      if (!prev) { vistos.set(clave, p); return; }
      if ((p.foto && !prev.foto) || (p.id && !prev.id)) vistos.set(clave, p);
    });
    return Array.from(vistos.values()).map(p => ({ ...p, catKey: catKey, foto: p.foto || null }));
  }
  
  function obtenerPlantilla(catKey) {
    const lista = cache.plantillas[catKey] || null;
    return lista ? normalizarPlantilla(catKey, lista) : null;
  }
  
  function guardarPlantilla(catKey, lista) {
    cache.plantillas = Object.assign({}, cache.plantillas, { [catKey]: normalizarPlantilla(catKey, lista) });
    post('plantillas/guardar', { catKey, lista: cache.plantillas[catKey] });
  }
  
  function obtenerStaff() { return cache.staff; }
  function guardarStaff(lista) { cache.staff = lista; post('staff/guardar', { lista }); }
  function obtenerTorneo() { return cache.torneo; }
  function guardarTorneo(data) { cache.torneo = data; post('documentos/guardar', { key: 'torneo', value: data }); }
  function obtenerCategorias() { return cache.categorias; }
  function guardarCategorias(lista) { cache.categorias = lista; post('categorias/guardar', { lista }); }
  
  function upsertCategoria(cat) {
    const lista = [...(cache.categorias || [])];
    const i = lista.findIndex(c => c.key === cat.key || (cat.id && c.id === cat.id));
    if (i >= 0) lista[i] = Object.assign({}, lista[i], cat); else lista.push(cat);
    guardarCategorias(lista);
    return lista;
  }
  
  function eliminarCategoria(key) {
    guardarCategorias((cache.categorias || []).filter(c => c.key !== key));
    return true;
  }
  
  function reordenarCategorias(ordenKeys) {
    const lista = cache.categorias || [];
    const ordenada = ordenKeys.map(k => lista.find(c => c.key === k)).filter(Boolean);
    lista.forEach(c => { if (!ordenada.some(o => o.key === c.key)) ordenada.push(c); });
    guardarCategorias(ordenada);
    return ordenada;
  }
  
  function guardarJugadorReciclado(player) {
    const copia = Object.assign({}, player, { guardadoEn: new Date().toISOString() });
    const l = [...cache.jugadoresReciclados];
    const i = l.findIndex(x => x.catKey === player.catKey && String(x.dorsal) === String(player.dorsal));
    if (i >= 0) l[i] = copia; else l.push(copia);
    cache.jugadoresReciclados = l;
    post('reciclados/guardar', copia);
  }
  
  function obtenerJugadorReciclado(catKey, dorsal) {
    return cache.jugadoresReciclados.find(x => x.catKey === catKey && String(x.dorsal) === String(dorsal)) || null;
  }
  
  function quitarJugadorReciclado(catKey, dorsal) {
    cache.jugadoresReciclados = cache.jugadoresReciclados.filter(x => !(x.catKey === catKey && String(x.dorsal) === String(dorsal)));
    post('reciclados/quitar', { catKey, dorsal });
  }
  
  function registrarEntrenador(data) {
    if (cache.usuarios.find(u => u.usuario === data.usuario)) return { ok: false, msg: 'El usuario ya existe' };
    const maxId = cache.usuarios.length > 0 ? Math.max(...cache.usuarios.map(u => u.id)) : 0;
    const nuevoId = Math.max(10, maxId) + 1;
    const nuevo = { id: nuevoId, usuario: data.usuario, password: data.password, rol: 'entrenador', nombre: data.nombre, catKey: data.catKey || null, catLabel: data.catLabel || '' };
    cache.usuarios = [...cache.usuarios, nuevo];
    post('usuarios/guardar', nuevo);
    return { ok: true, msg: 'Entrenador registrado correctamente' };
  }
  
  function obtenerEntrenadores() { return cache.usuarios.filter(u => u.rol === 'entrenador'); }
  
  function editarEntrenador(id, data) {
    const t = [...cache.usuarios];
    const i = t.findIndex(x => x.rol === 'entrenador' && x.id === id);
    if (i >= 0) { 
      t[i] = Object.assign({}, t[i], data); 
      cache.usuarios = t;
      post('usuarios/guardar', t[i]); 
    }
    return true;
  }
  
  function eliminarEntrenador(id) {
    cache.usuarios = cache.usuarios.filter(u => !(u.rol === 'entrenador' && u.id === id));
    post('usuarios/eliminar', { id, rol: 'entrenador' });
    return true;
  }
  
  function guardarPagoClub(pago) {
    pago.id = pago.id || Date.now();
    cache.pagosClub = [...cache.pagosClub, pago];
    post('pagosClub/agregar', pago);
  }
  
  function obtenerPagosClub() { return cache.pagosClub; }
  
  function guardarComprobante(c) {
    c.id = Date.now(); c.estado = 'pendiente';
    cache.comprobantes = [...cache.comprobantes, c];
    post('comprobantes/agregar', c);
  }
  
  function obtenerComprobantes() { return cache.comprobantes; }
  
  function validarComprobante(id, aprobar) {
    const t = [...cache.comprobantes];
    const i = t.findIndex(c => c.id === id);
    if (i < 0) return false;
    
    const validacionData = Object.assign({}, t[i], { estado: aprobar ? 'validado' : 'rechazado' });
    t[i] = validacionData;
    cache.comprobantes = t;
    
    if (aprobar) {
      const pago = { id: Date.now(), padreId: t[i].padreId, padreNombre: t[i].padreNombre, concepto: t[i].concepto, monto: t[i].monto, fecha: t[i].fecha, referencia: t[i].referencia || '', metodo: 'digital', estado: 'validado' };
      cache.pagosClub = [...cache.pagosClub, pago];
      
      const pPadres = [...cache.pagosPadre];
      const j = pPadres.findIndex(x => String(x.padreId) === String(t[i].padreId) && x.referencia === t[i].referencia && x.estado === 'pendiente');
      if (j >= 0) {
        pPadres[j] = Object.assign({}, pPadres[j], { estado: 'validado' });
        cache.pagosPadre = pPadres;
      }
      post('comprobantes/validar', { id, aprobar: true, pago });
    } else {
      post('comprobantes/validar', { id, aprobar: false });
    }
    return true;
  }
  
  function guardarPagoPadre(padreId, pago) {
    pago.id = Date.now(); pago.padreId = padreId; pago.estado = pago.estado || 'pendiente';
    cache.pagosPadre = [...cache.pagosPadre, pago];
    post('pagosPadre/agregar', pago);
  }
  
  function obtenerPagosPadre(padreId) {
    return cache.pagosPadre.filter(p => String(p.padreId) === String(padreId));
  }
  
  function guardarPagoPlataforma(p) {
    p.id = Date.now(); p.estado = 'pendiente';
    cache.pagosPlataforma = [...cache.pagosPlataforma, p];
    post('pagosPlataforma/agregar', p);
  }
  
  function obtenerPagosPlataforma() { return cache.pagosPlataforma; }

  return {
    listo, login, quitarAsistencia,
    guardarEvaluacion, obtenerEvaluacion,
    marcarAsistencia, obtenerAsistencias,
    guardarEncuesta, obtenerEncuestas,
    registrarPadre, obtenerPadres, eliminarPadre, actualizarPadre,
    actualizarStats, obtenerStats,
    obtenerTrofeosCustom, guardarTrofeoCustom, eliminarTrofeoCustom,
    obtenerTrofeosOcultos, ocultarTrofeoBase,
    obtenerTrofeosEditados, guardarTrofeoEditado,
    obtenerEvalGlobal, guardarEvalGlobal,
    obtenerTextos, guardarTexto,
    normalizarPlantilla, obtenerPlantilla, guardarPlantilla,
    obtenerStaff, guardarStaff,
    obtenerTorneo, guardarTorneo,
    obtenerCategorias, guardarCategorias, upsertCategoria, eliminarCategoria, reordenarCategorias,
    guardarJugadorReciclado, obtenerJugadorReciclado, quitarJugadorReciclado,
    registrarEntrenador, obtenerEntrenadores, editarEntrenador, eliminarEntrenador,
    guardarPagoClub, obtenerPagosClub,
    guardarComprobante, obtenerComprobantes, validarComprobante,
    guardarPagoPadre, obtenerPagosPadre,
    guardarPagoPlataforma, obtenerPagosPlataforma
  };
})();