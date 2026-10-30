# Clinical_Data

Sistema del Hospital de Clínicas (proyecto de egreso, 3.º de Bachillerato en
Tecnología de la Información). Tiene dos módulos:

1. **Documentos para pacientes**: el personal sube PDFs y el paciente los ve
   desde el celular escaneando un QR. Incluye encuestas de satisfacción anónimas.
2. **Traslados de ambulancias**: se registra cada traslado y se sigue su estado
   desde que sale del hospital hasta que vuelve.

## Instalación (XAMPP)

1. Copiar la carpeta **Clinical_Data** dentro de `htdocs`.
2. Encender Apache y MySQL.
3. En phpMyAdmin, importar `database/schema.sql` (crea la base `clinical_data`).
4. Entrar a `http://localhost/Clinical_Data/`

## Usuarios de prueba

| Usuario    | Rol           | Contraseña    | Ve                     |
|------------|---------------|---------------|------------------------|
| mrodriguez | Administrador | direccion2024 | todo                   |
| vfernandez | Recepcionista | recepcion2024 | documentos y encuestas |
| jalvarez   | Coordinador   | coordina2024  | traslados y recursos   |

## Archivos

```
Clinical_Data/
├── index.html        login
├── panel.html        resumen y alta de usuarios (solo Administrador)
├── documentos.html   subir, editar y eliminar documentos; códigos QR
├── encuestas.html    crear encuestas y ver resultados
├── traslados.html    traslados de ambulancias
├── recursos.html     ambulancias, personal y rutas
├── publico.html      página del QR (sin login)
├── conexion.php      conexión a la base y funciones comunes
├── acceso.php        login, logout, números del panel y alta de usuarios
├── documentos.php    documentos y encuestas
├── traslados.php     traslados, ambulancias, personal y rutas
├── assets/           estilos.css y comun.js
├── database/         schema.sql
└── documentos/       acá se guardan los PDF subidos
```

Cada página HTML tiene su propio JavaScript adentro. El PHP solo se usa para
hablar con la base de datos y devuelve JSON.

## Flujo de un traslado

Pendiente → En viaje → En destino → Regresando → Finalizado (o Cancelado
mientras esté Pendiente o En viaje). El sistema pone las horas reales al
cambiar de estado, marca la ambulancia "En servicio" mientras dura el
recorrido y guarda un historial con usuario, fecha y comentario.

## QR

En la pantalla Documentos hay dos tipos de QR (se generan en el navegador con
`assets/qr.js`, sin internet):

- **QR general:** apunta a `http://<servidor>/Clinical_Data/publico.html`
  (todos los documentos y la encuesta).
- **QR por documento:** apunta directo al PDF. Se ve con el botón "QR" de cada fila
  o con "Seleccionar" y "Ver QR". Se puede descargar como PNG.

Antes de imprimirlos hay que poner en "Dirección del servidor" la IP o el nombre del
servidor que usan los celulares (si se entra con `localhost`, el QR no abre en un celular).

## Documentos: seleccionar, editar y eliminar

El botón "Seleccionar" activa casillas en la lista. Con un documento marcado se puede
**Editar** (título, categoría, descripción y, si se quiere, reemplazar el PDF). Con uno o
más marcados se puede **Ver QR** o **Eliminar**.

## Usuarios

Solo el rol Administrador ve, al final del Panel, el botón "Añadir usuario". Ahí crea
usuarios nuevos y les asigna el rol (Administrador, Recepcionista o Coordinador). La
contraseña debe tener al menos 8 caracteres y se guarda cifrada. El servidor vuelve a
comprobar el rol en `acceso.php`, así que otro rol no puede crear usuarios aunque
conozca la dirección.
