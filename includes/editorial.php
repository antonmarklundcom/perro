<?php

declare(strict_types=1);

// Authored content, not user submissions. Dates represent significant editorial changes.
function editorial_pages(): array
{
    static $pages;
    return $pages ??= [
        'guias-adopcion' => [
            'title'=>'Guías de adopción de perros en Paraguay',
            'description'=>'Prepará una adopción gratuita y responsable: requisitos, elección del perro, hogar temporal, entrega y seguimiento en Paraguay.',
            'lead'=>'Elegí el paso que necesitás hoy. Las guías te ayudan a conversar con la persona responsable; los avisos muestran únicamente perros aprobados y vigentes.',
            'sections'=>[
                ['Antes de buscar', '<p>Revisá los <a href="/requisitos-para-adoptar">requisitos y preguntas para adoptar</a>, y compará tu rutina con las necesidades del perro en <a href="/elegir-perro">cómo elegir un perro compatible</a>. Después abrí los <a href="/perros">avisos en adopción</a>; una guía no confirma disponibilidad.</p>'],
                ['Si estás ayudando a un perro', '<p>Prepará una <a href="/reubicacion-responsable">publicación y entrega responsables</a> o acordá un <a href="/hogar-temporal">hogar temporal</a>. Si se perdió o lo encontraste, seguí los <a href="/perros-perdidos-paraguay#perdi-un-perro">pasos para buscar y reunirlo</a> antes de plantear una adopción.</p>'],
                ['Cuando ya acordaron la adopción', '<p>Usá la <a href="/seguridad">lista de entrega segura</a> y prepará los <a href="/primeros-dias-perro-adoptado">primeros días y el seguimiento</a>. Avisale al equipo cuando se confirme la adopción para cerrar la ficha.</p>'],
            ], 'faq'=>[], 'sources'=>[],
        ],
        'requisitos-para-adoptar' => [
            'title'=>'Requisitos para adoptar un perro en Paraguay',
            'description'=>'Qué preguntar y preparar para adoptar un perro: hogar, rutina, presupuesto, salud y acuerdo de entrega. Adopción gratuita en Perro Paraguay.',
            'lead'=>'En Perro no pagás para acceder a un aviso ni para reservar un perro. Antes de decidir, conversá con su responsable y revisá qué necesita ese animal en tu hogar.',
            'sections'=>[
                ['Qué pide Perro y qué acuerdan las personas', '<p>Perro revisa los avisos, sus permisos y que no sean ventas. No entrega animales ni aprueba a quienes adoptan. Los requisitos particulares deben explicarse en la ficha y confirmarse directamente con el responsable; no existe aquí un formulario nacional único de adopción.</p><p>El formulario de publicación exige que quien envía sea mayor de edad y esté autorizado. Eso es una regla de esta plataforma; no reemplaza las normas aplicables ni un acuerdo entre las partes.</p>'],
                ['Tu casa y tu tiempo', '<ul><li>Confirmá con todas las personas del hogar quién se ocupará del cuidado.</li><li>Si alquilás, revisá las condiciones y el permiso correspondiente antes de comprometerte.</li><li>Comprobá puertas, portones y un espacio protegido del calor y la lluvia.</li><li>Explicá cuántas horas estará solo, quién lo cuidará y cómo organizarás paseos y descanso.</li><li>Contá si hay niños u otros animales; acordá cómo evaluar la convivencia.</li></ul><p>No elijas solo por tamaño o fotografía. <a href="/elegir-perro">Compará cachorro, adulto y mestizo con tu rutina</a>.</p>'],
                ['Preguntas para el responsable', '<ul><li>¿Por qué necesita otro hogar y desde cuándo lo conoce?</li><li>¿Qué edad y tamaño son conocidos y cuáles aproximados?</li><li>¿Tiene registros de atención, vacunas o esterilización? Pedí lo que exista, sin asumir que está completo.</li><li>¿Cómo se comporta en casa, al pasear y con otras personas o animales?</li><li>¿Qué alimento recibe, en qué horarios y qué cuidados actuales requiere?</li><li>¿Qué seguimiento espera y cómo avisarían si la adaptación presenta dificultades?</li></ul>'],
                ['El presupuesto empieza antes de la entrega', '<p>Anotá alimento, traslado, elementos básicos, atención veterinaria y una reserva para imprevistos. La gratuidad de la adopción no elimina esos gastos. Pedí presupuestos actuales en tu zona; no hay una tarifa universal para cuidar un perro.</p><p>Conservá el historial disponible y coordiná una consulta. Los calendarios y tratamientos los define el profesional para ese perro.</p>'],
                ['Confirmá con cuidado', '<p>Conocé al perro, verificá la relación de quien lo entrega y acordá por escrito fecha, información conocida y contacto de seguimiento. No publiques documentos ni domicilios. Leé la <a href="/seguridad">guía de entrega segura</a> y los <a href="/primeros-dias-perro-adoptado">primeros días en casa</a>.</p><p>La Ley 7513/2025 es el marco nacional de bienestar y protección animal. Consultá el texto vigente y la autoridad correspondiente cuando el caso requiera orientación legal.</p>'],
            ],
            'faq'=>[
                ['¿Tengo que donar para adoptar?', 'No. Perro no permite ventas, señas ni donaciones obligatorias para reservar o entregar un perro. Reportá cualquier pedido de ese tipo desde la ficha.'],
                ['¿Todos los responsables piden los mismos requisitos?', 'No. Consultá los requisitos publicados, su motivo y las necesidades del perro. La revisión del aviso no garantiza su salud, conducta ni el resultado de una adopción.'],
                ['¿Puedo adoptar si vivo en un departamento?', 'Conversá sobre la rutina, los paseos, la seguridad y las necesidades del individuo. Tener patio por sí solo tampoco resuelve el cuidado.'],
            ], 'sources'=>['law'],
        ],
        'elegir-perro' => [
            'title'=>'Cómo elegir un perro para adoptar en Paraguay',
            'description'=>'Compará cachorros, adultos y perros mestizos según tu tiempo, convivencia y presupuesto. Preguntas prácticas antes de adoptar en Paraguay.',
            'lead'=>'La mejor elección empieza por lo que podés sostener cada día. Usá estas preguntas para conversar sobre un perro real, sin decidir solamente por una raza o una foto.',
            'sections'=>[
                ['Cachorro o adulto: qué preguntar', '<p>Con un cachorro, preguntá por su edad estimada, alimentación y cuidados conocidos; organizá tiempo para acompañarlo y enseñarle rutinas. El tamaño adulto y el carácter no se pueden garantizar por una foto.</p><p>Con un adulto, pedí ejemplos de su vida cotidiana: estar solo, caminar, descansar y convivir. La información disponible ayuda a elegir, aunque un cambio de hogar también puede cambiar lo que observás. Abrí <a href="/cachorros-en-adopcion">cachorros en adopción</a> o el <a href="/perros">catálogo completo</a>.</p>'],
                ['Mestizo o raza declarada', '<p>Un mestizo merece la misma evaluación individual. Pedí información de tamaño, rutina y conducta sin atribuirle cualidades solo por su apariencia. En <a href="/perros-de-raza-en-adopcion">avisos de raza declarada</a>, la etiqueta es orientativa: Perro no certifica pureza ni pedigrí.</p><p>Si pensás en un labrador, compará primero el individuo con tu tiempo disponible y pedí ejemplos de actividad y convivencia. No asumás que hay uno disponible porque exista una guía de raza.</p>'],
                ['Cuatro respuestas antes de escribir', '<ol><li><strong>Tiempo:</strong> ¿quién acompaña, pasea y cuida al perro en cada turno?</li><li><strong>Convivencia:</strong> ¿qué personas y animales compartirán la casa?</li><li><strong>Recursos:</strong> ¿cómo cubrirás alimento, atención, traslados e imprevistos?</li><li><strong>Continuidad:</strong> ¿qué pasa cuando viajás, cambiás de trabajo o necesitás ayuda?</li></ol><p>Llevá estas respuestas al encuentro y pedí observar lo que sea posible sin forzar al perro. Si una situación genera miedo o conducta de riesgo, buscá orientación profesional antes de avanzar.</p>'],
                ['Prepará un primer contacto útil', '<p>Al abrir una ficha, comprobá el estado y el código público. El botón de consulta identifica al perro y su zona. Completá el borrador con tu tipo de hogar y tu rutina; un clic no reserva ni confirma una adopción.</p><p>Revisá también los <a href="/requisitos-para-adoptar">requisitos</a> y acordá una <a href="/seguridad">entrega segura</a>.</p>'],
            ], 'faq'=>[
                ['¿Un perro pequeño necesita menos tiempo?', 'El tamaño no indica por sí solo cómo será su rutina. Preguntá por actividad, compañía, aprendizaje y cuidados del perro concreto.'],
                ['¿Perro tiene labradores para adoptar?', 'La disponibilidad depende de avisos reales aprobados y vigentes. Revisá el catálogo; no mantenemos páginas de raza con perros inventados ni prometemos conseguir uno.'],
            ], 'sources'=>['company'], 'mascota'=>[
                ['/mascotas/perros/razas/', 'Razas de perros y sus necesidades'],
                ['/mascotas/perros/razas/labrador/', 'Características y cuidados del labrador'],
            ],
        ],
        'hogar-temporal' => [
            'title'=>'Hogar temporal para perros en Paraguay: cómo organizarlo',
            'description'=>'Acordá duración, alimento, atención, gastos y seguimiento antes de ofrecer un hogar temporal a un perro en Paraguay.',
            'lead'=>'Un hogar temporal ofrece cuidado mientras se busca una solución estable. Antes de recibir al perro, dejá claro quién sigue siendo responsable y qué podés sostener.',
            'sections'=>[
                ['Un acuerdo antes de recibirlo', '<p>Anotá quién entrega y quién recibe, período previsto, contacto de respaldo, condiciones conocidas y qué pasará si el cuidado necesita extenderse. El hogar temporal no confirma por sí solo una adopción ni transfiere automáticamente todas las decisiones.</p><p>Perro difunde avisos; no dispone de una red garantizada de hogares temporales, transporte ni atención financiada. Consultá con la persona responsable del caso.</p>'],
                ['Repartí los gastos y las decisiones', '<ul><li>Quién aporta el alimento y cómo se repone.</li><li>Quién coordina y paga las consultas; qué hacer ante una urgencia.</li><li>Quién organiza traslados y tiene autorización para decisiones de cuidado.</li><li>Quién evalúa a interesados y acuerda la entrega definitiva.</li><li>Qué información puede compartirse y quién actualiza el aviso.</li></ul><p>Para apoyo de alimento, pedí el producto, cantidad y frecuencia que el responsable necesita; confirmá con él las entregas reales. No publiques una colecta con beneficiarios o totales que no estén verificados.</p>'],
                ['Prepará el espacio y la salida', '<p>Comprobá portones, descanso protegido y cómo separar espacios si ya hay otros animales. Pedí información de salud y convivencia, y orientación veterinaria cuando corresponda. Guardá registros privados de gastos acordados y comunicaciones, sin publicar contactos ajenos.</p><p>Revisá semanalmente si el plan sigue funcionando; este es un intervalo de organización propuesto, no una garantía de adopción. Acordá un relevo antes de llegar a una situación que no puedas mantener.</p>'],
                ['Publicá con permiso y actualizá', '<p>Si estás autorizado, seleccioná “Hogar temporal” como relación en <a href="/dar-perro-en-adopcion">publicar un aviso</a>. Explicá la situación sin domicilios ni información privada. Conservá la referencia para <a href="/como-funciona">corregir o cerrar la ficha</a> cuando cambie el caso.</p>'],
            ], 'faq'=>[
                ['¿El hogar temporal recibe un pago de Perro?', 'No. Perro no promete pagos ni reembolsos. Cualquier reparto de gastos debe acordarse directamente con el responsable antes de recibir al perro.'],
                ['¿Qué hago si no puedo continuar?', 'Avisá con tiempo al responsable y usá el contacto de respaldo acordado. No abandones al perro ni lo entregues a una persona sin verificar su relación con el caso.'],
            ], 'sources'=>['foster'], 'mascota'=>[['/productos/comida-para-perros/', 'Cómo elegir alimento para perros']],
        ],
        'reubicacion-responsable' => [
            'title'=>'Dar un perro en adopción responsablemente en Paraguay',
            'description'=>'Cómo preparar un aviso honesto, conversar con interesados y organizar una entrega segura sin ventas ni pagos obligatorios en Paraguay.',
            'lead'=>'Buscar otro hogar requiere información clara y tiempo. Conservá el cuidado del perro mientras se verifica una alternativa; publicar una ficha no garantiza que aparezca una familia.',
            'sections'=>[
                ['Aclarar la situación primero', '<p>Contá el motivo real y qué ayuda necesitás. Si la dificultad es de convivencia o cuidado, buscá orientación adecuada antes de decidir. Si encontraste un perro, intentá identificar a su responsable y seguí los <a href="/perros-perdidos-paraguay#encontre-un-perro">pasos para un perro encontrado</a>; no lo presentes inmediatamente como disponible.</p>'],
                ['Un aviso que permita decidir', '<ul><li>Usá fotos actuales del perro con permiso, sin documentos, teléfonos ni direcciones visibles.</li><li>Indicá ciudad y departamento; guardá la ubicación exacta para una coordinación privada.</li><li>Separá datos conocidos de estimaciones de edad, raza y tamaño.</li><li>Explicá cuidados, antecedentes y dificultades de convivencia con honestidad.</li><li>Aclará qué requisitos se conversarán y quién puede autorizar la entrega.</li></ul><p>Completá <a href="/dar-perro-en-adopcion">el formulario gratuito</a>. Elegí publicar tu nombre y WhatsApp únicamente si querés dar esos permisos; son opciones separadas.</p>'],
                ['Conversar con interesados', '<p>Preguntá por la rutina, el hogar, los animales presentes y quién asumirá el cuidado. Explicá lo que necesitás comprobar y coordiná un encuentro seguro. No uses depósitos, señas ni donaciones obligatorias como prueba de compromiso.</p><p>Las personas deben conocer la información relevante para decidir. No ocultes tratamientos, incidentes o incertidumbres para conseguir una entrega más rápida.</p>'],
                ['Entrega y seguimiento', '<p>Usá la <a href="/seguridad">lista de entrega segura</a>, conservá lo acordado de forma privada y fijá un contacto posterior. Cuando la adopción esté confirmada, escribí al equipo con tu referencia para cerrar el aviso. No marques un resultado solo porque alguien preguntó.</p><p>Si el plazo se alarga, conversá sobre un <a href="/hogar-temporal">hogar temporal acordado</a>. Perro no tiene custodia ni promete recoger animales.</p>'],
            ], 'faq'=>[
                ['¿Publicar garantiza que el perro será adoptado?', 'No. La ficha pasa por revisión y la disponibilidad debe mantenerse al día. El responsable conserva el cuidado mientras busca una solución.'],
                ['¿Puedo pedir una donación para entregarlo?', 'En Perro no se permiten donaciones obligatorias, señas ni pagos para reservar o entregar un perro. La adopción y la publicación son gratuitas.'],
            ], 'sources'=>['rehoming'],
        ],
        'primeros-dias-perro-adoptado' => [
            'title'=>'Primeros días con un perro adoptado: plan y seguimiento',
            'description'=>'Prepará la llegada de un perro adoptado, su información de cuidado, presupuesto y seguimiento. Lista práctica para hogares en Paraguay.',
            'lead'=>'La entrega es el comienzo. Prepará una rutina que puedas observar, guardá la información disponible y mantené un contacto de seguimiento con el responsable anterior.',
            'sections'=>[
                ['Antes de llegar a casa', '<p>Confirmá traslado seguro, identificación y un lugar tranquilo de descanso. Pedí información de alimento, horarios, medicación indicada por su veterinario y registros disponibles. Comprobá portones y acordá quién estará presente para recibirlo.</p><p>Consultá con un veterinario los cuidados particulares; no cambies tratamientos ni improvises dosis a partir de una ficha.</p>'],
                ['Una llegada tranquila', '<p>Dejá que conozca el espacio de forma gradual, sin visitas ni encuentros forzados. Prepará agua, descanso y una rutina consistente. Supervisá la convivencia con niños y otros animales; si hace falta, mantené espacios separados y pedí orientación.</p><p>No hay un número de días que garantice adaptación. Anotá lo que observás para distinguir lo conocido de lo que todavía necesita ayuda.</p>'],
                ['Tu presupuesto de adopción', '<p>Completá esta lista con presupuestos actuales, no con precios supuestos:</p><ul><li><strong>Una vez:</strong> traslado, collar o arnés, correa, recipientes y lugar de descanso.</li><li><strong>Cada mes:</strong> alimento adecuado, higiene y apoyo de cuidado si lo necesitás.</li><li><strong>Programado:</strong> consultas y cuidados acordados con el veterinario.</li><li><strong>Reserva:</strong> dinero separado para imprevistos.</li></ul><p>Para alimento, compará costo por kilo y consumo indicado para ese perro. Si la bolsa cuesta A y contiene B kilos, A dividido B es el costo por kilo; multiplicalo por el consumo mensual estimado. El cálculo no decide una dieta.</p>'],
                ['Seguimiento propuesto, sin prometer resultados', '<p>Acordá una primera conversación después de la llegada y otra cuando hayan podido observar la rutina. Como agenda opcional, pueden elegir días 2, 7 y 30; no es un calendario médico ni una regla de adaptación.</p><ul><li>¿Come, descansa y se desplaza como esperaba la información recibida?</li><li>¿Hay dificultades de convivencia o cuidado que requieren ayuda?</li><li>¿Se concretó la consulta acordada?</li><li>¿La adopción está confirmada y puede cerrarse el aviso?</li></ul><p>Informá al equipo solo el resultado confirmado y si Perro contribuyó. Un clic en WhatsApp no equivale a una adopción. Compartir fotos o una historia de resultado requiere permiso adicional; no publicamos historias inventadas.</p>'],
                ['Cuándo pedir ayuda profesional', '<p>Si hay dificultad para respirar, convulsiones, colapso o una lesión importante, buscá atención veterinaria inmediata. No esperes una respuesta del equipo de Perro ni una aprobación del aviso. Para cambios menos urgentes, anotá cuándo empezaron y consultá al profesional.</p>'],
            ], 'faq'=>[
                ['¿Tengo que esperar un mes para confirmar la adopción?', 'No. Avisá cuando el resultado esté confirmado. La agenda de seguimiento es un acuerdo práctico y puede ajustarse a cada caso.'],
                ['¿Perro publica automáticamente la historia del resultado?', 'No. El cierre del aviso y el permiso para una historia o fotos son cosas diferentes. Solo se puede preparar una historia real con autorización.'],
            ], 'sources'=>['arrival','emergency'], 'mascota'=>[
                ['/productos/comida-para-perros/', 'Cómo elegir comida para perros'],
                ['/cuidados/elegir-veterinaria/', 'Qué comprobar al elegir una veterinaria'],
                ['/cuidados/vomitos-en-perros/', 'Vómitos en perros: señales y consulta'],
            ],
        ],
        'seguridad' => [
            'title'=>'Adopción segura de perros en Paraguay',
            'description'=>'Lista para verificar un aviso, conocer al perro y acordar una entrega segura: sin señas, ventas ni donaciones obligatorias en Perro Paraguay.',
            'lead'=>'Una ficha revisada ayuda a conversar; verificá los datos directamente antes de entregar o recibir un perro. La adopción es gratuita.',
            'sections'=>[
                ['Antes del encuentro', '<ul><li>Abrí la ficha vigente y comprobá el código, estado y zona.</li><li>Pedí información sobre salud, rutina, conducta y motivo de adopción.</li><li>Confirmá la relación de la persona con el perro. Una foto o una referencia no prueban autorización.</li><li>No envíes dinero para reservar ni aceptes donaciones obligatorias.</li><li>No compartas documentos, claves o domicilios en publicaciones abiertas.</li></ul>'],
                ['Durante el encuentro y la entrega', '<p>Elegí un lugar seguro, andá acompañado si podés y coordiná el traslado. Observá sin forzar interacciones. Si algo no coincide con lo explicado, detené la decisión y pedí aclaraciones.</p><ul><li>Confirmá qué información de salud es conocida y pedí los registros disponibles.</li><li>Dejá por escrito fecha de entrega, datos relevantes y contacto de seguimiento en un canal privado.</li><li>Acordá quién asumirá los cuidados y qué harán si surge una dificultad.</li><li>Planificá las presentaciones con animales del hogar y buscá ayuda profesional si hay señales de riesgo.</li></ul>'],
                ['Después y si hay un problema', '<p>Prepará los <a href="/primeros-dias-perro-adoptado">primeros días</a> y coordiná la revisión veterinaria. Para un aviso con cobros, datos engañosos o permisos dudosos, usá “Reportar” en la ficha. Perro revisa el reporte; no es un servicio de emergencia ni reemplaza una denuncia ante la autoridad.</p><p>Si sospechás maltrato o un delito, consultá los canales oficiales vigentes. La Ley 7513/2025 regula bienestar y protección animal en Paraguay. No publiques acusaciones ni datos privados para intentar resolver el caso.</p>'],
            ], 'faq'=>[
                ['¿La aprobación del aviso garantiza al perro?', 'No. Perro no garantiza identidad, salud, conducta ni disponibilidad. Verificá la información con el responsable y el profesional que corresponda.'],
                ['¿Qué hago si me piden una seña?', 'No pagues para reservar un perro. Reportá el aviso y conservá la información de forma privada para la revisión correspondiente.'],
            ], 'sources'=>['law'],
        ],
        'apoyar' => [
            'title'=>'Cómo apoyar Perro y mantener gratuita la adopción',
            'description'=>'Apoyá avisos reales y actualizados. Conocé las reglas de futuros aportes voluntarios y patrocinios; la adopción y la publicación siguen gratuitas.',
            'lead'=>'Hoy podés ayudar difundiendo fichas vigentes, corrigiendo información y avisando resultados confirmados. Perro no recibe pagos ni donaciones desde esta página.',
            'sections'=>[
                ['Ayuda útil que podés dar hoy', '<ul><li>Compartí el enlace del aviso vigente; abrilo de nuevo antes de difundirlo.</li><li>Publicá un caso real con permiso y una historia clara.</li><li>Avisá si el perro fue adoptado o reencontrado, o si cambió la disponibilidad.</li><li>Reportá ventas, cobros obligatorios o información que requiera revisión.</li></ul><p><a href="/como-funciona">Cómo publicar y mantener un aviso</a> explica cada paso.</p>'],
                ['Aportes voluntarios: reglas antes de habilitarlos', '<p>Un futuro aporte para operar Perro deberá identificar al operador real, el destino, el medio de pago y cómo se informa su uso. Apoyar la operación del sitio es distinto de donar a un refugio o rescate: cualquier beneficiario externo debe identificarse y autorizarse por separado.</p><p>No afirmamos condición de entidad benéfica, deducciones fiscales, beneficiarios ni recaudaciones. No hay un medio de cobro habilitado aquí. Una donación nunca será requisito para ver, publicar, reservar o adoptar.</p>'],
                ['Productos y espacios patrocinados', '<p>Si más adelante se ofrecen productos, la compra será opcional y deberá informar vendedor, precio, entrega y devoluciones. Los espacios veterinarios pagados se identificarán como “Patrocinado”; pagar no decidirá una adopción ni equivaldrá a una recomendación clínica.</p><p>Perro y Mascota.com.py pertenecen al mismo propietario. Mascota se dedica a información de cuidado; Perro ayuda con avisos y procesos de adopción y reencuentro. Los acuerdos comerciales requieren empresas reales, condiciones revisadas y configuración del operador antes de activarse.</p>'],
            ], 'faq'=>[], 'sources'=>[],
        ],
    ];
}

