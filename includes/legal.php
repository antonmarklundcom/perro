<?php

declare(strict_types=1);

const PERRO_TERMS_VERSION = '2026-10-07';
const PERRO_PRIVACY_VERSION = '2026-10-07';

// Compatible editorial revisions retain earlier explicit consent. Material changes
// must remove incompatible versions here and require a new owner submission.
const PERRO_COMPATIBLE_TERMS = ['2026-10-05'];
const PERRO_COMPATIBLE_PRIVACY = ['2026-10-05'];

function legal_contact_html(): string
{
    global $config;
    $html = '<p>Canal de consultas, reclamos y solicitudes de privacidad: <a href="' . h(project_whatsapp_url('Hola, quisiera hacer una consulta o solicitud de privacidad sobre Perro.com.py.')) . '" rel="noopener noreferrer">WhatsApp del equipo de Perro</a>.';
    $email = filter_var($config['privacy_email'] ?? '', FILTER_VALIDATE_EMAIL);
    if ($email) {
        $html .= ' También podés escribir a <a href="mailto:' . h($email) . '">' . h($email) . '</a>.';
    }
    $html .= '</p>';
    if (!empty($config['operator_name'])) {
        $html .= '<p><strong>Responsable de la plataforma y del tratamiento de datos:</strong> ' . h($config['operator_name']) . '.';
        if (!empty($config['operator_address'])) $html .= ' Domicilio de contacto: ' . h($config['operator_address']) . '.';
        if (!empty($config['operator_ruc'])) $html .= ' RUC: ' . h($config['operator_ruc']) . '.';
        $html .= '</p>';
    }
    return $html;
}

