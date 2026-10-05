<?php

declare(strict_types=1);

function listing_options(): array
{
    return [
        'listing_type' => ['adoption' => 'Adopción', 'lost' => 'Perro perdido', 'found' => 'Perro encontrado'],
        'age_group' => array_combine(['Cachorro', 'Joven', 'Adulto', 'Senior', 'No se sabe'], ['Cachorro', 'Joven', 'Adulto', 'Senior', 'No se sabe']),
        'sex' => array_combine(['Hembra', 'Macho', 'No se sabe'], ['Hembra', 'Macho', 'No se sabe']),
        'size' => array_combine(['Pequeño', 'Mediano', 'Grande', 'No se sabe'], ['Pequeño', 'Mediano', 'Grande', 'No se sabe']),
        'vaccination_status' => array_combine(['No informado', 'Al día', 'Parcial', 'Sin vacunas'], ['No informado', 'Al día', 'Parcial', 'Sin vacunas']),
        'sterilization_status' => array_combine(['No informado', 'Esterilizado', 'No esterilizado'], ['No informado', 'Esterilizado', 'No esterilizado']),
    ];
}

function listing_fields(): array
{
    return ['listing_type' => 20, 'name' => 80, 'department' => 80, 'city' => 100,
        'age_group' => 30, 'approximate_age' => 60, 'sex' => 20, 'size' => 30, 'breed_label' => 100,
        'description' => 3000, 'health_information' => 1200, 'vaccination_status' => 20,
        'sterilization_status' => 20, 'compatibility' => 800, 'reason' => 1000,
        'adoption_requirements' => 1200, 'last_location' => 180, 'incident_date' => 20];
}

function listing_input_errors(): array
{
    $errors = [];
    foreach (['name', 'department', 'city', 'description'] as $key) {
        if (text($key) === '') $errors[] = 'Completá los datos obligatorios del perro. Si no sabés su nombre, usá «Sin nombre».';
    }
    foreach (listing_options() as $key => $options) {
        $value = text($key);
        if ($value === '' && in_array($key, ['vaccination_status', 'sterilization_status'], true)) continue;
        if (!array_key_exists($value, $options)) $errors[] = 'Elegí una opción válida para tipo, edad, sexo, tamaño y salud.';
    }
    if (preg_match_all('/./us', text('description'), $unused) < 40) $errors[] = 'Contá la historia con al menos 40 caracteres.';
    $date = text('incident_date');
    if ($date !== '') {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date || $date > date('Y-m-d')) $errors[] = 'Ingresá una fecha real que no sea futura.';
    }
    if (in_array(text('listing_type'), ['lost', 'found'], true) && (text('last_location') === '' || $date === '')) {
        $errors[] = 'Para un aviso perdido o encontrado, indicá la fecha y la zona aproximada, sin publicar un domicilio privado.';
    }
    return array_values(array_unique($errors));
}

function publication_fields(array $submission): array
{
    $dog = [];
    foreach (listing_fields() as $key => $limit) $dog[$key] = $submission[$key] ?? '';
    $dog['mixed_breed'] = ($submission['mixed_breed'] ?? false) === true;
    $dog['photos'] = $submission['photos'] ?? [];
    $dog['public_name'] = ($submission['public_name'] ?? false) === true;
    $dog['contact_name'] = $dog['public_name'] ? ($submission['public_display_name'] ?? '') : '';
    $dog['contact_whatsapp'] = ($submission['public_whatsapp'] ?? false) === true ? ($submission['whatsapp'] ?? '') : '';
    return $dog;
}

function policy_accepted(array $record): bool
{
    $consents = $record['consents'] ?? [];
    foreach (['adult_confirm', 'authorized_confirm', 'photo_consent', 'no_sale_confirm'] as $key) {
        if (($consents[$key] ?? false) !== true) return false;
    }
    return ($consents['terms_version'] ?? '') === PERRO_TERMS_VERSION
        && ($consents['privacy_version'] ?? '') === PERRO_PRIVACY_VERSION;
}

