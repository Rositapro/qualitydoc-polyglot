import { Router } from 'express';
import { loginUser } from '../controllers/auth.controller';

const router = Router();

// Endpoint POST: Iniciar sesión (simulado)
router.post('/login', loginUser);

export default router;
