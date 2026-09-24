# Formatos de importación

<!-- Generado por bin/generar-docs.php. No editar a mano: vuelve a ejecutar el script. -->

Todas las importaciones tabulares siguen dos pasos: **analizar** (se lee el archivo, se valida fila por fila y se muestra una vista previa; no se guarda nada) y **confirmar** (se guardan solo las filas válidas, en una transacción). Detalles comunes:

- Formatos: CSV (coma, punto y coma, tabulador o barra; UTF-8 o Windows-1252, detectados solos), XLSX y XLS.
- Tamaño máximo: 5 MB. El contenido se comprueba (tipo real y firma del archivo), no solo la extensión.
- Encabezados en cualquier orden, con o sin tildes; se aceptan los alias indicados. Sin encabezado reconocible, se usa el orden de la plantilla.
- Cada pantalla ofrece la **plantilla CSV** con los encabezados y una fila de ejemplo.
- La vista previa se guarda fuera de la web y caduca a la hora.

## Usuarios

Ruta: `/usuarios/importar` · Quién: Coordinación · Máximo de filas: 1.000

| Columna | Descripción | Obligatoria | También se acepta |
|---|---|---|---|
| `nombre` | Nombre completo | sí | `nombres`, `nombre_completo`, `nombres_y_apellidos` |
| `email` | Correo | sí | `correo`, `correo_electronico`, `e_mail`, `mail` |
| `rol` | Rol — coordinador, instructor o aprendiz | sí | `perfil`, `tipo` |

**Ejemplo:** nombre=`MARÍA FERNANDA LÓPEZ` · email=`mflopez@sena.edu.co` · rol=`instructor`

**Reglas:**

- La primera fila debe llevar los encabezados (se aceptan en cualquier orden, con o sin tildes).
- El separador del CSV (coma o punto y coma) y la codificación (UTF-8 o la de Excel en Windows) se detectan solos.
- Reimportar el mismo archivo no duplica: lo que ya existe se omite.

## Competencias

Ruta: `/competencias/importar` · Quién: Coordinación · Máximo de filas: 2.000

| Columna | Descripción | Obligatoria | También se acepta |
|---|---|---|---|
| `codigo_programa` | Código del programa | sí | `programa`, `cod_programa` |
| `codigo` | Código de la competencia | sí | `codigo_competencia`, `cod_competencia` |
| `nombre` | Nombre | sí | `competencia`, `denominacion`, `nombre_competencia` |
| `horas` | Horas | sí | `duracion`, `duracion_horas` |
| `descripcion` | Descripción | no | — |
| `etapa_practica` | Etapa práctica (sí/no) — Déjelo vacío o "no" salvo la competencia de etapa productiva | no | `es_etapa_practica` |

**Ejemplo:** codigo_programa=`228118` · codigo=`220501094` · nombre=`ESTABLECER REQUISITOS DE LA SOLUCIÓN DE SOFTWARE` · horas=`48` · descripcion=`` · etapa_practica=`no`

**Reglas:**

- La primera fila debe llevar los encabezados (se aceptan en cualquier orden, con o sin tildes).
- El separador del CSV (coma o punto y coma) y la codificación (UTF-8 o la de Excel en Windows) se detectan solos.
- Reimportar el mismo archivo no duplica: lo que ya existe se omite.

## Resultados de aprendizaje

Ruta: `/resultados-aprendizaje/importar` · Quién: Coordinación · Máximo de filas: 3.000

| Columna | Descripción | Obligatoria | También se acepta |
|---|---|---|---|
| `codigo_competencia` | Código de la competencia | sí | `competencia`, `cod_competencia` |
| `codigo` | Código del RAP | sí | `codigo_rap`, `cod_rap`, `rap` |
| `denominacion` | Denominación | sí | `nombre`, `resultado`, `resultado_de_aprendizaje` |
| `codigo_programa` | Código del programa — Solo si la competencia existe en varios programas | no | `programa` |

**Ejemplo:** codigo_competencia=`220501094` · codigo=`220501094-01` · denominacion=`CARACTERIZAR LOS PROCESOS DE LA ORGANIZACIÓN` · codigo_programa=``

