import express, { Request, Response } from 'express';
import cors from 'cors';
import path from 'path';
import documentRoutes from './routes/document.routes';
import authRoutes from './routes/auth.routes';

const app = express();

// Middlewares
app.use(cors()); // Permitir solicitudes de origen cruzado (útil para PHP y .NET)
app.use(express.json({ limit: '10mb' })); // Limitar el tamaño de carga para documentos pesados
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Servir archivos estáticos del frontend
app.use(express.static(path.join(__dirname, '../public')));

// Endpoint de verificación de salud
app.get('/health', (req: Request, res: Response) => {
  res.status(200).json({ status: 'UP', timestamp: new Date().toISOString() });
});

// Rutas del Microservicio
app.use('/api/documents', documentRoutes);
app.use('/api/auth', authRoutes);

// Manejo de rutas no encontradas (404)
app.use((req: Request, res: Response) => {
  res.status(404).json({
    success: false,
    error: 'Ruta no encontrada.'
  });
});

// Middleware global de manejo de errores
app.use((err: any, req: Request, res: Response, next: express.NextFunction) => {
  console.error('Error no controlado en Express:', err);
  res.status(500).json({
    success: false,
    error: 'Error interno del servidor.',
    details: err.message || 'Error desconocido'
  });
});

export default app;
