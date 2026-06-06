import app from './app';
import { connectDatabase } from './config/database';
import dotenv from 'dotenv';

dotenv.config();

const PORT = process.env.PORT || 3000;

const startServer = async () => {
  // Conectar a la base de datos de MongoDB
  await connectDatabase();

  // Iniciar la escucha en el puerto configurado
  app.listen(PORT, () => {
    console.log(`===================================================`);
    console.log(`  Microservicio de Indexación y Búsqueda de Calidad`);
    console.log(`  Corriendo en el puerto: ${PORT}`);
    console.log(`  URL: http://localhost:${PORT}`);
    console.log(`===================================================`);
  });
};

// Captura de errores no controlados en el proceso
process.on('unhandledRejection', (reason, promise) => {
  console.error('Unhandled Rejection at:', promise, 'reason:', reason);
});

process.on('uncaughtException', (error) => {
  console.error('Uncaught Exception thrown:', error);
  process.exit(1);
});

startServer();
