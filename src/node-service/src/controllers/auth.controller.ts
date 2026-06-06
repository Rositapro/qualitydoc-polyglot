import { Request, Response, NextFunction } from 'express';

/**
 * Endpoint POST: Iniciar sesión (simulado)
 * Replica la lógica de la API de PHP en ConsultaGestion/api/simulador_odeth.php
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

    console.log(`Inicio de sesión exitoso (simulado) para el usuario: ${usuario.trim()}`);

    // Determinar la empresa simulada según el correo ingresado
    const emailLower = usuario.trim().toLowerCase();
    const empresaid = (emailLower.includes('2') || emailLower.includes('empresa2')) ? 2 : 1;

    // Retornar datos de usuario simulados
    res.status(200).json({
      success: true,
      user: {
        idusuario: 99,
        nombre: "Rosalinda (Simulada por API)",
        rol: "Administrador",
        empresaid: empresaid
      }
    });
  } catch (error: any) {
    console.error('Error al procesar el login:', error);
    res.status(500).json({
      success: false,
      error: 'Error interno del servidor al procesar el inicio de sesión.',
      details: error.message
    });
  }
};