function editorial_sources(): array
{
    return [
        'law'=>['BACN · Ley 7513/2025 de bienestar y protección animal', 'https://www.bacn.gov.py/leyes-paraguayas/12783/ley-n-75132025-de-bienestar-y-proteccion-animal'],
        'company'=>['RSPCA · Compañía y convivencia de perros', 'https://www.rspca.org.uk/adviceandwelfare/pets/dogs/company'],
        'foster'=>['RSPCA · Cuidado temporal de perros', 'https://science.rspca.org.uk/en/web/rspca/findapet/foster/dog'],
        'rehoming'=>['RSPCA · Reubicación responsable', 'https://www.rspca.org.uk/adviceandwelfare/pets/givingupapet/giving-up-a-dog-for-adoption'],
        'arrival'=>['RSPCA · Preparar la llegada de un perro rescatado', 'https://www.rspca.org.uk/documents/d/rspca/getting-ready-for-your-rescue-dog'],
        'emergency'=>['Merck Veterinary Manual · Cuándo acudir al veterinario', 'https://www.merckvetmanual.com/multimedia/table/when-to-see-a-veterinarian'],
        'lost'=>['RSPCA · Perros perdidos y encontrados', 'https://www.rspca.org.uk/adviceandwelfare/pets/lost/dog'],
    ];
}

