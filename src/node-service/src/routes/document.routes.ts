import { Router } from 'express';
import { createDocument, searchDocuments, getObsoleteVersions } from '../controllers/document.controller';

const router = Router();

// Endpoint POST: Recibir carga de metadatos y contenido desde .NET Core
router.post('/', createDocument);

// Endpoint GET: Búsqueda de documentos con MongoDB $text (para portal PHP)
router.get('/search', searchDocuments);

// Endpoint GET: Obtener versiones obsoletas para un documento específico
router.get('/obsolete/:documentId', getObsoleteVersions);

export default router;
