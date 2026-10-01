<?php
/* api/bootstrap.php — Devuelve TODA la data de MySQL en un solo JSON */
require __DIR__ . '/conexion.php';
$out = [];

/* usuarios */
$rows = [];
foreach (consultar("SELECT * FROM usuarios ORDER BY id") as $r) {
    $rows[] = ['id' => (int)$r['id'], 'usuario' => $r['usuario'], 'password' => $r['password'], 'rol' => $r['rol'],
        'nombre' => $r['nombre'], 'hijoCat' => $r['hijo_cat'],
        'hijoId' => $r['hijo_id'] !== null ? (int)$r['hijo_id'] : null,
        'catKey' => $r['cat_key'], 'catLabel' => $r['cat_label']];
}
$out['usuarios'] = $rows;

/* evaluaciones */
$rows = [];
foreach (consultar("SELECT * FROM evaluaciones") as $r) {
    $rows[] = ['catKey' => $r['cat_key'], 'playerId' => (int)$r['player_id'], 'fecha' => $r['fecha'],
        'tecnicos' => json_decode($r['tecnicos'], true) ?: [], 'tacticos' => json_decode($r['tacticos'], true) ?: [],
        'fisicos' => json_decode($r['fisicos'], true) ?: [], 'actitudinales' => json_decode($r['actitudinales'], true) ?: []];
}
$out['evaluaciones'] = $rows;

/* asistencias */
$rows = [];
foreach (consultar("SELECT * FROM asistencias") as $r) {
    $rows[] = ['catKey' => $r['cat_key'], 'fecha' => $r['fecha'], 'playerId' => (int)$r['player_id'], 'estado' => $r['estado']];
}
$out['asistencias'] = $rows;

/* encuestas */
$rows = [];
foreach (consultar("SELECT * FROM encuestas ORDER BY id") as $r) {
    $rows[] = ['padre' => $r['padre'], 'fecha' => $r['fecha'], 'aspectos' => json_decode($r['aspectos'], true) ?: [],
        'gustaMas' => $r['gusta_mas'], 'mejorar' => $r['mejorar'], 'crecimiento' => $r['crecimiento'],
        'crecimientoWhy' => $r['crecimiento_why'], 'recomienda' => $r['recomienda'],
        'recomiendaWhy' => $r['recomienda_why'], 'observaciones' => $r['observaciones']];
}
$out['encuestas'] = $rows;

/* player_stats */
$rows = [];
foreach (consultar("SELECT * FROM player_stats") as $r) {
    $rows[] = ['catKey' => $r['cat_key'], 'playerId' => (int)$r['player_id'], 'goles' => (int)$r['goles'],
        'asistencias' => (int)$r['asistencias'], 'mvp' => (int)$r['mvp'], 'updatedAt' => $r['updated_at']];
}
$out['playerStats'] = $rows;

/* jugadores -> plantillas por categoría */
$map = [];
foreach (consultar("SELECT * FROM jugadores ORDER BY cat_key, dorsal") as $r) {
    $map[$r['cat_key']][] = ['id' => (int)$r['id'], 'catKey' => $r['cat_key'], 'name' => $r['name'],
        'dorsal' => (int)$r['dorsal'], 'pos' => $r['pos'], 'foto' => $r['foto']];
}
$out['plantillas'] = $map;

/* trofeos */
$rows = [];
foreach (consultar("SELECT * FROM trofeos_custom") as $r) {
    $rows[] = ['id' => $r['id'], 'nombre' => $r['nombre'], 'catKey' => $r['cat_key'], 'categoria' => $r['categoria'],
        'año' => $r['anio'], 'icono' => $r['icono'], 'color' => $r['color'], 'descripcion' => $r['descripcion'],
        'evaluacion' => json_decode($r['evaluacion']) ?: (object)[]];
}
$out['trofeosCustom'] = $rows;
$out['trofeosOcultos'] = array_map(fn($r) => $r['id'], consultar("SELECT id FROM trofeos_ocultos"));
$map = [];
foreach (consultar("SELECT * FROM trofeos_editados") as $r) $map[$r['id']] = json_decode($r['data']);
$out['trofeosEditados'] = (object)$map;