function editorial_modified(string $route): ?string
{
    // Keep legal pages without an asserted date until their content is substantively updated.
    return isset(editorial_pages()[$route]) || in_array($route, ['', 'perros', 'cachorros-en-adopcion', 'perros-de-raza-en-adopcion', 'perros-perdidos-paraguay', 'dar-perro-en-adopcion', 'como-funciona'], true) ? '2026-10-09' : null;
}

function editorial_faq(array $questions): void
{
    if (!$questions) return;
    echo '<h2>Preguntas frecuentes</h2>';
    foreach ($questions as [$question, $answer]) echo '<details class="guide-faq"><summary>' . h($question) . '</summary><p>' . h($answer) . '</p></details>';
}

function editorial_source_links(array $ids): void
{
    if (!$ids) return;
    echo '<aside class="editorial-sources"><h2>Fuentes y alcance</h2><p>Consultadas el 9 de octubre de 2026. Orientación general; no afirmamos revisión profesional de esta guía. Las fuentes extranjeras orientan el cuidado, no definen trámites ni servicios disponibles en Paraguay.</p><ul>';
    foreach ($ids as $id) { [$label, $url] = editorial_sources()[$id]; echo '<li><a href="' . h($url) . '">' . h($label) . '</a></li>'; }
    echo '</ul></aside>';
}

