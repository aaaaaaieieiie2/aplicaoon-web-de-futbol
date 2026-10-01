<?php
/* api/api.php — Endpoint de escrituras: POST api/api.php?op=... */
require __DIR__ . '/conexion.php';
$d = entrada(); $db = db();
$op = isset($_GET['op']) ? $_GET['op'] : '';

switch ($op) {

case 'usuarios/guardar':
    q("REPLACE INTO usuarios (id,usuario,password,rol,nombre,hijo_cat,hijo_id,cat_key,cat_label) VALUES (?,?,?,?,?,?,?,?,?)",
      'isssssiss', [(int)$d['id'], $d['usuario'] ?? '', $d['password'] ?? '', $d['rol'] ?? 'padre', $d['nombre'] ?? '',
      $d['hijoCat'] ?? null, isset($d['hijoId']) ? (int)$d['hijoId'] : null, $d['catKey'] ?? null, $d['catLabel'] ?? null]);
    break;

case 'usuarios/eliminar':
    q("DELETE FROM usuarios WHERE id=? AND rol=?", 'is', [(int)$d['id'], $d['rol'] ?? '']);
    break;

case 'evaluaciones/guardar':
    q("REPLACE INTO evaluaciones (cat_key,player_id,fecha,tecnicos,tacticos,fisicos,actitudinales) VALUES (?,?,?,?,?,?,?)",
      'sisssss', [$d['catKey'], (int)$d['playerId'], $d['fecha'] ?? '', json_encode($d['tecnicos'] ?? []),
      json_encode($d['tacticos'] ?? []), json_encode($d['fisicos'] ?? []), json_encode($d['actitudinales'] ?? [])]);
    break;

case 'asistencias/guardar':
    q("REPLACE INTO asistencias (cat_key,fecha,player_id,estado) VALUES (?,?,?,?)",
      'ssis', [$d['catKey'], $d['fecha'], (int)$d['playerId'], $d['estado']]);
    break;

case 'asistencias/quitar':
    q("DELETE FROM asistencias WHERE cat_key=? AND fecha=? AND player_id=?", 'ssi', [$d['catKey'], $d['fecha'], (int)$d['playerId']]);
    break;

case 'encuestas/guardar':
    q("INSERT INTO encuestas (padre,fecha,aspectos,gusta_mas,mejorar,crecimiento,crecimiento_why,recomienda,recomienda_why,observaciones) VALUES (?,?,?,?,?,?,?,?,?,?)",
      'ssssssssss', [$d['padre'] ?? '', $d['fecha'] ?? '', json_encode($d['aspectos'] ?? []), $d['gustaMas'] ?? '',
      $d['mejorar'] ?? '', $d['crecimiento'] ?? '', $d['crecimientoWhy'] ?? '', $d['recomienda'] ?? '',
      $d['recomiendaWhy'] ?? '', $d['observaciones'] ?? '']);
    break;

case 'stats/guardar':
    q("REPLACE INTO player_stats (cat_key,player_id,goles,asistencias,mvp,updated_at) VALUES (?,?,?,?,?,?)",
      'siiiis', [$d['catKey'], (int)$d['playerId'], (int)($d['goles'] ?? 0), (int)($d['asistencias'] ?? 0), (int)($d['mvp'] ?? 0), $d['updatedAt'] ?? '']);
    break;

case 'plantillas/guardar':
    $db->begin_transaction();
    q("DELETE FROM jugadores WHERE cat_key=?", 's', [$d['catKey']]);
    foreach (($d['lista'] ?? []) as $p) {
        q("INSERT INTO jugadores (id,cat_key,name,dorsal,pos,foto) VALUES (?,?,?,?,?,?)",
          'ississ', [(int)$p['id'], $d['catKey'], $p['name'] ?? '', (int)$p['dorsal'], $p['pos'] ?? '', $p['foto'] ?? null]);
    }
    $db->commit();
    break;

case 'trofeosCustom/guardar':
    q("REPLACE INTO trofeos_custom (id,nombre,cat_key,categoria,anio,icono,color,descripcion,evaluacion) VALUES (?,?,?,?,?,?,?,?,?)",
      'sssssssss', [$d['id'], $d['nombre'] ?? '', $d['catKey'] ?? '', $d['categoria'] ?? '', $d['año'] ?? '',
      $d['icono'] ?? 'fa-trophy', $d['color'] ?? 'text-amber-400', $d['descripcion'] ?? '', json_encode($d['evaluacion'] ?? (object)[])]);
    break;

case 'trofeosCustom/eliminar':
    q("DELETE FROM trofeos_custom WHERE id=?", 's', [$d['id']]);
    break;

case 'trofeosOcultos/agregar':
    q("INSERT IGNORE INTO trofeos_ocultos (id) VALUES (?)", 's', [$d['id']]);
    break;

case 'trofeosEditados/guardar':
    q("REPLACE INTO trofeos_editados (id,data) VALUES (?,?)", 'ss', [$d['id'], json_encode($d)]);
    break;

case 'textos/guardar':
    q("REPLACE INTO textos (clave,html) VALUES (?,?)", 'ss', [$d['clave'], $d['html'] ?? '']);
    break;

case 'documentos/guardar':
    if (in_array($d['key'] ?? '', ['torneo', 'eval_global'], true))
        q("REPLACE INTO documentos (doc_key,value) VALUES (?,?)", 'ss', [$d['key'], json_encode($d['value'] ?? null)]);
    break;

case 'staff/guardar':
    $db->begin_transaction();
    q("DELETE FROM staff");
    foreach (($d['lista'] ?? $d ?? []) as $i => $s) {
        if (!is_array($s)) continue;
        q("REPLACE INTO staff (id,nombre,cargo,licencia,exp,especialidad,bio,foto_icon,color,orden) VALUES (?,?,?,?,?,?,?,?,?,?)",
          'issssssssi', [(int)$s['id'], $s['nombre'] ?? '', $s['cargo'] ?? '', $s['licencia'] ?? '', $s['exp'] ?? '',
          $s['especialidad'] ?? '', $s['bio'] ?? '', $s['fotoIcon'] ?? 'fa-user', $s['color'] ?? '', $i + 1]);
    }
    $db->commit();
    break;

case 'categorias/guardar':
    $db->begin_transaction();
    q("DELETE FROM categorias");
    foreach (($d['lista'] ?? []) as $i => $c) {
        q("REPLACE INTO categorias (cat_key,label,descripcion,core,orden) VALUES (?,?,?,?,?)",
          'sssii', [$c['key'], $c['label'] ?? '', $c['descripcion'] ?? '', !empty($c['core']) ? 1 : 0, $i + 1]);
    }
    $db->commit();
    break;

case 'reciclados/guardar':
    q("REPLACE INTO jugadores_reciclados (cat_key,dorsal,data,guardado_en) VALUES (?,?,?,?)",
      'siss', [$d['catKey'], (int)$d['dorsal'], json_encode($d), $d['guardadoEn'] ?? '']);
    break;

case 'reciclados/quitar':
    q("DELETE FROM jugadores_reciclados WHERE cat_key=? AND dorsal=?", 'si', [$d['catKey'], (int)$d['dorsal']]);
    break;

case 'pagosClub/agregar':
    q("REPLACE INTO pagos_club (id,padre_id,padre_nombre,concepto,monto,fecha,referencia,metodo,estado) VALUES (?,?,?,?,?,?,?,?,?)",
      'iissdssss', [(int)$d['id'], (int)($d['padreId'] ?? 0), $d['padreNombre'] ?? '', $d['concepto'] ?? '',
      (float)($d['monto'] ?? 0), $d['fecha'] ?? '', $d['referencia'] ?? '', $d['metodo'] ?? 'efectivo', $d['estado'] ?? 'validado']);
    break;

case 'comprobantes/agregar':
    q("REPLACE INTO comprobantes (id,padre_id,padre_nombre,concepto,monto,fecha,referencia,observaciones,comprobante,estado) VALUES (?,?,?,?,?,?,?,?,?,?)",
      'iissdsssss', [(int)$d['id'], (int)($d['padreId'] ?? 0), $d['padreNombre'] ?? '', $d['concepto'] ?? '',
      (float)($d['monto'] ?? 0), $d['fecha'] ?? '', $d['referencia'] ?? '', $d['observaciones'] ?? '',
      $d['comprobante'] ?? '', $d['estado'] ?? 'pendiente']);
    break;

case 'comprobantes/validar':
    $aprobar = !empty($d['aprobar']);
    q("UPDATE comprobantes SET estado=? WHERE id=?", 'si', [$aprobar ? 'validado' : 'rechazado', (int)$d['id']]);
    if ($aprobar && isset($d['pago'])) {
        $p = $d['pago'];
        q("REPLACE INTO pagos_club (id,padre_id,padre_nombre,concepto,monto,fecha,referencia,metodo,estado) VALUES (?,?,?,?,?,?,?,?,?)",
          'iissdssss', [(int)$p['id'], (int)($p['padreId'] ?? 0), $p['padreNombre'] ?? '', $p['concepto'] ?? '',
          (float)($p['monto'] ?? 0), $p['fecha'] ?? '', $p['referencia'] ?? '', 'digital', 'validado']);
        q("UPDATE pagos_padre SET estado='validado' WHERE padre_id=? AND referencia=? AND estado='pendiente'",
          'is', [(int)($p['padreId'] ?? 0), $p['referencia'] ?? '']);
    }
    break;

case 'pagosPadre/agregar':
    q("REPLACE INTO pagos_padre (id,padre_id,concepto,monto,fecha,referencia,observaciones,comprobante,estado) VALUES (?,?,?,?,?,?,?,?,?)",
      'iisdsssss', [(int)$d['id'], (int)($d['padreId'] ?? 0), $d['concepto'] ?? '', (float)($d['monto'] ?? 0),
      $d['fecha'] ?? '', $d['referencia'] ?? '', $d['observaciones'] ?? '', $d['comprobante'] ?? '', $d['estado'] ?? 'pendiente']);
    break;

case 'pagosPlataforma/agregar':
    q("REPLACE INTO pagos_plataforma (id,tipo,monto,referencia,fecha,observaciones,comprobante,estado) VALUES (?,?,?,?,?,?,?,?)",
      'isdsssss', [(int)$d['id'], $d['tipo'] ?? '', (float)($d['monto'] ?? 0), $d['referencia'] ?? '',
      $d['fecha'] ?? '', $d['observaciones'] ?? '', $d['comprobante'] ?? '', $d['estado'] ?? 'pendiente']);
    break;

default:
    salida(['error' => 'Operación desconocida: ' . $op]);
}
salida(['ok' => true]);