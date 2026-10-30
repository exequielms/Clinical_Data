<?php
// traslados.php
// Módulo 2: traslados de ambulancias, y los datos que usa
// (ambulancias, personal y rutas). Solo Administrador y Coordinador.
require_once "conexion.php";
pedir_rol(array("Administrador", "Coordinador"));

$accion = isset($_GET["accion"]) ? $_GET["accion"] : "";
$tipos_validos = array("Paciente", "Material biológico", "Equipamiento médico", "Insumo");

// pasa lo que manda un input datetime-local a formato de MySQL
// devuelve "" si está vacío y false si no es una fecha
function arreglar_fecha($texto) {
    $texto = trim(str_replace("T", " ", $texto));
    if ($texto == "") {
        return "";
    }
    $segundos = strtotime($texto);
    if ($segundos === false) {
        return false;
    }
    return date("Y-m-d H:i:s", $segundos);
}

function anotar_seguimiento($id_traslado, $estado, $comentario) {
    ejecutar("INSERT INTO seguimiento (id_traslado, estado, fecha_hora, comentario, id_usuario) VALUES (?, ?, NOW(), ?, ?)",
             "issi", array($id_traslado, $estado, $comentario, $_SESSION["id_usuario"]));
}


// ---------------- TRASLADOS ----------------

if ($accion == "listar") {
    $estado = isset($_GET["estado"]) ? $_GET["estado"] : "";
    $sql = "SELECT t.id_traslado, t.tipo_elemento, t.elemento, t.origen, t.destino, t.estado,
                   t.hora_salida_prevista, t.hora_llegada_estimada, a.patente,
                   CONCAT(c.nombre, ' ', c.apellido) AS conductor
            FROM traslados t
            JOIN ambulancias a ON a.id_ambulancia = t.id_ambulancia
            JOIN personal c ON c.id_personal = t.id_conductor";
    if ($estado != "") {
        $filas = consultar($sql . " WHERE t.estado = ? ORDER BY t.hora_salida_prevista DESC", "s", array($estado));
    } else {
        $filas = consultar($sql . " ORDER BY t.hora_salida_prevista DESC");
    }
    responder(array("traslados" => $filas));
}