function legal_pages(): array
{
    $contact = legal_contact_html();
    return [
        'terminos' => ['Términos de uso', 'Reglas para publicar avisos gratuitos y conectar con responsabilidad.',
            '<p>Versión ' . PERRO_TERMS_VERSION . '.</p>' . $contact . '
            <h2>Qué hace Perro</h2><p>Perro.com.py difunde avisos de adopción, perros perdidos y perros encontrados en Paraguay. Publicar y consultar es gratuito. No ofrecemos animales en venta ni servicios de cría. La persona responsable conserva el cuidado del animal; publicar un aviso no transfiere su custodia a la plataforma.</p>
            <h2>Quién puede publicar</h2><p>Debés tener al menos 18 años y ser responsable del animal o contar con autorización para difundir el aviso. Indicá tu relación con él y contá solo lo que sabés. Si no conocés su historia, salud o raza, decilo. No publiques identidades, domicilios exactos ni teléfonos de otras personas sin autorización.</p>
            <h2>Adopción gratuita</h2><p>No admitimos ventas, ofertas de criaderos, servicios de reproducción, señas, pagos para reservar, canjes ni cobros disfrazados de adopción. Una donación obligatoria para acceder a un perro también está prohibida. Los costos normales de su cuidado no autorizan a cobrar por entregarlo mediante esta plataforma.</p>
            <h2>Revisión y fotografías</h2><p>Los avisos quedan pendientes hasta su revisión. Podemos pedir aclaraciones, rechazar o retirar contenido que incumpla estas reglas. Una ficha revisada no certifica identidad, pedigrí, salud, conducta o disponibilidad. Debés tener permiso para usar las fotos y evitar rostros de menores, documentos, patentes y otros datos ajenos. Nos autorizás, de forma no exclusiva y limitada a este servicio, a almacenar, optimizar y mostrar el contenido aprobado. No recibimos derechos para vender tus fotos. Podés solicitar su retiro.</p>
            <h2>Antes de adoptar o entregar un animal</h2><p>Verificá a la persona responsable, conocé al perro de forma segura, preguntá por salud y carácter y acordá por escrito los cuidados y la entrega cuando corresponda. Perro no toma la decisión final de adopción ni sustituye a un veterinario o a las autoridades. No garantizamos el resultado de una adopción. Estas reglas no excluyen responsabilidades ni derechos que la ley reconozca.</p>
            <h2>Perdidos y encontrados</h2><p>Publicar acá no reemplaza un aviso o denuncia ante las autoridades. La Ley 7513/2025, artículo 16, exige al propietario comunicar pérdida, sustracción o desaparición a la Dirección de Bienestar Animal, la Policía Nacional o el Ministerio Público dentro de 72 horas. Un perro encontrado se publica para buscar a su responsable; no se ofrece en adopción hasta aclarar su situación. Verificá pruebas de propiedad de forma privada y no reveles todos los rasgos identificatorios del animal.</p>
            <h2>Actualizaciones y retiro</h2><p>Los avisos tienen una vigencia inicial de 60 días. Avisanos con la referencia cuando el animal sea adoptado, reencontrado o deje de estar disponible, o si necesitás corregir información. También podés reportar una ficha desde su página. Por seguridad podemos verificar que tengas relación con el aviso antes de modificar datos. La plataforma no es un canal de emergencias: ante riesgo o maltrato, contactá a las autoridades.</p>
            <h2>Aportes y cambios</h2><p>Apoyar al proyecto siempre debe ser opcional y no afecta la aprobación de avisos ni el acceso a una adopción. Antes de habilitar cobros se debe identificar al receptor, el destino y las condiciones del aporte. Las modificaciones de estas reglas tendrán fecha y versión; una nueva publicación debe aceptar la versión vigente.</p>
            <p>Consultá la <a href="/privacidad">política de privacidad</a> y la <a href="https://www.bienestaranimal.gov.py/ley-n7513-de-bienestar-y-proteccion-animal/" rel="noopener noreferrer">autoridad de bienestar animal</a>. Se aplica la legislación paraguaya, sin renuncia a derechos obligatorios.</p>'],
        'privacidad' => ['Privacidad', 'Tu nombre y tu contacto son privados por defecto.',
            '<p>Versión ' . PERRO_PRIVACY_VERSION . '.</p>' . $contact . '
            <h2>Datos que recibimos y para qué</h2><p>Recibimos tu nombre, correo, WhatsApp, relación con el animal, datos del aviso, fotos y las confirmaciones del formulario. Los usamos para revisar el aviso, comunicarnos con vos, gestionar correcciones y prevenir abusos. Registramos la fecha y versión de las reglas aceptadas y tus permisos de publicación. Los reportes incluyen el motivo y, si lo proporcionás, un contacto. No uses estos campos para enviar documentos de identidad, datos bancarios ni información sensible de otras personas.</p>
            <h2>Lo privado y lo público</h2><p>Tu nombre completo, correo y WhatsApp se mantienen privados para la administración por defecto. Si querés mostrar un nombre o alias, debés autorizarlo por separado y escribir el nombre público elegido. Mostrar el WhatsApp requiere otro permiso independiente. El correo nunca forma parte de la ficha pública. Los datos del animal, ciudad, descripción y fotos aprobadas sí son públicos: no incluyas información personal privada en esos campos o dentro de las imágenes. Los buscadores, redes y terceros pueden copiar contenido publicado; no podemos garantizar la eliminación de copias ajenas.</p>
            <h2>Quién accede y dónde se procesa</h2><p>El equipo autorizado accede a los datos privados para administrar el servicio. El sitio se aloja en Hostinger; el proveedor y sus servicios de infraestructura procesan datos necesarios para alojarlo y mantenerlo. Puede existir procesamiento fuera de Paraguay según la infraestructura contratada. Los registros técnicos del alojamiento pueden incluir direcciones IP y datos de solicitudes. No vendemos los datos de quienes publican. Podemos entregar información ante una obligación o requerimiento legal válido.</p>
            <h2>WhatsApp, cookies y fotos</h2><p>Los enlaces de WhatsApp abren un servicio externo con sus propias condiciones. El sitio usa una cookie de sesión para seguridad, formularios y acceso de administración; no contiene funciones de publicidad o medición de terceros en esta versión. Para limitar abusos guardamos identificadores derivados de la IP y contadores, sin registrar la IP en texto en esos controles. La ventana de bloqueo administrativo es de 15 minutos; la de envíos y reportes es de una hora. Los contadores vencidos se depuran al procesar nuevas solicitudes. Las fotos aceptadas se convierten en copias optimizadas sin los metadatos originales; su contenido visible puede seguir identificando personas o lugares.</p>
            <h2>Consultas y resultados</h2><p>Contamos los clics en el botón de contacto por aviso, de forma agregada y sin guardar identificadores del visitante, IP ni cookies de medición. El contador no demuestra que se haya enviado un mensaje ni concretado una adopción. La administración registra el resultado y si la persona responsable confirmó que fue por Perro.</p><h2>Conservación y seguridad</h2><p>La vigencia pública inicial de un aviso es de 60 días; vencer o retirarlo no elimina automáticamente el registro interno. Conservamos registros mientras sean necesarios para gestionar avisos, atender reclamos, prevenir abusos o cumplir obligaciones legales. Revisamos la necesidad de conservarlos al tramitar un pedido de supresión. El alojamiento puede conservar copias de respaldo según su configuración. El acceso privado requiere autenticación, pero ningún sistema garantiza seguridad absoluta. No prometemos una supresión automática que esta versión no implementa.</p>
            <h2>Tus solicitudes</h2><p>Podés pedir acceso, corrección, retiro del aviso, supresión o revocar el permiso para mostrar tu nombre o WhatsApp mediante el canal indicado arriba. Indicá la referencia y el cambio solicitado. Verificaremos tu relación con el aviso usando la mínima información necesaria; no envíes tu cédula por un formulario público. Retirar permisos impide nuevas publicaciones autorizadas de esos datos; copias externas o respaldos pueden requerir tratamiento separado. Si debemos conservar información por una obligación o reclamo, te explicaremos el motivo.</p>
            <h2>Menores y aportes</h2><p>Este formulario es para mayores de 18 años. Si detectás datos de un menor publicados sin autorización, avisá para que los revisemos. En un futuro aporte, no mostrar tu nombre debe ser la opción inicial; cualquier reconocimiento público necesita permiso separado. Anonimato público no implica que el receptor o proveedor de pago no deba identificar al aportante. No hay un formulario de pago ni una lista de donantes activos en esta versión.</p>
            <h2>Marco legal</h2><p>Respetamos las garantías paraguayas de intimidad y hábeas data. La Ley 7593/2025 prevé su entrada en vigor 24 meses después de su publicación oficial; esta política no la presenta como plenamente vigente antes de ese plazo. Podés consultar su <a href="https://www.bacn.gov.py/leyes-paraguayas/12924/ley-n-75932025-de-proteccion-de-datos-personales-en-la-republica-del-paraguay" rel="noopener noreferrer">texto oficial</a>. Tus derechos legales no dependen de aceptar publicidad o de mostrar tu identidad.</p>'],
    ];
}
