<?php
declare(strict_types=1);

/**
 * Correo de recuperación de contraseña (HTML).
 *
 * Vive fuera de recover.php porque sigue reglas distintas a las de una
 * página: en un correo los estilos van en línea (Gmail elimina el <head>)
 * y la maquetación es con tablas. La CSP de la aplicación, que prohíbe los
 * estilos en línea, no se aplica a un correo.
 *
 * Los parámetros llegan ya escapados para HTML.
 */
function plantillaCorreoRecuperacion(string $nombre_html, string $link_html, int $ttl_min, string $anio): string {
    return <<<HTML
                <!DOCTYPE html>
                <html lang="es">
                <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width,initial-scale=1">
                <title>Recuperación de contraseña - SENA</title>
                </head>
                <body style="margin:0;padding:0;background-color:#f1f4ef;">
                <!-- Texto de vista previa (oculto en el cuerpo del correo) -->
                <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#f1f4ef;">Restablece tu contraseña del Sistema de Seguimiento SENA. El enlace expira en {$ttl_min} minutos.</div>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background-color:#f1f4ef;margin:0;padding:0;">
                  <tr>
                    <td align="center" style="padding:24px 12px;">

                      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border:1px solid #e2e8e0;border-radius:10px;">

                        <!-- ENCABEZADO -->
                        <tr>
                          <td align="center" bgcolor="#39A900" style="background-color:#39A900;padding:30px 24px 24px 24px;border-radius:10px 10px 0 0;">
                            <div style="font-family:Arial,Helvetica,sans-serif;font-size:30px;line-height:34px;font-weight:bold;color:#ffffff;letter-spacing:8px;">SENA</div>
                            <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#dff0d0;letter-spacing:2px;padding-top:8px;">SEGUIMIENTO DE PROYECTOS FORMATIVOS</div>
                          </td>
                        </tr>
                        <tr>
                          <td style="height:5px;line-height:5px;font-size:0;background-color:#2f8a00;">&nbsp;</td>
                        </tr>

                        <!-- CUERPO -->
                        <tr>
                          <td style="padding:32px 32px 0 32px;font-family:Arial,Helvetica,sans-serif;">
                            <p style="margin:0 0 14px 0;font-family:Arial,Helvetica,sans-serif;font-size:19px;line-height:26px;font-weight:bold;color:#111827;">Restablecer contraseña</p>
                            <p style="margin:0 0 16px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:24px;color:#374151;">Hola <strong style="color:#111827;">{$nombre_html}</strong>,</p>
                            <p style="margin:0 0 24px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:24px;color:#374151;">Recibimos una solicitud para restablecer la contraseña de tu cuenta institucional. Para continuar, pulsa el siguiente botón:</p>
                          </td>
                        </tr>

                        <!-- BOTÓN -->
                        <tr>
                          <td align="center" style="padding:0 32px 26px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                              <tr>
                                <td align="center" bgcolor="#39A900" style="background-color:#39A900;border-radius:6px;">
                                  <a href="{$link_html}" target="_blank" style="display:inline-block;padding:15px 36px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:20px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:6px;">Restablecer mi contraseña</a>
                                </td>
                              </tr>
                            </table>
                          </td>
                        </tr>

                        <!-- ENLACE EN TEXTO PLANO -->
                        <tr>
                          <td style="padding:0 32px;font-family:Arial,Helvetica,sans-serif;">
                            <p style="margin:0 0 6px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;color:#6b7280;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
                            <p style="margin:0 0 24px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;word-break:break-all;"><a href="{$link_html}" target="_blank" style="color:#2f7a00;text-decoration:underline;">{$link_html}</a></p>
                          </td>
                        </tr>

                        <!-- AVISO DE SEGURIDAD -->
                        <tr>
                          <td style="padding:0 32px 28px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background-color:#f2f8ec;border-left:4px solid #39A900;border-radius:4px;">
                              <tr>
                                <td style="padding:14px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;color:#3f5236;">
                                  Por seguridad, este enlace <strong>expira en {$ttl_min} minutos</strong> y solo puede usarse una vez.<br>
                                  Si no fuiste tú quien lo solicitó, ignora este mensaje: tu contraseña actual seguirá siendo válida.
                                </td>
                              </tr>
                            </table>
                          </td>
                        </tr>

                        <!-- PIE -->
                        <tr>
                          <td align="center" bgcolor="#f7f9f6" style="background-color:#f7f9f6;border-top:1px solid #e5e7eb;padding:20px 32px;border-radius:0 0 10px 10px;">
                            <p style="margin:0 0 6px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#6b7280;">Sistema de Seguimiento de Proyectos Formativos &middot; SENA</p>
                            <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:17px;color:#9ca3af;">Este es un correo automático, por favor no respondas a este mensaje.<br>&copy; {$anio} Servicio Nacional de Aprendizaje &middot; Colombia</p>
                          </td>
                        </tr>

                      </table>

                    </td>
                  </tr>
                </table>
                </body>
                </html>
HTML;
}