if ($accion == "detalle") {
    $id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
    $filas = consultar("SELECT t.*, a.patente, a.modelo,
                               CONCAT(c.nombre, ' ', c.apellido) AS conductor,
                               CONCAT(p.nombre, ' ', p.apellido) AS copiloto,
                               r.nombre AS ruta,
                               CONCAT(u.nombre, ' ', u.apellido) AS registrado_por
                        FROM traslados t
                        JOIN ambulancias a ON a.id_ambulancia = t.id_ambulancia
                        JOIN personal c ON c.id_personal = t.id_conductor
                        LEFT JOIN personal p ON p.id_personal = t.id_copiloto
                        LEFT JOIN rutas r ON r.id_ruta = t.id_ruta
                        JOIN usuarios u ON u.id_usuario = t.id_usuario
                        WHERE t.id_traslado = ?", "i", array($id));
    if (count($filas) == 0) {
        fallar("El traslado no existe.");
    }
    $historial = consultar("SELECT s.estado, s.fecha_hora, s.comentario, CONCAT(u.nombre, ' ', u.apellido) AS usuario
                            FROM seguimiento s
                            JOIN usuarios u ON u.id_usuario = s.id_usuario
                            WHERE s.id_traslado = ?
                            ORDER BY s.id_seguimiento", "i", array($id));
    responder(array("traslado" => $filas[0], "historial" => $historial));
}

if ($accion == "crear") {
    $d = leer_body();
    $tipo = isset($d["tipo_elemento"]) ? $d["tipo_elemento"] : "";
    $elemento = isset($d["elemento"]) ? trim($d["elemento"]) : "";
    $origen = isset($d["origen"]) ? trim($d["origen"]) : "";
    $destino = isset($d["destino"]) ? trim($d["destino"]) : "";
    $id_ambulancia = isset($d["id_ambulancia"]) ? (int) $d["id_ambulancia"] : 0;
    $id_conductor = isset($d["id_conductor"]) ? (int) $d["id_conductor"] : 0;
    $id_copiloto = isset($d["id_copiloto"]) ? (int) $d["id_copiloto"] : 0;
    $id_ruta = isset($d["id_ruta"]) ? (int) $d["id_ruta"] : 0;
    $observaciones = isset($d["observaciones"]) ? trim($d["observaciones"]) : "";
    $salida = arreglar_fecha(isset($d["hora_salida_prevista"]) ? $d["hora_salida_prevista"] : "");
    $llegada = arreglar_fecha(isset($d["hora_llegada_estimada"]) ? $d["hora_llegada_estimada"] : "");

    if (!in_array($tipo, $tipos_validos)) {
        fallar("Elija qué se va a trasladar.");
    }
    if ($elemento == "" || $origen == "" || $destino == "") {
        fallar("Complete el elemento a trasladar, el origen y el destino.");
    }
    if ($salida === "" || $salida === false) {
        fallar("La hora de salida prevista no es válida.");
    }
    if ($llegada === false) {
        fallar("La hora de llegada estimada no es válida.");
    }
    if ($llegada != "" && $llegada < $salida) {
        fallar("La llegada estimada no puede ser antes de la salida.");
    }

    $amb = consultar("SELECT estado FROM ambulancias WHERE id_ambulancia = ?", "i", array($id_ambulancia));
    if (count($amb) == 0) {
        fallar("Elija una ambulancia.");
    }
    if ($amb[0]["estado"] == "Mantenimiento") {
        fallar("Esa ambulancia está en mantenimiento.");
    }
    if (contar("SELECT COUNT(*) AS total FROM personal WHERE id_personal = ? AND cargo = 'Conductor'", "i", array($id_conductor)) == 0) {
        fallar("Elija un conductor.");
    }
    if ($id_copiloto > 0) {
        if ($id_copiloto == $id_conductor) {
            fallar("El acompañante no puede ser el mismo que el conductor.");
        }
        if (contar("SELECT COUNT(*) AS total FROM personal WHERE id_personal = ?", "i", array($id_copiloto)) == 0) {
            fallar("El acompañante elegido no existe.");
        }
    }
    if ($id_ruta > 0 && contar("SELECT COUNT(*) AS total FROM rutas WHERE id_ruta = ?", "i", array($id_ruta)) == 0) {
        fallar("La ruta elegida no existe.");
    }

    // NULLIF convierte el 0 en NULL para las columnas opcionales
    $id = ejecutar("INSERT INTO traslados (id_ambulancia, id_conductor, id_copiloto, id_ruta, tipo_elemento, elemento, origen, destino,
                                           hora_salida_prevista, hora_llegada_estimada, observaciones, fecha_creacion, id_usuario)
                    VALUES (?, ?, NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, ?, ?, NULLIF(?, ''), ?, NOW(), ?)",
                   "iiiisssssssi",
                   array($id_ambulancia, $id_conductor, $id_copiloto, $id_ruta, $tipo, $elemento, $origen, $destino,
                         $salida, $llegada, $observaciones, $_SESSION["id_usuario"]));
    anotar_seguimiento($id, "Pendiente", "Traslado registrado");
    responder(array("id" => $id, "mensaje" => "Traslado registrado correctamente."));
}

if ($accion == "cambiar_estado") {
    $d = leer_body();
    $id = isset($d["id"]) ? (int) $d["id"] : 0;
    $nuevo = isset($d["estado"]) ? $d["estado"] : "";
    $comentario = isset($d["comentario"]) ? mb_substr(trim($d["comentario"]), 0, 300) : "";

    $filas = consultar("SELECT * FROM traslados WHERE id_traslado = ?", "i", array($id));
    if (count($filas) == 0) {
        fallar("El traslado no existe.");
    }
    $t = $filas[0];
    $actual = $t["estado"];

    // el recorrido siempre sigue este orden
    $siguiente = array("Pendiente" => "En viaje", "En viaje" => "En destino", "En destino" => "Regresando", "Regresando" => "Finalizado");
    $es_el_que_sigue = isset($siguiente[$actual]) && $siguiente[$actual] == $nuevo;
    $es_cancelacion = $nuevo == "Cancelado" && ($actual == "Pendiente" || $actual == "En viaje");
    if (!$es_el_que_sigue && !$es_cancelacion) {
        fallar("No se puede pasar de '" . $actual . "' a '" . $nuevo . "'.");
    }

    if ($nuevo == "En viaje") {
        $amb = consultar("SELECT estado FROM ambulancias WHERE id_ambulancia = ?", "i", array($t["id_ambulancia"]));
        if ($amb[0]["estado"] != "Disponible") {
            fallar("La ambulancia no está disponible (estado: " . $amb[0]["estado"] . ").");
        }
        $copiloto = $t["id_copiloto"] == null ? 0 : (int) $t["id_copiloto"];
        $ocupados = contar("SELECT COUNT(*) AS total FROM traslados
                            WHERE id_traslado <> ? AND estado IN ('En viaje', 'En destino', 'Regresando')
                            AND (id_conductor IN (?, ?) OR id_copiloto IN (?, ?))",
                           "iiiii", array($id, $t["id_conductor"], $copiloto, $t["id_conductor"], $copiloto));
        if ($ocupados > 0) {
            fallar("El conductor o el acompañante están en otro traslado en curso.");
        }
        ejecutar("UPDATE traslados SET estado = 'En viaje', hora_salida_real = NOW() WHERE id_traslado = ?", "i", array($id));
        ejecutar("UPDATE ambulancias SET estado = 'En servicio' WHERE id_ambulancia = ?", "i", array($t["id_ambulancia"]));
    }
    if ($nuevo == "En destino") {
        ejecutar("UPDATE traslados SET estado = 'En destino', hora_llegada_real = NOW() WHERE id_traslado = ?", "i", array($id));
    }
    if ($nuevo == "Regresando") {
        ejecutar("UPDATE traslados SET estado = 'Regresando' WHERE id_traslado = ?", "i", array($id));
    }
    if ($nuevo == "Finalizado") {
        ejecutar("UPDATE traslados SET estado = 'Finalizado', hora_regreso = NOW() WHERE id_traslado = ?", "i", array($id));
        ejecutar("UPDATE ambulancias SET estado = 'Disponible' WHERE id_ambulancia = ?", "i", array($t["id_ambulancia"]));
    }
    if ($nuevo == "Cancelado") {
        ejecutar("UPDATE traslados SET estado = 'Cancelado' WHERE id_traslado = ?", "i", array($id));
        ejecutar("UPDATE ambulancias SET estado = 'Disponible' WHERE id_ambulancia = ? AND estado = 'En servicio'", "i", array($t["id_ambulancia"]));
    }

    anotar_seguimiento($id, $nuevo, $comentario);
    responder(array("mensaje" => "Estado actualizado: " . $nuevo));
}

if ($accion == "eliminar_traslado") {
    $d = leer_body();
    $id = isset($d["id"]) ? (int) $d["id"] : 0;
    $filas = consultar("SELECT estado FROM traslados WHERE id_traslado = ?", "i", array($id));
    if (count($filas) == 0) {
        fallar("El traslado no existe.");
    }
    if ($filas[0]["estado"] != "Pendiente" && $filas[0]["estado"] != "Cancelado") {
        fallar("Solo se pueden eliminar traslados pendientes o cancelados.");
    }
    ejecutar("DELETE FROM traslados WHERE id_traslado = ?", "i", array($id));
    responder(array("mensaje" => "Traslado eliminado."));
}


// ---------------- AMBULANCIAS, PERSONAL Y RUTAS ----------------

if ($accion == "recursos") {
    responder(array(
        "ambulancias" => consultar("SELECT * FROM ambulancias ORDER BY patente"),
        "personal" => consultar("SELECT * FROM personal ORDER BY apellido, nombre"),
        "rutas" => consultar("SELECT * FROM rutas ORDER BY nombre")
    ));
}

if ($accion == "ambulancia_guardar") {
    $d = leer_body();
    $patente = isset($d["patente"]) ? strtoupper(trim($d["patente"])) : "";
    $modelo = isset($d["modelo"]) ? trim($d["modelo"]) : "";
    if ($patente == "" || $modelo == "") {
        fallar("Complete la patente y el modelo.");
    }
    if (contar("SELECT COUNT(*) AS total FROM ambulancias WHERE patente = ?", "s", array($patente)) > 0) {
        fallar("Ya hay una ambulancia con esa patente.");
    }
    ejecutar("INSERT INTO ambulancias (patente, modelo) VALUES (?, ?)", "ss", array($patente, $modelo));
    responder(array("mensaje" => "Ambulancia guardada."));
}

if ($accion == "ambulancia_estado") {
    $d = leer_body();
    $id = isset($d["id"]) ? (int) $d["id"] : 0;
    $estado = isset($d["estado"]) ? $d["estado"] : "";
    if ($estado != "Disponible" && $estado != "Mantenimiento") {
        fallar("Estado no válido.");
    }
    $filas = consultar("SELECT estado FROM ambulancias WHERE id_ambulancia = ?", "i", array($id));
    if (count($filas) == 0) {
        fallar("La ambulancia no existe.");
    }
    if ($filas[0]["estado"] == "En servicio") {
        fallar("La ambulancia está en un traslado. Su estado cambia solo al terminar el recorrido.");
    }
    ejecutar("UPDATE ambulancias SET estado = ? WHERE id_ambulancia = ?", "si", array($estado, $id));
    responder(array("mensaje" => "Estado de la ambulancia actualizado."));
}

if ($accion == "ambulancia_eliminar") {
    $d = leer_body();
    $id = isset($d["id"]) ? (int) $d["id"] : 0;
    if (contar("SELECT COUNT(*) AS total FROM traslados WHERE id_ambulancia = ?", "i", array($id)) > 0) {
        fallar("No se puede eliminar: la ambulancia tiene traslados registrados.");
    }
    ejecutar("DELETE FROM ambulancias WHERE id_ambulancia = ?", "i", array($id));
    responder(array("mensaje" => "Ambulancia eliminada."));
}

if ($accion == "personal_guardar") {
    $d = leer_body();
    $nombre = isset($d["nombre"]) ? trim($d["nombre"]) : "";
    $apellido = isset($d["apellido"]) ? trim($d["apellido"]) : "";
    $documento = isset($d["documento"]) ? trim($d["documento"]) : "";
    $cargo = isset($d["cargo"]) ? $d["cargo"] : "";
    $telefono = isset($d["telefono"]) ? trim($d["telefono"]) : "";
    if ($nombre == "" || $apellido == "" || $documento == "") {
        fallar("Complete nombre, apellido y documento.");
    }
    if (!in_array($cargo, array("Conductor", "Enfermera", "Auxiliar"))) {
        fallar("Elija un cargo.");
    }
    if (contar("SELECT COUNT(*) AS total FROM personal WHERE documento = ?", "s", array($documento)) > 0) {
        fallar("Ya hay una persona con ese documento.");
    }
    ejecutar("INSERT INTO personal (nombre, apellido, documento, cargo, telefono) VALUES (?, ?, ?, ?, ?)",
             "sssss", array($nombre, $apellido, $documento, $cargo, $telefono));
    responder(array("mensaje" => "Persona guardada."));
}

if ($accion == "personal_eliminar") {
    $d = leer_body();
    $id = isset($d["id"]) ? (int) $d["id"] : 0;
    if (contar("SELECT COUNT(*) AS total FROM traslados WHERE id_conductor = ? OR id_copiloto = ?", "ii", array($id, $id)) > 0) {
        fallar("No se puede eliminar: la persona figura en traslados registrados.");
    }
    ejecutar("DELETE FROM personal WHERE id_personal = ?", "i", array($id));
    responder(array("mensaje" => "Persona eliminada."));
}

if ($accion == "ruta_guardar") {
    $d = leer_body();
    $nombre = isset($d["nombre"]) ? trim($d["nombre"]) : "";
    $origen = isset($d["origen"]) ? trim($d["origen"]) : "";
    $destino = isset($d["destino"]) ? trim($d["destino"]) : "";
    $km = isset($d["distancia_km"]) ? (float) $d["distancia_km"] : 0;
    $minutos = isset($d["tiempo_min"]) ? (int) $d["tiempo_min"] : 0;
    if ($nombre == "" || $origen == "" || $destino == "") {
        fallar("Complete nombre, origen y destino.");
    }
    if ($km <= 0 || $minutos <= 0) {
        fallar("La distancia y el tiempo tienen que ser mayores a cero.");
    }
    ejecutar("INSERT INTO rutas (nombre, origen, destino, distancia_km, tiempo_min) VALUES (?, ?, ?, ?, ?)",
             "sssdi", array($nombre, $origen, $destino, $km, $minutos));
    responder(array("mensaje" => "Ruta guardada."));
}

if ($accion == "ruta_eliminar") {
    $d = leer_body();
    $id = isset($d["id"]) ? (int) $d["id"] : 0;
    // los traslados que la usaban quedan sin ruta (ON DELETE SET NULL)
    ejecutar("DELETE FROM rutas WHERE id_ruta = ?", "i", array($id));
    responder(array("mensaje" => "Ruta eliminada."));
}

fallar("Acción no reconocida.");