function render_guide_links(string $heading): void
{
    echo '<section class="section section-note"><div class="shell"><h2>' . h($heading) . '</h2><div class="editorial-grid">';
    foreach (['requisitos-para-adoptar', 'elegir-perro', 'hogar-temporal', 'reubicacion-responsable', 'primeros-dias-perro-adoptado', 'seguridad'] as $slug) {
        $page = editorial_pages()[$slug];
        echo '<article><h3><a href="/' . h($slug) . '">' . h($page['title']) . '</a></h3><p>' . h($page['description']) . '</p></article>';
    }
    echo '</div><p><a class="text-link" href="/guias-adopcion">Todas las guías para adoptar y ayudar →</a></p></div></section>';
}

function render_editorial(string $path): void
{
    $page = editorial_pages()[$path];
    render_header(page_meta($page['title'] . ' | Perro', $page['description'], $path));
    echo '<section class="page-hero compact"><div class="shell"><nav class="breadcrumbs" aria-label="Ruta de navegación"><a href="/">Inicio</a> / <a href="/guias-adopcion">Guías</a></nav><h1>' . h($page['title']) . '</h1><p>' . h($page['lead']) . '</p><div class="button-row"><a class="button" href="/perros">Ver avisos en adopción</a><a class="button button-secondary" href="/dar-perro-en-adopcion">Publicar un aviso gratis</a></div></div></section><section class="section"><article class="shell prose"><p class="editorial-date">Actualizado el 9 de octubre de 2026 · Equipo de Perro</p><nav class="guide-toc" aria-label="En esta guía"><strong>En esta guía</strong><ul>';
    foreach ($page['sections'] as $i => [$heading]) echo '<li><a href="#paso-' . ($i + 1) . '">' . h($heading) . '</a></li>';
    echo '</ul></nav>';
    foreach ($page['sections'] as $i => [$heading, $html]) echo '<section id="paso-' . ($i + 1) . '"><h2>' . h($heading) . '</h2>' . $html . '</section>';
    editorial_faq($page['faq']);
    global $config;
    if (!empty($page['mascota']) && !empty($config['mascota_guides_enabled'])) {
        echo '<aside class="notice"><h2>Para profundizar en los cuidados</h2><p>En Mascota.com.py, del mismo propietario que Perro, podés consultar estas guías complementarias:</p><ul>';
        foreach ($page['mascota'] as [$url, $label]) echo '<li><a href="https://mascota.com.py' . h($url) . '">' . h($label) . '</a></li>';
        echo '</ul></aside>';
    }
    editorial_source_links($page['sources']);
    echo '<p><a class="text-link" href="/guias-adopcion">Volver a las guías</a> · <a class="text-link" href="/como-funciona">Cómo publicar y actualizar un aviso</a></p></article></section>';
    if ($path === 'guias-adopcion') render_guide_links('Elegí tu siguiente paso');
    render_footer();
}