/* textos y documentos */
$map = [];
foreach (consultar("SELECT * FROM textos") as $r) $map[$r['clave']] = $r['html'];
$out['textos'] = (object)$map;
$docs = [];
foreach (consultar("SELECT * FROM documentos") as $r) $docs[$r['doc_key']] = $r['value'];
$out['torneo'] = isset($docs['torneo']) ? json_decode($docs['torneo']) : null;
$out['evalGlobal'] = isset($docs['eval_global']) ? json_decode($docs['eval_global']) : null;

/* staff y categorías */
$rows = [];
foreach (consultar("SELECT * FROM staff ORDER BY orden") as $r) {
    $rows[] = ['id' => (int)$r['id'], 'nombre' => $r['nombre'], 'cargo' => $r['cargo'], 'licencia' => $r['licencia'],
        'exp' => $r['exp'], 'especialidad' => $r['especialidad'], 'bio' => $r['bio'],
        'fotoIcon' => $r['foto_icon'], 'color' => $r['color']];
}
$out['staff'] = count($rows) ? $rows : null;
$rows = [];
foreach (consultar("SELECT * FROM categorias ORDER BY orden") as $r) {
    $rows[] = ['key' => $r['cat_key'], 'label' => $r['label'], 'descripcion' => $r['descripcion'], 'core' => (int)$r['core']];
}
$out['categorias'] = count($rows) ? $rows : null;

/* papelera */
$rows = [];
foreach (consultar("SELECT * FROM jugadores_reciclados") as $r) {
    $p = json_decode($r['data']) ?: (object)[]; $p->guardadoEn = $r['guardado_en']; $rows[] = $p;
}
$out['jugadoresReciclados'] = $rows;

/* pagos */
$rows = [];
foreach (consultar("SELECT * FROM pagos_club ORDER BY id") as $r) {
    $rows[] = ['id' => (int)$r['id'], 'padreId' => (int)$r['padre_id'], 'padreNombre' => $r['padre_nombre'],
        'concepto' => $r['concepto'], 'monto' => (float)$r['monto'], 'fecha' => $r['fecha'],
        'referencia' => $r['referencia'], 'metodo' => $r['metodo'], 'estado' => $r['estado']];
}
$out['pagosClub'] = $rows;
$rows = [];
foreach (consultar("SELECT * FROM comprobantes ORDER BY id") as $r) {
    $rows[] = ['id' => (int)$r['id'], 'padreId' => (int)$r['padre_id'], 'padreNombre' => $r['padre_nombre'],
        'concepto' => $r['concepto'], 'monto' => (float)$r['monto'], 'fecha' => $r['fecha'], 'referencia' => $r['referencia'],
        'observaciones' => $r['observaciones'], 'comprobante' => $r['comprobante'], 'estado' => $r['estado']];
}
$out['comprobantes'] = $rows;
$rows = [];
foreach (consultar("SELECT * FROM pagos_padre ORDER BY id") as $r) {
    $rows[] = ['id' => (int)$r['id'], 'padreId' => (int)$r['padre_id'], 'concepto' => $r['concepto'],
        'monto' => (float)$r['monto'], 'fecha' => $r['fecha'], 'referencia' => $r['referencia'],
        'observaciones' => $r['observaciones'], 'comprobante' => $r['comprobante'], 'estado' => $r['estado']];
}
$out['pagosPadre'] = $rows;
$rows = [];
foreach (consultar("SELECT * FROM pagos_plataforma ORDER BY id") as $r) {
    $rows[] = ['id' => (int)$r['id'], 'tipo' => $r['tipo'], 'monto' => (float)$r['monto'], 'referencia' => $r['referencia'],
        'fecha' => $r['fecha'], 'observaciones' => $r['observaciones'], 'comprobante' => $r['comprobante'], 'estado' => $r['estado']];
}
$out['pagosPlataforma'] = $rows;

salida($out);