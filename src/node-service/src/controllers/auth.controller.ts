import { Request, Response, NextFunction } from 'express';

/**
 * Endpoint POST: Iniciar sesión
 * Valida las credenciales contra la API central de .NET.
 */
export const loginUser = async (
  req: Request,
  res: Response,
  next: NextFunction
): Promise<void> => {
  try {
    const { usuario, password } = req.body;

    // Validar campos requeridos
    if (!usuario || !password || usuario.trim() === '' || password.trim() === '') {
      res.status(400).json({
        success: false,
        error: 'El usuario/correo y la contraseña son requeridos.'
      });
      return;
    }

    // URL de la API central de autenticación
    const authApiUrl = process.env.AUTH_API_URL || 'http://admin-dotnet:8080/api/login';

    try {
      // Hacer petición POST a .NET
      const authResponse = await globalThis.fetch(authApiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          email: usuario.trim(),
          password: password
        })
      });

      if (authResponse.ok) {
        const responseData = await authResponse.json() as any;

        if (responseData && responseData.success) {
          // Responder al frontend con el usuario de manera exitosa
          res.status(200).json({
            success: true,
            user: {
              idusuario: Number(responseData.user.idusuario),
              nombre: responseData.user.nombreusuario,
              rol: responseData.user.rol,
              empresaid: Number(responseData.user.empresaid),
              empresanombre: responseData.user.empresanombre || "Empresa"
            }
          });
          return;
        }
      }

      // Si la respuesta no es 200/OK o success no es true (ej. 401 de .NET)
      res.status(401).json({
        success: false,
        error: 'Credenciales incorrectas.'
      });

    } catch (apiError: any) {
      console.error('Error al conectar con la API de autenticación externa:', apiError);
      res.status(500).json({
        success: false,
        error: 'Error al conectar con el servidor de autenticación central.',
        details: apiError.message
      });
    }

  } catch (error: any) {
    console.error('Error al procesar el login:', error);
    res.status(500).json({
      success: false,
      error: 'Error interno del servidor al procesar el inicio de sesión.',
      details: error.message
    });
  }
};
