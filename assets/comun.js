// comun.js
// Funciones que usan todas las páginas: menú lateral, logo, mensajes
// y la función "pedir" para hablar con los archivos PHP.

// ---------- logo ----------
function svgPulso(alto) {
    return '<svg width="' + (alto * 3) + '" height="' + alto + '" viewBox="0 0 150 40" fill="none">' +
        '<path d="M0 20 L45 20 L55 5 L65 35 L75 20 L150 20" stroke="#c0392b" stroke-width="4" ' +
        'stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function logoChico(idElemento) {
    var el = document.getElementById(idElemento);
    if (el) {
        el.innerHTML = '<div class="logo-cds">' + svgPulso(24) + '<span class="logo-texto">Clinical_Data</span></div>';
    }
}

function logoGrande(idElemento) {
    var el = document.getElementById(idElemento);
    if (el) {
        el.innerHTML = '<div class="logo-cds logo-grande">' +
            '<span class="logo-texto">Clinical_Data</span>' + svgPulso(50) +
            '<span class="logo-sub">HOSPITAL DE CLÍNICAS</span></div>';
    }
}

// ---------- íconos del menú ----------
var ICONOS = {
    home: 'M3 10.5 12 3l9 7.5 M5 9.5V21h14V9.5 M9 21v-6h6v6',
    documento: 'M6 3h8l5 5v13H6z M14 3v5h5',
    encuesta: 'M9 5h6a1 1 0 0 1 1 1v1H8V6a1 1 0 0 1 1-1z M8 5H6a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-2 M9 13l2 2 4-4',
    ambulancia: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z M12 8v8 M8 12h8',
    traslado: 'M3 8h14 M13 4l4 4-4 4 M21 16H7 M11 12l-4 4 4 4',
    persona: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M4 20c0-4 3.6-7 8-7s8 3 8 7',
    reloj: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M12 7v5l3 3',
    salir: 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4 M16 17l5-5-5-5 M21 12H9'
};

function icono(nombre) {
    var trazos = ICONOS[nombre].split(" M");
    var paths = "";
    for (var i = 0; i < trazos.length; i++) {
        paths += '<path d="' + (i == 0 ? trazos[i] : "M" + trazos[i]) + '"/>';
    }
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
        'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + paths + '</svg>';
}

// ---------- menú: qué ve cada rol ----------
var MENU = [
    { texto: "Panel", pagina: "panel.html", icono: "home", roles: ["Administrador", "Recepcionista", "Coordinador"] },
    { texto: "Documentos", pagina: "documentos.html", icono: "documento", roles: ["Administrador", "Recepcionista"] },
    { texto: "Encuestas", pagina: "encuestas.html", icono: "encuesta", roles: ["Administrador", "Recepcionista"] },
    { texto: "Traslados", pagina: "traslados.html", icono: "traslado", roles: ["Administrador", "Coordinador"] },
    { texto: "Recursos", pagina: "recursos.html", icono: "ambulancia", roles: ["Administrador", "Coordinador"] }
];

// Se llama al principio de cada página privada. Si no hay sesión manda al
// login; si el rol no puede ver la página manda al panel; si todo va bien
// arma el menú lateral.
async function iniciarPagina() {
    var datos = await pedir("acceso.php?accion=sesion");
    if (!datos.logueado) {
        window.location.href = "index.html";
        return null;
    }

    var actual = window.location.pathname.split("/").pop();
    var enlaces = "";
    for (var i = 0; i < MENU.length; i++) {
        var item = MENU[i];
        if (item.roles.indexOf(datos.rol) == -1) {
            if (item.pagina == actual) {
                window.location.href = "panel.html";
                return null;
            }
            continue;
        }
        var activo = item.pagina == actual ? " activo" : "";
        enlaces += '<a class="enlace-nav' + activo + '" href="' + item.pagina + '">' +
            icono(item.icono) + '<span>' + item.texto + '</span></a>';
    }

    document.getElementById("barra-lateral").innerHTML =
        '<div id="logo-barra"></div>' +
        '<nav class="navegacion">' + enlaces + '</nav>' +
        '<div class="zona-usuario">' +
        '<div class="avatar-usuario">' + iniciales(datos.nombre) + '</div>' +
        '<div class="datos-usuario"><span class="nombre-usuario">' + esc(datos.nombre) + '</span>' +
        '<span class="rol-usuario">' + esc(datos.rol) + '</span></div>' +
        '<button class="boton-salir" onclick="cerrarSesion()" title="Cerrar sesión">' + icono("salir") + '</button>' +
        '</div>';
    logoChico("logo-barra");
    return datos;
}

async function cerrarSesion() {
    await pedir("acceso.php?accion=logout");
    window.location.href = "index.html";
}

function iniciales(nombre) {
    var palabras = nombre.split(" ");
    return (palabras[0][0] + palabras[palabras.length - 1][0]).toUpperCase();
}

// ---------- hablar con PHP ----------

// pide algo a un .php y devuelve el JSON. Si el PHP dice ok:false, lanza un error.
async function pedir(url, opciones) {
    var respuesta = await fetch(url, opciones || {});
    var datos;
    try {
        datos = await respuesta.json();
    } catch (e) {
        throw new Error("El servidor no respondió bien. ¿Están encendidos Apache y MySQL?");
    }
    if (!datos.ok && datos.ok !== undefined) {
        throw new Error(datos.mensaje || "Ocurrió un error.");
    }
    return datos;
}

// manda un objeto como JSON (POST)
function enviar(url, objeto) {
    return pedir(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(objeto)
    });
}

// ---------- cosas chicas ----------

// evita que un texto con < o > rompa el HTML
function esc(texto) {
    if (texto === null || texto === undefined) {
        return "";
    }
    return String(texto).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

// muestra un mensaje arriba de la página (en el div #zona-mensaje)
function avisar(texto, tipo) {
    var zona = document.getElementById("zona-mensaje");
    var clase = tipo == "error" ? "mensaje-error" : "mensaje-exito";
    zona.innerHTML = '<div class="mensaje ' + clase + '">' + esc(texto) + '</div>';
    window.scrollTo(0, 0);
    setTimeout(function () { zona.innerHTML = ""; }, 5000);
}

// "2026-10-15 14:30:00" -> "15/10/2026 14:30"
function fechaCorta(texto) {
    if (!texto) {
        return "—";
    }
    var partes = texto.split(" ");
    var f = partes[0].split("-");
    var hora = partes[1] ? " " + partes[1].substring(0, 5) : "";
    return f[2] + "/" + f[1] + "/" + f[0] + hora;
}

// "En viaje" -> <span class="estado estado-En-viaje">En viaje</span>
function etiquetaEstado(estado) {
    return '<span class="estado estado-' + estado.replace(/ /g, "-") + '">' + esc(estado) + '</span>';
}

function pie() {
    return '<footer class="pie">Clinical_Data &middot; Hospital de Clínicas</footer>';
}
