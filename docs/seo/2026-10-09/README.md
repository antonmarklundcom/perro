# SEO de Perro + modelo de ingresos — 9 octubre 2026

Base verificada: main `aa3b5dd49bdca83e60fbab89ffcf526313a7869d`; PR #8 **merged**, 8 octubre. No había PRs abiertos antes de editar. Checkout nuevo `C:/Projects/perro`, rama `codex/seo-adoption-business-20261009`; OneDrive retenido. No merge, deploy, cobros, contacto a organizaciones o cambio de cuentas externas.

## Qué mostró la auditoría

| Prioridad | Hallazgo en main | Resultado |
| --- | --- | --- |
| P0 | Permisos, contacto privado, vencimiento, moderación y estados ya protegidos en PR8 | Preservados y sometidos a las suites existentes |
| P0 | Preview no disponía de control explícito de indexación | `PERRO_NOINDEX=1`: robots Disallow /, meta y cabecera noindex, sin schema público |
| P0 | Documentación futura CSV/JSON podría servirse en Apache al agregar docs | `/docs` denegado por Apache y router; release excluye docs y datos privados |
| P1 | H1 de inicio y publicación genéricos | H1 concretos sobre adopción/publicación; acciones conservadas |
| P1 | Página 999 se convertía en última página válida | HTTP 404 con noindex para páginas inexistentes, cero, arrays y sintaxis inválida |
| P1 | Parámetros ignorados podían generar variantes indexables | Variantes noindex,follow; HTML links de paginación sin filtros/defaults, canonical propio válido |
| P1 | Robots publicaba Allow / sin exclusiones orientativas | Exclusiones privadas; siguen existiendo controles de acceso. Robots no protege datos |
| P1 | Sitemap estático sin fechas editoriales | URLs de guías y fechas de modificación explícitas de 9 octubre; legales sin fecha inventada |
| P1 | Solo seguridad y cómo funciona tenían orientación breve | Siete rutas nuevas y seguridad ampliada; preguntas, pasos, fuentes y CTAs reales |
| P1 | Perdidos/encontrados, cachorros y raza con poco contenido distinto | Guías integradas al catálogo existente, sin páginas de variantes |
| P2 | Sin modelo salarial ni acuerdos comerciales | Plan de paquetes, costos, cobranza, capacidad, piloto y reserva; cobros deshabilitados |

Las nuevas rutas son `/guias-adopcion`, `/requisitos-para-adoptar`, `/elegir-perro`, `/hogar-temporal`, `/reubicacion-responsable`, `/primeros-dias-perro-adoptado`, `/apoyar`. Seguridad conserva `/seguridad`. HTML links enlazan páginas desde inicio, pie y catálogos; no dependen de JavaScript. FAQs visibles, Article/CollectionPage/WebPage y breadcrumbs solo según el contenido visible; no Product, precios de perros, reviews ni rich results prometidos.

La biblioteca de Mascota está preparada localmente pero el dominio mostró página de hosting por defecto. Las referencias Perro → Mascota están implementadas detrás de `PERRO_MASCOTA_GUIDES_ENABLED=1` para activarse solo tras verificación del lanzamiento. Esto es una acción pendiente concreta, no una afirmación de que la coordinación ya esté publicada.

## Inventario y prioridad editorial

- [KEYWORD-PAGE-MAP.csv](KEYWORD-PAGE-MAP.csv): grupos relevantes, volumen completo/detalle separado, fila principal, intención, dominio primario, URL y oportunidad distinta secundaria.
- [PAGE-INVENTORY.csv](PAGE-INVENTORY.csv): metadatos finales renderizados de 17 rutas estáticas indexables; avisos activos son adicionales y dinámicos.
- [GROUP-DETAIL-READS.json](GROUP-DETAIL-READS.json): evidencia de lectura de grupos relevantes y hash de fuente. No publica el archivo original ni inventa una exportación completa.
- [KWP-FOLLOW-UP.md](KWP-FOLLOW-UP.md): faltantes y lotes de investigación; adopción no dispone de volúmenes específicos comprobados.
- [MASCOTA-HANDOFF.md](MASCOTA-HANDOFF.md): cambios recomendados allí sin editar Mascota.
- [Modelo de ingresos](../../business/2026-10-09/REVENUE-PLAN.md): objetivo provisional Gs. 4 millones netos, con costo laboral ilustrativo mayor; precios para validar.
- [Verificación y release](../../verification/2026-10-09/SEO-RELEASE.md).

Primario no prohíbe que aparezca el otro sitio: Perro ofrece inventario y procesos; Mascota referencia y cuidados. No sumar alias alemanes/husky ni grupos completos con filas ya contenidas. Algunos grupos mezclan especies, marcas, instituciones o consultas de salud humana. El número grande no valida intención y no implica que se pueda convertir cada búsqueda.

## Inventario vacío, terminado y nuevas páginas locales

Catálogos nacionales vacíos mantienen HTTP 200 por su orientación útil y estado honesto. Combinaciones filtradas vacías responden 404/noindex; sus formularios sirven al usuario pero no se incorporan al sitemap. Perfiles pending, vencidos o retirados no son públicos; adoptados/reunidos responden 410/noindex y no exponen ficha privada. Los perfiles y medios conservan controles de visibilidad existentes. No hay casos reales ni outcomes inventados en el checkout.

Ciudad/raza se decide después: datos KWP limpios o consultas reales de Search Console, contenido específico y suficientes avisos autorizados vigentes. Si se crea una página útil en el futuro, documentar la conducta al expirar sus avisos; no actualizar lastmod automáticamente cada día ni redirigir toda ciudad vacía a inicio. No construir combinaciones indexables de filtros.

## Qué sigue y quién lo hace

Anton revisa y decide merge/deployment; preservar storage y settings del servidor. Completar identidad/inbox y operaciones pendientes del manual. Poner Mascota en línea, verificar destinos y luego habilitar referencias. Su novia puede validar con clínicas los paquetes y trabajar la cola con permisos; no se enviaron mensajes ni se habilitaron contratos/pagos en este trabajo. Definir su objetivo salarial real y presupuesto de inicio.

Fuentes técnicas: [Google sobre facetas](https://developers.google.com/crawling/docs/faceted-navigation), [sitemap y lastmod](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap), [patrocinados](https://developers.google.com/search/docs/crawling-indexing/qualify-outbound-links). Fuentes de cuidado/legal están visibles en cada guía. No se afirma revisión veterinaria o jurídica profesional.
