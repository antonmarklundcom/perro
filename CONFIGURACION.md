# Antes de publicar

- [ ] Confirmar que `https://perro.com.py` apunta a este sitio y tiene SSL.
- [ ] Cambiar la contraseña inicial del administrador.
- [ ] Revisar y completar un canal real para solicitudes de privacidad o retiro.
- [ ] Hacer revisar los textos legales por una persona competente en Paraguay.
- [ ] Probar un envío con fotos y confirmar que aparece solo en `/admin`.
- [ ] Aprobar la prueba y revisar la ficha pública.
- [ ] Retirar todos los datos de prueba.
- [ ] Confirmar que ventas, criaderos y cobros están prohibidos en la práctica de moderación.
- [ ] Confirmar que `/robots.txt` permite el rastreo y que `/sitemap.xml` carga en el dominio público.
- [ ] Probar el botón flotante de WhatsApp desde inicio, publicación, perdidos y una ficha individual.
- [ ] Confirmar que `+595 992 279 599` pertenece a la persona que administrará las consultas.
- [ ] Hacer una copia de la carpeta `storage` antes de actualizar el sitio.
- [ ] Completar identidad, domicilio de contacto y canal de privacidad del responsable según `LEGAL-RELEASE.md`.
- [ ] Confirmar que el formulario nuevo mantiene nombre y WhatsApp privados por defecto.
- [ ] Probar retiro y vencimiento de una ficha y sus fotos; no cargar copias de datos locales en producción.

## Valores configurables

Abrí `config.php` y revisá:

- `base_url`
- `demo_mode`
- `contact_whatsapp`
- `admin_username`
- `admin_password_sha256`
- `max_uploads`
- `max_upload_bytes`
- `listing_expiry_days`