**Reglas:**

- La primera fila debe llevar los encabezados (se aceptan en cualquier orden, con o sin tildes).
- El separador del CSV (coma o punto y coma) y la codificación (UTF-8 o la de Excel en Windows) se detectan solos.
- Reimportar el mismo archivo no duplica: lo que ya existe se omite.

## Matrículas de aprendices

Ruta: `/matriculas/importar` · Quién: Coordinación · Máximo de filas: 500

| Columna | Descripción | Obligatoria | También se acepta |
|---|---|---|---|
| `nombre` | Nombre completo | sí | `nombres`, `nombre_completo`, `aprendiz` |
| `email` | Correo | sí | `correo`, `correo_electronico`, `mail` |
| `tipo_documento` | Tipo de documento — CC, TI, CE, PEP o PA (por defecto CC) | no | `tipo_doc`, `tipo` |
| `numero_documento` | Número de documento | sí | `documento`, `numero_doc`, `identificacion`, `cedula` |
| `genero` | Género — M, F u O | no | `sexo` |
| `telefono` | Teléfono | no | `celular`, `movil` |
| `ciudad` | Ciudad | no | `municipio` |
| `fecha_nacimiento` | Fecha de nacimiento — AAAA-MM-DD | no | `nacimiento` |

**Ejemplo:** nombre=`LAURA CAMILA VARGAS` · email=`lcvargas@soy.sena.edu.co` · tipo_documento=`CC` · numero_documento=`1117500123` · genero=`F` · telefono=`3101234567` · ciudad=`Florencia` · fecha_nacimiento=`2004-05-17`

**Reglas:**

- La primera fila debe llevar los encabezados (se aceptan en cualquier orden, con o sin tildes).
- El separador del CSV (coma o punto y coma) y la codificación (UTF-8 o la de Excel en Windows) se detectan solos.
- Reimportar el mismo archivo no duplica: lo que ya existe se omite.

## Juicios evaluativos (Sofia Plus)

Ruta: `/evaluaciones/importar` · Quién: Instructor y coordinación · Máximo de filas: 8.000

| Columna | Descripción | Obligatoria | También se acepta |
|---|---|---|---|
| `tipo_documento` | Tipo de documento | no | `tipo_doc` |
| `numero_documento` | Número de documento | sí | `documento`, `numero_doc` |
| `nombre` | Nombre | no | `nombres` |
| `apellidos` | Apellidos | no | `apellido` |
| `estado` | Estado | no | `estado_aprendiz` |
| `competencia` | Competencia — "220501046 - Nombre de la competencia" | sí | — |
| `resultado` | Resultado de aprendizaje — "220501046 - 01 Denominación del RAP" | sí | `rap`, `resultado_aprendizaje` |
| `juicio` | Juicio de evaluación — APROBADO, NO APROBADO / DEFICIENTE o POR EVALUAR | sí | `juicio_evaluacion`, `concepto` |
| `fecha` | Fecha del juicio — DD/MM/AAAA | no | `fecha_juicio` |
| `funcionario` | Funcionario que registró | no | `instructor` |

**Ejemplo:** tipo_documento=`CC` · numero_documento=`1117500123` · nombre=`LAURA CAMILA` · apellidos=`VARGAS RUIZ` · estado=`EN FORMACION` · competencia=`220501046 - Utilizar herramientas informáticas` · resultado=`220501046 - 01 Configurar el equipo según el manual` · juicio=`APROBADO` · fecha=`24/09/2026` · funcionario=``

**Reglas:**

- Sube el reporte de juicios evaluativos de Sofia Plus tal como se descarga (.xls); también sirve en .xlsx o .csv.
- La ficha se toma del reporte y tiene que existir en el sistema. Si tu archivo no la trae, elígela arriba.
- Un juicio ya emitido nunca vuelve a «pendiente» desde aquí; si el reporte lo cambia de A a D (o al revés), queda en el historial.
- Instructores: solo se cargan los RAP que calificas en esa ficha. Coordinación: la estructura y los aprendices que falten se crean, con aviso previo.

