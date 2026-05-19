# Sistema de Seguimiento de Proyectos Formativos SENA

Plataforma institucional, modular y de nivel enterprise para la gestión integral, evaluación y seguimiento de proyectos formativos del Servicio Nacional de Aprendizaje (SENA).

## 🚀 Tecnologías Core

*   **Backend:** PHP 8 (Procedural, tipado estricto)
*   **Base de Datos:** MySQL
*   **Frontend HTML:** HTML5 Semántico
*   **Frontend Estilos:** CSS3 Vanilla (Arquitectura de Design Tokens + Variables) y Bootstrap 5 (Base Grid)
*   **Frontend Lógica:** JavaScript ES6 (Vanilla, sin frameworks pesados)
*   **Librerías Visuales:** Bootstrap Icons, Chart.js (Gráficos), Google Fonts (Inter)

---

## 📁 Arquitectura del Proyecto

El sistema fue diseñado siguiendo un modelo de **arquitectura modular** estricta, lo que garantiza escalabilidad y facilidad de mantenimiento.

```text
proyecto_sena/
├── assets/                 # Archivos estáticos
│   ├── css/                # Sistema de estilos modulares
│   └── js/                 # Sistema de scripts globales
├── includes/               # Lógica y configuraciones PHP
├── layouts/                # Plantillas estructurales de la UI
├── modules/                # Módulos del sistema (Vistas)
└── index.php               # Enrutador principal
```

---

## 🎨 Motor de Estilos (CSS Framework)

Se implementó un framework CSS propio (basado en Variables CSS) con soporte nativo para **Dark Mode** y enfoque **Mobile-First**.

*   `variables.css`: Diccionario de diseño (Design Tokens), paleta verde institucional (SENA), sombras, radios y tipografía.
*   `base.css`: Reset CSS, tipografía fluida y clases de utilidad globales.
*   `layout.css`: Estructura del Layout (Sidebar responsivo colapsable, Navbar, App Wrapper).
*   `components.css`: Componentes reusables (Botones, Cards, Alertas, Modales, Tabs, Timelines, Badges).
*   `forms.css`: Elementos de formulario avanzados (Floating Labels, Custom Selects, Drag & Drop, Switches).
*   `tables.css`: Data Tables con soporte para toolbars, filtros, paginación y cabeceras dinámicas.
*   `dashboard.css`: Cuadrículas de métricas (KPIs), contenedores de gráficas Chart.js.
*   `auth.css`: Estilos visuales del sistema de Login (Pantalla dividida con Branding).
*   `dark.css`: Sobrescritura de tokens genéricos para activar el Modo Oscuro.
*   `responsive.css`: Media queries para adaptar la interfaz a Tablets y Móviles.

---

## 🧠 Lógica Frontend (JS Framework)

*   `app.js`: Script de inicialización (Bootstrap de eventos globales, Dropdowns, Tabs, Modales).
*   `charts.js`: Wrapper pre-configurado de Chart.js adaptado a los colores institucionales.
*   `sidebar.js`: Control persistente (localStorage) de expansión y colapso del Sidebar lateral.
*   `theme.js`: Control persistente para alternar entre Modo Claro y Oscuro.
*   `notifications.js`: Sistema Toast (Notificaciones emergentes no bloqueantes).
*   `utils.js`: Helpers globales (Formatos de fechas, moneda, acrónimos, generación de colores aleatorios).

---

## ⚙️ Core PHP

*   `config.php`: Constantes de Base de Datos, URLs base y definición de los Menús Laterales por Rol.
*   `session.php`: Verificador de roles (`requireAuth()`, `requireRole()`) y control de estado de sesión.
*   `auth.php`: Lógica y validación del inicio y cierre de sesión.
*   `functions.php`: Utilidades para PHP (Fechas en español, breadcrumbs dinámicos, sanitización).

---

## ✅ Progreso del Desarrollo

### Fase 1: Arquitectura, Login y Dashboards (Completado)
- [x] Motor de Estilos UI (CSS modular).
- [x] Pantalla de Login interactiva y visualmente moderna.
- [x] Enrutador principal (`index.php`) basado en Roles de Usuario.
- [x] **Dashboard Coordinador:** KPIs de centro, gráficos de avance, estado de fichas y alertas de gestión.
- [x] **Dashboard Instructor:** Resumen de fichas asignadas, progreso de los aprendices, calendario de evaluaciones.
- [x] **Dashboard Aprendiz:** Seguimiento personal de evaluaciones, tareas próximas y estado del programa.

### Fase 2: Módulo Core de Gestión Académica (Completado Parcial)
- [x] **Módulo de Fichas (`/fichas/index.php`)**: Tabla avanzada de grupos de formación, filtro por estados.
- [x] **Creación de Ficha (`/fichas/crear.php`)**: Formulario con interfaz de etiquetas flotantes (*Floating Labels*).
- [x] **Detalle de Ficha (`/fichas/ver.php`)**: Panel de métricas del grupo y listado de aprendices matriculados.

### Próximos Pasos (Pendientes)
*   Desarrollar el Módulo de Usuarios (Listado, perfiles y permisos).
*   Desarrollar Módulo de Programas y Competencias.
*   Módulo de Proyectos Formativos (Fases y Actividades).
*   Módulo de Juicios Evaluativos y Evidencias.
*   Conexión de todos los mocks a la base de datos MySQL mediante consultas preparadas PDO/MySQLi.