function moderate_record(string $dataset, string $id, string $action): array
{
    global $config;
    return with_data_lock(static function () use ($config, $dataset, $id, $action): array {
        $record = find_record($dataset, $id);
        if (!$record) return ['error', 'El registro ya no existe.'];
        if ($dataset === 'submissions' && $action === 'approve') {
            // An approved record is immutable through this action; retries do not
            // republish withdrawn dogs or reset their expiry/contact preferences.
            if (($record['status'] ?? '') === 'approved') return ['success', 'Esta solicitud ya fue aprobada; no se creó otra ficha.'];
            if (($record['status'] ?? '') !== 'pending') return ['error', 'Solo se pueden aprobar solicitudes pendientes.'];
            if (!checked('review_confirm')) return ['error', 'Revisá la ficha, las fotos, los permisos y la adopción gratuita antes de aprobar.'];
            if (!policy_accepted($record)) return ['error', 'Este envío necesita aceptar las reglas actuales. Pedile al responsable un nuevo envío; no aceptes por él.'];
            $dogs = read_dataset('dogs');
            foreach ($dogs as $existing) {
                if (($existing['source_submission_id'] ?? '') === $id) return ['error', 'Ya existe una ficha para este envío. Revisala antes de publicar.'];
            }
            $dog = publication_fields($record) + [
                'id' => 'dog-' . substr(hash('sha256', $id), 0, 20), 'source_submission_id' => $id,
                'slug' => slugify($record['name']) . '-' . substr(hash('sha256', $id), 0, 10),
                'status' => 'published', 'adoption_status' => 'available',
                'created_at' => now_iso(), 'updated_at' => now_iso(), 'published_at' => now_iso(),
                'last_confirmed_at' => now_iso(),
                'expires_at' => date(DATE_ATOM, strtotime('+' . (int) $config['listing_expiry_days'] . ' days')),
            ];
            $dogs[] = $dog;
            $submissions = read_dataset('submissions');
            foreach ($submissions as &$submission) {
                if ($submission['id'] === $id) {
                    $submission['status'] = 'approved';
                    $submission['updated_at'] = now_iso();
                    $submission['published_dog_id'] = $dog['id'];
                }
            }
            unset($submission);
            $saved = commit_datasets(['dogs' => $dogs, 'submissions' => $submissions]);
            if ($saved) record_moderation('published', $dog['id'], 'Solicitud ' . ($record['reference'] ?? $id));
            return $saved ? ['success', 'La ficha quedó publicada. Avisale al responsable por WhatsApp.'] : ['error', 'No pudimos guardar la publicación.'];
        }
        if ($dataset === 'submissions' && $action === 'reject') {
            if (($record['status'] ?? '') !== 'pending') return ['error', 'Solo se pueden rechazar solicitudes pendientes.'];
            $record['status'] = 'rejected';
            $record['internal_note'] = text('note', 1000);
        } elseif ($dataset === 'dogs' && in_array($action, ['available', 'reserved', 'adopted', 'reunited', 'expired', 'unpublish'], true)) {
            $type = $record['listing_type'] ?? 'adoption';
            if (($type !== 'adoption' && in_array($action, ['adopted', 'reserved'], true)) || ($type === 'adoption' && $action === 'reunited')) {
                return ['error', 'Ese estado no corresponde al tipo de aviso.'];
            }
            if ($action === 'available') {
                if (!checked('owner_confirmed')) return ['error', 'Confirmá con el responsable que el aviso sigue vigente antes de renovarlo.'];
                $record['status'] = 'published';
                $record['adoption_status'] = 'available';
                $record['last_confirmed_at'] = now_iso();
                $record['expires_at'] = date(DATE_ATOM, strtotime('+' . (int) $config['listing_expiry_days'] . ' days'));
            } elseif (in_array($action, ['expired', 'unpublish'], true)) {
                $record['status'] = $action === 'expired' ? 'expired' : 'removed';
            } else {
                $record['adoption_status'] = $action;
            }
        } elseif ($dataset === 'reports' && $action === 'resolve') {
            $record['status'] = 'resolved';
        } else return ['error', 'La acción solicitada no está disponible.'];
        $record['updated_at'] = now_iso();
        if (!save_record($dataset, $record)) return ['error', 'No se pudo guardar el cambio.'];
        record_moderation($action, $id, $record['internal_note'] ?? '');
        return ['success', 'Cambio guardado.'];
    });
}