function render_discovery_guidance(string $path): void
{
    echo '<section class="section section-note"><article class="shell prose">';
    if ($path === 'perros-perdidos-paraguay') {
        echo '<h2 id="perdi-un-perro">Perdí un perro: pasos para buscarlo</h2><ol><li>Anotá cuándo y en qué zona lo viste por última vez. Revisá lugares cercanos y avisá a personas de confianza.</li><li>Prepará una foto reciente, rasgos reconocibles y ciudad o barrio; evitá publicar tu domicilio y datos privados.</li><li><a href="/dar-perro-en-adopcion?type=lost">Enviá un aviso perdido</a> con fecha y última ubicación. La publicación espera revisión; no frena la búsqueda.</li><li>Revisá los avisos encontrados y pedí detalles verificables en privado antes de coordinar un encuentro. No transfieras dinero por supuestas pruebas.</li><li>Cuando se confirme el reencuentro, avisá al equipo con la referencia para cerrar la ficha.</li></ol><h2 id="encontre-un-perro">Encontré un perro: buscá a su responsable</h2><ol><li>No te expongas ni lo fuerces a acercarse. Si está lesionado o hay peligro, buscá ayuda adecuada y atención veterinaria.</li><li>Si podés hacerlo con seguridad, comprobá identificación visible y consultá con un veterinario si puede revisar un microchip. No prometemos que todos los animales lo tengan.</li><li><a href="/dar-perro-en-adopcion?type=found">Publicá un aviso encontrado</a> con foto, fecha y zona. Reservá algún detalle para verificar a quien lo reclame.</li><li>Pedí fotos anteriores y rasgos coincidentes; coordiná una entrega segura. No lo ofrezcas inmediatamente en adopción.</li><li>Si nadie lo reconoce, consultá con la autoridad o un responsable competente antes de decidir una reubicación.</li></ol>';
        editorial_faq([['¿El aviso se publica inmediatamente?', 'No. Todos los envíos esperan revisión. Continuá la búsqueda y las medidas de seguridad mientras el equipo revisa los datos.'], ['¿Puedo publicar mi teléfono?', 'El formulario ofrece un permiso opcional para mostrar WhatsApp. Si lo mantenés privado, las consultas se canalizan por el equipo; no lo escribas en la historia ni en las fotos.']]);
        editorial_source_links(['lost']);
    } elseif ($path === 'cachorros-en-adopcion') {
        echo '<h2>Adoptar un cachorro requiere un plan</h2><p>Preguntá qué edad es conocida, qué alimento recibe y qué información de salud existe. Organizá tiempo, seguridad y una consulta veterinaria para sus cuidados particulares. No se garantiza tamaño adulto ni raza por una foto.</p><p>Compará con un adulto en <a href="/elegir-perro">cómo elegir un perro</a>, revisá los <a href="/requisitos-para-adoptar">requisitos</a> y prepará los <a href="/primeros-dias-perro-adoptado">primeros días</a>. Si no hay avisos vigentes, podés volver al catálogo completo; no inventamos cachorros para llenar esta página.</p>';
        editorial_faq([['¿Hay cachorros gratis en Perro?', 'La adopción y la publicación son gratuitas. La disponibilidad depende de avisos reales vigentes y los cuidados tienen costos. No pagues señas para reservar.']]);
    } elseif ($path === 'perros-de-raza-en-adopcion') {
        echo '<h2>Adoptar por compatibilidad, con raza orientativa</h2><p>Estos avisos tienen una raza declarada por su responsable y no están marcados como mestizos. No es una certificación. Consultá por el individuo, su rutina y su historial. No creamos páginas de labrador, husky u otra raza sin contenido e inventario que las justifiquen.</p><p>Revisá <a href="/elegir-perro">cómo elegir un perro</a> y el <a href="/perros">catálogo completo, incluidos mestizos</a>.</p>';
        editorial_faq([['¿Se permite pedir un precio por un perro de raza?', 'No. Perro no publica ventas ni permite señas o donaciones obligatorias para entregar un perro.']]);
    } else {
        echo '<h2>Cómo adoptar un perro en Paraguay desde Perro</h2><ol><li>Revisá los <a href="/requisitos-para-adoptar">requisitos y preguntas</a>.</li><li>Abrí un aviso vigente, comprobá su estado y conversá sobre el perro y tu hogar.</li><li>Coordiná una <a href="/seguridad">entrega segura</a>; no pagues para reservar.</li><li>Prepará la llegada y confirmá el resultado para actualizar el aviso.</li></ol><p>Para cachorros, adultos o mestizos, priorizá las necesidades reales. <a href="/elegir-perro">Elegí según tu rutina</a>. Si necesitás buscar otro hogar, seguí la <a href="/reubicacion-responsable">reubicación responsable</a> antes de publicar.</p>';
        editorial_faq([['¿Una consulta por WhatsApp reserva al perro?', 'No. Abre un borrador para conversar sobre esa ficha. La persona responsable confirma disponibilidad y acuerda la adopción; un clic no confirma un resultado.']]);
    }
    echo '<p><a href="/guias-adopcion">Más guías para adoptar y ayudar</a></p></article></section>';
}

