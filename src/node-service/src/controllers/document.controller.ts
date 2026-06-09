import { Request, Response, NextFunction } from 'express';
import { DocumentModel } from '../models/document.model';

/**
 * Endpoint POST: Crear y guardar metadatos e indexar documento
 * Recibe la carga de metadatos desde el módulo de .NET Core.
 */
export const createDocument = async (
  req: Request,
  res: Response,
  next: NextFunction
): Promise<void> => {
  try {
    const { title, fileExtension, metadata, tags, textContent, empresaid } = req.body;

    // Validaciones básicas
    if (!title || typeof title !== 'string' || title.trim() === '') {
      res.status(400).json({ error: 'El campo "title" es requerido y debe ser una cadena no vacía.' });
      return;
    }

    if (!fileExtension || typeof fileExtension !== 'string' || fileExtension.trim() === '') {
      res.status(400).json({ error: 'El campo "fileExtension" es requerido y debe ser una cadena no vacía.' });
      return;
    }

    if (!textContent || typeof textContent !== 'string' || textContent.trim() === '') {
      res.status(400).json({ error: 'El campo "textContent" es requerido y debe ser una cadena no vacía.' });
      return;
    }

    const cleanTitle = title.trim();
    const cleanEmpresaId = empresaid ? Number(empresaid) : 1;

    // Marcar versiones anteriores del mismo documento como "Obsoleto"
    await DocumentModel.updateMany(
      { title: cleanTitle, empresaid: cleanEmpresaId },
      { $set: { status: 'Obsoleto' } }
    );

    // Crear y guardar la versión vigente en la base de datos
    const newDocument = new DocumentModel({
      title: cleanTitle,
      fileExtension: fileExtension.trim().toLowerCase(),
      metadata: metadata || {},
      tags: Array.isArray(tags) ? tags.map(t => String(t).trim()) : [],
      textContent: textContent.trim(),
      empresaid: cleanEmpresaId,
      status: 'Vigente'
    });

    const savedDocument = await newDocument.save();

    console.log(`Documento guardado e indexado con éxito: ${savedDocument._id}`);
    res.status(201).json({
      success: true,
      message: 'Documento indexado correctamente.',
      data: savedDocument
    });
  } catch (error: any) {
    console.error('Error al guardar el documento:', error);
    res.status(500).json({
      success: false,
      error: 'Error interno del servidor al procesar la solicitud.',
      details: error.message
    });
  }
};

/**
 * Endpoint GET: Buscar coincidencias utilizando el operador $text de MongoDB
 * Devuelve las coincidencias ordenadas por relevancia para el portal de PHP.
 */
export const searchDocuments = async (
  req: Request,
  res: Response,
  next: NextFunction
): Promise<void> => {
  try {
    const { q, extension, empresaid } = req.query;

    const queryObj: any = {};
    queryObj.status = 'Vigente'; // Sólo retornar documentos vigentes

    if (q && typeof q === 'string' && q.trim() !== '') {
      queryObj.$text = { $search: q.trim() };
    }

    if (extension && typeof extension === 'string' && extension.trim() !== '') {
      queryObj.fileExtension = extension.trim().toLowerCase();
    }

    if (empresaid) {
      queryObj.empresaid = Number(empresaid);
    }

    let query = DocumentModel.find(queryObj);

    if (queryObj.$text) {
      query = query.select({ score: { $meta: 'textScore' } }).sort({ score: { $meta: 'textScore' } });
    } else {
      query = query.sort({ createdAt: -1 });
    }

    let matches = await query.exec();

    // Fallback: si no hay resultados por búsqueda de texto y hay consulta 'q', buscamos por regex
    if (matches.length === 0 && q && typeof q === 'string' && q.trim() !== '') {
      const regexQuery = new RegExp(q.trim(), 'i');
      const fallbackQueryObj: any = {
        status: 'Vigente',
        $or: [
          { title: regexQuery },
          { textContent: regexQuery }
        ]
      };
      if (extension && typeof extension === 'string' && extension.trim() !== '') {
        fallbackQueryObj.fileExtension = extension.trim().toLowerCase();
      }
      if (empresaid) {
        fallbackQueryObj.empresaid = Number(empresaid);
      }
      matches = await DocumentModel.find(fallbackQueryObj).limit(20).exec();
    }

    console.log(`Búsqueda realizada: q="${q || ''}", extension="${extension || ''}", empresaid="${empresaid || ''}". Resultados encontrados: ${matches.length}`);

    res.status(200).json({
      success: true,
      query: q ? String(q).trim() : '',
      extension: extension ? String(extension).trim() : '',
      empresaid: empresaid ? Number(empresaid) : null,
      count: matches.length,
      data: matches
    });
  } catch (error: any) {
    console.error('Error al realizar la búsqueda:', error);
    res.status(500).json({
      success: false,
      error: 'Error interno del servidor al realizar la búsqueda.',
      details: error.message
    });
  }
};