function record_revision(array $record): string
{
    return hash('sha256', (string) json_encode($record));
}

function admin_edit_routes(string $path): void
{
    if (preg_match('#^admin/media/([a-z0-9-]+)/([a-z0-9._-]+)$#i', $path, $matches)) {
        require_admin();
        $record = find_record('submissions', $matches[1]) ?? find_record('dogs', $matches[1]);
        $file = $record ? PERRO_UPLOADS . '/' . basename($record['source_submission_id'] ?? $record['id']) . '/' . basename($matches[2]) : '';
        $details = $record && in_array($matches[2], $record['photos'] ?? [], true) && is_file($file) ? @getimagesize($file) : false;
        if (!$details) { http_response_code(404); exit; }
        header('Content-Type: ' . $details['mime']);
        header('Cache-Control: no-store');
        readfile($file);
        exit;
    }
    if ($path !== 'admin/edit') return;
    require_admin();
    if (method_is_post()) require_csrf();
    $dataset = method_is_post() ? text('dataset', 30) : (is_string($_GET['dataset'] ?? null) ? $_GET['dataset'] : '');
    $id = method_is_post() ? text('id', 100) : (is_string($_GET['id'] ?? null) ? $_GET['id'] : '');
    if (!in_array($dataset, ['submissions', 'dogs'], true) || !($record = find_record($dataset, $id))) {
        render_error_page('Ficha no encontrada', 'Volvé al panel para elegir una ficha.', 404); exit;
    }
    if ($dataset === 'dogs' && !empty($record['source_submission_id']) && find_record('submissions', $record['source_submission_id'])) {
        if (method_is_post()) set_flash('error', 'Editá el envío original para mantener sus datos y permisos sincronizados.');
        redirect('admin/edit?dataset=submissions&id=' . rawurlencode($record['source_submission_id']));
    }
    if (method_is_post()) {
        $errors = listing_input_errors();
        if (!checked('review_confirm')) $errors[] = 'Confirmá que revisaste los cambios y su autorización.';
        if (!$errors) {
            $result = with_data_lock(static function () use ($dataset, $id): array {
                global $config;
                $current = find_record($dataset, $id);
                if (!$current || !hash_equals(record_revision($current), text('revision', 100))) return ['error', 'La ficha cambió en otra sesión. Cargá de nuevo antes de editar.'];
                // Edit the source for approved submissions so publication and
                // private permissions remain synchronized in a single commit.
                $records = read_dataset($dataset);
                $new = $current;
                foreach (listing_fields() as $key => $limit) $new[$key] = text($key, $limit);
                $new['mixed_breed'] = checked('mixed_breed');
                $new['internal_note'] = text('internal_note', 1500);
                $new['updated_at'] = now_iso();
                // Administrators can revoke permissions, but cannot invent an
                // owner's consent or replace their chosen public alias.
                if (checked('hide_name')) {
                    $new['public_name'] = false;
                    $new['public_display_name'] = '';
                    $new['contact_name'] = '';
                    $new['privacy_revocations'][] = ['permission' => 'public_name', 'at' => now_iso()];
                }
                if (checked('hide_whatsapp')) {
                    $new['public_whatsapp'] = false;
                    $new['contact_whatsapp'] = '';
                    $new['privacy_revocations'][] = ['permission' => 'public_whatsapp', 'at' => now_iso()];
                }
                $keep = $_POST['keep_photos'] ?? [];
                $keep = is_array($keep) ? $keep : [];
                $new['photos'] = array_values(array_filter($current['photos'] ?? [], static fn(string $photo): bool => in_array($photo, $keep, true)));
                $hasPhotos = array_filter($_FILES['photos']['error'] ?? [], static fn($error): bool => $error !== UPLOAD_ERR_NO_FILE);
                if ($hasPhotos && !checked('photo_authorized')) return ['error', 'Confirmá el permiso para las fotos nuevas.'];
                $newPhotos = save_submission_images($current['source_submission_id'] ?? $id, $photoErrors);
                if ($photoErrors) return ['error', implode(' ', $photoErrors)];
                $folder = PERRO_UPLOADS . '/' . basename($current['source_submission_id'] ?? $id);
                if (count($new['photos']) + count($newPhotos) > (int) $config['max_uploads']) {
                    foreach ($newPhotos as $photo) @unlink($folder . '/' . $photo);
                    return ['error', 'La ficha puede tener hasta ' . $config['max_uploads'] . ' fotos.'];
                }
                $new['photos'] = array_merge($new['photos'], $newPhotos);
                foreach ($records as &$item) if ($item['id'] === $id) $item = $new;
                unset($item);
                $datasets = [$dataset => $records];
                if ($dataset === 'submissions') {
                    $dogs = read_dataset('dogs');
                    foreach ($dogs as &$dog) {
                        if (($dog['source_submission_id'] ?? '') === $id) {
                            if (($dog['listing_type'] ?? '') !== $new['listing_type']) {
                                // A changed category needs a fresh, deliberate
                                // publication instead of silently reopening it.
                                $dog['status'] = 'removed';
                                $dog['adoption_status'] = 'available';
                            }
                            $dog = array_replace($dog, publication_fields($new), ['updated_at' => now_iso()]);
                        }
                    }
                    unset($dog);
                    $datasets['dogs'] = $dogs;
                } elseif (($current['listing_type'] ?? '') !== $new['listing_type']) {
                    foreach ($datasets['dogs'] as &$dog) if ($dog['id'] === $id) {
                        $dog['status'] = 'removed'; $dog['adoption_status'] = 'available';
                    }
                    unset($dog);
                }
                if (!commit_datasets($datasets)) {
                    foreach ($newPhotos as $photo) @unlink($folder . '/' . $photo);
                    return ['error', 'No se pudo guardar la edición.'];
                }
                foreach (array_diff($current['photos'] ?? [], $new['photos']) as $photo) @unlink($folder . '/' . basename($photo));
                record_moderation('edited', $id, $new['internal_note']);
                return ['success', 'Ficha corregida. Cambiar el tipo retira el aviso: confirmá la situación con el responsable antes de renovarlo.'];
            });
            set_flash($result[0], $result[1]);
            if ($result[0] === 'success') redirect('admin');
        } else set_flash('error', implode(' ', $errors));
        // Preserve submitted text after validation failure without weakening the
        // saved revision or auto-selecting new publication permissions.
        foreach (listing_fields() as $key => $limit) $record[$key] = text($key, $limit);
    }
    $revisionRecord = find_record($dataset, $id);
    render_header(page_meta('Revisar y editar ficha | Perro', 'Revisión privada de la ficha.', 'admin/edit', false));
    ?>
    <section class="section"><div class="shell narrow"><a class="text-link" href="/admin">← Volver al panel</a><h1>Revisar y editar ficha</h1>
    <?php if ($dataset === 'submissions'): ?><div class="notice"><strong>Contacto privado:</strong> <?= h($record['submitter_name'] ?? '') ?> · <?= h($record['email'] ?? '') ?> · <?= h($record['whatsapp'] ?? '') ?><br>Relación: <?= h($record['relationship'] ?? '') ?> · Referencia: <?= h($record['reference'] ?? '') ?></div><?php endif; ?>
    <?php if ($dataset === 'submissions') admin_whatsapp_links($record); ?>
    <form class="submission-form" method="post" action="/admin/edit" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="dataset" value="<?= h($dataset) ?>"><input type="hidden" name="id" value="<?= h($id) ?>"><input type="hidden" name="revision" value="<?= h(record_revision($revisionRecord)) ?>">
    <fieldset><legend>Datos del aviso</legend><div class="field-grid">
    <?php $labels = ['listing_type'=>'Tipo de aviso', 'name'=>'Nombre del perro', 'department'=>'Departamento', 'city'=>'Ciudad', 'age_group'=>'Etapa', 'approximate_age'=>'Edad aproximada', 'sex'=>'Sexo', 'size'=>'Tamaño', 'breed_label'=>'Raza o apariencia', 'vaccination_status'=>'Vacunas', 'sterilization_status'=>'Esterilización', 'last_location'=>'Zona aproximada del aviso', 'incident_date'=>'Fecha de pérdida o hallazgo'];
    foreach ($labels as $key => $label): ?><label><?= h($label) ?><?php if (isset(listing_options()[$key])): ?><select name="<?= h($key) ?>"><?php select_options(listing_options()[$key], ($record[$key] ?? '') ?: (in_array($key, ['vaccination_status', 'sterilization_status'], true) ? 'No informado' : '')); ?></select><?php else: ?><input name="<?= h($key) ?>" type="<?= $key === 'incident_date' ? 'date' : 'text' ?>" maxlength="<?= listing_fields()[$key] ?>" value="<?= h($record[$key] ?? '') ?>"><?php endif; ?></label><?php endforeach; ?>
    </div><label class="check"><input type="checkbox" name="mixed_breed" value="1" <?= !empty($record['mixed_breed']) ? 'checked' : '' ?>> Es mestizo o la raza es aproximada</label>
    <?php foreach (['description'=>'Historia y personalidad', 'health_information'=>'Salud', 'compatibility'=>'Compatibilidad', 'reason'=>'Motivo del aviso', 'adoption_requirements'=>'Requisitos de adopción'] as $key => $label): ?><label><?= h($label) ?><textarea name="<?= h($key) ?>" maxlength="<?= listing_fields()[$key] ?>" <?= $key === 'description' ? 'required minlength="40"' : '' ?>><?= h($record[$key] ?? '') ?></textarea></label><?php endforeach; ?></fieldset>
    <fieldset><legend>Fotos y privacidad</legend><p>Revisá que las fotos no expongan domicilios, documentos ni datos ajenos. Destildá una foto para retirarla.</p><div class="review-photos">
    <?php foreach ($record['photos'] ?? [] as $photo): ?><label><img src="/admin/media/<?= h($id) ?>/<?= h($photo) ?>" alt="Foto para revisión de <?= h($record['name']) ?>"><span><input type="checkbox" name="keep_photos[]" value="<?= h($photo) ?>" checked> Conservar esta foto</span></label><?php endforeach; ?></div>
    <label>Agregar fotos<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label><label class="check"><input type="checkbox" name="photo_authorized" value="1"> Tengo permiso para agregar las fotos nuevas.</label>
    <p><strong>Permisos actuales:</strong> nombre <?= ($record['public_name'] ?? false) === true ? h($record['public_display_name'] ?? $record['contact_name'] ?? '') : 'privado' ?> · WhatsApp <?= !empty($record['public_whatsapp'] ?? $record['contact_whatsapp'] ?? '') ? 'autorizado' : 'privado' ?>.</p>
    <label class="check"><input type="checkbox" name="hide_name" value="1"> Retirar el nombre público (revocar permiso)</label><label class="check"><input type="checkbox" name="hide_whatsapp" value="1"> Retirar el WhatsApp público (revocar permiso)</label><p>No se puede conceder un nuevo permiso en nombre del responsable. Necesita un nuevo envío con su autorización.</p></fieldset>
    <fieldset><legend>Revisión interna</legend><label>Nota privada<textarea name="internal_note" maxlength="1500"><?= h($record['internal_note'] ?? '') ?></textarea></label><label class="check"><input type="checkbox" name="review_confirm" value="1" required> Revisé los datos, las fotos y los permisos. Confirmé con el responsable cualquier cambio de situación; el aviso no ofrece venta, cría ni cobros por entrega.</label></fieldset><div class="editor-save-bar"><button class="button button-full" type="submit">Guardar correcciones</button><a class="text-link" href="/admin">Volver sin guardar</a></div></form></div></section>
    <?php render_footer(); exit;
}
