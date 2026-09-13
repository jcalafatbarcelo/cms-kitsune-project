# **Software Design Document (SDD) Inicial - CMS Modular Multiidioma**

## **1. Introducción y Propósito**

Este documento define la arquitectura base, las reglas de negocio y el alcance funcional para el desarrollo de un Sistema de Gestión de Contenidos (CMS) como Proyecto de Fin de Máster (TFM). El objetivo principal es construir un producto real, modular, desacoplado y multiidioma, inspirado en la separación estricta de responsabilidades (al estilo de Joomla! 6), pero desarrollado íntegramente bajo el ecosistema de **Laravel**.
Este SDD servirá como artefacto académico de referencia ("Idea Inicial") y como marco normativo para la posterior generación de especificaciones técnicas orientadas a la Inteligencia Artificial (Spec Driven Development).

## **2. Arquitectura del Sistema**

### **2.1. Patrón Arquitectónico: Monolito Modular**

Tras evaluar la Arquitectura Hexagonal, se ha optado por un enfoque de **Monolito Modular** (implementado a través de paquetes como nWidart/laravel-modules). Esta decisión se justifica por:

  - **Sinergia con el Framework:** Permite capitalizar la potencia de Eloquent ORM y las validaciones nativas de Laravel sin la sobrecarga de *boilerplate* (puertos, mappers, repositorios abstractos) que exige la arquitectura hexagonal pura.
  - **Fronteras Estrictas:** El código se agrupa lógicamente (ej. Core, Pages, Media, Menu), permitiendo a futuro escalar o extraer módulos independientemente sin generar un "código espagueti".
  - **Velocidad de Iteración:** Crítica para garantizar la viabilidad del Producto Mínimo Viable (MVP) dentro de los plazos académicos del TFM.

### **2.2. Stack Tecnológico**

  - **Base de Datos:** MySQL / MariaDB (garantizando compatibilidad con hostings tradicionales LAMP/LEMP).
  - **Backend:** Laravel (PHP 8.x).
  - **Frontend Backoffice:** Laravel Blade como base de las páginas; Vue 3 con Composition API para zonas interactivas, incluido el editor del PageBuilder.
  - **Renderizado Público:** Laravel Blade (Server-Side Rendering) para el contenido esencial, con componentes Vue puntuales cuando necesiten interactividad.
  - **Assets Frontend:** Vue como dependencia compartida entre módulos, con carga bajo demanda y compilación mediante Vite; sin SPA ni Inertia por defecto.

La decisión y sus límites se recogen en el
[ADR-0001: Integración de Blade y Vue bajo demanda](../docs/adr/ADR-0001-blade-vue-bajo-demanda.md).
Es arquitectura prevista, todavía no implementada.

## **3. Reglas de Negocio y Modelado de Datos (Knowhow)**

### **3.1. Separación Estricta: Página vs. Traducción**

Se aplicará el patrón **Entity-Translation**. La entidad Page actúa como contenedor estable de identidad, mientras que las variaciones de contenido, el slug público y la metadata SEO residirán en la entidad PageTranslation vinculada al idioma correspondiente. Esto garantiza que las referencias estructurales no se rompan al añadir o modificar idiomas.

### **3.2. PageBuilder: Edición Declarativa y Renderizado Blade**

Para reconciliar la flexibilidad visual de Vue 3 en el backoffice con la robustez de Blade en el frontend:

  - **Configuración Declarativa:** Cada tipo de bloque declarará su estructura, tipos de campo (text, image, wysiwyg, boolean) y translatabilidad. JSON o clases PHP son alternativas iniciales para describir ese esquema; su representación no determina por sí sola cómo se persiste el contenido.
  - **Almacenamiento:** La estructura y el contenido tendrán una representación validada por Laravel y apta para renderizarse con Blade, conservando su asociación con las traducciones. JSON nativo en MySQL es la alternativa inicial, no una decisión definitiva; la Spec del PageBuilder evaluará y aprobará la persistencia antes de implementarla.
  - **Renderización:** Blade recibirá datos estructurados y validados y los renderizará de forma directa, sin parsear Blade mediante expresiones regulares. Los bloques podrán incorporar islas de interactividad Vue conforme al ADR-0001, sin cargar el editor en la web pública.

Esta aclaración sustituye la prescripción inicial de almacenamiento JSON nativo;
no selecciona una alternativa ni difiere las garantías de validación e integridad.

### **3.3. Medios y Gestión de Assets**

Las imágenes se tratarán como entidades reutilizables, no como simples rutas de archivo en el HTML. Cada asset contará con:

  - Metadatos (alt y title) traducibles con sistema de fallback al idioma por defecto.
  - Diferenciación por **Perfil de Uso** (imagen de contenido responsive vs. imagen OpenGraph estática).

### **3.4. Independencia de Menús y Visibilidad en Cascada**

El árbol jerárquico de URLs canónicas está estrictamente separado de los árboles de navegación visual (Menús). Además, regirá un principio de **publicación en cascada**: una traducción solo será accesible públicamente si la página, el idioma y todas las páginas ascendentes en la jerarquía se encuentran publicadas y activas para ese mismo idioma.

## **4. Alcance del Proyecto (Matriz MoSCoW)**

| Prioridad | Categoría | Funcionalidad |
| :--- | :--- | :--- |
| **MUST** | Arquitectura | Core Monolito Modular sobre Laravel. |
| **MUST** | Idiomas | Sistema Core (Activo/Inactivo, prefijos de URL automáticos). |
| **MUST** | Contenido | Separación Estricta: Entidad Página vs. Traducción (Entity-Translation). |
| **MUST** | Contenido | PageBuilder reactivo (Vue 3, configuración declarativa y render Blade; persistencia por decidir, con JSON como alternativa inicial). |
| **MUST** | Medios | Gestor de Assets reutilizables con metadatos traducibles (alt/title). |
| **MUST** | Navegación | Menús de navegación independientes de las URLs canónicas. |
| **SHOULD** | Navegación / Idioma | Visibilidad condicional en menús, publicación en cascada y selector inteligente. |
| **COULD** | Integración IA | Uso de LLMs para traducir contenido estructurado desde el editor, sin fijar su formato de persistencia. |
| **WON'T** | Arquitectura | Instalador visual de plugins/módulos desde backoffice (Fuera del MVP). |
