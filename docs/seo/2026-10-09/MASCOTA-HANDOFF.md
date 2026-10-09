# Coordinación Mascota → Perro y conversiones comerciales

9 octubre 2026. **Handoff, no cambios al repositorio Mascota.** Contexto leído: `C:/Projects/mascota/docs/seo/2026-10-09/README.md`, mapa de keywords, inventario de 62 URLs y `php-site/content/articles.json`. No se supone que el build local esté desplegado.

## Prioridad 0: poner la biblioteca correcta en línea

El dominio raíz respondió con página por defecto en la inspección pública del 9 de octubre. Confirmar cuenta/document root, revisar el release PHP de Mascota y sus instrucciones, desplegar únicamente con autorización y comprobar enlaces, HTTPS, robots y sitemap. Esta tarea no hace la publicación.

Perro incorpora seis referencias contextuales a cinco URLs distintas de Mascota, desactivadas por defecto porque su publicación no está comprobada. Activar `PERRO_MASCOTA_GUIDES_ENABLED=1` en el entorno PHP de Perro solo después de comprobar todas las rutas de abajo; no es una redirección ni un canonical entre sitios:

`/mascotas/perros/razas/`, `/mascotas/perros/razas/labrador/`, `/productos/comida-para-perros/`, `/cuidados/elegir-veterinaria/`, `/cuidados/vomitos-en-perros/` (cinco destinos únicos en tres guías; algunas referencias se repiten contextualmente).

## Enlaces editoriales propuestos en Mascota

Agregar en una sección pertinente, con divulgación de mismo propietario, sin intercambios masivos en el pie. Una página de cuidado no necesita un banner de adopción en todos sus párrafos.

| Página existente Mascota | Ubicación/anchor natural | Destino Perro | Motivo |
| --- | --- | --- | --- |
| `/mascotas/perros/` | Elección de compañero: “avisos de perros en adopción en Paraguay” | `/perros` | Inventario actual frente a referencia de especie |
| `/cuidados/primer-perro/` | Antes de decidir: “preguntas y requisitos para adoptar” | `/requisitos-para-adoptar` | Flujo práctico y gratuidad |
| `/mascotas/perros/razas/` | Elección: “elegir un perro para adoptar según tu rutina” | `/elegir-perro` | Necesidades individuales y mestizos |
| `/mascotas/perros/razas/labrador/` | Adopción: “consultar avisos de raza declarada” | `/perros-de-raza-en-adopcion` | No afirmar disponibilidad de labradores |
| `/mascotas/perros/razas/golden-retriever/` | Igual contexto, solo si aporta | `/perros-de-raza-en-adopcion` | Una ficha de raza no es un aviso |
| `/mascotas/perros/razas/husky-siberiano/` | Cachorros: “comparar cachorro y adulto antes de adoptar” | `/elegir-perro` | No crear duplicado de cuidados |
| `/productos/comida-para-perros/` | Apoyo a rescates: “acordar alimento y gastos de un hogar temporal” | `/hogar-temporal` | Logística de cuidado temporal distinta de elegir alimento |
| `/cuidados/presupuesto-mascota/` | Antes de adoptar: “lista de gastos para la llegada de un perro” | `/primeros-dias-perro-adoptado` | Plan operativo sin precios ficticios |
| `/cuidados/vomitos-en-perros/` | Si recibe un perro: “organizar historial y seguimiento” | `/primeros-dias-perro-adoptado` | Opcional; atención médica y urgencias tienen prioridad |

Verificar rutas y anchors del build final antes de editar. Cada dominio conserva su canonical propio para contenido distinto. “Primario” en el mapa describe prioridad editorial, no exclusividad de ranking. No crear páginas separadas por alias, ciudad o raza sin demanda limpia, contenido local y avisos/establecimientos reales suficientes. Perro ofrece un catálogo nacional con filtros, no cobertura garantizada por ciudad.

## Conversión veterinaria, próxima implementación en Mascota

Prioridad comercial tras lanzamiento: perfiles reales contratados y ruta `/veterinarias/` funcional. Mantenerla diferida mientras no haya oferta útil. Un perfil patrocinado informa servicios y horarios declarados, dirección verificada, fecha de comprobación y contacto directo. La página enumera espacios patrocinados y cobertura real. San Lorenzo tiene una fila de 880 búsquedas; eso no verifica una clínica ni autoriza una página local vacía. Villarrica/Luque mezclan nombres y peluquería: reexportar antes de inversión.

Un botón orientado a la necesidad del visitante debe identificar clínica, servicio general y página de origen; revisar privacidad para no transferir síntomas ni contactos sin autorización. Medir clic, consulta confirmada y cita como etapas separadas. No publicar “24 h”, “abierto ahora”, “mejor” ni “cerca” sin capacidad y datos que lo sostengan. En síntomas de emergencia, CTA de atención inmediata por delante de captación comercial. Los socios comerciales no revisaron automáticamente la biblioteca.

Modelo recomendado: paquetes mensuales con trabajo entregable y reporte, no tráfico prometido. Ver [REVENUE-PLAN.md](../../business/2026-10-09/REVENUE-PLAN.md).

## Medición conjunta

Search Console: propiedades de dominio separadas con DNS autorizado; exportar fecha, consulta, página, país y dispositivo. Construir vista combinada conservando dominio y filas originales. Las consultas anonimizadas/omitidas pueden impedir sumar el total completo. El KWP es prioridad investigativa, no un pronóstico de visitas.

Eventos propuestos: Mascota `vet_contact_click` y `product_outbound_click`; Perro ya dispone de clics agregados de contacto y resultados confirmados por el equipo. Antes de agregar analítica nueva: definir base de privacidad, retención y exclusión de datos personales. No cambiar cuentas ni instalar trackers en esta tarea. No deduplicar personas por teléfono a partir de avisos privados.