function render_editorial_schema(array $meta): bool
{
    $path = request_path();
    $page = editorial_pages()[$path] ?? null;
    $discovery = in_array($path, ['perros', 'cachorros-en-adopcion', 'perros-de-raza-en-adopcion', 'perros-perdidos-paraguay'], true);
    if (!$page && !$discovery) return false;
    $type = $discovery || $path === 'guias-adopcion' ? 'CollectionPage' : ($path === 'apoyar' ? 'WebPage' : 'Article');
    $node = ['@type'=>$type, '@id'=>$meta['canonical'] . '#contenido', 'url'=>$meta['canonical'], 'name'=>$meta['title'], 'description'=>$meta['description'], 'inLanguage'=>'es-PY'];
    if ($type === 'Article') { $node['headline'] = $page['title']; if ($path !== 'seguridad') $node['datePublished'] = '2026-10-09'; $node['dateModified'] = editorial_modified($path); $node['author'] = ['@type'=>'Organization', 'name'=>'Equipo de Perro']; }
    // FAQs stay visible only; no claim to Google's restricted FAQ rich result eligibility.
    $crumbs = [['@type'=>'ListItem','position'=>1,'name'=>'Inicio','item'=>app_url()]];
    if ($page && $path !== 'guias-adopcion' && $path !== 'apoyar') $crumbs[] = ['@type'=>'ListItem','position'=>2,'name'=>'Guías','item'=>app_url('guias-adopcion')];
    $crumbs[] = ['@type'=>'ListItem','position'=>count($crumbs)+1,'name'=>$page['title'] ?? preg_replace('/ \| Perro$/','',$meta['title']),'item'=>$meta['canonical']];
    $graph = [$node, ['@type'=>'BreadcrumbList','itemListElement'=>$crumbs]];
    echo '<script type="application/ld+json">' . json_encode(['@context'=>'https://schema.org','@graph'=>$graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
    return true;
}
