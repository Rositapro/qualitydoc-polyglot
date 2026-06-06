import mongoose from 'mongoose';
import dotenv from 'dotenv';

dotenv.config();

const MONGODB_URI = process.env.MONGODB_URI || 'mongodb://localhost:27017/qualitydoc';

export const connectDatabase = async (): Promise<void> => {
  try {
    console.log('Intentando conectar a MongoDB...');
    await mongoose.connect(MONGODB_URI);
    console.log('Conexión exitosa a MongoDB');
  } catch (error) {
    console.error('Error al conectar a MongoDB:', error);
    process.exit(1); // Finalizar el proceso si no se puede conectar a la base de datos
  }
};
