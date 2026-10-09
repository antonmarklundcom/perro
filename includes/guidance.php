<?php

declare(strict_types=1);

function render_how_it_works(): void
{
    global $config;
    $expiryDays = (int) $config['listing_expiry_days'];
    $teamUrl = project_whatsapp_url('Hola, necesito ayuda con un aviso de Perro. Mi referencia es: ');
    render_header(page_meta('Cómo funciona Perro | Perro', 'Publicá sin cuenta, cuidá tus datos y compartí una ficha revisada para ayudar a un perro en Paraguay.', 'como-funciona'));
    ?>
    <section class="page-hero compact"><div class="shell">
        <span class="eyebrow">Perro · Paraguay</span>
        <h1>Cómo publicar y actualizar un aviso en Perro</h1>
        <p>Enviás los datos, el equipo revisa y después podés compartir la ficha pública. Sin crear una cuenta y con tu contacto privado por defecto.</p>
        <div class="button-row"><a class="button button-coral" href="<?= h(app_url('dar-perro-en-adopcion')) ?>">Publicá un aviso</a><a class="button button-secondary" href="<?= h(app_url('perros')) ?>">Buscá un perro</a></div>
    </div></section>

    <section class="section section-blue"><div class="shell">
        <div class="section-head"><div><span class="eyebrow">Del envío a la publicación</span><h2>Así funciona</h2></div></div>
        <div class="steps guide-flow">
            <article><span aria-hidden="true">1</span><h3>Enviá la ficha</h3><p>Completá el formulario desde tu celular con la historia del perro, su zona, fotos que tengas permiso de usar y tu WhatsApp privado. No necesitás una cuenta.</p></article>
            <article><span aria-hidden="true">2</span><h3>Guardá la referencia</h3><p>Al terminar, la pantalla de confirmación muestra la referencia de tu envío. Copiala o guardá una captura: te ayuda a consultar, corregir o retirar el aviso. Todavía no está publicado.</p></article>
            <article><span aria-hidden="true">3</span><h3>Esperá la revisión</h3><p>Diana y el equipo revisan datos, fotos y permisos desde el celular. Pueden editar la ficha, pedirte aclaraciones por WhatsApp, aprobarla o rechazarla. La aprobación no es automática ni está garantizada.</p></article>
        </div>
    </div></section>

    <section class="section"><article class="shell prose">
        <h2>Tu nombre y tu contacto son privados por defecto</h2>
        <p>El equipo usa tus datos para revisar y mantener el aviso. Mostrar un nombre o alias y mostrar tu WhatsApp son permisos opcionales y separados. Si mantenés tu contacto privado, las personas interesadas consultan al equipo de Perro.</p>
        <p>No incluyas teléfonos, documentos, direcciones exactas ni datos ajenos en la historia o las fotos. Los materiales para compartir tampoco deben incluir tu contacto privado. Consultá la <a href="<?= h(app_url('privacidad')) ?>">política de privacidad</a>.</p>

        <h2>¿Querés corregir, retirar o cerrar un aviso?</h2>
        <p>Escribí al WhatsApp del equipo desde el contacto que usaste al enviar. Indicá la referencia y qué necesitás: corregir información, retirar el aviso o un permiso, o avisar que el perro fue adoptado o reencontrado.</p>
        <p>Antes de hacer cambios, el equipo confirma manualmente tu identidad y tu relación con el aviso. La referencia ayuda a encontrarlo; tenerla sola no autoriza a modificarlo.</p>
        <?php if ($teamUrl !== ''): ?><p><a class="button button-whatsapp" href="<?= h($teamUrl) ?>" target="_blank" rel="noopener noreferrer">Escribí al equipo por WhatsApp</a></p><?php endif; ?>

        <h2>Mantené la disponibilidad al día</h2>
        <p>El equipo actualiza el estado cuando recibe tu confirmación. Un aviso adoptado, reencontrado, vencido o retirado deja de aparecer públicamente.</p>
        <p>La vigencia configurada es de <?= h((string) $expiryDays) ?> días. Si el aviso es antiguo, el equipo puede preguntarte si sigue vigente. Solo se renueva después de que vos confirmes que todavía corresponde publicarlo; no se renueva automáticamente.</p>
    </article></section>

    <section class="section section-note"><article class="shell prose">
        <span class="eyebrow">Después de la aprobación</span>
        <h2>Compartí la ficha pública</h2>
        <ol>
            <li><strong>Abrí el aviso vigente.</strong> Revisá su estado y sus datos antes de difundirlo. Compartí el enlace de esa ficha para que otras personas puedan consultar la información actual.</li>
            <li><strong>En Facebook.</strong> Usá la opción para compartir el enlace y su vista previa. También podés copiar el texto para acompañar la publicación; Facebook puede tardar en actualizar una vista previa guardada.</li>
            <li><strong>En Instagram.</strong> Si la ficha tiene una foto disponible y el servidor permite generar imágenes, descargá los materiales para historia y publicación hechos con la foto real del perro. Copiá el texto que acompaña la ficha.</li>
            <li><strong>Subí los archivos desde Instagram.</strong> La descarga no publica por vos. Elegí el archivo en Instagram y pegá el texto. En una historia, agregá un sticker de enlace con la dirección de la ficha.</li>
        </ol>
        <p>Si no hay una foto utilizable o no están disponibles las descargas, podés compartir el enlace de la ficha. Nunca agregues el contacto privado del responsable a las imágenes o al texto.</p>
        <div class="notice">Una imagen descargada, una captura o una copia compartida en otra red no se actualiza cuando cambia el aviso. Antes de consultar o volver a compartir, abrí el enlace y comprobá el estado actual.</div>
    </article></section>

    <section class="section"><article class="shell prose">
        <h2>Adopción gratuita y responsable</h2>
        <p>No aceptamos ventas, ofertas de cría, señas, pagos para reservar ni donaciones obligatorias para entregar al perro. La persona responsable y quien quiere adoptar conversan, verifican la información y acuerdan un encuentro seguro.</p>
        <p>Perro difunde avisos: no tiene la custodia de los animales ni garantiza su identidad, salud, conducta, disponibilidad o el resultado de una adopción. Una ficha aprobada no reemplaza esas verificaciones.</p>
        <p>Leé la <a href="<?= h(app_url('seguridad')) ?>">guía de adopción segura</a> y los <a href="<?= h(app_url('terminos')) ?>">términos de uso</a> antes de coordinar una entrega.</p>
        <div class="button-row"><a class="button button-coral" href="<?= h(app_url('dar-perro-en-adopcion')) ?>">Enviar una ficha</a><a class="button button-secondary" href="<?= h(app_url('perros')) ?>">Ver perros en adopción</a></div>
    </article></section>
    <?php
    render_guide_links('Prepará una adopción responsable');
    render_footer();
}
